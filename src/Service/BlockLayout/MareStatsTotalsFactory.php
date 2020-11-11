<?php
namespace Mare\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Mare\BlockLayout\MareStatsTotals;
use Zend\ServiceManager\Factory\FactoryInterface;

class MareStatsTotalsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MareStatsTotals($services->get('Mare\Mare'));
    }
}
