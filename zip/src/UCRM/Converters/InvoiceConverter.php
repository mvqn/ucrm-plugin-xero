<?php
declare(strict_types=1);

namespace UCRM\Converters;

use MVQN\Common\Strings;
use MVQN\REST\UCRM\Endpoints\Client;
use MVQN\REST\UCRM\Endpoints\Invoice;
use MVQN\REST\UCRM\Endpoints\Lookups\ClientBankAccount;
use MVQN\REST\UCRM\Endpoints\Lookups\ClientContact;
use MVQN\REST\UCRM\Endpoints\Lookups\InvoiceItem;
use MVQN\UCRM\Plugins\Log;
use MVQN\Collections\Collection;

use MVQN\UCRM\Plugins\Plugin;
use XeroPHP\Models\Accounting\Contact as XeroContact;
use XeroPHP\Models\Accounting\Contact;
use XeroPHP\Models\Accounting\Invoice\LineItem as XeroLineItem;
use XeroPHP\Models\Accounting\Invoice as XeroInvoice;


final class InvoiceConverter
{
    // Group1: Country Code (i.e. 1 or 86)
    // Group2: Area Code (i.e. 800)
    // Group3: Exchange (i.e. 555)
    // Group4: Subscriber (i.e. 1234)
    // Group5: Extension (i.e. 5678)

    private const REGEX_PHONE_NUMBERS =
        '/^\s*(?:\+?(\d{1,3}))?[-. (]*(\d{3})[-. )]*(\d{3})[-. ]*(\d{4})(?: *x(\d+))?\s*$/';


    public static function toNewXeroInvoice(Invoice $invoice, string $contactId): ?XeroInvoice
    {
        $xeroInvoice = new XeroInvoice();

        // > Type
        $xeroInvoice->setType("ACCREC");

        // > Contact
        $contact = new Contact();
        $contact->setContactID($contactId);
        $xeroInvoice->setContact($contact);

        $accountCode = Plugin::config()->getValue("xeroSalesAccountCode");

        // > LineItems...
        foreach($invoice->getItems() as $item)
        {
            /** @var InvoiceItem $item */

            $xeroItem = new XeroLineItem();
            $xeroItem->setDescription($item->getLabel());
            $xeroItem->setQuantity($item->getQuantity());
            $xeroItem->setUnitAmount($item->getPrice());
            $xeroItem->setAccountCode($accountCode);

            $xeroInvoice->addLineItem($xeroItem);
        }

        // Discount as a LineItem...
        $discountAmount = $invoice->getDiscount();

        if($discountAmount > 0)
        {
            $discountLabel = $invoice->getDiscountLabel();

            $xeroItem = new XeroLineItem();
            $xeroItem->setDescription($discountLabel);
            $xeroItem->setQuantity(1);
            $xeroItem->setUnitAmount(-$discountAmount);
            $xeroItem->setAccountCode($accountCode);

            $xeroInvoice->addLineItem($xeroItem);
        }

        // > Date (include the time component)
        $date = new \DateTime($invoice->getCreatedDate());
        //$date->setTime(0,0);
        $xeroInvoice->setDate($date);

        // > DueDate (ZERO the time component)
        $dueDate = new \DateTime($invoice->getDueDate());
        $dueDate->setTime(0,0);
        $xeroInvoice->setDueDate($dueDate);

        // > LineAmountTypes

        // > Reference

        // > Status
        $xeroInvoice->setStatus("AUTHORISED");

        // > SentToContact
        $xeroInvoice->setSentToContact($invoice->getEmailSentDate() !== null);

        return $xeroInvoice;
    }







}


