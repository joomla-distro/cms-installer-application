<?php

namespace JoomlaDistro\CmsInstallerApplication\View\Preinstall;

use JoomlaDistro\CmsInstallerApplication\View\DefaultHtmlView;

class PreinstallHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->form = $this->model->getForm('preinstall');
		$this->options = $this->model->getPhpOptions();
		$this->settings = $this->model->getPhpSettings();
    }
}
