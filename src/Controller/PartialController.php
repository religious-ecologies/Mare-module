<?php
namespace Mare\Controller;

use Mare\Stdlib\Mare;
use Omeka\Entity\Property;
use Omeka\Entity\ResourceClass;
use Omeka\Api\Representation\ItemRepresentation;
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

        $data = $this->getParentsNavData($stateTerritoryClass, $countyClass, $stateTerritoryProperty);

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('statesTerritories', $data);
        return $view;
    }

    public function countiesNavAction()
    {
        $stateTerritory = $this->api()->read('items', $this->params()->fromQuery('state-territory-id'))->getContent();

        $countyClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'County');
        $stateTerritoryProperty = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'stateTerritory');

        $data = $this->getChildrenNavData($stateTerritory, $countyClass, $stateTerritoryProperty, 'mare:countyName');

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('counties', $data);
        return $view;
    }

    public function denominationFamiliesNavAction()
    {
        $denominationFamilyClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'DenominationFamily');
        $denominationClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'Denomination');
        $denominationFamilyProperty = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'denominationFamily');

        $data = $this->getParentsNavData($denominationFamilyClass, $denominationClass, $denominationFamilyProperty);

        // Add the "Uncategorized" family of denominations.
        $query = [
            'limit' => 0,
            'resource_class_id' => $denominationClass->getId(),
            'property' => [
                [
                    'joiner' => 'and',
                    'property' => $denominationFamilyProperty->getId(),
                    'type' => 'nex',
                ],
            ],
        ];
        $childCount = $this->api()->search('items', $query)->getTotalResults();
        $data[] = [
            'representation' => null,
            'id' => 0,
            'title' => 'Uncategorized',
            'child_count' => $childCount,
        ];

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('denominationFamilies', $data);
        return $view;
    }

    public function denominationsNavAction()
    {
        $denominationFamilyId = (int) $this->params()->fromQuery('denomination-family-id');
        $denominationClass = $this->mare->getResourceClass('http://religiousecologies.org/vocab#', 'Denomination');
        $denominationFamilyProperty = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'denominationFamily');

        if (0 === $denominationFamilyId) {
            // Get denominations without a family.
            $query = [
                'sort_by' => 'title',
                'sort_order' => 'asc',
                'resource_class_id' => $denominationClass->getId(),
                'property' => [
                    [
                        'joiner' => 'and',
                        'property' => $denominationFamilyProperty->getId(),
                        'type' => 'nex',
                    ],
                ],
            ];
            $response = $this->api()->search('items', $query);
            $data = [];
            foreach ($response->getContent() as $denomination) {
                $data[] = [
                    'representation' => $denomination,
                    'id' => $denomination->id(),
                    'title' => $denomination->title(),
                ];
            }
        } else {
            $denominationFamily = $this->api()->read('items', $denominationFamilyId)->getContent();
            $data = $this->getChildrenNavData($denominationFamily, $denominationClass, $denominationFamilyProperty, 'dcterms:title');
        }

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('denominations', $data);
        return $view;
    }

    /**
     * Get data needed for navigating parent items.
     *
     * @param ResourceClass $parentClass
     * @param ResourceClass $childClass
     * @param Property $parentProperty
     * @return array
     */
    public function getParentsNavData(ResourceClass $parentClass, ResourceClass $childClass, Property $parentProperty)
    {
        $query = [
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'resource_class_id' => $parentClass->getId(),
        ];
        $response = $this->api()->search('items', $query);

        $parents = [];
        foreach ($response->getContent() as $parent) {
            $query = [
                'limit' => 0,
                'resource_class_id' => $childClass->getId(),
                'property' => [
                    [
                        'joiner' => 'and',
                        'property' => $parentProperty->getId(),
                        'type' => 'res',
                        'text' => $parent->id(),
                    ],
                ],
            ];
            $childCount = $this->api()->search('items', $query)->getTotalResults();
            if ($childCount) {
                $parents[] = [
                    'representation' => $parent,
                    'id' => $parent->id(),
                    'title' => $parent->title(),
                    'child_count' => $childCount,
                ];
            }
        }
        return $parents;
    }

    /**
     * Get data needed for navigating child items.
     *
     * @param ItemRepresentation $parent
     * @param ResourceClass $childClass
     * @param Property $parentProperty
     * @param string $childTitleTerm
     * @return array
     */
    public function getChildrenNavData(ItemRepresentation $parent, ResourceClass $childClass, Property $parentProperty, $childTitleTerm)
    {
        $query = [
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'resource_class_id' => $childClass->getId(),
            'property' => [
                [
                    'joiner' => 'and',
                    'property' => $parentProperty->getId(),
                    'type' => 'res',
                    'text' => $parent->id(),
                ],
            ],
        ];
        $response = $this->api()->search('items', $query);
        $children = [];
        foreach ($response->getContent() as $child) {
            $children[] = [
                'representation' => $child,
                'id' => $child->id(),
                'title' => $child->value($childTitleTerm)->value(),
            ];
        }
        return $children;
    }

    public function countyAction()
    {
        $countyId = (int) $this->params()->fromQuery('county-id');
        $county = $this->api()->read('items', $countyId)->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('county', $county);
        return $view;
    }

    public function countySchedulesAction()
    {
        $countyId = (int) $this->params()->fromQuery('county-id');
        $page = (int) $this->params()->fromQuery('page', 1);
        $perPage = 25;

        $county = $this->api()->read('items', $countyId)->getContent();
        $property = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'county');
        $totalCount = $county->subjectValueTotalCount($property->getId());

        $schedules = [];
        $subjectValues = $county->subjectValues($page, $perPage, $property->getId());
        if (isset($subjectValues['mare:county'])) {
            foreach($subjectValues['mare:county'] as $value) {
                $schedules[] = $value->resource();
            }
        }

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('county', $county);
        $view->setVariable('schedules', $schedules);
        $view->setVariable('page', $page);
        $view->setVariable('perPage', $perPage);
        $view->setVariable('totalCount', $totalCount);
        return $view;
    }

    public function denominationAction()
    {
        $denominationId = (int) $this->params()->fromQuery('denomination-id');
        $denomination = $this->api()->read('items', $denominationId)->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('denomination', $denomination);
        return $view;
    }

    public function denominationSchedulesAction()
    {
        $denominationId = (int) $this->params()->fromQuery('denomination-id');
        $page = (int) $this->params()->fromQuery('page', 1);
        $perPage = 25;

        $denomination = $this->api()->read('items', $denominationId)->getContent();
        $property = $this->mare->getProperty('http://religiousecologies.org/vocab#', 'denomination');
        $totalCount = $denomination->subjectValueTotalCount($property->getId());

        $schedules = [];
        $subjectValues = $denomination->subjectValues($page, $perPage, $property->getId());
        if (isset($subjectValues['mare:denomination'])) {
            foreach($subjectValues['mare:denomination'] as $value) {
                $schedules[] = $value->resource();
            }
        }

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('denomination', $denomination);
        $view->setVariable('schedules', $schedules);
        $view->setVariable('page', $page);
        $view->setVariable('perPage', $perPage);
        $view->setVariable('totalCount', $totalCount);
        return $view;
    }
}
