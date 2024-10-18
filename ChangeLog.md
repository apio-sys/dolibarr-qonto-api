# CHANGELOG BANKIMPORTAPI FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

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