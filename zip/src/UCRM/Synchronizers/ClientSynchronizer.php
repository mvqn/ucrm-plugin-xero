<?php
declare(strict_types=1);

namespace UCRM\Synchronizers;

use MVQN\Synchronization\ClassMap;
use MVQN\Synchronization\MapResults;
use MVQN\Synchronization\Synchronizer;
use MVQN\UCRM\Plugins\Log;
use MVQN\UCRM\Plugins\Plugin;
use MVQN\REST\UCRM\Endpoints\Client;

use UCRM\Converters\ClientConverter;

use XeroPHP\Models\Accounting\Contact;
use XeroPHP\Models\Accounting\ContactGroup;
use XeroPHP\Models\Accounting\Contact as XeroContact;


/**
 * Class ClientSynchronizer.
 *
 * @package UCRM\Synchronizers
 * @author Ryan Spaeth <rspaethm@mvqn.net>
 */
final class ClientSynchronizer
{
    //public const XERO_SYNC_TYPE_CLIENTS = 1;
    //public const XERO_SYNC_TYPE_CLIENTS_INVOICES = 2;
    //public const XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS = 3;
    //public const XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS_REFUNDS = 4;



    public const XERO_NAME_FORMAT_FIRST_LAST = 1;
    public const XERO_NAME_FORMAT_LAST_FIRST = 2;
    // TODO: Add other formats as requested...






    public static function map(array $ucrmClients, array $xeroContacts,
        MapResults &$sourceChanges = null, MapResults &$destinationChanges = null)
    {

        $sourceHandler = function (Client $client)
        {
            $format = Plugin::config()->getValue("xeroNameFormat");

            switch($client->getClientType())
            {
                case Client::CLIENT_TYPE_RESIDENTIAL:
                    $first = $client->getFirstName();
                    $last = $client->getLastName();

                    switch($format)
                    {
                        case self::XERO_NAME_FORMAT_FIRST_LAST:
                            return $client->getFirstName()." ".$client->getLastName();
                            break;
                        case self::XERO_NAME_FORMAT_LAST_FIRST:
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

        $sourceMap = new ClassMap(Client::class, $sourceHandler, "ucrmId", "id");



        $destinationHandler = function (XeroContact $contact)
        {
            return $contact->getName();
        };

        $destinationMap = new ClassMap(XeroContact::class, $destinationHandler, "xeroId", "GUID");

        return Synchronizer::map($ucrmClients, $xeroContacts, $sourceMap, $destinationMap,
            $sourceChanges, $destinationChanges, Plugin::dataPath()."/clients.json");


    }







    /**
     * @param array $map An associative array of "name" => ["ucrmId","xeroId"] mappings for use in synchronization.
     * @param ContactGroup|null $group
     * @param array|null $modified An optional associative (reference) array that will be populated with any changes made.
     * @return Contact[] Returns an array of Xero contacts that should be passed to the REST API for INSERT/UPDATE.
     * @throws \Exception Throws an exception if any errors occur!
     */
    public static function changesToXero(array $map = [], ?ContactGroup &$group = null, ?array &$modified = null): array
    {
        $jsonFile = Plugin::dataPath()."/clients.json";
        $map = $map === [] && file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : $map;

        if($map === [])
        {
            Log::write("ERROR: No 'map' provided and 'data/clients.json' file could not be found!");
            return [];
            //throw new \Exception("No 'map' provided and 'data/clients.json' file could not be found!");
        }

        $modified = [];

        /** @var Contact[] $contacts */
        $contacts = [];

        // Loop through each entry in the map...
        foreach($map as $name => $ids)
        {
            $ucrmId = array_key_exists("ucrmId", $ids) ? $ids["ucrmId"] : 0;
            $xeroId = array_key_exists("xeroId", $ids) ? $ids["xeroId"] : "";

            // IF the Client does not exist in the UCRM system...
            if($ucrmId === 0)
            {
                // THEN skip the entry, as this is a one-way sync from UCRM to Xero!
                continue;
            }

            // OTHERWISE, IF the Contact does not exist in Xero, OR the "force" flag is set...
            if($xeroId === "") // || $force)
            {
                // THEN convert the Client to a Contact and attempt to add/update it to/in Xero.

                /** @var Client $client */
                $client = Client::getById($ucrmId);

                /** @var Contact $contact */
                $contact = ClientConverter::toNewXeroContact($client, $group);

                $contacts[] = $contact;

                $modified[$name] = [];
                $modified[$name]["xero"] = $force ? "updated" : "added";
            }
        }

        return $contacts;
    }

    /**
     * @param array $map An associative array of "name" => ["ucrmId","xeroId"] mappings for use in synchronization.
     * @param bool $force An optional flag, which when TRUE, updates/overwrites existing UCRM client information.
     * @param array $modified An optional associative (reference) array that will be populated with any changes made.
     * @return Client[] Returns an array of UCRM clients that should be passed to the REST API for INSERT/UPDATE.
     * @throws \Exception Throws an exception if any errors occur!
     *
     * @deprecated Not yet implemented!
     */
    public static function changesToUcrm(array $map = [], bool $force = false, array &$modified = []): array
    {
        print_r($map);
        print_r($force);
        $modified = [];

        return [];
    }




}