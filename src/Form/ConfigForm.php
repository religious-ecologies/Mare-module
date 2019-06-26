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
                'label' => 'Link "Schedule (1926)" schedules', // @translate
                'info' => 'Link "Schedule (1926)" schedules to their linked items.', // @translate
            ],
        ]);
    }
}
