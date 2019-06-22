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
 * - "template_label": the resource template of the items
 * - "links": an array of links, each including the following:
 *     - "linked_items_template_label": the resource template of the linked items
 *     - "linked_id_property_term": the ID property shared between the item and the linked items
 *     - "linking_property_term": the linking property of the items
 */
class LinkItems extends AbstractJob
{
    /**
     * @var string
     */
    protected $templateLabel;

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
        $itemTemplate = $this->getResourceTemplate($this->templateLabel);
        $itemIds = $api->search(
            'items',
            ['resource_template_id' => $itemTemplate->id()],
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
                    $linkedIdProperty = $em->find('Omeka\Entity\Property', $link['linked_id_property']->getId());
                    $linkingProperty = $em->find('Omeka\Entity\Property', $link['linking_property']->getId());

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
                    $linkedItemId = array_search($linkingId, $this->linkedItemMaps[$link['linking_property_term']]);
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
        $templateLabel = $this->getArg('template_label');
        $links = $this->getArg('links');

        $invalidArgs = [];
        if (!is_string($templateLabel)) {
            $invalidArgs[] = 'template_label';
        }
        if (is_array($links)) {
            foreach ($links as $link) {
                if (!isset($link['linked_items_template_label']) || !is_string($link['linked_items_template_label'])) {
                    $invalidArgs[] = 'linked_items_template_label';
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

        $this->templateLabel = $templateLabel;
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

            // Get all items of this template.
            $template = $this->getResourceTemplate($this->links[$index]['linked_items_template_label']);
            $linkedItems = $api->search(
                'items',
                ['resource_template_id' => $template->id()],
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
     * Get resource template
     *
     * @param string $label
     * @return Omeka\Entity\Property
     */
    public function getResourceTemplate($label)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $response = $api->search('resource_templates', ['label' => $label]);
        if (!$response->getTotalResults()) {
            throw new Exception\RuntimeException(sprintf('Invalid resource template: "%s"', $label));
        }
        return $response->getContent()[0];
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
