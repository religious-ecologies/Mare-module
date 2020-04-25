<?php
namespace Mare\DatascribeDataType;

use Datascribe\DatascribeDataType\DataTypeInterface;
use Mare\Form\Element as MareElement;
use Zend\Form\Fieldset;

class PopulatedPlaceSelect implements DataTypeInterface
{
    public function getLabel() : string
    {
        return 'Populated place select'; // @translate
    }

    public function addFieldElements(Fieldset $fieldset, array $fieldData) : void
    {
    }

    public function getFieldDataFromUserData(array $userData) : array
    {
        return [];
    }

    public function fieldDataIsValid(array $fieldData) : bool
    {
        return true;
    }

    public function addValueElements(Fieldset $fieldset, array $fieldData, ?string $valueText) : void
    {
        $element = new MareElement\PopulatedPlaceSelect('value');
        $element->setLabel('Select a place'); // @translate
        $element->setValue($valueText);
        $fieldset->add($element);
    }

    public function getValueTextFromUserData(array $userData) : ?string
    {
        $placeId = null;
        if (isset($userData['value']) && is_numeric($userData['value'])) {
            $placeId = $userData['value'];
        }
        return $placeId;
    }

    public function valueTextIsValid(array $fieldData, ?string $valueText) : bool
    {
        return is_numeric($valueText);
    }
}
