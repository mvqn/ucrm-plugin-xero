<?php

require __DIR__."/vendor/autoload.php";

use MVQN\Collections\Collection;

use MVQN\REST\RestClient;
use MVQN\UCRM\Plugins\Log;
use MVQN\UCRM\Plugins\Plugin;
use MVQN\REST\UCRM\Endpoints\Client;
use MVQN\REST\UCRM\Endpoints\Version;
use MVQN\REST\UCRM\Endpoints\Invoice;
use MVQN\REST\UCRM\Endpoints\CustomAttribute;
use MVQN\REST\UCRM\Endpoints\Lookups\ClientContactAttribute;

use UCRM\Converters\ClientConverter;
use UCRM\Synchronizers\ClientSynchronizer;
use UCRM\Synchronizers\InvoiceSynchronizer;

use MVQN\Synchronization\ClassMap;
use MVQN\Synchronization\MapResults;
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

        $ucrmClientsMap = [];

        $ucrmValid = true;

        foreach($ucrmClients as $ucrmClient)
        {
            $xeroName = ClientConverter::toXeroContactName($ucrmClient);

            if(!array_key_exists($xeroName, $ucrmClientsMap))
            {
                $ucrmClientsMap[$xeroName] = $ucrmClient;

                if($ucrmClient->getContacts()->count() > 5)
                {
                    Log::write("WARN : Xero Contacts can only contain a maximum of 5 contact persons!");
                    $ucrmValid = false;
                }

                // TODO: Add further pre-checks here, as needed!
            }
            else
            {
                // This is a duplicate UCRM Client...
                Log::write("WARN : A UCRM Client named '$xeroName' already exists and Xero requires unique names!");

                // TODO: Determine how we want to handle this long term, for now skip duplicates!
                //$ucrmValid = false;
            }
        }

        if(!$ucrmValid)
            die("UCRM Pre-Checks have failed, check the log for further information!");

        Log::write("INFO : Successfully connected to the UCRM REST API!");
    }
    catch(\Exception $e)
    {
        die("ERROR: Could not connect to the UCRM REST API!");
    }

    // =================================================================================================================
    // XERO REST INITIALIZATION
    // -----------------------------------------------------------------------------------------------------------------

    $xeroCallbackUrl = array_key_exists("callbackUrl", $ucrmConfig) ? $ucrmConfig["callbackUrl"] : null;
    $xeroConsumerKey = array_key_exists("consumerKey", $ucrmConfig) ? $ucrmConfig["consumerKey"] : null;
    $xeroConsumerSecret = array_key_exists("consumerSecret", $ucrmConfig) ? $ucrmConfig["consumerSecret"] : null;
    $xeroPrivateKey = array_key_exists("privateKey", $ucrmConfig) ? $ucrmConfig["privateKey"] : null;

    $xeroSettingsValid = true;

    if ($xeroCallbackUrl === null)
    {
        Log::write("ERROR: The 'callbackUrl' entry appears to be missing from 'config.json'!");
        $xeroSettingsValid = false;
    }

    if ($xeroConsumerKey === null)
    {
        Log::write("ERROR: The 'consumerKey' entry appears to be missing from 'config.json'!");
        $xeroSettingsValid = false;
    }

    if ($xeroConsumerSecret === null)
    {
        Log::write("ERROR: The 'consumerSecret' entry appears to be missing from 'config.json'!");
        $xeroSettingsValid = false;
    }

    if ($xeroPrivateKey === null)
    {
        Log::write("ERROR: The 'privateKey' entry appears to be missing from 'config.json'!");
        $xeroSettingsValid = false;
    }

    if(!$xeroSettingsValid)
        die("Xero Settings are invalid, check the log for further information!");

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

        $xeroContactsMap = [];

        $xeroValid = true;

        foreach($xeroContacts as $xeroContact)
        {
            $xeroContactsMap[$xeroContact->getName()] = $xeroContact;

            // TODO: Add any pre-checks here, as needed!
        }

        if(!$xeroValid)
            die("Xero Pre-Checks have failed, check the log for further information!");

        Log::write("INFO : Successfully connected to the UCRM REST API!");
    }
    catch(\Exception $e)
    {
        Log::write("ERROR: Could not connect to the Xero REST API!");
        die();
    }

    // =================================================================================================================
    // OPTIONS
    // -----------------------------------------------------------------------------------------------------------------

    $xeroSettingsValid = true;

    $startDate = array_key_exists("startDate", $ucrmConfig) ?
        $ucrmConfig["startDate"] :
        null;

    if($startDate === null)
    {
        Log::write("ERROR: A 'Start Date' must be provided in UCRM Plugin Settings!");
        $xeroSettingsValid = false;
    }

    $synchronizations = array_key_exists("synchronizations", $ucrmConfig) ?
        $ucrmConfig["synchronizations"] :
        null;

    if($synchronizations === null)
    {
        Log::write("ERROR: A 'Synchronization Type' must be provided in UCRM Plugin Settings!");
        $xeroSettingsValid = false;
    }

    $format = array_key_exists("xeroNameFormat", $ucrmConfig) ?
        $ucrmConfig["xeroNameFormat"] :
        null;

    if($format === null)
    {
        Log::write("WARN : A 'Xero Name Format' was not provided in UCRM Plugin Settings, default used!");
        $format = ClientSynchronizer::XERO_NAME_FORMAT_FIRST_LAST; // DEFAULT
    }

    $forceOverwrite = array_key_exists("xeroForceContactOverwrite", $ucrmConfig) ?
        $ucrmConfig["xeroForceContactOverwrite"] :
        null;

    if($forceOverwrite === null)
    {
        Log::write("WARN : A 'Xero Force Contact Overwrite' was not provided in UCRM Plugin Settings, default used!");
        $forceOverwrite = false; // DEFAULT
    }

    $useContactGroup = array_key_exists("xeroContactGroup", $ucrmConfig) ?
        $ucrmConfig["xeroContactGroup"] :
        null;

    if($forceOverwrite === null)
    {
        Log::write("INFO : A 'Xero Contact FGroup' was not provided in UCRM Plugin Settings, default used!");
        $useContactGroup = null; // DEFAULT
    }

    if(!$xeroSettingsValid)
        die("Xero Settings are invalid, check the log for further information!");

    // =================================================================================================================
    // ONE-TIME UCRM CONFIG
    // -----------------------------------------------------------------------------------------------------------------

    // CREATE Custom Attribute in UCRM, as needed!

    /** @var CustomAttribute|null $clientAttribute */
    $clientAttribute = null;
    $existingAttributes = CustomAttribute::get()->where("key", "xeroId");

    if($existingAttributes->count() === 0)
    {
        $xeroIdAttribute = new CustomAttribute([
            "name" => "Xero ID",
            "attributeType" => CustomAttribute::ATTRIBUTE_TYPE_CLIENT
        ]);

        $clientAttribute = $xeroIdAttribute->insert();

        Log::write("INFO : A Custom Attribute of 'Xero ID' has been created for use by Clients!");
    }
    else
    {
        $clientAttribute = $existingAttributes->first();
    }

    if($clientAttribute === null)
    {
        Log::write("ERROR: Unable to find or create the necessary Custom Attribute field in UCRM!");
        die();
    }

    // =================================================================================================================
    // MAPPING
    // -----------------------------------------------------------------------------------------------------------------

    // Clear existing data, if the "overwrite" flag is set!
    if($forceOverwrite && file_exists(Plugin::dataPath()."/clients.json"))
        unlink(Plugin::dataPath()."/clients.json");

    // Create or update the Client map.
    $map = ClientSynchronizer::map($ucrmClients, $xeroContacts, $ucrmChanges, $xeroChanges);

    echo "UCRM CHANGES: ".$ucrmChanges."\n";
    echo "XERO CHANGES: ".$xeroChanges."\n";


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

    /** @var MapResults $ucrmChanges */
    /** @var MapResults $xeroChanges */

    $addedIndices = [];

    $xeroMissing = $xeroChanges->getMissing();

    foreach($xeroMissing as $index => $name)
    {
        //$ucrmId = $map[$name]["ucrmId"];
        $ucrmClient = $ucrmClientsMap[$name];

        $xeroContact = ClientConverter::toNewXeroContact($ucrmClient, $contactGroup);

        $results = $xero->save($xeroContact);

        if($results->getStatus() === 200 && count($results->getElements()) > 0)
        {
            $inserted = $results->getElements()[0];
            $xeroId = $inserted["ContactID"];

            $map[$name]["xeroId"] = $xeroId;
            $xeroChanges->delMissing($name);
        }
    }

    file_put_contents(Plugin::dataPath()."/clients.json", json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));



    echo "UCRM CHANGES: ".$ucrmChanges."\n";
    echo "XERO CHANGES: ".$xeroChanges."\n";

    die();



    // PAIRING
    $ucrmHandled = [];

    foreach($ucrmClients as $client)
    {
        /** @var Client $client */

        /** @var Collection $attributes */
        $attributes = $client->getAttributes();
        /** @var ClientContactAttribute|null $xeroAttribute */
        $matching = $attributes->where("key", "xeroId");

        if($matching->count() === 0)
        {
            $xeroName = ClientConverter::toXeroContactName($client);

            if(!array_key_exists($xeroName, $xeroContactsMap))
            {
                // NEED to create the Xero Contact First!!!

                continue;
            }

            /** @var XeroContact $xeroContact */
            $xeroContact = $xeroContactsMap[$xeroName];
            $xeroGuid = $xeroContact->getGUID();

            $attributes->push(new ClientContactAttribute([
                "customAttributeId" => $clientAttribute->getId(),
                "value" => $xeroGuid
            ]));

            $client->setAttributes($attributes);

            $updated = $client->update();
        }
        else
        {
            // Existing Xero GUID set on Client OR UCRM User has overridden, so do nothing!
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
