<?php

namespace App\View;

use App\App;
use Joomla\View\AbstractHtmlView;

class DefaultHtmlView extends AbstractHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        $this->application = $app;

        $q = new \SplPriorityQueue();
        if (!empty($paths)) {
            foreach ($paths as $path) {
                $q->insert($path);
            }
        }

        $q->insert(JPATH_TEMPLATE.'/html/', 1);

        parent::__construct($model, $q);
    }
}
