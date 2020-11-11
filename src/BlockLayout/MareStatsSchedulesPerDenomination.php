<?php
namespace Mare\BlockLayout;

use Doctrine\ORM\EntityManager;
use Mare\Stdlib\Mare;
use Omeka\Api\Manager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Zend\View\Renderer\PhpRenderer;

class MareStatsSchedulesPerDenomination extends AbstractMareStats
{
    public function getLabel()
    {
        return 'MARE stats: schedules per denomination'; // @translate
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $api = $this->mare->getApiManager();
        $html = [];

        // Schedules per denomination
        $html[] = '<div class="mare-denomination-schedules">';
        $html[] = '<h3>Schedules per denomination (top 25)</h3>';
        $html[] = '<table><thead><tr><th scope="col">Denomination</th><th scope="col">Schedule count</th></tr></thead><tbody>';
        $denominations = $this->getSchedulesPer($this->denomination->getId(), $this->denominationId->getId());
        foreach ($denominations as $denomination) {
            $html[] = sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                $denomination['title'],
                $view->hyperlink(
                    number_format($denomination['schedule_count']),
                    $view->url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['query' => $denomination['query']], true)
                ));
        }
        $html[] = '</tbody></table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}
