<?php
namespace Mare\Service\Delegator;

use Mare\Form\Element as MareElement;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\DelegatorFactoryInterface;

class FormElementDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name,
        callable $callback, array $options = null
    ) {
        $formElement = $callback();
        $formElement->addClass(MareElement\PopulatedPlaceSelect::class, 'mareFormPopulatedPlaceSelect');
        return $formElement;
    }
}
