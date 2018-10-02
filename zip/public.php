<?php
declare(strict_types=1);

namespace AirNMS;
require __DIR__."/./vendor/autoload.php";

use XeroPHP\Application\PrivateApplication;

list(
    $consumerKey,
    $consumerSecret,
    $privateKey,
    $callbackUrl,
    $startDate,
    $synchronizations) = array_values(Plugin::config()->getValues());

$startDate = new \DateTime($startDate);



$config = [
    "oauth" => [
        "callback"        => $callbackUrl,
        "consumer_key"    => $consumerKey,
        "consumer_secret" => $consumerSecret,

        "rsa_private_key" => $privateKey
    ]
];









$xero = new PrivateApplication($config);


use XeroPHP\Models\Accounting\Organisation;
use XeroPHP\Models\Accounting\Contact;

//print_r(json_encode($xero->load("Accounting\Contact")->execute()));
//print_r(json_encode($xero->load("Accounting\Organisation")->execute()));

$client = $xero->load(Contact::class)
    ->where("Name", "Spaeth, Ryan")
    ->execute();


//print_r(json_encode($client));
//echo "<br/>";


// =====================================================================================================================
// REST MODULE INITIALIZATION
// ---------------------------------------------------------------------------------------------------------------------

RestClient::baseUrl(Plugin::data()->getServerUrl()."api/v1.0");
RestClient::ucrmKey(Plugin::data()->getAppKey());

$clients = Client::get();

#region > TEST: Get ALL UCRM Clients w/ registration dates after the plugin's start date

/*
$includedClients = $clients->find(
    function(Client $client) use ($startDate)
    {
        $registrationDate = new \DateTime($client->getRegistrationDate());
        return $registrationDate > $startDate;
    }
);

echo $includedClients;
*/

#endregion

#region > TEST: Get ALL UCRM Clients w/ an outstanding account balance

/*
$balanceClients = $clients->find(
    function(Client $client)
    {
        $balance = $client->getAccountOutstanding();
        return $balance > 0;
    }
);

echo $balanceClients;
*/

#endregion

#region > TEST: Get ALL UCRM Clients w/ an existing invoice

/*
$invoicedClients = $clients->find(
    function(Client $client)
    {
        $hasInvoices = false;

        $invoices = Invoice::get()->find(
            function(Invoice $invoice) use ($client, &$hasInvoices)
            {
                $clientId = $invoice->getClientId();
                if($clientId === $client->getId())
                    $hasInvoices = true;
            }
        );

        return $hasInvoices;
    }
);

echo $invoicedClients;
*/

#endregion



$invoices = Invoice::getByCreatedDateBetween($startDate, new \DateTime());


echo $invoices;


//$contact = new Contact($xero);
