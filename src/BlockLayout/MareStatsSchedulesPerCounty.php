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

class MareStatsSchedulesPerCounty extends AbstractMareStats
{
    public function getLabel()
    {
        return 'MARE stats: schedules per county'; // @translate
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $api = $this->mare->getApiManager();
        $html = [];

        // Schedules per county
        $html[] = '<div class="mare-county-schedules">';
        $html[] = '<h3>Schedules per county (top 25)</h3>';
        $html[] = '<table><thead><tr><th scope="col">County</th><th scope="col">Schedule count</th></tr></thead><tbody>';
        $counties = $this->getSchedulesPer($this->county->getId(), $this->ahcbCountyId->getId());
        foreach ($counties as $county) {
            $html[] = sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                $county['title'],
                $view->hyperlink(
                    number_format($county['schedule_count']),
                    $view->url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['query' => $county['query']], true)
                ));
        }
        $html[] = '</tbody></table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}
