<?php
namespace Mare\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Mare\BlockLayout\MareStatsSchedulesPerDenomination;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MareStatsSchedulesPerDenominationFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MareStatsSchedulesPerDenomination($services->get('Mare\Mare'));
    }
}
