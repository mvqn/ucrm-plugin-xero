<?php
declare(strict_types=1);

namespace UCRM\Synchronizers;

use MVQN\Synchronization\SyncDefinition;
use MVQN\Synchronization\SyncChanges;
use MVQN\Synchronization\SyncMap;
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

    private const DEFAULT_MAP_FILE = "/clients.json";





    public const XERO_NAME_FORMAT_FIRST_LAST = 1;
    public const XERO_NAME_FORMAT_LAST_FIRST = 2;
    // TODO: Add other formats as requested...



    protected function sourceHandler(Client $client): string
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
    }





    public static function map(array $ucrmClients, array $xeroContacts,
        SyncChanges &$sourceChanges = null, SyncChanges &$destinationChanges = null): SyncMap
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

        $sourceMap = new SyncDefinition(Client::class, $sourceHandler, "ucrmId", "id");



        $destinationHandler = function (XeroContact $contact)
        {
            return $contact->getName();
        };

        $destinationMap = new SyncDefinition(XeroContact::class, $destinationHandler, "xeroId", "GUID");

        $map = Synchronizer::map($ucrmClients, $xeroContacts, $sourceMap, $destinationMap,
            $sourceChanges, $destinationChanges, Plugin::dataPath().self::DEFAULT_MAP_FILE);

        return $map;
    }











}