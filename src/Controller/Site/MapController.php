<?php
namespace Mare\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class MapController extends AbstractActionController
{
    const COUNTY_IDS = [
        'ak_terr',  'al_state', 'ar_state', 'az_state', 'ca_state',
        'co_state', 'ct_state', 'dc',       'de_state', 'fl_state',
        'ga_state', 'hi_terr',  'ia_state', 'id_state', 'il_state',
        'in_state', 'ks_state', 'ky_state', 'la_state', 'ma_state',
        'md_state', 'me_state', 'mi_state', 'mn_state', 'mo_state',
        'ms_state', 'mt_state', 'nc_state', 'nd_state', 'ne_state',
        'nh_state', 'nj_state', 'nm_state', 'nv_state', 'ny_state',
        'oh_state', 'ok_state', 'or_state', 'pa_state', 'ri_state',
        'sc_state', 'sd_state', 'tn_state', 'tx_state', 'ut_state',
        'va_state', 'vt_state', 'wa_state', 'wi_state', 'wv_state',
        'wy_state',
    ];

    public function indexAction()
    {
        $response = $this->api()->search('items', ['resource_class_id' => 110, 'sort_by' => 'title']);
        $valueOptions = [];
        foreach ($response->getContent() as $denomination) {
            $denominationId = $denomination->value('mare:denominationId');
            if ($denominationId) {
                $valueOptions[$denominationId->value()] = $denomination->title();
            }
        }
        $denominationSelect = (new \Laminas\Form\Element\Select('denomination'))
            ->setValueOptions($valueOptions)
            ->setEmptyOption('[All denominations]')
            ->setAttribute('id', 'denomination-select');

        $view = new ViewModel;
        $view->setVariable('countyIds', self::COUNTY_IDS);
        $view->setVariable('denominationSelect', $denominationSelect);
        return $view;
    }
}
