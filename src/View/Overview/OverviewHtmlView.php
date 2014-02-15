<?php

namespace JoomlaDistro\CmsInstallerApplication\View\Overview;

use JoomlaDistro\CmsInstallerApplication\View\DefaultHtmlView;

class OverviewHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->form 		= $this->model->getForm('overview');
		$this->phpoptions  	= $this->model->getPhpOptions();
		$this->phpsettings 	= $this->model->getPhpSettings();
		$this->options     	= $this->model->getOptions();
    }
}
