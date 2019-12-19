<?php
namespace Mare\Job;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Value;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;

/**
 * Derive AHCB state/territory IDs from AHCB county IDs.
 */
class DeriveAhcbStateTerritoryIds extends AbstractJob
{
    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        $itemIds = $api->search(
            'items',
            ['resource_template_id' => $this->getArg('resource_template_id')],
            ['returnScalar' => 'id']
        )->getContent();
        foreach (array_chunk($itemIds, 100) as $itemIdsChunk) {

            // Clear the entity manager at the beginning of every chunk to
            // reduce memory load.
            $em->clear();

            foreach ($itemIdsChunk as $itemId) {

                // Get the item entity.
                $item = $em->find('Omeka\Entity\Item', $itemId);
                $itemValues = $item->getValues();

                // Get this item's AHCB county ID.
                $propertyCountyId = $this->getProperty('mare:ahcbCountyId');
                $criteria = Criteria::create()->where(Criteria::expr()->eq('property', $propertyCountyId));
                $valueCountyId = $itemValues->matching($criteria)->first();
                if (!$valueCountyId) {
                    // This item has no AHCB county ID.
                    continue;
                }

                // Derive a AHCB state/territory ID from the AHCB county ID.
                // For example, derive "me_state" from "mes_kennebec".
                $countyId = sprintf('%s_state', substr($valueCountyId->getValue(), 0, 2));

                // Get the item's AHCB state/territory ID, if it exists. Here we
                // can reuse existing AHCB state/territory ID values to avoid
                // primary key churn. Note that it's possible that the AHCB
                // state/territory ID has changed since the last job.
                $propertyStateTerritoryId = $this->getProperty('mare:ahcbStateTerritoryId');
                $criteria = Criteria::create()->where(Criteria::expr()->eq('property', $propertyStateTerritoryId));
                $valueStateTerritoryId = $itemValues->matching($criteria)->first();
                if (!$valueStateTerritoryId) {
                    // If an AHCB state/territory ID doesn't exist, create it.
                    $valueStateTerritoryId = new Value;
                    $valueStateTerritoryId->setResource($item);
                    $valueStateTerritoryId->setProperty($propertyStateTerritoryId);
                    $valueStateTerritoryId->setType('literal');
                    $valueStateTerritoryId->setValue($countyId);
                    $itemValues->add($valueStateTerritoryId);
                }
                $valueStateTerritoryId->setValue($countyId);

                $em->flush($item);
            }
        }
    }

    /**
     * Get property entity
     *
     * @param string $term
     * @return Omeka\Entity\Property
     */
    public function getProperty($term)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $response = $api->search(
            'properties',
            ['term' => $term],
            ['responseContent' => 'resource']
        );
        if (!$response->getTotalResults()) {
            throw new Exception\RuntimeException(sprintf('Invalid property: "%s"', $term));
        }
        return $response->getContent()[0];
    }
}
