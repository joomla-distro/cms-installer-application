<?php

namespace App\View;

use App\App;
use Joomla\View\AbstractHtmlView;
use Joomla\Language\Language;

class DefaultJsonView extends AbstractHtmlView
{
    public function __construct($app, $model, array $paths)
    {
    	$input = $app->input;
        $response = array();

        $response['token'] = $app->getFormToken();
        $response['lang'] = $app->getSession()->get('default.language');
        $response['messages'] = $app->getMessageQueue();
        $response['error'] = false;
        $response['data'] = array();
        $response['data']['view'] = $input->getCmd('view');

        echo json_encode($response);
        $app->close();
    }
}
