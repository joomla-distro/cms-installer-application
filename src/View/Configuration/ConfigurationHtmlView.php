<?php

namespace JoomlaDistro\CmsInstallerApplication\View\Configuration;

use JoomlaDistro\CmsInstallerApplication\View\DefaultHtmlView;

class ConfigurationHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->form = $this->model->getForm('configuration');
    }
}
