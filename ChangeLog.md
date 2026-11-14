# CHANGELOG BANK API (QONTO) FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 3.4.14
FIX : Deleting in Dolibarr a payment created or linked by an import left its Qonto transaction marked as imported, with a link to a payment that no longer exists: it could not be imported again without the "forget" action. When the bank line of the payment is deleted, the module now forgets the import of its transaction, which can be imported again. A VAT paid in several times keeps its marks while one of its payments remains, since they can't be told apart. The core keeps the bank line of a deleted expense report payment, so its transaction stays imported too.

## 3.4.13
FIX : Imports were not atomic (#36): when a step failed, the steps before it stayed, for example a validated invoice and its payment without bank line, and the transaction was not marked as imported, so it could be imported again. Each import now runs in one database transaction: if any step fails, nothing is written. Two simultaneous imports of the same transaction from two sessions also left the elements of the second one: it is now rolled back entirely and reports the transaction as already imported. The Qonto attachments are copied once the import is committed, since files can't be rolled back: a failed copy no longer cancels the import and is reported with it.

## 3.4.12
FIX : Untranslated messages (#43). About 30 keys used by the module were defined in none of its languages, so Dolibarr showed the raw key: the statement field said "ReleveRef", the "forget" icon "ForgetImport", the options of the "link" dialog "paymentCustomer", "payment_salary" and so on, and most error messages of the import endpoints were keys. Three keys had typos ("ReleveNoot defined", "InsurancetNotValid", and an amount mismatch message with the amounts glued into the key): they are fixed, and the amount mismatch now shows the amounts paid and the amount of the transaction. The en_US, fr_FR, de_DE and es_ES files now hold the same 102 keys, the keys used nowhere are removed, and their texts are corrected (fr_FR had a Spanish line). de_DE had only 65 keys and is complete now. The pages also load the core language files of the labels they borrow from the core (payment method, bank, balance, credit, debit, customer, invoice ref., deposit, donation, multicurrency amounts), which were shown as keys. The select of the "link" dialog was labelled "Expense report": it is now labelled "Link to". The invoice lists answered "EmptyBankid" when the third party was missing: they now say that the vendor or customer is not valid. A missing payment method erased the other error messages of the same import: it is now added to them.
FIX : Leftovers of the ModuleBuilder template (#43). sql/llx_bankimportapi_myobject_extrafields.key.sql added an index to a table that does not exist at each activation of the module: it is removed. class/importedline.class.php keeps only what the module uses (create, fetch, update, delete), without the template methods that were never called, and no longer declares extrafields, which the module has no table for. The \file and \brief headers of the AJAX endpoints now name their own file and purpose, and the placeholder copyright lines name the original author.

## 3.4.11
FIX : The transactions list showed the emission date of each transaction while the period is filtered on its settlement date (#56): a card payment settled the day after was missing from the period of the date it showed. The list now shows the settlement date, the one that counts (a transaction can still be cancelled before it settles), and the bank statement name is taken from its month. The bank line keeps the emission date as operation date and the settlement date as value date.

## 3.4.10
SEC : The AJAX endpoints of the module disabled the CSRF check of the core (NOCSRFCHECK) (#52). Only the SameSite=Lax session cookie protected them: a page of the same site (another subdomain, for example) opened by a logged-in user could create, pay, link and reconcile in their name. The core check now applies to every endpoint, and the endpoints that create, pay or link also check the token themselves, so a wrong token is refused even when the core check is disabled. The page and its dialogs send the current token on each call (the payments list sent none), and the endpoints no longer renew it, so it stays valid with MAIN_SECURITY_CSRF_TOKEN_RENEWAL_ON_EACH_CALL. The "pay invoices" dialogs also send it to the core compta/ajaxpayment.php, which refused them without it: the automatic calculation of the amounts works again.
SEC : The supplier invoices list of the credit note dialog returned the database error message to the browser. The error now goes to the Dolibarr log, as in the other lists.

## 3.4.9
FIX : The same Qonto transaction could be imported twice (#37): a double click, two tabs, two users or a retry after a slow answer created two payments and two bank lines. Every import endpoint now checks, before any write, that the transaction is not imported yet on the bank account, and refuses it with a clear message. The Import button of the dialogs is disabled during the request. A unique key on (fk_bank, ref) of llx_bankimportapi_importedline now guarantees one mark per transaction and bank account: if a transaction was already imported twice, its first mark is kept and the extra marks are renamed (ref followed by -duplicate- and their id) so the key can be created; they stay in the table with the id of the duplicate element, which can be checked and deleted by hand. The redundant index on rowid is dropped. Disable and enable the module again to apply these database changes.
FIX : Some import failures were not detected (#39). A negative error code went unnoticed when paying an expense report or a loan, because of the precedence of ! over > in the tests. The bank line of a loan payment was added even when the payment could not be created. "Create and pay" a VAT recorded the import without reference, so its link showed nothing: the VAT id is now recorded (imports done before keep an empty reference). rapprocher() and addImportedLine() returned 1 on success and an array on failure, which gave a PHP warning on every import and an "Array" message on failure: they now always return an array, with the error messages of the object.

## 3.4.8
FIX : The transactions of the end date of the period were never listed (#38): Qonto was asked for the transactions settled until 00:00 of that day. It now gets the end of the end date (23:59:59), and the current time when no end date is chosen. The default period (BANKIMPORTAPI_PERIOD_SHOWED_DAYS, 45 days) now starts at the beginning of its first day instead of the time the page was opened.

## 3.4.7
FIX : "Create and pay a salary" was unusable on Dolibarr 21 and later: the employee field showed "Error bad param $morefilter" (#42), because these versions only accept the universal search filter syntax in select_dolusers(). The dialog now uses (u.employee:=:1) from Dolibarr 21, and keeps the SQL filter for older versions. The salary also never got the employee's reference salary: the endpoint read the employee id from a POST parameter that the dialog does not send.

## 3.4.6
FIX : Paying an expense report from a transaction always failed with ErrorBadValueForParameterCreatePaymentExpenseReport (#41): the module set the payment date in $datepaid, while the core requires $datep. The payment now gets the transaction time as a timestamp. The payment amount was also assigned to the user making the import instead of the employee of the expense report, so the bank line would have been linked to the wrong user: it is now assigned to the employee, as the core does.

## 3.4.5
FIX : Dates of the elements and payments created from a transaction (#40). The VAT, salary, loan payment, customer invoice, vendor invoice and credit note endpoints gave the core 'Y-m-d H:i:s' strings, as the social contributions did before 3.4.2. With a payment term, a customer invoice got a due date in January 1970, because the core adds the term days to the invoice date. The core also reads such a string as GMT, so an import made late in the evening could date the element, its payment and its bank line the next day. They now get timestamps: the transaction time for operation and payment dates, and noon of the chosen day for the dates entered in the dialogs, as the core forms do. The salary payment date was also taken from an undefined variable.

## 3.4.4
SEC : The statement name typed in the import dialogs was concatenated into the SQL query that reconciles the bank line (rapprocher() in lib/bankimportapi.lib.php). A user with the module Create/pay permission could rewrite any column of any bank line, of every bank account, or copy data from other tables into it with a subquery. The line is now reconciled by the core (AccountLine::update_conciliation()), which escapes the statement name, records who reconciled the line and applies the BANK_STATEMENT_REGEX_RULE setting.
SEC : The "forget" action of the transactions page deletes the "imported" mark of a transaction, so that it can be imported again. It was a plain link that only needed the Read permission and no CSRF token: a link opened by a logged-in user could delete marks, and the transactions could then be imported twice. It now requires the Create/pay permission and the CSRF token, carried by the links of the page and of the import results, which are only shown to users with that permission. The transaction id is escaped in the query, and the page stops right after the redirection instead of loading the transactions again.

## 3.4.3
FIX : The transactions page only listed the first page of the Qonto API response, so a period with more than 100 transactions lost the oldest ones: they could not be imported, linked or reconciled from the module, and the Credit / Debit totals left them out, with nothing on screen to tell. The module now reads every page (100 transactions each, newest first) and lists each transaction once, even when one is settled while the pages are read. Reading stops after 50 pages (5,000 transactions): the page then keeps the most recent ones and shows a warning to choose a shorter period.

## 3.4.2
FIX : Social contributions. "Create and pay" failed on Dolibarr 23 with "Incorrect date value: '' for column periode", because the core now reads the period from $period and the module only set $periode: both are set now. The contribution, its due date and its payment were given 'Y-m-d H:i:s' strings, which the core reads as GMT and completes with the current time, so an import made late in the evening was dated the next day: they now get timestamps, as the invoice endpoints do. "Reconcile" looked for the payment's bank line with the type of a salary payment: it reconciled the bank line of a salary payment that happened to have the same id, or silently did nothing. It now uses the payment's own bank line (payment_sc). The link shown for an imported social contribution opened the contribution whose id equals the payment id (another contribution, or none): "create and pay" and "link" both store the payment id, and the link now opens that payment.

## 3.4.1
SEC : When Qonto could not return an attachment, lib/ajax_get_attachement_url.php sent the whole bankApi object back to the browser, including the decrypted Qonto API secret key, the API login and the public properties of the database handler. Any user with the module Read permission could get the key by asking for an attachment that does not exist, and a failed attachment display also printed it in the browser console. The endpoint now reads its parameters with GETPOST and only returns the attachment URL or an error message, bankApi::getFile() returns false on any error, and the API credentials are private properties of bankApi. If users you don't fully trust had the Read permission, generate a new secret key in Qonto and update KEY API on your bank accounts.

## 3.4
NEW : Setting "Allow creating new elements" (BANKIMPORTAPI_ALLOW_CREATE, on by default). Set to 0 to hide the "create and pay" actions (customer invoice, credit note, vendor invoice, VAT, salary, social/fiscal tax) and only pay or link existing elements. When off, the matching AJAX endpoints refuse requests too.

## 3.3
FIX : The "Default project" setting only listed projects without a third party, on the setup page and in the import dialogs (create & pay vendor/customer invoice, credit note, charge, salary), because the project selector was filtered on an undefined third party. All open projects are now listed, so the default project can be chosen and is pre-selected. Removed the "Only show opened projects" setting, which had no effect (closed projects were always hidden), plus dead setup code (list of prefixes, default payment types) and the unused listSelectProject() helper. On re-activation the module deletes the BANKIMPORTAPI_ONLY_OPENED_PROJECTS and BANKIMPORTAPI_LIST_PREFIXES constants.

## 3.2.1
FIX : Saving a new KEY API on a bank account crashed with PHP 8 (TypeError "Cannot access offset of type string on string" in CommonObject::insertExtraFields), because the key_api password extrafield had an empty param. On re-activation the module now sets a valid param: on Dolibarr 19+ the key is stored encrypted (dolcrypt) and existing keys stored in clear are encrypted, on older versions it is kept as is. The key_api column is widened to 255 characters for the encrypted value.

## 3.2
SEC : Hardened diagnostic output. The transactions page and AJAX endpoints no longer dump internal objects to the browser: debug mode (BANKIMPORTAPI_DEV_MODE=1) now masks the API secret key and leaves out the database handler, an empty period shows "No transaction in the selected period", an IBAN not found in Qonto shows a plain error instead of dumping the organization, and an SQL error in ajax_get_payments_list.php is logged instead of printed. API error messages are HTML-escaped.

## 3.1
SEC : Removed all third-party PHP code. lib/oauth-api (an unused phpclasses OAuth library whose ~70 demo scripts were reachable from the web without Dolibarr authentication) is deleted. The tavux/qonto-php library and its vendored Guzzle 6.5.2 / psr7 1.6.1 stack (affected by multiple security advisories, none fixed in Guzzle 6 or psr7 1.x) are replaced by a small built-in client, class/qontoclient.class.php, using PHP curl (HTTPS only, certificate verification, no redirects, timeouts, Dolibarr proxy settings). lib/vendor, lib/composer.json and lib/composer.lock are removed.

## 3.0
FEAT : CSV statement import removed, the module now only works with bank APIs (Qonto). Removed loadcsv.php, the csvlines class/table, the CSV-only constants and translations, and the CSV fallback and delete-CSV-line action on the transactions page. The bank menu now only lists bank accounts with a BANK API configured. On re-activation the module drops llx_bankimportapi_csvlines and the BANKIMPORTAPI_DATE_FORMAT / AMOUNT_FORMAT / PAYMENTMODE_FORMAT / PAYMENT_MODE_TRANSLATION_TAB constants (back up that table first if you still need CSV lines). Module renamed to "Bank API (Qonto)" with a new description; README rewritten.

## 2.34
FIX : Linking a bank transaction to an existing VAT payment found nothing when the VAT declaration was paid later or in several payments. The search now looks at VAT payments (llx_payment_vat, amount and payment date) and links their declaration, instead of matching the declaration amount and date. Also fixed undefined $htmlList / $retour['message'] warnings in ajax_get_payments_list.php.

## 2.33
FIX : PHP warnings when paying invoices: $atleastonepaymentnotnull and $amounts were not initialised in ajax_import_p_ffour.php / ajax_import_p_fclient.php, and supplier payments did not fill the per-invoice multicurrency_code / multicurrency_tx arrays read by PaiementFourn::create() ("Undefined array key" in paiementfourn.class.php).

## 2.32
FIX : Closed (inactive) suppliers and customers were listed in the thirdparty dropdowns of the pay / create-and-pay / credit note dialogs (unless COMPANY_HIDE_INACTIVE_IN_COMBOBOX was enabled). The select_company() filters now also require s.status = 1.

## 2.31
FIX : Arrow to quick-copy "remain to pay" into "paid amount" was missing since Dolibarr v20+/23.0.3. Dolibarr's img_picto() no longer falls back to the legacy 'rightarrow.png' image for a bare 'rightarrow' picto name (it now always tries to render a FontAwesome icon instead), so the icon silently rendered empty. Now calls img_picto("Auto fill", 'rightarrow.png') with the explicit .png extension, matching what Dolibarr's own native "add payment" page (compta/paiement.php) still does today, and moved the AutoFillAmout class/data attributes onto a wrapping button so the icon markup stays identical to core's.

## 2.30
FIX : Security - enforce access control on all AJAX endpoints (were reachable by any authenticated user). Added a dedicated 'write' permission for create/pay/reconcile/link endpoints, kept 'read' for list endpoints, via a shared guard (lib/security_ajax.inc.php). Existing 'read' holders are auto-granted 'write' on module upgrade.

## 2.29
FIX : Security - hide Qonto API secret key from bank account lists (key_api extrafield changed to 'password' type and no longer displayed in lists, existing installs migrated on module upgrade)

## 2.28
FIX : update compatibility for version 20 of Dolibarr

## 2.27
FIX : ajax_get_payments_list.php daterequest need Y-m-d H:i:s format on SQL
FIX : When modal link manu is open, reset list to initial value "no_link"
NEW : When getting list of payment from link modal, we add a sign before the amount '-' for debit and '' for credit

## 2.26
FIX : Correct ajax_import_c_p_salary.php change method paiement-> create() to adapt for Dolibarr V19

## 2.25
FIX : CSRF token issue on POST to import CSV file

## 2.24
NEW : Add card number details (4 last digits) next to card

## 2.23
FIX : Enable payment various credit to link a line
FIX : The button "LINK" did not appears when no_link was selected

## 2.22
FIX : Qonto.eu is not working anymore, change to qonto.com for API URL

## 2.21
FIX : Add more code on ajax_import_c_p_ffour.php to see error if error is_array..

## 2.20
FIX : Hide PHP warnings

## 2.19
NEW : if many accounts on Qonto, we find accound by IBAN

## 2.18
NEW : compatible with V17
NEW : create credit note directly from dolimport
NEW : allow to disable warning icon if VAT not defined 
FIX : expense report user linked with payment was not correct
FIX : Create and pay invoice : fatal error to gettimestamp on bool, more tests are done to check dates formats

## 2.17
FIX : Allow use of Dolibarr V.16.0.x

## 2.16
FIX : UTF8 decode when showing table before import

## 2.15
FIX : import CSV with date format dd.mm.yyyy was not working

## 2.14
FIX : on ajax_import_p_ffour.php, use strval() to compare double value -> double values have floating point and can't be compared directly

## 2.13
NEW : When error AmountNotEgualAsInvoices appears, the value of amount and invoices is shown

## 2.12
FIX : vat dont link vat table and vat payment

## 2.11
FIX : Salary paiement didnt add payment to bank

## 2.10
FIX : For simple link of element, do not check if payment mode is present (ajax_link_manu.php)

## 2.9
FIX : Add error fopen to check what is the issue

## 2.8
FIX : Add error management

## 2.7
FIX : File transfer is working when using "create and paye provider invoices"
NEW : Debug mode value 2 allow to debug file transfer issue

## 2.6
NEW : It is possible to add account number of Qonto
NEW : Debug mode to see qonto response about organisation

## 2.5
NEW : Compatibility with POSTGRE

## 2.4
FIX : Impossible to pay salaries after update dolibarr v14

## 2.3
NEW : Show total of credit and total of debit of Qonto lines
FIX : List of user is not displayed correctly (pay salarie)

## 2.2
NEW : Allow to delete only one CSV line imported

## 2.0
NEW : Icon show if VAT had been imported

## 1.9
FIX : Project is shown only if project module is enable
FIX : Project is not required

## 1.7
FIX : Change precision of VAT value

## 1.6
FIX : Change calcul to evaluate php limite of post Variables
FIX : Issue with SQL MODE "ONLY_FULL_GROUP_BY"

## 1.5
Allow transfert of files from QONTO to DOLIBARR

## 1.0
Initial version