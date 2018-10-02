<?php
declare(strict_types=1);

namespace UCRM\Synchronizers;

use MVQN\REST\UCRM\Endpoints\Invoice;
use MVQN\UCRM\Plugins\Log;
use MVQN\UCRM\Plugins\Plugin;
use MVQN\REST\UCRM\Endpoints\Client;

use UCRM\Converters\ClientConverter;

use UCRM\Converters\InvoiceConverter;
use XeroPHP\Application;
use XeroPHP\Models\Accounting\Contact as XeroContact;
use XeroPHP\Models\Accounting\Invoice as XeroInvoice;
use XeroPHP\Models\Accounting\ContactGroup as XeroContactGroup;


/**
 * Class InvoiceSynchronizer.
 *
 * @package UCRM\Synchronizers
 * @author Ryan Spaeth <rspaethm@mvqn.net>
 */
final class InvoiceSynchronizer
{
    /**
     * @param Invoice[] $ucrmInvoices The array of UCRM Invoices for which to map.
     * @param XeroInvoice[] $xeroInvoices The array of Xero Invoices for which to map.
     * @param bool $clean An optional flag, which when TRUE, removes any entries that no longer exist in either system.
     * @param array|null $cleaned
     * @return array Returns an assoc array of "number" => ["ucrmId","xeroId"] mappings for use in synchronization.
     * @throws \Exception Throws an exception if any errors occur!
     */
    public static function map(array $ucrmInvoices, array $xeroInvoices, bool $clean = false, ?array &$cleaned = null): array
    {
        $jsonFile = Plugin::dataPath()."/invoices.json";
        $map = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

        $existing = $map;

        // Check UCRM Invoices first...
        foreach($ucrmInvoices as $invoice)
        {
            $number = $invoice->getNumber();

            if (array_key_exists($number, $map) &&
                array_key_exists("ucrmId", $map[$number]) && $map[$number]["ucrmId"] !== $invoice->getId())
            {
                throw new \Exception("Previous synchronization must have corrupted the mapping!");
            }
            else
            {
                $map[$number]["ucrmId"] = $invoice->getId();
                $map[$number]["ucrmClientId"] = $invoice->getClientId();
                unset($existing[$number]);
            }
        }

        // Check Xero Invoices next...
        foreach($xeroInvoices as $invoice)
        {
            $number = $invoice->getInvoiceNumber();

            if (array_key_exists($number, $map) &&
                array_key_exists("xeroId", $map[$number]) && $map[$number]["xeroId"] !== $invoice->getGUID())
            {
                throw new \Exception("Previous synchronization must have corrupted the mapping!");
            }
            else
            {
                $map[$number]["xeroId"] = $invoice->getGUID();
                $map[$number]["xeroContactId"] = $invoice->getContact()->getGUID();
                unset($existing[$number]);
            }
        }

        // IF the "clean" flag is set...
        if($clean)
        {
            $cleaned = [];

            // THEN remove any entries that were not found in either system!
            foreach ($existing as $key => $value)
            {
                $cleaned[$key] = $value;
                unset($map[$key]);
            }
        }

        // Save the results into the "data/clients.json" file for later usage.
        file_put_contents($jsonFile, json_encode($map, JSON_PRETTY_PRINT));

        // Finally, return the new mapping!
        return $map;
    }

    /**
     * @param array $map An associative array of "number" => ["ucrmId","xeroId"] mappings for use in synchronization.
     * @param bool $force An optional flag, which when TRUE, updates/overwrites existing Xero Invoice information.
     * @param array|null $modified An optional associative (reference) array that will be populated with any changes made.
     * @return XeroInvoice[] Returns an array of Xero Invoices that should be passed to the REST API for INSERT/UPDATE.
     * @throws \Exception Throws an exception if any errors occur!
     */
    public static function changesToXero(array $map = [], bool $force = false, ?array &$modified = null): array
    {
        $jsonFile = Plugin::dataPath()."/invoices.json";
        $map = $map === [] && file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : $map;

        if($map === [])
        {
            Log::write("ERROR: No 'map' provided and 'data/invoices.json' file could not be found!");
            return [];
            //throw new \Exception("No 'map' provided and 'data/clients.json' file could not be found!");
        }

        $modified = [];

        /** @var XeroInvoice[] $invoices */
        $invoices = [];

        // Loop through each entry in the map...
        foreach($map as $number => $ids)
        {
            $ucrmId = array_key_exists("ucrmId", $ids) ? $ids["ucrmId"] : 0;
            $ucrmClientId = array_key_exists("ucrmClientId", $ids) ? $ids["ucrmClientId"] : 0;

            $xeroId = array_key_exists("xeroId", $ids) ? $ids["xeroId"] : "";
            $xeroContactId = array_key_exists("xeroContactId", $ids) ? $ids["xeroContactId"] : "";

            // IF the Invoice does not exist in the UCRM system...
            if($ucrmId === 0)
            {
                // THEN skip the entry, as this is a one-way sync from UCRM to Xero!
                continue;
            }

            // OTHERWISE, IF the Invoice does not exist in Xero, OR the "force" flag is set...
            if($xeroId === "" || $force)
            {
                // THEN convert the UCRM Invoice to a Xero Invoice and attempt to add/update it to/in Xero.

                /** @var Invoice $invoice */
                $invoice = Invoice::getById($ucrmId);

                /** @var XeroInvoice $xeroInvoice */
                $xeroInvoice = InvoiceConverter::toNewXeroInvoice($invoice, $xeroContactId);

                $invoices[] = $xeroInvoice;

                $modified[$number] = [];
                $modified[$number]["xero"] = $force ? "updated" : "added";
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