<?php
namespace Mare;

use Doctrine\DBAL\Connection;
use Mare\Form\ConfigForm;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /**
     * @var array Cache of vocabulary members (classes and properties).
     */
    protected $vocabMembers;

    public function getConfig()
    {
        return [
            'service_manager' => [
                'factories' => [
                    'Mare\Mare' => Service\MareFactory::class,
                ],
            ],
            'view_manager' => [
                'template_path_stack' => [
                    OMEKA_PATH . '/modules/Mare/view',
                ],
            ],
            'controllers' => [
                'factories' => [
                    'Mare\Controller\Site\Partial' => Service\Controller\PartialControllerFactory::class,
                ],
                'invokables' => [
                    'Mare\Controller\Site\Map' => Controller\Site\MapController::class,
                ],
            ],
            'block_layouts' => [
                'factories' => [
                    'mareStats' => Service\BlockLayout\MareStatsFactory::class,
                ],
            ],
            'navigation_links' => [
                'invokables' => [
                    'mare_schedule_map' => Site\NavigationLink\ScheduleMap::class,
                ],
            ],
            'router' => [
                'routes' => [
                    'site' => [
                        'child_routes' => [
                            'mare' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/mare/:controller/:action',
                                    'constraints' => [
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Mare\Controller\Site',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            [
                'Mare\Controller\Site\Partial',
            ]
        );
    }

    /**
     * Install this module only once.
     *
     * Any subsequent changes to the data model should be done via the user
     * interface or during self::upgrade() after a version bump.
     */
    public function install(ServiceLocatorInterface $services)
    {
        $this->installDataModel($services);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $form = new ConfigForm;
        $form->init();
        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $form = new ConfigForm;
        $form->init();
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $dispatcher = $services->get('Omeka\Job\Dispatcher');

        $scheduleTemplate = $api->search(
            'resource_templates',
            ['label' => 'Schedule (1926)']
        )->getContent()[0];
        $countyTemplate = $api->search(
            'resource_templates',
            ['label' => 'County']
        )->getContent()[0];
        $denominationTemplate = $api->search(
            'resource_templates',
            ['label' => 'Denomination']
        )->getContent()[0];
        $denominationFamilyTemplate = $api->search(
            'resource_templates',
            ['label' => 'Denomination Family']
        )->getContent()[0];
        $stateTerritoryTemplate = $api->search(
            'resource_templates',
            ['label' => 'State/Territory']
        )->getContent()[0];

        $formData = $form->getData();
        if ($formData['derive_ahcb_state_territory_ids_schedules']) {
            $dispatcher->dispatch(
                'Mare\Job\DeriveAhcbStateTerritoryIds',
                ['resource_template_id' => $scheduleTemplate->id()]
            );
        }
        if ($formData['derive_ahcb_state_territory_ids_counties']) {
            $dispatcher->dispatch(
                'Mare\Job\DeriveAhcbStateTerritoryIds',
                ['resource_template_id' => $countyTemplate->id()]
            );
        }
        if ($formData['link_schedules']) {
            $dispatcher->dispatch(
                'Mare\Job\LinkItems',
                [
                    'linking_items_query' => ['resource_template_id' => $scheduleTemplate->id()],
                    'links' => [
                        [
                            'linked_items_query' => ['resource_template_id' => $countyTemplate->id()],
                            'linked_id_property_term' => 'mare:ahcbCountyId',
                            'linking_property_term' => 'mare:county',
                        ],
                        [
                            'linked_items_query' => ['resource_template_id' => $denominationTemplate->id()],
                            'linked_id_property_term' => 'mare:denominationId',
                            'linking_property_term' => 'mare:denomination',
                        ],
                        [
                            'linked_items_query' => ['resource_template_id' => $stateTerritoryTemplate->id()],
                            'linked_id_property_term' => 'mare:ahcbStateTerritoryId',
                            'linking_property_term' => 'mare:stateTerritory',
                        ],
                    ],
                ]
            );
        }
        if ($formData['link_counties']) {
            $dispatcher->dispatch(
                'Mare\Job\LinkItems',
                [
                    'linking_items_query' => ['resource_template_id' => $countyTemplate->id()],
                    'links' => [
                        [
                            'linked_items_query' => ['resource_template_id' => $stateTerritoryTemplate->id()],
                            'linked_id_property_term' => 'mare:ahcbStateTerritoryId',
                            'linking_property_term' => 'mare:stateTerritory',
                        ],
                    ],
                ]
            );
        }
        if ($formData['link_denominations']) {
            $dispatcher->dispatch(
                'Mare\Job\LinkItems',
                [
                    'linking_items_query' => ['resource_template_id' => $denominationTemplate->id()],
                    'links' => [
                        [
                            'linked_items_query' => ['resource_template_id' => $denominationFamilyTemplate->id()],
                            'linked_id_property_term' => 'mare:denominationFamilyName',
                            'linking_property_term' => 'mare:denominationFamily',
                        ],
                    ],
                ]
            );
        }
        return true;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'showDenominationStats']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'showCountyStats']
        );
    }

    /**
     * Show denomination stats on a denomination item page.
     *
     * @param Event $event
     */
    public function showDenominationStats(Event $event)
    {
        $view = $event->getTarget();
        $item = $view->item;
        if (!$this->isClass('mare:Denomination', $item)) {
            return;
        }
        $mare = $this->getServiceLocator()->get('Mare\Mare');
        $itemAdapter = $this->getServiceLocator()->get('Omeka\ApiAdapterManager')->get('items');
        $countyEntities = $mare->getCountiesByDenomination($item->id());
        $counties = [];
        $stateTerritories = [];
        foreach ($countyEntities as $countyEntity) {
            $countyRepresentation = $itemAdapter->getRepresentation($countyEntity);
            $stateTerritory = $countyRepresentation->value('mare:stateTerritoryName')->value();
            if (!isset($stateTerritories[$stateTerritory])) {
                $stateTerritories[$stateTerritory] = [
                    'state_territory_representation' => null,
                    'county_representations' => [],
                    'schedule_count' => 0,
                ];
            }
            if (null === $stateTerritories[$stateTerritory]['state_territory_representation']) {
                $stateTerritoryResourceValue = $countyRepresentation->value('mare:stateTerritory');
                if ($stateTerritoryResourceValue) {
                    $stateTerritories[$stateTerritory]['state_territory_representation'] = $stateTerritoryResourceValue->valueResource();
                }
            }
            $scheduleCount = $mare->getScheduleCountInCountyForDenomination($countyRepresentation->id(), $item->id());
            $counties[] = [
                'county_representation' => $countyRepresentation,
                'county_title' => $countyRepresentation->title(),
                'state_territory' => $stateTerritory,
                'schedule_count' => $scheduleCount,
            ];
            $stateTerritories[$stateTerritory]['county_representations'][] = $countyRepresentation;
            $stateTerritories[$stateTerritory]['schedule_count'] += $scheduleCount;
        }
        uasort($counties, function($a, $b) {
            if ($a['schedule_count'] === $b['schedule_count']) {
                return strcmp($a['county_title'], $b['county_title']);
            }
            return ($a['schedule_count'] > $b['schedule_count']) ? -1 : 1;
        });
        uasort($stateTerritories, function($a, $b) {
            if ($a['schedule_count'] === $b['schedule_count']) {
                return 0;
            }
            return ($a['schedule_count'] > $b['schedule_count']) ? -1 : 1;
        });
        echo $view->partial('mare/denomination-stats', [
            'counties' => $counties,
            'stateTerritories' => $stateTerritories,
        ]);
    }

    /**
     * Show county stats on a county item page.
     *
     * @param Event $event
     */
    public function showCountyStats(Event $event)
    {
        $view = $event->getTarget();
        $item = $view->item;
        if (!$this->isClass('mare:County', $item)) {
            return;
        }
        $mare = $this->getServiceLocator()->get('Mare\Mare');
        $itemAdapter = $this->getServiceLocator()->get('Omeka\ApiAdapterManager')->get('items');
        $denominationEntities = $mare->getDenominationsByCounty($item->id());
        $denominations = [];
        foreach ($denominationEntities as $denominationEntity) {
            $denominationRepresentation = $itemAdapter->getRepresentation($denominationEntity);
            $scheduleCount = $mare->getScheduleCountInDenominationForCounty($denominationRepresentation->id(), $item->id());
            $denominations[] = [
                'denomination_representation' => $denominationRepresentation,
                'denomination_title' => $denominationRepresentation->title(),
                'schedule_count' => $scheduleCount,
            ];
        }
        uasort($denominations, function($a, $b) {
            if ($a['schedule_count'] === $b['schedule_count']) {
                return strcmp($a['denomination_title'], $b['denomination_title']);
            }
            return ($a['schedule_count'] > $b['schedule_count']) ? -1 : 1;
        });
        echo $view->partial('mare/county-stats', [
            'denominations' => $denominations,
        ]);
    }

    /**
     * Check whether the passed item is an instance of the passed class.
     *
     * @param string $className
     * @param ItemRepresentation $item
     * @return bool
     */
    public function isClass($className, ItemRepresentation $item)
    {
        $class = $item->resourceClass();
        if (!$class) {
            return false;
        }
        if ($className !== $class->term()) {
            return false;
        }
        return true;
    }

    /**
     * Get vocabulary members (classes and properties).
     *
     * @param Connection $conn
     * @return array
     */
    public function getVocabMembers(Connection $conn)
    {
        if (isset($this->vocabMembers)) {
            return $this->vocabMembers;
        }
        // Cache vocab members.
        $vocabMembers = [];
        foreach (['resource_class', 'property'] as $member) {
            $sql = 'SELECT m.id, m.local_name, v.prefix FROM %s m JOIN vocabulary v ON m.vocabulary_id = v.id';
            $stmt = $conn->query(sprintf($sql, $member));
            $vocabMembers[$member] = [];
            foreach ($stmt as $row) {
                $vocabMembers[$member][sprintf('%s:%s', $row['prefix'], $row['local_name'])] = $row['id'];
            }
        }
        return $this->vocabMembers = $vocabMembers;
    }

    /**
     * Install the initial data model.
     *
     * @param ServiceLocatorInterface $services
     */
    public function installDataModel(ServiceLocatorInterface $services)
    {
        $importer = $services->get('Omeka\RdfImporter');
        $conn = $services->get('Omeka\Connection');
        $api = $services->get('Omeka\ApiManager');

        // Import the MARE vocabulary.
        $importer->import(
            'file',
            [
                'o:namespace_uri' => 'http://religiousecologies.org/vocab#',
                'o:prefix' => 'mare',
                'o:label' => 'Mapping American Religious Ecologies',
                'o:comment' =>  null,
            ],
            [
                'file' => __DIR__ . '/vocabs/mare.n3',
                'format' => 'turtle',
            ]
        );

        $vocabMembers = $this->getVocabMembers($conn);

        // Create the MARE item sets.
        $response = $api->batchCreate('item_sets', [
            [
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $vocabMembers['property']['dcterms:title'],
                        '@value' => 'Schedules',
                    ],
                ],
            ],
            [
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $vocabMembers['property']['dcterms:title'],
                        '@value' => 'Denominations',
                    ],
                ],
            ],
            [
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $vocabMembers['property']['dcterms:title'],
                        '@value' => 'Counties',
                    ],
                ],
            ],
            [
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $vocabMembers['property']['dcterms:title'],
                        '@value' => '1926 U.S. Census of Religious Bodies',
                    ],
                ],
            ],
        ]);

        // Create the MARE resource templates.
        $response = $api->batchCreate('resource_templates', [
            [
                'o:label' => 'Schedule (1926)',
                'o:resource_class' => ['o:id' => $vocabMembers['resource_class']['mare:Schedule']],
                'o:resource_template_property' => [
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:title']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:scheduleId']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:creator']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:source']],
                        'o:data_type' => 'uri',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:box']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:denomination']],
                        'o:data_type' => 'resource:item',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:denominationId']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:county']],
                        'o:data_type' => 'resource:item',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:ahcbCountyId']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:digitized']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:digitizedBy']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:catalogedBy']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:imageRecheck']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:imageOriginalPath']],
                        'o:data_type' => 'literal',
                    ],
                ],
            ],
            [
                'o:label' => 'Denomination',
                'o:resource_class' => ['o:id' => $vocabMembers['resource_class']['mare:Denomination']],
                'o:resource_template_property' => [
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:title']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:alternative']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:denominationId']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:denominationFamily']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:description']],
                        'o:data_type' => 'literal',
                    ],
                ]
            ],
            [
                'o:label' => 'County',
                'o:resource_class' => ['o:id' => $vocabMembers['resource_class']['mare:County']],
                'o:resource_template_property' => [
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:title']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:ahcbCountyId']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:fipsCountyCode']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:countyName']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['mare:stateTerritory']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:type']],
                        'o:data_type' => 'literal',
                    ],
                    [
                        'o:property' => ['o:id' => $vocabMembers['property']['dcterms:source']],
                        'o:data_type' => 'uri',
                    ],
                ]
            ],
        ]);
    }
}
