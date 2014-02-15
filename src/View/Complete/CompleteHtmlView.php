<?php

namespace JoomlaDistro\CmsInstallerApplication\View\Complete;

use JoomlaDistro\CmsInstallerApplication\View\DefaultHtmlView;

class CompleteHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->options = $this->model->getOptions();
        // Get the config string from the session.
        $session = $app->getSession();
        $this->config = $session->get('setup.config', null);
    }
}
