<?php

namespace App\View\Database;

use App\App;
use App\View\DefaultHtmlView;

class DatabaseHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->form = $this->model->getForm('database');
    }
}
