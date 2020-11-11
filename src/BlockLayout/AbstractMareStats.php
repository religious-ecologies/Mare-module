<?php
namespace Mare\BlockLayout;

use Doctrine\ORM\EntityManager;
use Mare\Stdlib\Mare;
use Omeka\Api\Manager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Zend\View\Renderer\PhpRenderer;

abstract class AbstractMareStats extends AbstractBlockLayout
{
    const SCHEDULE_TOTAL_COUNT = 232154;

    protected $mare;

    // Resource class entities
    protected $county;
    protected $denomination;
    protected $schedule;

    // Property entities
    protected $ahcbCountyId;
    protected $denominationId;

    public function __construct(Mare $mare)
    {
        $this->mare = $mare;

        $this->county = $mare->getResourceClass('http://religiousecologies.org/vocab#', 'County');
        $this->denomination = $mare->getResourceClass('http://religiousecologies.org/vocab#', 'Denomination');
        $this->schedule = $mare->getResourceClass('http://religiousecologies.org/vocab#', 'Schedule');

        $this->ahcbCountyId = $mare->getProperty('http://religiousecologies.org/vocab#', 'ahcbCountyId');
        $this->denominationId = $mare->getProperty('http://religiousecologies.org/vocab#', 'denominationId');
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        return 'This block will contain statistics about the current state of the MARE database.';
    }

    public function getSchedulesPer($classId, $propertyId)
    {
        $em = $this->mare->getEntityManager();

        // Get the number of schedules in every class.
        $dql = '
        SELECT v.value, COUNT(v.value) schedule_count
        FROM Omeka\Entity\Value v
        JOIN v.resource r
        WHERE v.property = :property_id
        AND r.resourceClass = :class_schedule
        GROUP BY v.value
        ORDER BY schedule_count DESC';
        $query = $em->createQuery($dql);
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
            $query = $em->createQuery($dql);
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
