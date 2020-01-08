<?php
namespace Mare\Controller;

use Mare\Stdlib\Mare;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class PartialController extends AbstractActionController
{
    protected $mare;

    public function __construct(Mare $mare)
    {
        $this->mare = $mare;
    }

    public function statesTerritoriesNavAction()
    {
        $stateTerritoryClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'StateTerritory');
        $countyClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'County');
        $stateTerritoryProperty = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'stateTerritory');

        $query = [
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'resource_class_id' => $stateTerritoryClass->getId(),
        ];
        $response = $this->api()->search('items', $query);

        $statesTerritories = [];
        foreach ($response->getContent() as $stateTerritory) {
            // Get counties where mare:stateTerritory is this stateTerritory.
            $query = [
                'limit' => 0,
                'resource_class_id' => $countyClass->getId(),
                'property' => [
                    [
                        'joiner' => 'and',
                        'property' => $stateTerritoryProperty->getId(),
                        'type' => 'res',
                        'text' => $stateTerritory->id(),
                    ],
                ],
            ];
            $countyCount = $this->api()->search('items', $query)->getTotalResults();
            if ($countyCount) {
                $statesTerritories[] = [
                    'state_territory' => $stateTerritory,
                    'id' => $stateTerritory->id(),
                    'title' => $stateTerritory->title(),
                    'county_count' => $countyCount,
                ];
            }
        }

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('statesTerritories', $statesTerritories);
        return $view;
    }

    public function countiesNavAction()
    {
        $stateTerritory = $this->api()->read('items', $this->params()->fromQuery('state-territory-id'))->getContent();

        $countyClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'County');
        $stateTerritoryProperty = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'stateTerritory');

        $query = [
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'resource_class_id' => $countyClass->getId(),
            'property' => [
                [
                    'joiner' => 'and',
                    'property' => $stateTerritoryProperty->getId(),
                    'type' => 'res',
                    'text' => $stateTerritory->id(),
                ],
            ],
        ];
        $response = $this->api()->search('items', $query);
        $counties = [];
        foreach ($response->getContent() as $county) {
            $counties[] = [
                'county' => $county,
                'id' => $county->id(),
                'title' => $county->value('mare:countyName')->value(),
            ];
        }

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('counties', $counties);
        return $view;
    }
}
