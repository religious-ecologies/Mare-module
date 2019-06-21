<?php
namespace Mare\Job;

use Doctrine\Common\Collections\Criteria;
use Omeka\Entity\Value;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;

/**
 * Link "Schedule (1926)" schedules to their linked items.
 */
class LinkSchedules extends AbstractJob
{
    /**
     * Links
     *
     * [
     *   <linking_property_term> => [
     *     'label' => <resource_template_label>,
     *     'term' => <linking/linked_id_property_term>,
     *   ],
     * ]
     *
     * @param array
     */
    protected $links = [
        'mare:county' => [
            'label' => 'County',
            'term' => 'mare:ahcbCountyId',
        ],
        'mare:denomination' => [
            'label' => 'Denomination',
            'term' => 'mare:denominationId',
        ],
    ];

    /**
     * Cache of properties.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Linked item maps
     *
     * [
     *   <linking_property_term> => [
     *     <linked_item_id> => <linked_id>,
     *   ],
     * ]
     *
     * @var array
     */
    protected $linkedItemMaps = [];

    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        // Build the linked item maps.
        foreach ($this->links as $term => $link) {

            // Cache the property entities.
            $this->properties[$link['term']] = $this->getProperty($link['term']);
            $this->properties[$term] = $this->getProperty($term);

            $template = $this->getResourceTemplate($link['label']);
            $linkedItems = $api->search(
                'items',
                ['resource_template_id' => $template->id()],
                ['responseContent' => 'resource']
            )->getContent();
            foreach ($linkedItems as $linkedItem) {
                $values = $linkedItem->getValues();
                $criteria = Criteria::create()
                    ->where(Criteria::expr()->eq('property', $this->properties[$link['term']]));
                $linkedValue = $values->matching($criteria)[0];
                $linkedId = $linkedValue ? trim($linkedValue->getValue()) : null;
                $this->linkedItemMaps[$term][$linkedItem->getId()] = $linkedId;
            }
        }

        // Do the actual linking.
        $scheduleTemplate = $this->getResourceTemplate('Schedule (1926)');
        $scheduleIds = $api->search(
            'items',
            ['resource_template_id' => $scheduleTemplate->id()],
            ['returnScalar' => 'id']
        )->getContent();
        foreach (array_chunk($scheduleIds, 100) as $scheduleIdsChunk) {

            // Clear the entity manager at the beginning of every chunk to
            // reduce memory allocation.
            $em->clear();

            // Iterate over each Schedule.
            foreach ($scheduleIdsChunk as $scheduleId) {
                $schedule = $em->find('Omeka\Entity\Item', $scheduleId);
                $scheduleValues = $schedule->getValues();
                foreach ($this->links as $term => $link) {

                    // Get properties at the beginning of every iteration so we
                    // can clear the entity manager. Note that clearing puts the
                    // properties into an unmanaged state so we have to find
                    // them again to avoid Doctrine's "A new entity was found"
                    // error. For some reason, simply merging the property back
                    // into a managed state doesn't work.
                    $linkedIdProperty = $em->find(
                        'Omeka\Entity\Property',
                        $this->properties[$link['term']]->getId()
                    );
                    $linkingProperty = $em->find(
                        'Omeka\Entity\Property',
                        $this->properties[$term]->getId()
                    );

                    // Get the linked item (i.e. County, Denomination).
                    $criteria = Criteria::create()
                        ->where(Criteria::expr()->eq('property', $linkedIdProperty));
                    $linkingIdValue = $scheduleValues->matching($criteria)->first();
                    if (!$linkingIdValue) {
                        // This schedule has no linking ID of this property so
                        // skip adding a linked item.
                        continue;
                    }
                    $linkingId = trim($linkingIdValue->getValue());
                    $linkedItemId = array_search($linkingId, $this->linkedItemMaps[$term]);
                    $linkedItem = $em->find('Omeka\Entity\Item', $linkedItemId);

                    // Get the Schedule's linked item value, if it exists. Here
                    // we can reuse existing linked item values to avoid primary
                    // key churn. Note that it's possible that the linked item
                    // has changed since the last linking job.
                    $criteria = Criteria::create()
                        ->where(Criteria::expr()->eq('property', $linkingProperty));
                    $linkedItemValue = $scheduleValues->matching($criteria)->first();
                    if (!$linkedItemValue) {
                        // If a linked item value doesn't exist, create it.
                        $linkedItemValue = new Value;
                        $linkedItemValue->setResource($schedule);
                        $linkedItemValue->setProperty($linkingProperty);
                        $linkedItemValue->setType('resource');
                        $scheduleValues->add($linkedItemValue);
                    }
                    $linkedItemValue->setValueResource($linkedItem);
                }
                $em->flush($schedule);
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
        return $response->getContent()[0];
    }
}
