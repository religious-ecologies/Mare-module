<?php
namespace Mare\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Mare\BlockLayout\MareStatsSchedulesPerCounty;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MareStatsSchedulesPerCountyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MareStatsSchedulesPerCounty($services->get('Mare\Mare'));
    }
}
