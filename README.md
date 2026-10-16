# BANK API (QONTO) FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

Fetch your bank transactions straight from the [Qonto](https://qonto.com) API and turn them into Dolibarr elements: create, pay and reconcile them without re-typing anything.

This is a maintained fork of the *Bank Import API* module by Florian DUFOURG (simple-soft.eu), which is still available on [Dolistore](https://www.dolistore.com). The fork focuses only on bank APIs. **CSV statement import was removed in 3.0.** If you need CSV import, use the original module.

## Features

For each Qonto transaction on a configured bank account, you can:

- create and pay a vendor invoice, a customer invoice or a credit note
- create and pay a social/fiscal charge, a VAT declaration or a salary
- pay an existing unpaid vendor or customer invoice, loan or expense report
- link the transaction to a payment that already exists in Dolibarr
- reconcile the bank line against the Qonto statement
- optionally copy the attachments stored in Qonto into the Dolibarr element

Transactions that are already processed are flagged so you don't import them twice. You can unlink them at any time.

## Requirements

- Dolibarr with the *Banks & Cash* module enabled
- PHP with the curl extension (already required by Dolibarr). The module bundles no third-party libraries.
- A Qonto account with an API key (login and secret key, generated from the Qonto web app)

## Installation

The module directory **must** be named `bankimportapi`:

```sh
cd /path/to/dolibarr/htdocs/custom
git clone https://github.com/apio-sys/dolibarr-qonto-api.git bankimportapi
```

Then, as a Dolibarr administrator, go to *Home > Setup > Modules* and enable **Bank API (Qonto)**.

If Dolibarr doesn't list the module, check that `$dolibarr_main_url_root_alt` and `$dolibarr_main_document_root_alt` are set in `htdocs/conf/conf.php`.

## Configuration

1. Open the Dolibarr bank account that matches your Qonto account (*Banks & Cash > Bank accounts*) and edit it:
   - **IBAN**: must match the Qonto account's IBAN (used to pick the right account when the organisation has several)
   - **BANK API**: `QONTO`
   - **ID API**: your Qonto API login
   - **KEY API**: your Qonto API secret key
2. Save. The account now appears in the left menu of *Banks & Cash*. Only bank accounts with a **BANK API** set show up there.
3. Grant users the module permissions (*Read* to see transactions, *Create/pay* to import them).
4. Optionally adjust the defaults on the module setup page (default project, default VAT rate, number of days shown, attachment transfer, etc.).

## Upgrading from 2.x

- CSV import (the *Load CSV* menu, `loadcsv.php` and the `csvlines` class) is gone. Bank accounts without a **BANK API** no longer show up in the menu.
- When the module is **re-activated** after the upgrade, it drops the `llx_bankimportapi_csvlines` table and the CSV-only constants (`BANKIMPORTAPI_DATE_FORMAT`, `BANKIMPORTAPI_AMOUNT_FORMAT`, `BANKIMPORTAPI_PAYMENTMODE_FORMAT`, `BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB`). **Back up that table first** if you still need lines you imported from CSV. Dolibarr elements and payments that were already created from those lines are not touched.

## Translations

Translations are in the `langs` directory (en_US, fr_FR, es_ES, de_DE).

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
