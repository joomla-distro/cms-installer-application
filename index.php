<?php
opcache_reset();

ini_set("display_errors",true);
error_reporting(E_ALL);
set_time_limit(0);
/**
 * @copyright  Copyright (C) 2012 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// Define required paths
define('JPATH_ROOT',  __DIR__);
define('JPATH_INSTALLATION', dirname(__DIR__) . '/installer');
define('JPATH_ADMINISTRATOR', dirname(__DIR__) . '/administrator');
define('JPATH_SITE', dirname(__DIR__) . '/frontend');
define('JPATH_APP_ETC', JPATH_INSTALLATION . '/etc');
define('JPATH_APP_TEMPLATE', JPATH_INSTALLATION . '/template');
// root paths
define('JPATH_ETC', dirname(__DIR__) . '/etc');
define('JPATH_VENDOR', dirname(__DIR__) . '/vendor');


// Load the Composer autoloader
$vendor_autoload = JPATH_VENDOR . '/autoload.php';
if (!file_exists($vendor_autoload) || (is_dir(basename($vendor_autoload)) && !file_exists(basename($vendor_autoload)) )) {
    throw new Exception('run composer install');
}
require $vendor_autoload;

$container = new \Joomla\DI\Container;
$container->registerServiceProvider(new \App\Service\ConfigurationServiceProvider(JPATH_ETC . '/config.json'))
    ->registerServiceProvider(new \App\Service\DatabaseServiceProvider);

// Instantiate the application.
$application = new \App\App($container);
// Execute the application.
$application->execute();
