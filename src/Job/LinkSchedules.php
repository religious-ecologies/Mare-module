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
            $template = $this->getResourceTemplate($link['label']);
            $linkedIdProperty = $this->getProperty($link['term']);
            $linkedItems = $api->search(
                'items',
                ['resource_template_id' => $template->id()],
                ['responseContent' => 'resource']
            )->getContent();
            foreach ($linkedItems as $linkedItem) {
                $values = $linkedItem->getValues();
                $criteria = Criteria::create()
                    ->where(Criteria::expr()->eq('property', $linkedIdProperty));
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
        foreach ($scheduleIds as $scheduleId) {
            $schedule = $em->find('Omeka\Entity\Item', $scheduleId);
            $values = $schedule->getValues();
            foreach ($this->links as $term => $link) {

                // Get properties at the beginning of every iteration so we can
                // clear() the entity manager at the end of the iteration.
                $linkedIdProperty = $this->getProperty($link['term']);
                $linkingProperty = $this->getProperty($term);

                // Get the linked item.
                $criteria = Criteria::create()
                    ->where(Criteria::expr()->eq('property', $linkedIdProperty));
                $linkingValues = $values->matching($criteria);
                if ($linkingValues->isEmpty()) {
                    // This schedule has no linking ID of this property so skip
                    // adding a linking value.
                    continue;
                }
                $linkingId = trim($linkingValues->first()->getValue());
                $linkedItemId = array_search($linkingId, $this->linkedItemMaps[$term]);
                $linkedItem = $em->find('Omeka\Entity\Item', $linkedItemId);

                // Delete existing linking values from the schedule.
                $criteria = Criteria::create()
                    ->where(Criteria::expr()->eq('property', $linkingProperty));
                $linkedValues = $values->matching($criteria);
                foreach ($linkedValues as $linkingValue) {
                    $values->removeElement($linkingValue);
                }

                // Add the linking value to the schedule.
                $value = new Value;
                $value->setResource($schedule);
                $value->setProperty($linkingProperty);
                $value->setValueResource($linkedItem);
                $value->setType('resource');
                $values->add($value);
            }

            // Flush and clear at the end of every iteration to save memory.
            $em->flush($schedule);
            $em->clear();
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
