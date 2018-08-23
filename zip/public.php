<?php

require __DIR__."/vendor/autoload.php";

use AirNMS\Logger;

/**
 * public.php (optional)
 *
 * If this file is present, public URL will be generated for the plugin which will point to this file. When the URL is
 * accessed, the file will be parsed as PHP script and executed.
 *
 */

chdir(__DIR__);



$logger = new Logger();

$logger->log(
    sprintf(
        'Executed from public URL: %s',
        file_get_contents("php://input")
    )
);
