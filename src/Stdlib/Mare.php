<?php
namespace Mare\Stdlib;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\Property;
use Omeka\Entity\ResourceClass;
use Laminas\ServiceManager\ServiceManager;

/**
 * A general-purpose service to facilitate MARE fuctionality.
 */
class Mare
{
    /**
     * @var ServiceManager
     */
    protected $services;

    /**
     * @param ServiceManager $services
     */
    public function __construct(ServiceManager $services)
    {
        $this->services = $services;
    }

    /**
     * Get a resource class entity.
     *
     * @param string $namespaceUri
     * @param string $localName
     * @return ResourceClass
     */
    public function getResourceClass(string $namespaceUri, string $localName) : ResourceClass
    {
        $em = $this->services->get('Omeka\EntityManager');
        $dql = '
        SELECT rc
        FROM Omeka\Entity\ResourceClass rc
        JOIN rc.vocabulary v
        WHERE v.namespaceUri = :namespace_uri
        AND rc.localName = :local_name';
        $query = $em->createQuery($dql);
        $query->setParameters([
            'namespace_uri' => $namespaceUri,
            'local_name' => $localName,
        ]);
        return $query->getSingleResult();
    }

    /**
     * Get a property entity.
     *
     * @param string $namespaceUri
     * @param string $localName
     * @return Property
     */
    public function getProperty(string $namespaceUri, string $localName) : Property
    {
        $em = $this->services->get('Omeka\EntityManager');
        $dql = '
        SELECT p
        FROM Omeka\Entity\Property p
        JOIN p.vocabulary v
        WHERE v.namespaceUri = :namespace_uri
        AND p.localName = :local_name';
        $query = $em->createQuery($dql);
        $query->setParameters([
            'namespace_uri' => $namespaceUri,
            'local_name' => $localName,
        ]);
        return $query->getSingleResult();
    }

    public function getCountiesByDenomination(int $denominationItemId) : array
    {
        $em = $this->services->get('Omeka\EntityManager');
        $dql = '
        SELECT county
        FROM Omeka\Entity\Item county
        JOIN Omeka\Entity\Value v WITH v.valueResource = county
        WHERE v.resource IN (
            SELECT IDENTITY(v2.resource)
            FROM Omeka\Entity\Value v2
            WHERE v2.valueResource = :denomination_item_id
            AND v2.property = :denomination_property_id
        )
        AND v.property = :county_property_id
        GROUP BY county
        ORDER BY county.title';
        $query = $em->createQuery($dql);
        $query->setParameters([
            'denomination_item_id' => $denominationItemId,
            'denomination_property_id' => $this->getProperty('http://religiousecologies.org/vocab#', 'denomination')->getId(),
            'county_property_id' => $this->getProperty('http://religiousecologies.org/vocab#', 'county')->getId(),
        ]);
        return $query->getResult();
    }

    public function getDenominationsByCounty(int $countyItemId) : array
    {
        $em = $this->services->get('Omeka\EntityManager');
        $dql = '
        SELECT denomination
        FROM Omeka\Entity\Item denomination
        JOIN Omeka\Entity\Value v WITH v.valueResource = denomination
        WHERE v.resource IN (
            SELECT IDENTITY(v2.resource)
            FROM Omeka\Entity\Value v2
            WHERE v2.valueResource = :county_item_id
            AND v2.property = :county_property_id
        )
        AND v.property = :denomination_property_id
        GROUP BY denomination
        ORDER BY denomination.title';
        $query = $em->createQuery($dql);
        $query->setParameters([
            'county_item_id' => $countyItemId,
            'county_property_id' => $this->getProperty('http://religiousecologies.org/vocab#', 'county')->getId(),
            'denomination_property_id' => $this->getProperty('http://religiousecologies.org/vocab#', 'denomination')->getId(),
        ]);
        return $query->getResult();
    }

    public function getScheduleCountInCountyForDenomination(int $countyId, int $denominationId) : int
    {
        $em = $this->services->get('Omeka\EntityManager');
        $dql = sprintf('
            SELECT COUNT(schedule)
            FROM Omeka\Entity\Item schedule
            LEFT JOIN schedule.values county_value WITH county_value.property = %s
            LEFT JOIN schedule.values denomination_value WITH denomination_value.property = %s
            WHERE county_value.valueResource = :county_id
            AND denomination_value.valueResource = :denomination_id',
            $this->getProperty('http://religiousecologies.org/vocab#', 'county')->getId(),
            $this->getProperty('http://religiousecologies.org/vocab#', 'denomination')->getId()
        );
        $query = $em->createQuery($dql);
        $query->setParameters([
            'county_id' => $countyId,
            'denomination_id' => $denominationId,
        ]);
        return $query->getSingleScalarResult();
    }

    public function getScheduleCountInDenominationForCounty(int $denominationId, int $countyId) : int
    {
        $em = $this->services->get('Omeka\EntityManager');
        $dql = sprintf('
            SELECT COUNT(schedule)
            FROM Omeka\Entity\Item schedule
            LEFT JOIN schedule.values county_value WITH county_value.property = %s
            LEFT JOIN schedule.values denomination_value WITH denomination_value.property = %s
            WHERE county_value.valueResource = :county_id
            AND denomination_value.valueResource = :denomination_id',
            $this->getProperty('http://religiousecologies.org/vocab#', 'county')->getId(),
            $this->getProperty('http://religiousecologies.org/vocab#', 'denomination')->getId()
        );
        $query = $em->createQuery($dql);
        $query->setParameters([
            'county_id' => $countyId,
            'denomination_id' => $denominationId,
        ]);
        return $query->getSingleScalarResult();
    }

    /**
     * Get the Doctrine entity manager.
     *
     * @return EntityManager
     */
    public function getEntityManager() : EntityManager
    {
        return $this->services->get('Omeka\EntityManager');
    }

    /**
     * Get the Omeka API manager.
     *
     * @return ApiManager
     */
    public function getApiManager() : ApiManager
    {
        return $this->services->get('Omeka\ApiManager');
    }
}
