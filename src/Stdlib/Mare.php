<?php
namespace Mare\Stdlib;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\Property;
use Omeka\Entity\ResourceClass;
use Zend\ServiceManager\ServiceManager;

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
