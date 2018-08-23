<?php

require __DIR__."/vendor/autoload.php";

use AirNMS\Logger;

/**
 * main.php (required)
 *
 * Main file of the plugin. This is what will be executed when the plugin is run by UCRM.
 *
 */

chdir(__DIR__);



(function () {
    $logger = new Logger();
    $logger->log('Finished');
})();
