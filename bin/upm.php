<?php
declare(strict_types=1);

require_once __DIR__."/../zip/vendor/autoload.php";

use UCRM\Core\Plugin;

const COMPOSER_PATH = "composer";
const COMPOSER_ARGS = [
    "--working-dir=../zip/",
    "--no-interaction",
    "--verbose",
    "dump-autoload"
];

switch($argv[1])
{
    case "bundle":
        system(COMPOSER_PATH." ".implode(" ", COMPOSER_ARGS));
        Plugin::bundle();
        break;

    // TODO: More commands to come!

    default:
        break;
}

