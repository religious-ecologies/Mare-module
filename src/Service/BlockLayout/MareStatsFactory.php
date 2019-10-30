<?php
namespace Mare\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Mare\BlockLayout\MareStats;
use Zend\ServiceManager\Factory\FactoryInterface;

class MareStatsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MareStats(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiManager')
        );
    }
}
