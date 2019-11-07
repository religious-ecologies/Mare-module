<?php
namespace Mare\Service;

use Interop\Container\ContainerInterface;
use Mare\Stdlib\Mare;
use Zend\ServiceManager\Factory\FactoryInterface;

class MareFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Mare($services);
    }
}
