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
     * @var string
     */
    protected $templateLabel = 'Schedule (1926)';

    /**
     * @param array
     */
    protected $links = [
        [
            'linked_items_template_label' => 'County',
            'linked_id_property_term' => 'mare:ahcbCountyId',
            'linking_property_term' => 'mare:county',
        ],
        [
            'linked_items_template_label' => 'Denomination',
            'linked_id_property_term' => 'mare:denominationId',
            'linking_property_term' => 'mare:denomination',
        ],
    ];

    /**
     * @var array
     */
    protected $linkedItemMaps = [];

    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        $this->buildLinkedItemMaps();

        // Do the actual linking.
        $scheduleTemplate = $this->getResourceTemplate($this->templateLabel);
        $scheduleIds = $api->search(
            'items',
            ['resource_template_id' => $scheduleTemplate->id()],
            ['returnScalar' => 'id']
        )->getContent();
        foreach (array_chunk($scheduleIds, 100) as $scheduleIdsChunk) {

            // Clear the entity manager at the beginning of every chunk to
            // reduce memory.
            $em->clear();

            // Iterate over each Schedule.
            foreach ($scheduleIdsChunk as $scheduleId) {

                // Get the schedule entity.
                $schedule = $em->find('Omeka\Entity\Item', $scheduleId);
                $scheduleValues = $schedule->getValues();

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
                    $linkingIdValue = $scheduleValues->matching($criteria)->first();
                    if (!$linkingIdValue) {
                        // This schedule has no linking ID for this property so
                        // skip adding a linked item.
                        continue;
                    }
                    $linkingId = trim($linkingIdValue->getValue());
                    $linkedItemId = array_search($linkingId, $this->linkedItemMaps[$link['linking_property_term']]);
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
