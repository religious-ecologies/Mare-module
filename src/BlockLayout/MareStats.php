<?php
namespace Mare\BlockLayout;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Zend\View\Renderer\PhpRenderer;

class MareStats extends AbstractBlockLayout
{
    const SCHEDULE_TOTAL_COUNT = 232154;

    protected $em;
    protected $api;

    // Resource class entities
    protected $county;
    protected $denomination;
    protected $schedule;

    // Property entities
    protected $ahcbCountyId;
    protected $denominationId;

    public function __construct(EntityManager $em, Manager $api)
    {
        $this->em = $em;
        $this->api = $api;

        $this->county = $this->getResourceClass('County');
        $this->denomination = $this->getResourceClass('Denomination');
        $this->schedule = $this->getResourceClass('Schedule');

        $this->ahcbCountyId = $this->getProperty('ahcbCountyId');
        $this->denominationId = $this->getProperty('denominationId');
    }

    public function getLabel()
    {
        return 'MARE stats'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        return 'This block will contain statistics about the current state of the MARE database.';
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $html = [];

        // Totals
        $scheduleCount = $this->api->read('resource_classes', $this->schedule->getId())->getContent()->itemCount();
        $denominationCount = $this->api->read('resource_classes', $this->denomination->getId())->getContent()->itemCount();
        $countyCount = $this->api->read('resource_classes', $this->county->getId())->getContent()->itemCount();
        $html[] = '<h3>Totals</h3>';
        $html[] = sprintf('<p><b>%s</b> schedules digitized (<b>%s</b>%% of total)</p>', number_format($scheduleCount), number_format($scheduleCount / self::SCHEDULE_TOTAL_COUNT, 3));
        $html[] = sprintf('<p><b>%s</b> denominations</p>', number_format($denominationCount));
        $html[] = sprintf('<p><b>%s</b> counties</p>', number_format($countyCount));

        // Schedules per denomination
        $html[] = '<h3>Schedules per denomination (top 25)</h3>';
        $html[] = '<table><thead><tr><th>Denomination</th><th>Schedule count</th><th></th></tr></thead><tbody>';
        $denominations = $this->getSchedulesPer($this->denomination->getId(), $this->denominationId->getId());
        foreach ($denominations as $denomination) {
            $html[] = sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                $denomination['title'],
                $denomination['schedule_count'],
                $view->hyperlink(
                    'view schedules',
                    $view->url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['query' => $denomination['query']], true)
                ));
        }
        $html[] = '</tbody></table>';

        // Schedules per county
        $html[] = '<h3>Schedules per county (top 25)</h3>';
        $html[] = '<table><thead><tr><th>County</th><th>Schedule count</th><th></th></tr></thead><tbody>';
        $counties = $this->getSchedulesPer($this->county->getId(), $this->ahcbCountyId->getId());
        foreach ($counties as $county) {
            $html[] = sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                $county['title'],
                $county['schedule_count'],
                $view->hyperlink(
                    'view schedules',
                    $view->url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['query' => $county['query']], true)
                ));
        }
        $html[] = '</tbody></table>';

        return implode("\n", $html);
    }

    public function getResourceClass($localName)
    {
        $dql = '
        SELECT rc
        FROM Omeka\Entity\ResourceClass rc
        JOIN rc.vocabulary v
        WHERE v.namespaceUri = :namespace_uri
        AND rc.localName = :local_name';
        $query = $this->em->createQuery($dql);
        $query->setParameters([
            'namespace_uri' => 'http://religiousecologies.org/vocab#',
            'local_name' => $localName,
        ]);
        return $query->getSingleResult();
    }

    public function getProperty($localName)
    {
        $dql = '
        SELECT p
        FROM Omeka\Entity\Property p
        JOIN p.vocabulary v
        WHERE v.namespaceUri = :namespace_uri
        AND p.localName = :local_name';
        $query = $this->em->createQuery($dql);
        $query->setParameters([
            'namespace_uri' => 'http://religiousecologies.org/vocab#',
            'local_name' => $localName,
        ]);
        return $query->getSingleResult();
    }

    public function getSchedulesPer($classId, $propertyId)
    {
        // Get the number of schedules in every class.
        $dql = '
        SELECT v.value, COUNT(v.value) schedule_count
        FROM Omeka\Entity\Value v
        JOIN v.resource r
        WHERE v.property = :property_id
        AND r.resourceClass = :class_schedule
        GROUP BY v.value
        ORDER BY schedule_count DESC';
        $query = $this->em->createQuery($dql);
        $query->setMaxResults(25);
        $query->setParameters([
            'property_id' => $propertyId,
            'class_schedule' => $this->schedule->getId(),
        ]);
        $classes = [];
        foreach ($query->getResult() as $class) {
            // Get the item for this class.
            $dql = '
            SELECT i
            FROM Omeka\Entity\Item i
            JOIN Omeka\Entity\Value v WITH v.resource = i
            WHERE v.value = :id_value
            AND v.property = :property_id
            AND i.resourceClass = :class';
            $query = $this->em->createQuery($dql);
            $query->setParameters([
                'id_value' => $class['value'],
                'property_id' => $propertyId,
                'class' => $classId,
            ]);
            $classes[$class['value']] = [
                'title' => $query->getSingleResult()->getTitle(),
                'schedule_count' => $class['schedule_count'],
                'query' => [
                    'resource_class_id' => $this->schedule->getId(),
                    'property' => [
                        [
                            'joiner' => 'and',
                            'property' => $propertyId,
                            'type' => 'eq',
                            'text' => $class['value'],
                        ],
                    ],
                ],
            ];
        }
        return $classes;
    }
}
