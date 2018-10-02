<?php
declare(strict_types=1);

namespace UCRM\Converters;

use MVQN\Common\Strings;
use MVQN\REST\UCRM\Endpoints\Client;
use MVQN\REST\UCRM\Endpoints\Lookups\ClientBankAccount;
use MVQN\REST\UCRM\Endpoints\Lookups\ClientContact;
use MVQN\UCRM\Plugins\Log;
use MVQN\UCRM\Plugins\Plugin;
use XeroPHP\Models\Accounting\Address;
use XeroPHP\Models\Accounting\Contact as XeroContact;
use MVQN\Collections\Collection;
use XeroPHP\Models\Accounting\ContactGroup;
use XeroPHP\Models\Accounting\Phone;

final class ClientConverter
{
    public const XERO_NAME_FORMAT_FIRST_LAST = 1;
    public const XERO_NAME_FORMAT_LAST_FIRST = 2;
    // TODO: Add other formats as requested...



    // Group1: Country Code (i.e. 1 or 86)
    // Group2: Area Code (i.e. 800)
    // Group3: Exchange (i.e. 555)
    // Group4: Subscriber (i.e. 1234)
    // Group5: Extension (i.e. 5678)

    private const REGEX_PHONE_NUMBERS =
        '/^\s*(?:\+?(\d{1,3}))?[-. (]*(\d{3})[-. )]*(\d{3})[-. ]*(\d{4})(?: *x(\d+))?\s*$/';


    /**
     * @param Client $client The UCRM Client for which to format the name.
     * @param string|null $first A reference parameter in which the Client's first name is returned.
     * @param string|null $last A reference parameter in which the Client's last name is returned.
     * @return string Returns the prepared UCRM Client's name, based on the Xero Name Format in 'data/config.json'.
     * @throws \Exception Throws an exception if any errors occur.
     */
    public static function toXeroContactName(Client $client, string &$first = null, string &$last = null): string
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

    /**
     * @param Client $client
     * @return string Returns the GUID of the Xero Contact, given the UCRM Client. Expects a MAP to already exist.
     * @throws \Exception Throws an exception if any errors occur.
     */
    public static function toXeroContactId(Client $client): string
    {
        $jsonFile = Plugin::dataPath()."/clients.json";
        $map = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

        if($map === [])
            return "";

        $name = self::toXeroContactName($client);

        if($name === "")
            return "";

        foreach($map as $key => $ids)
        {
            if($key === $name)
                return array_key_exists("xeroId", $ids) ? $ids["xeroId"] : "";
        }

        return "";
    }


    /**
     * @param Client $client
     * @param ContactGroup|null $group
     * @return XeroContact|null
     * @throws \Exception
     */
    public static function toNewXeroContact(Client $client, ?ContactGroup &$group = null): ?XeroContact
    {
        $contact = new XeroContact();

        $name = self::toXeroContactName($client, $first, $last);

        // > Name
        $contact->setName($name);

        // > ContactID (created by Xero upon insertion)
        //$contact->setContactID();

        // > ContactNumber (max = 50 characters)
        //$contact->setContactNumber("U:{$client->getId()}");

        // > AccountNumber
        $contact->setAccountNumber($client->getUserIdent());

        // > ContactStatus = (ACTIVE, ARCHIVED, GDPRREQUEST)
        $contact->setContactStatus("ACTIVE");

        // > FirstName
        $contact->setFirstName($first);

        // > LastName
        $contact->setLastName($last);

        /** @var Collection $ucrmContacts */
        $ucrmContacts = $client->getContacts();

        $primaryEmail = "";
        $primaryPhone = "";

        if($ucrmContacts->count() > 0)
        {
            /** @var ClientContact $ucrmPrimaryContact */
            $ucrmPrimaryContact = $ucrmContacts->shift();
            $primaryEmail = $ucrmPrimaryContact->getEmail();
            $primaryPhone = $ucrmPrimaryContact->getPhone();

            // > EmailAddress (max = 255 characters)
            $contact->setEmailAddress($primaryEmail);
        }

        // > SkypeUserName
        //$contact->setSkypeUserName("");

        // > Contacts... (excludes primary contact which has already been removed from the collection)
        foreach($ucrmContacts as $ucrmContact)
        {
            /** @var ClientContact $ucrmContact */
            $nameField = $ucrmContact->getName();

            if($nameField === null)
                continue;

            if(Strings::contains($nameField, ","))
            {
                // Assume: LastName, FirstName <Suffix>

                $parts = array_map("trim", explode(",", $nameField));

                if(count($parts) != 2)
                {
                    Log::write("ERROR: Name could not be determined from the provided Client Contact: ".$client);
                    return null;
                    //throw new \Exception("Could not parse client contact's name field!");
                }

                $contactFirst = $parts[1];
                $contactLast = $parts[0];
            }
            else
            {
                // Assume: FirstName LastName <Suffix>

                $parts = array_map("trim", explode(" ", $nameField));

                if(count($parts) < 2 || count($parts) > 3)
                {
                    Log::write("ERROR: Name could not be determined from the provided Client Contact: ".$client);
                    return null;
                    //throw new \Exception("Could not parse client contact's name field!");
                }

                $contactFirst = $parts[0].(count($parts) === 3 ? $parts[2] : "");
                $contactLast = $parts[1];
            }

            $contactPerson = new XeroContact\ContactPerson();
            $contactPerson->setFirstName($contactFirst);
            $contactPerson->setLastName($contactLast);
            if($primaryEmail !== "")
                $contactPerson->setEmailAddress($primaryEmail);
            $contactPerson->setIncludeInEmail($ucrmContact->getIsBilling());

            // > ContactPersons
            $contact->addContactPerson($contactPerson);
        }

        /** @var Collection $ucrmBankAccounts */
        $ucrmBankAccounts = $client->getBankAccounts();

        if($ucrmBankAccounts->count() > 0)
        {
            /** @var ClientBankAccount $ucrmBankAccount */
            $ucrmBankAccount = $client->getBankAccounts()->first();
            $bankAccount = $ucrmBankAccount->getAccountNumber();

            // > BankAccountDetails
            $contact->setBankAccountDetail($bankAccount);
        }

        // > TaxNumber
        $contact->setTaxNumber($client->getCompanyTaxId());

        // > AccountsReceivableTaxType
        //$contact->setAccountsReceivableTaxType("");

        // > AccountsPayableTaxType
        //$contact->setAccountsPayableTaxType("");

        $state = $client->getState();
        $country = $client->getCountry();

        $invoiceState = $client->getInvoiceState();
        $invoiceCountry = $client->getInvoiceCountry();

        // > Addresses...
        if($client->getInvoiceAddressSameAsContact())
        {
            $postalAddress = new Address();
            $postalAddress->setAddressType("POBOX");
            $postalAddress->setAddressLine1($client->getStreet1());
            $postalAddress->setAddressLine2($client->getStreet2());
            $postalAddress->setCity($client->getCity());
            //$postalAddress->setRegion($client->getState()->getName());
            $postalAddress->setRegion($state ? $state->getName() : "");
            //$postalAddress->setCountry($client->getCountry()->getName());
            $postalAddress->setCountry($country ? $country->getName() : "");

            $contact->addAddress($postalAddress);

            $streetAddress = new Address();
            $streetAddress->setAddressType("STREET");
            $streetAddress->setAddressLine1($client->getStreet1());
            $streetAddress->setAddressLine2($client->getStreet2());
            $streetAddress->setCity($client->getCity());
            //$streetAddress->setRegion($client->getState()->getName());
            $streetAddress->setRegion($state ? $state->getName() : "");
            //$streetAddress->setCountry($client->getCountry()->getName());
            $streetAddress->setCountry($country ? $country->getName() : "");

            $contact->addAddress($streetAddress);
        }
        else
        {
            $postalAddress = new Address();
            $postalAddress->setAddressType("POBOX");
            $postalAddress->setAddressLine1($client->getInvoiceStreet1());
            $postalAddress->setAddressLine2($client->getInvoiceStreet2());
            $postalAddress->setCity($client->getInvoiceCity());
            //$postalAddress->setRegion($client->getInvoiceState()->getName());
            $postalAddress->setRegion($invoiceState ? $invoiceState->getName() : "");
            //$postalAddress->setCountry($client->getInvoiceCountry()->getName());
            $postalAddress->setCountry($invoiceCountry ? $invoiceCountry->getName() : "");

            $contact->addAddress($postalAddress);

            $streetAddress = new Address();
            $streetAddress->setAddressType("STREET");
            $streetAddress->setAddressLine1($client->getStreet1());
            $streetAddress->setAddressLine2($client->getStreet2());
            $streetAddress->setCity($client->getCity());
            //$streetAddress->setRegion($client->getState()->getName());
            $streetAddress->setRegion($state ? $state->getName() : "");
            //$streetAddress->setCountry($client->getCountry()->getName());
            $streetAddress->setCountry($country ? $country->getName() : "");

            $contact->addAddress($streetAddress);
        }

        // > Phones...
        if($primaryPhone !== "" && $primaryPhone !== null && preg_match(self::REGEX_PHONE_NUMBERS, $primaryPhone, $matches))
        {
            $phone = new Phone();
            $phone->setPhoneType("DEFAULT");
            $phone->setPhoneCountryCode($matches[1]);
            $phone->setPhoneAreaCode($matches[2]);
            $phone->setPhoneNumber($matches[3]."-".$matches[4].(count($matches) > 5 && $matches[5] ? " x".$matches[5] : ""));

            $contact->addPhone($phone);
        }

        // > IsSupplier (deprecated, as this is now automatically determined by invoices or bills)
        //$contact->setIsSupplier(false);

        // > IsCustomer (deprecated, as this is now automatically determined by invoices or bills)
        //$contact->setIsCustomer(true);

        // > DefaultCurrency
        //$contact->setDefaultCurrency("");

        // > XeroNetworkKey
        //$contact->setXeroNetworkKey("");

        // > SalesDefaultAccountCode
        //$contact->setSalesDefaultAccountCode("");

        // > PurchasesDefaultAccountCode
        //$contact->setPurchasesDefaultAccountCode("");

        // > SalesTrackingCategories
        //$contact->addSalesTrackingCategory();

        // > PurchasesTrackingCategories
        //$contact->addPurchasesTrackingCategory();

        // > TrackingCategoryName
        //$contact->setTrackingCategoryName("");

        // > TrackingCategoryOption
        //$contact->setTrackingCategoryOption("");

        // > PaymentTerms
        //$contact->addPaymentTerm();

        if($contact !== null && $group !== null)
            $group->addContact($contact);

        return $contact;
    }







}


