<?php
namespace Mare;

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function install(ServiceLocatorInterface $services)
    {
        // Import the MARE vocabulary if it doesn't already exist.
        $api = $services->get('Omeka\ApiManager');
        $response = $api->search('vocabularies', [
            'namespace_uri' => 'http://religiousecologies.org/vocab#',
            'limit' => 0,
        ]);
        if (0 === $response->getTotalResults()) {
            $importer = $services->get('Omeka\RdfImporter');
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
        }
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
    protected function isClass($className, ItemRepresentation $item)
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
}
