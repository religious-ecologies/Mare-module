<?php
namespace Mare\Controller\Site;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class MapController extends AbstractActionController
{
    public function browseAction()
    {
        $view = new ViewModel;
        return $view;
    }
}
