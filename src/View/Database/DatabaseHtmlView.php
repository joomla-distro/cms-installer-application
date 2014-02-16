<?php

namespace JoomlaDistro\CmsInstallerApplication\View\Database;

use JoomlaDistro\CmsInstallerApplication\View\DefaultHtmlView;

class DatabaseHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->form = $this->model->getForm('database');
    }
}
