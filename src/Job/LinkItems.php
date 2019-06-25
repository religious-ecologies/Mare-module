<?php
namespace Mare\Job;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Value;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;

/**
 * Link items to their linked items using a shared ID.
 *
 * Accepts the following arguments:
 *
 * - "linking_items_query": the API search query used to get all linking items
 * - "links": an array of links, each including the following:
 *     - "linked_items_query": the API search query used to get all linked items
 *     - "linked_id_property_term": the ID property shared between the item and the linked items
 *     - "linking_property_term": the linking property of the items
 */
class LinkItems extends AbstractJob
{
    /**
     * @var array
     */
    protected $linkingItemsQuery;

    /**
     * @param array
     */
    protected $links;

    /**
     * @var array
     */
    protected $linkedItemMaps = [];

    public function perform()
    {
        $this->handleArgs();
        $this->buildLinkedItemMaps();

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        // Do the actual linking.
        $itemIds = $api->search(
            'items',
            $this->linkingItemsQuery,
            ['returnScalar' => 'id']
        )->getContent();
        foreach (array_chunk($itemIds, 100) as $itemIdsChunk) {

            // Clear the entity manager at the beginning of every chunk to
            // reduce memory.
            $em->clear();

            // Iterate over each item.
            foreach ($itemIdsChunk as $itemId) {

                // Get the item entity.
                $item = $em->find('Omeka\Entity\Item', $itemId);
                $itemValues = $item->getValues();

                foreach ($this->links as $link) {

                    // Get properties at the beginning of every iteration so we
                    // can clear the entity manager. Note that clearing puts the
                    // properties into an unmanaged state so we have to find
                    // them again to avoid Doctrine's "A new entity was found"
                    // error. For some reason, simply merging the property back
                    // into a managed state doesn't work.
                    $linkedIdProperty = $em->find(
                        'Omeka\Entity\Property',
                        $link['linked_id_property']->getId()
                    );
                    $linkingProperty = $em->find(
                        'Omeka\Entity\Property',
                        $link['linking_property']->getId()
                    );

                    // Get the linked item entity (i.e. County, Denomination).
                    $criteria = Criteria::create()
                        ->where(Criteria::expr()->eq('property', $linkedIdProperty));
                    $linkingIdValue = $itemValues->matching($criteria)->first();
                    if (!$linkingIdValue) {
                        // This item has no linking ID for this property so skip
                        // adding a linked item.
                        continue;
                    }
                    $linkingId = trim($linkingIdValue->getValue());
                    $linkedItemId = array_search(
                        $linkingId,
                        $this->linkedItemMaps[$link['linking_property_term']]
                    );
                    if (!$linkedItemId ) {
                        // This item has a linking ID that doesn't exist so skip
                        // adding a linked item.
                        continue;
                    }
                    $linkedItem = $em->find('Omeka\Entity\Item', $linkedItemId);

                    // Get the item's linked item value, if it exists. Here we
                    // can reuse existing linked item values to avoid primary
                    // key churn. Note that it's possible that the linked item
                    // has changed since the last linking job.
                    $criteria = Criteria::create()
                        ->where(Criteria::expr()->eq('property', $linkingProperty));
                    $linkedItemValue = $itemValues->matching($criteria)->first();
                    if (!$linkedItemValue) {
                        // If a linked item value doesn't exist, create it.
                        $linkedItemValue = new Value;
                        $linkedItemValue->setResource($item);
                        $linkedItemValue->setProperty($linkingProperty);
                        $linkedItemValue->setType('resource');
                        $itemValues->add($linkedItemValue);
                    }
                    $linkedItemValue->setValueResource($linkedItem);
                }
                $em->flush($item);
            }
        }
    }

    /**
     * Handle passed arguments.
     */
    public function handleArgs()
    {
        $linkingItemsQuery = $this->getArg('linking_items_query');
        $links = $this->getArg('links');

        $invalidArgs = [];
        if (!is_array($linkingItemsQuery)) {
            $invalidArgs[] = 'linking_items_query';
        }
        if (is_array($links)) {
            foreach ($links as $link) {
                if (!isset($link['linked_items_query']) || !is_array($link['linked_items_query'])) {
                    $invalidArgs[] = 'linked_items_query';
                }
                if (!isset($link['linked_id_property_term']) || !is_string($link['linked_id_property_term'])) {
                    $invalidArgs[] = 'linked_id_property_term';
                }
                if (!isset($link['linking_property_term']) || !is_string($link['linking_property_term'])) {
                    $invalidArgs[] = 'linking_property_term';
                }
            }
        } else {
            $invalidArgs[] = 'links';
        }
        if ($invalidArgs) {
            throw new Exception\InvalidArgumentException(
                sprintf('Missing or invalid job arguments: "%s"', implode(', ', $invalidArgs))
            );
        }

        $this->linkingItemsQuery = $linkingItemsQuery;
        $this->links = $links;
    }

    /**
     * Build the linked item maps.
     */
    public function buildLinkedItemMaps()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        foreach ($this->links as $index => $link) {

            // Cache the property entities.
            $this->links[$index]['linked_id_property'] = $this->getProperty($link['linked_id_property_term']);
            $this->links[$index]['linking_property'] = $this->getProperty($link['linking_property_term']);

            // Get all linked item entities.
            $linkedItems = $api->search(
                'items',
                $link['linked_items_query'],
                ['responseContent' => 'resource']
            )->getContent();

            // Build the map between the linked item ID and the linked ID.
            foreach ($linkedItems as $linkedItem) {
                $values = $linkedItem->getValues();
                $criteria = Criteria::create()
                    ->where(Criteria::expr()->eq('property', $this->links[$index]['linked_id_property']));
                $linkedValue = $values->matching($criteria)[0];
                $linkedId = $linkedValue ? trim($linkedValue->getValue()) : null;
                $this->linkedItemMaps[$link['linking_property_term']][$linkedItem->getId()] = $linkedId;
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
