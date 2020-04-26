<?php
namespace Mare\Form\ViewHelper;

use Zend\Form\Element;
use Zend\Form\ElementInterface;
use Zend\Form\View\Helper\AbstractHelper;

class MareFormPopulatedPlaceSelect extends AbstractHelper
{
    protected $states = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District Of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];

    public function __invoke(ElementInterface $element)
    {
        return $this->render($element);
    }

    public function render(ElementInterface $element)
    {
        $view = $this->getView();
        $view->headScript()->appendFile($view->assetUrl('js/mare-populated-place-select.js', 'Mare'));

        $placeIdHidden = (new Element\Hidden($element->getName()))
            ->setValue($element->getValue())
            ->setAttribute('class', 'place-id');

        $stateSelect = (new Element\Select('state'))
            ->setEmptyOption($view->translate('Select a state'))
            ->setValueOptions($this->states)
            ->setAttribute('class', 'state');

        $countySelect = (new Element\Select('county'))
            ->setAttribute('class', 'county')
            ->setAttribute('data-empty-option', $view->translate('Select a county'));

        $placeSelect = (new Element\Select('place'))
            ->setAttribute('class', 'place')
            ->setAttribute('data-empty-option', $view->translate('Select a place'));

        return sprintf(
            '<div class="mare-populated-place-select" data-fetch-error="%s">%s%s%s%s</div>',
            $view->escapeHtml($view->translate('Error fetching populated place data.')),
            $view->formHidden($placeIdHidden),
            $view->formSelect($stateSelect),
            $view->formSelect($countySelect),
            $view->formSelect($placeSelect)
        );
    }
}
