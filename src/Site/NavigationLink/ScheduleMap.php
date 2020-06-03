<?php
namespace Mare\Site\NavigationLink;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class ScheduleMap implements LinkInterface
{
    public function getName()
    {
        return 'Schedule Map'; // @translate
    }

    public function getFormTemplate()
    {
        return 'mare/schedule-map-navigation-link';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return $data['label'] ?? $this->getName();
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return [
            'route' => 'site/mare',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => 'map',
                'action' => 'index',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
        ];
    }
}
