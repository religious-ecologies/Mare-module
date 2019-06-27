<?php
namespace Mare;

use Doctrine\DBAL\Connection;
use Mare\Form\ConfigForm;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /**
     * @var array Cache of vocabulary members (classes and properties).
     */
    protected $vocabMembers;

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
        $formData = $form->getData();
        if ($formData['link_schedules']) {
            $api = $services->get('Omeka\ApiManager');
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
            $services->get('Omeka\Job\Dispatcher')->dispatch(
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
                    ],
                ]
            );
        }
        return true;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add section navigation to items.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if ($this->isClass('mare:Schedule', $item)) {
                    $sectionNav = $event->getParam('section_nav');
                    $sectionNav['mare-schedule-transcribe'] = 'Transcribe';
                    //~ $event->setParam('section_nav', $sectionNav);
                }
            }
        );
        // Add section content to items.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if ($this->isClass('mare:Schedule', $item)) {
                    //~ echo $view->partial('religious-ecologies/transcribe', []);
                }
            }
        );
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
