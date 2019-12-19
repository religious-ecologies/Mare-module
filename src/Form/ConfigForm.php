<?php
namespace Mare\Form;

use Zend\Form\Form;
use Zend\Validator\Callback;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => 'checkbox',
            'name' => 'link_schedules',
            'options' => [
                'label' => 'Link "Schedule (1926)" items', // @translate
                'info' => 'This will link "Schedule (1926)" items to their linked items.', // @translate
            ],
        ]);
        $this->add([
            'type' => 'checkbox',
            'name' => 'derive_ahcb_state_territory_ids_schedules',
            'options' => [
                'label' => 'Add state IDs to "Schedule (1926)" items', // @translate
                'info' => 'This will derive a "AHCB state/territory ID" from the "AHCB county ID" for each "Schedule (1926)" item.' // @translate
            ],
        ]);
        $this->add([
            'type' => 'checkbox',
            'name' => 'derive_ahcb_state_territory_ids_counties',
            'options' => [
                'label' => 'Add state IDs to "County" items', // @translate
                'info' => 'This will derive a "AHCB state/territory ID" from the "AHCB county ID" for each "County" item.' // @translate
            ],
        ]);
    }
}
