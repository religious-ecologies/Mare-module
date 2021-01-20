<?php
namespace Mare\BlockLayout;

use Doctrine\ORM\EntityManager;
use Mare\Stdlib\Mare;
use Omeka\Api\Manager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Laminas\View\Renderer\PhpRenderer;

class MareStatsTotals extends AbstractMareStats
{
    public function getLabel()
    {
        return 'MARE stats: totals'; // @translate
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $api = $this->mare->getApiManager();
        $html = [];

        // Totals
        $scheduleCount = $api->read('resource_classes', $this->schedule->getId())->getContent()->itemCount();
        $denominationCount = $api->read('resource_classes', $this->denomination->getId())->getContent()->itemCount();
        $countyCount = $api->read('resource_classes', $this->county->getId())->getContent()->itemCount();
        $html[] = '<div class="mare-totals">';
        $html[] = '<h3>Totals</h3>';
        $html[] = sprintf('<p><b>%s</b> schedules digitized (<b>%s</b>%% of total)</p>', number_format($scheduleCount), number_format(100 * ($scheduleCount / self::SCHEDULE_TOTAL_COUNT), 1));
        $html[] = sprintf('<p><b>%s</b> denominations</p>', number_format($denominationCount));
        $html[] = sprintf('<p><b>%s</b> counties</p>', number_format($countyCount));
        $html[] = '</div>';

        return implode("\n", $html);
    }
}
