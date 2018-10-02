<?php
declare(strict_types=1);

namespace UCRM\Synchronizers;

use MVQN\UCRM\Plugins\Log;
use MVQN\UCRM\Plugins\Plugin;
use MVQN\REST\UCRM\Endpoints\Client;

use UCRM\Converters\ClientConverter;

use XeroPHP\Models\Accounting\Contact;
use XeroPHP\Models\Accounting\ContactGroup;


/**
 * Class ClientSynchronizer.
 *
 * @package UCRM\Synchronizers
 * @author Ryan Spaeth <rspaethm@mvqn.net>
 */
final class ClientSynchronizer
{
    public const XERO_SYNC_TYPE_CLIENTS = 1;
    public const XERO_SYNC_TYPE_CLIENTS_INVOICES = 2;
    public const XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS = 3;
    public const XERO_SYNC_TYPE_CLIENTS_INVOICES_PAYMENTS_REFUNDS = 4;



    public const XERO_NAME_FORMAT_FIRST_LAST = 1;
    public const XERO_NAME_FORMAT_LAST_FIRST = 2;
    // TODO: Add other formats as requested...




    /**
     * @param Client[] $ucrmClients The array of UCRM Clients for which to map.
     * @param Contact[] $xeroContacts The array of Xero Contacts for which to map.
     * @param int $xeroNameFormat The format of the name field in Xero to use as the map keys, defaults to "First Last".
     * @param string[]|null $ucrmChanges
     * @param string[]|null $xeroChanges
     * @return array Returns an associative array of "name" => ["ucrmId","xeroId"] mappings for use in synchronization.
     * @throws \Exception Throws an exception if any errors occur!
     */
    public static function map(array $ucrmClients, array $xeroContacts,
        //int $xeroNameFormat = self::XERO_NAME_FORMAT_FIRST_LAST,
        ?array &$ucrmChanges = null, ?array &$xeroChanges = null
    ): array
    {
        // Load the existing JSON map file, if one exists...
        $jsonFile = Plugin::dataPath()."/clients.json";
        $map = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

        // Initialize all the local UCRM mapping changes.
        $ucrmChanges = [];
        $ucrmChanges["created"] = [];
        $ucrmChanges["updated"] = [];
        $ucrmChanges["deleted"] = [];
        $ucrmChanges["missing"] = [];

        // Duplicate the map to be used as a reverse lookup to determine UCRM changes.
        $ucrmHandled = $map;

        // Loop through the provided UCRM Clients first...
        foreach($ucrmClients as $client)
        {
            // Get the Client's name
            $name = ClientConverter::toXeroContactName($client);

            if ($name === "")
                throw new \Exception("Name format not supported!");

            // IF the name already exists in previous mapping...
            if (array_key_exists($name, $map))
            {
                // IF the mapping already contains an UCRM ID entry...
                if(array_key_exists("ucrmId", $map[$name]))
                {
                    // THEN check to see if the ID is the same...
                    if($map[$name]["ucrmId"] !== $client->getId())
                    {
                        $map[$name]["ucrmId"] = $client->getId();
                        $ucrmChanges["updated"][] = $name;

                        // Mark this one as handled!
                        unset($ucrmHandled[$name]);
                    }
                    else
                    {
                        // OTHERWISE nothing has changed on the UCRM side of things!

                        // Mark this one as handled!
                        unset($ucrmHandled[$name]);
                    }
                }
                else
                {
                    // OTHERWISE the mapping does not exist, so create it and add it to the list of "created" clients!
                    //$map[$name] = [];
                    $map[$name]["ucrmId"] = $client->getId();
                    $ucrmChanges["created"][] = $name;
                    //$xeroChanges["missing"][] = $name;

                    // Mark this one as handled!
                    unset($ucrmHandled[$name]);
                }
            }
            else
            {
                $existing = "";

                // Check for matching IDs, in case the Client name was simply updated!
                foreach($map as $oldName => $oldIds)
                {
                    if(array_key_exists("ucrmId", $oldIds) && $oldIds["ucrmId"] === $client->getId())
                        $existing = $oldName;
                }

                if($existing !== "")
                {
                    // THEN we found a matching ID on another name, so update the existing entry.
                    $map[$name] = $map[$existing];
                    unset($map[$existing]);
                    $ucrmChanges["updated"][] = $name;

                    // Mark the old one as handled!
                    unset($ucrmHandled[$existing]);
                }
                else
                {
                    // OTHERWISE create the mapping and add it to the list of "created" clients!
                    $map[$name] = [];
                    $map[$name]["ucrmId"] = $client->getId();
                    $ucrmChanges["created"][] = $name;

                    // Mark this one as handled!
                    unset($ucrmHandled[$name]);
                }
            }
        }








        // Loop through each unhandled client...
        foreach($ucrmHandled as $name => $ids)
        {
            if(array_key_exists("ucrmId", $ids))
            {
                // THEN remove the UCRM mapping and add this to the list of "deleted" clients!
                unset($map[$name]["ucrmId"]);
                $ucrmChanges["deleted"][] = $name;
            }
            else
            {
                if(array_key_exists("xeroId", $ids))
                    $ucrmChanges["missing"][] = $name;
            }

            unset($ucrmHandled[$name]);
        }



        // Initialize all the local Xero mapping changes.
        $xeroChanges = [];
        $xeroChanges["created"] = [];
        $xeroChanges["updated"] = [];
        $xeroChanges["deleted"] = [];
        $xeroChanges["missing"] = [];


        // Duplicate the map to be used as a reverse lookup to determine Xero changes.
        $xeroHandled = $map;



        // Loop through the provided Xero Contacts next...
        foreach($xeroContacts as $contact)
        {
            $name = $contact->getName();

            // IF the name already exists in previous mapping...
            if (array_key_exists($name, $map))
            {
                // IF the mapping already contains a Xero ID entry...
                if(array_key_exists("xeroId", $map[$name]))
                {
                    // THEN check to see if the ID is the same...
                    if($map[$name]["xeroId"] !== $contact->getGUID())
                    {
                        $map[$name]["xeroId"] = $contact->getGUID();
                        $xeroChanges["updated"][] = $name;

                        // Mark this one as handled!
                        unset($xeroHandled[$name]);
                    }
                    else
                    {
                        // OTHERWISE nothing has changed on the Xero side of things!

                        // Mark this one as handled!
                        unset($xeroHandled[$name]);
                    }
                }
                else
                {
                    // OTHERWISE the mapping does not exist, so create it and add it to the list of "created" contacts!
                    //$map[$name] = [];
                    $map[$name]["xeroId"] = $contact->getGUID();
                    $xeroChanges["created"][] = $name;
                    //$ucrmChanges["missing"][] = $name;

                    // Mark this one as handled!
                    unset($xeroHandled[$name]);
                }
            }
            else
            {
                $existing = "";

                // Check for matching IDs, in case the Client name was simply updated!
                foreach($map as $oldName => $oldIds)
                {
                    if(array_key_exists("xeroId", $oldIds) && $oldIds["xeroId"] === $contact->getGUID())
                        $existing = $oldName;
                }

                if($existing !== "")
                {
                    // THEN we found a matching ID on another name, so update the existing entry.
                    $map[$name] = $map[$existing];
                    unset($map[$existing]);
                    $xeroChanges["updated"][] = $name;

                    // Mark changes in "missing" UCRM, as it has already been generated.
                    if(in_array($existing, $ucrmChanges["missing"]))
                    {
                        $ucrmChanges["missing"][] = $name;
                        $index = array_search($existing, $ucrmChanges["missing"]);
                        unset($ucrmChanges["missing"][$index]);

                        // Reindex the array.
                        $ucrmChanges["missing"] = array_values($ucrmChanges["missing"]);
                    }

                    // Mark the old one as handled!
                    unset($xeroHandled[$existing]);
                }
                else
                {
                    // OTHERWISE create the mapping and add it to the list of "created" clients!
                    $map[$name] = [];
                    $map[$name]["xeroId"] = $contact->getGUID();
                    $xeroChanges["created"][] = $name;

                    // Mark this one as handled!
                    unset($xeroHandled[$name]);
                }
            }
        }

        // Loop through each unhandled contact...
        foreach($xeroHandled as $name => $ids)
        {
            if(array_key_exists("xeroId", $ids))
            {
                // THEN remove the Xero mapping and add this to the list of "deleted" clients!
                unset($map[$name]["xeroId"]);
                $xeroChanges["deleted"][] = $name;
            }
            else
            {
                if(array_key_exists("ucrmId", $ids))
                    $xeroChanges["missing"][] = $name;
            }

            unset($xeroHandled[$name]);
        }





        foreach($map as $name => $ids)
        {
            if(!array_key_exists("ucrmId", $ids) && !array_key_exists("xeroId", $ids))
                unset($map[$name]);
        }




        // Save the results into the "data/clients.json" file for later usage.
        file_put_contents($jsonFile, json_encode($map, JSON_PRETTY_PRINT));

        // Finally, return the new mapping!
        return $map;
    }








    /**
     * @param array $map An associative array of "name" => ["ucrmId","xeroId"] mappings for use in synchronization.
     * @param ContactGroup|null $group
     * @param bool $force An optional flag, which when TRUE, updates/overwrites existing Xero contact information.
     * @param array|null $modified An optional associative (reference) array that will be populated with any changes made.
     * @return Contact[] Returns an array of Xero contacts that should be passed to the REST API for INSERT/UPDATE.
     * @throws \Exception Throws an exception if any errors occur!
     */
    public static function changesToXero(array $map = [], ?ContactGroup &$group = null, bool $force = false, ?array &$modified = null): array
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
            if($xeroId === "" || $force)
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