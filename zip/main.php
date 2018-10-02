<?php

require __DIR__."/vendor/autoload.php";

use MVQN\Collections\Collection;

use MVQN\REST\RestClient;
use MVQN\UCRM\Plugins\Log;
use MVQN\UCRM\Plugins\Plugin;
use MVQN\REST\UCRM\Endpoints\Client;
use MVQN\REST\UCRM\Endpoints\Version;
use MVQN\REST\UCRM\Endpoints\Invoice;

use UCRM\Converters\ClientConverter;
use UCRM\Synchronizers\ClientSynchronizer;
use UCRM\Synchronizers\InvoiceSynchronizer;

use MVQN\Synchronization\ClassMap;
use MVQN\Synchronization\Synchronizer;

use XeroPHP\Application\PrivateApplication;

use XeroPHP\Models\Accounting\Contact as XeroContact;
use XeroPHP\Models\Accounting\Invoice as XeroInvoice;
use XeroPHP\Models\Accounting\ContactGroup as XeroContactGroup;
use XeroPHP\Models\Accounting\Organisation as XeroOrganization;

/**
 * main.php (required)
 *
 * Main file of the plugin. This is what will be executed when the plugin is run by UCRM.
 *
 */

(function ()
{

    $ucrmConfig = Plugin::config()->getValues();

    // =================================================================================================================
    // UCRM REST INITIALIZATION
    // -----------------------------------------------------------------------------------------------------------------

    $ucrmServerUrl = Plugin::data()->getServerUrl();
    $ucrmPluginUrl = Plugin::data()->getPluginUrl();
    $ucrmPluginKey = Plugin::data()->getAppKey();

    if ($ucrmServerUrl === null || $ucrmServerUrl === "")
    {
        Log::write("ERROR: The 'ucrmPublicUrl' entry appears to be missing from 'ucrm.json'!");
        die();
    }

    if ($ucrmPluginUrl === null || $ucrmPluginUrl === "")
    {
        Log::write("ERROR: The 'pluginPublicUrl' entry appears to be missing from 'ucrm.json'!");
        die();
    }

    if ($ucrmPluginKey === null || $ucrmPluginKey === "")
    {
        Log::write("ERROR: The 'pluginAppKey' entry appears to be missing from 'ucrm.json'!");
        die();
    }

    RestClient::setBaseUrl($ucrmServerUrl."api/v1.0");
    RestClient::setHeaders([
        "Content-Type: application/json",
        "X-Auth-App-Key: ".$ucrmPluginKey
    ]);

    try
    {
        /** @var Client[] $ucrmClients */
        $ucrmClients = Client::get()->elements();
        Log::write("INFO : Successfully connected to the UCRM REST API!");
    }
    catch(\Exception $e)
    {
        Log::write("ERROR: Could not connect to the UCRM REST API!");
        die();

    }

    // =================================================================================================================
    // XERO REST INITIALIZATION
    // -----------------------------------------------------------------------------------------------------------------

    $xeroCallbackUrl = array_key_exists("callbackUrl", $ucrmConfig) ? $ucrmConfig["callbackUrl"] : null;
    $xeroConsumerKey = array_key_exists("consumerKey", $ucrmConfig) ? $ucrmConfig["consumerKey"] : null;
    $xeroConsumerSecret = array_key_exists("consumerSecret", $ucrmConfig) ? $ucrmConfig["consumerSecret"] : null;
    $xeroPrivateKey = array_key_exists("privateKey", $ucrmConfig) ? $ucrmConfig["privateKey"] : null;

    if ($xeroCallbackUrl === null)
    {
        Log::write("ERROR: The 'callbackUrl' entry appears to be missing from 'config.json'!");
        die();
    }

    if ($xeroConsumerKey === null)
    {
        Log::write("ERROR: The 'consumerKey' entry appears to be missing from 'config.json'!");
        die();
    }

    if ($xeroConsumerSecret === null)
    {
        Log::write("ERROR: The 'consumerSecret' entry appears to be missing from 'config.json'!");
        die();
    }

    if ($xeroPrivateKey === null)
    {
        Log::write("ERROR: The 'privateKey' entry appears to be missing from 'config.json'!");
        die();
    }

    //These are the minimum settings - for more options, refer to examples/config.php
    $config = [
        "oauth" => [
            "callback"        => $xeroCallbackUrl,
            "consumer_key"    => $xeroConsumerKey,
            "consumer_secret" => $xeroConsumerSecret,
            "rsa_private_key" => $xeroPrivateKey
        ]
    ];

    $xero = new PrivateApplication($config);
    // TODO: Change to Public/Partner application at a later time???

    try
    {
        /** @var XeroContact[] $xeroContacts */
        $xeroContacts = $xero->load(XeroContact::class)->execute()->getArrayCopy();
        Log::write("INFO : Successfully connected to the UCRM REST API!");
    }
    catch(\Exception $e)
    {
        Log::write("ERROR: Could not connect to the Xero REST API!");
        die();
    }

    // =================================================================================================================
    // MAPPING
    // -----------------------------------------------------------------------------------------------------------------

    $startDate = array_key_exists("startDate", $ucrmConfig) ?
        $ucrmConfig["startDate"] :
        null;

    $synchronizations = array_key_exists("synchronizations", $ucrmConfig) ?
        $ucrmConfig["synchronizations"] :
        null;

    $format = array_key_exists("xeroNameFormat", $ucrmConfig) ?
        $ucrmConfig["xeroNameFormat"] :
        ClientSynchronizer::XERO_NAME_FORMAT_FIRST_LAST; // DEFAULT

    $forceOverwrite = array_key_exists("xeroForceContactOverwrite", $ucrmConfig) ?
        $ucrmConfig["xeroForceContactOverwrite"] :
        null;

    $useContactGroup = array_key_exists("xeroContactGroup", $ucrmConfig) ?
        $ucrmConfig["xeroContactGroup"] :
        null;

    // =================================================================================================================
    // MAPPING
    // -----------------------------------------------------------------------------------------------------------------

    $map = ClientSynchronizer::map($ucrmClients, $xeroContacts, $ucrmChanges, $xeroChanges);

    echo "UCRM CHANGES: ".json_encode($ucrmChanges)."\n";
    echo "XERO CHANGES: ".json_encode($xeroChanges)."\n";

    $sourceHandler = function(Client $client)
    {
        $format = Plugin::config()->getValue("xeroNameFormat");

        switch($client->getClientType())
        {
            case Client::CLIENT_TYPE_RESIDENTIAL:
                $first = $client->getFirstName();
                $last = $client->getLastName();

                switch($format)
                {
                    case 1:
                        return $client->getFirstName()." ".$client->getLastName();
                        break;
                    case 2:
                        return $client->getLastName().", ".$client->getFirstName();
                        break;
                    default:
                        break;
                }
                break;

            case Client::CLIENT_TYPE_COMMERCIAL:
                $first = $client->getCompanyContactFirstName();
                $last = $client->getCompanyContactLastName();
                return $client->getCompanyName();
                break;

            default:
                break;
        }

        Log::write("ERROR: Name could not be determined from the provided Client: ".$client);
        return "";
    };

    $destinationHandler = function(XeroContact $contact)
    {
        return $contact->getName();
    };

    $sourceMap = new ClassMap(Client::class, $sourceHandler, "ucrmId", "id");
    $destinationMap = new ClassMap(XeroContact::class, $destinationHandler, "xeroId", "GUID");

    $testMap = Synchronizer::map($ucrmClients, $xeroContacts, $sourceMap, $destinationMap, $sourceChanges, $destinationChanges, __DIR__."/data/test-clients.json");

    echo "UCRM CHANGES: ".$sourceChanges."\n";
    echo "XERO CHANGES: ".$destinationChanges."\n";


    die();

    // =================================================================================================================
    // SYNCHRONIZATION: CONTACT GROUP (Optional)
    // -----------------------------------------------------------------------------------------------------------------

    /** @var XeroContactGroup $contactGroup */
    $contactGroup = null;

    if($useContactGroup !== null && $useContactGroup !== "")
    {
        $existing = $xero->load(XeroContactGroup::class)
            ->where("Name", $useContactGroup)
            ->execute();

        if($existing !== null && $existing->first() !== null)
        {
            // ContactGroup already exists!
            $contactGroup = $existing->first();
        }
        else
        {
            // Need to create the ContactGroup first!
            $contactGroup = new XeroContactGroup();
            $contactGroup->setName($useContactGroup);

            // Actually create the group in Xero
            $xero->save($contactGroup);
        }
    }

    // =================================================================================================================
    // SYNCHRONIZATION: CLIENTS
    // -----------------------------------------------------------------------------------------------------------------

    $contactUpdates = ClientSynchronizer::changesToXero($map, $contactGroup, false, $modified);

    if(count($contactUpdates) > 0)
    {
        $results = $xero->saveAll($contactUpdates);
        $errors = $results->getElementErrors();
        $warnings = $results->getElementErrors();

        foreach ($results->getElements() as $element)
        {


            echo "";
        }

    }



    // =================================================================================================================
    // SYNCHRONIZATION: INVOICES
    // -----------------------------------------------------------------------------------------------------------------

    // IF Invoices are supposed to be synchronizes...
    if ($synchronizations !== null && (
        $synchronizations === ClientSynchronizer::XERO_SYNC_TYPE_CLIENTS_INVOICES ||
        $synchronizations === ClientSynchronizer::XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS ||
        $synchronizations === ClientSynchronizer::XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS_REFUNDS))
    {
        // THEN handle their synchronization.

        $ucrmInvoices = Invoice::get()->elements();
        $xeroInvoices = $xero->load(XeroInvoice::class)->execute()->getArrayCopy();

        $invoiceMap = InvoiceSynchronizer::map($ucrmInvoices, $xeroInvoices, true, $cleaned);
        $invoiceUpdates = InvoiceSynchronizer::changesToXero($invoiceMap, true, $modified);




        echo "";
    }

    // =================================================================================================================
    // SYNCHRONIZATION: PAYMENTS
    // -----------------------------------------------------------------------------------------------------------------

    // IF Payments are supposed to be synchronizes...
    if ($synchronizations !== null && (
        $synchronizations === ClientSynchronizer::XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS ||
        $synchronizations === ClientSynchronizer::XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS_REFUNDS))
    {
        // THEN handle their synchronization.


        echo "";
    }

    // =================================================================================================================
    // SYNCHRONIZATION: REFUNDS
    // -----------------------------------------------------------------------------------------------------------------

    // IF Refunds are supposed to be synchronizes...
    if ($synchronizations !== null && (
        $synchronizations === ClientSynchronizer::XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS_REFUNDS))
    {
        // THEN handle their synchronization.


        echo "";
    }



    echo "";


})();
