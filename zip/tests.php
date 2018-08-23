<?php
declare(strict_types=1);

require_once __DIR__."/./vendor/autoload.php";

use UCRM\Options;

var_dump(Options::config());
var_dump(Options::plugin(__DIR__."/./ucrm.json"));