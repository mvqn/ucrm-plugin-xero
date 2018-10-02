<?php
declare(strict_types=1);

namespace AirNMS;
require __DIR__."/../vendor/autoload.php";

use XeroPHP\Application\PrivateApplication;
use XeroPHP\Application\PublicApplication;
use XeroPHP\Remote\Request;
use XeroPHP\Remote\URL;

list(
    $consumerKey,
    $consumerSecret,
    $privateKey,
    $callbackUrl,
    $startDate,
    $synchronizations) = array_values(Plugin::config()->getValues());

echo $consumerKey."<br/>";
echo $consumerSecret."<br/>";
echo $privateKey."<br/>";
echo $callbackUrl."<br/>";
echo $startDate."<br/>";
echo $synchronizations."<br/>";

die();

// Start a session for the oauth session storage
session_start();

//These are the minimum settings - for more options, refer to examples/config.php
$config = [
    "oauth" => [
        "callback"        => "http://localhost/",
        "consumer_key"    => "0LY4ETTYW5OYXUQSH11FFMELGIBU0R",
        "consumer_secret" => "5JIRHUXGID666HHHCHIUNGVDWAK86V",

        "rsa_private_key" => "file://".__DIR__."/../certs/private.pem"
    ]
];

$xero = new PrivateApplication($config);


use XeroPHP\Models\Accounting\Organisation;
use XeroPHP\Models\Accounting\Contact;

//print_r(json_encode($xero->load("Accounting\Contact")->execute()));
print_r(json_encode($xero->load("Accounting\Organisation")->execute()));


