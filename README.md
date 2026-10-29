# Bank API (Qonto) for Dolibarr

**Turn your Qonto bank transactions into Dolibarr invoices, payments and reconciled bank lines, without re-typing anything.**

[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](COPYING)
[![Dolibarr 20+](https://img.shields.io/badge/Dolibarr-20%2B-263c5c.svg)](https://www.dolibarr.org)
[![Latest release](https://img.shields.io/github/v/release/apio-sys/dolibarr-qonto-api)](https://github.com/apio-sys/dolibarr-qonto-api/releases/latest)

*🇫🇷 Une [présentation en français](#en-français) se trouve en bas de cette page.*

If your company banks with [Qonto](https://qonto.com) and keeps its books in [Dolibarr ERP CRM](https://www.dolibarr.org), this module connects the two. Open your bank account in Dolibarr and you see your Qonto transactions. For each one, a few clicks create the matching invoice, charge or salary, record its payment and reconcile the bank line. Receipts attached in Qonto can come along too.

It is free software (GPLv3), free of charge, and has no dependencies beyond what Dolibarr already needs.

## What you can do with each transaction

| You see in Qonto... | ...and in one dialog the module can |
|---|---|
| A payment from a customer | create and pay a customer invoice, or pay an existing unpaid one |
| A payment to a supplier | create and pay a vendor invoice, or pay an existing unpaid one |
| A refund | create and pay a credit note |
| Tax, social charges, VAT | create and pay a social/fiscal charge or a VAT declaration |
| Salaries | create and pay a salary |
| Loan instalments, expense reports | pay the existing loan or expense report |
| Anything already entered by hand | link the transaction to the payment that already exists |

Every action can also reconcile the bank line against your Qonto statement and copy the Qonto attachments (invoices, receipts) into the Dolibarr element.

Transactions already processed are flagged, so you don't book them twice. If you made a mistake, you can unlink a transaction and process it again.

Prefer to only pay or link what's already in Dolibarr? Switch off **Allow creating new elements** on the module setup page and the "create and pay" actions disappear.

<!--
## Screenshots

![Qonto transactions listed in Dolibarr](docs/screenshots/transactions.png)

![Creating and paying a vendor invoice from a transaction](docs/screenshots/import-dialog.png)
-->

## Requirements

- **Dolibarr 20 or later.** We use it daily on Dolibarr 24. Older versions may work but are not supported.
- **PHP 8** with the curl extension (Dolibarr already needs curl).
- The Dolibarr **Banks | Cash** module enabled.
- A **Qonto API key** (a login and a secret key), generated in the Qonto web app.

## Installation

### From the release zip (easiest)

1. Download `module_bankimportapi-x.y.z.zip` from the [latest release](https://github.com/apio-sys/dolibarr-qonto-api/releases/latest). Don't unzip it.
2. In Dolibarr, as an administrator, go to *Home > Setup > Modules/Applications*, open the **Deploy/install external app/module** tab and upload the zip.
3. On the module list, enable **Bank API (Qonto)**.

### With git

If you'd rather use git, clone into Dolibarr's `custom` directory. The directory **must** be named `bankimportapi`:

```sh
cd /path/to/dolibarr/htdocs/custom
git clone https://github.com/apio-sys/dolibarr-qonto-api.git bankimportapi
```

Then enable **Bank API (Qonto)** in *Home > Setup > Modules/Applications*.

## Configuration

1. **Connect your Qonto account.** In *Banks | Cash > Bank accounts*, edit the Dolibarr account that matches your Qonto account and fill in:
   - **BANK API**: `QONTO`
   - **ID API**: your Qonto API login
   - **KEY API**: your Qonto API secret key
   - **IBAN**: the IBAN of the Qonto account. It is needed when your Qonto organisation has more than one account, so the module picks the right one.
2. **Open it.** After saving, the account shows up in the left menu of *Banks | Cash*, and clicking it lists the Qonto transactions of the last 45 days.
3. **Give your team access.** Grant the module permissions to the users who need them: **Read** to see the transactions, **Create/pay** to process them.
4. **Fine-tune (optional).** The module setup page sets the defaults: number of days shown, default project, default VAT rate, attachment transfer and whether new elements can be created.

## Upgrading

Download the new zip and upload it the same way (or `git pull` in the module directory). Then **disable and re-enable** the module in *Home > Setup > Modules/Applications*: some upgrades update the database or settings, and that only happens on activation. Your data and settings are kept.

If you come from a 2.x version, read the [notes on upgrading from 2.x](#upgrading-from-2x) first.

## How it works, and what about security

- The module talks to Qonto's official API directly from your Dolibarr server, over HTTPS with certificate checks. Nothing goes through a third-party service, and it bundles no third-party libraries.
- It only **reads** from Qonto (transactions, account details and attachments). It never sends payments or changes anything on your Qonto account. Everything it creates is in Dolibarr.
- On Dolibarr 19 and later, the API secret key is stored encrypted in the database.
- What users can do is controlled by the module's Dolibarr permissions.

Found a security issue? Please report it privately through [GitHub's security advisories](https://github.com/apio-sys/dolibarr-qonto-api/security/advisories/new) instead of opening a public issue.

## Something not working?

Problems and their fixes change from one version to the next, so they live in the [issues](https://github.com/apio-sys/dolibarr-qonto-api/issues?q=is%3Aissue), not in this README. Search the open and closed ones first: someone may already have had the same problem. If not, open a new issue with your Dolibarr and PHP versions and what you see. Leave your API keys and bank details out of it.

Found the fix yourself? A pull request is even better, see [Support and contributing](#support-and-contributing).

## Upgrading from 2.x

Version 3.0 removed the CSV statement import to concentrate on bank APIs. If you still need CSV import, stay on the original module (see [History and credits](#history-and-credits)).

- The *Load CSV* menu is gone, and bank accounts without a **BANK API** no longer show up in the module menu.
- When the module is **re-enabled** after the upgrade, it deletes the `llx_bankimportapi_csvlines` table and the CSV-only settings. **Back up that table first** if you still need the lines you imported from CSV. Invoices, payments and other elements already created from those lines are not touched.

The [ChangeLog](ChangeLog.md) lists every change since then.

## Support and contributing

This fork is maintained by [Apio systems](https://github.com/apio-sys), who use it every day on their own Dolibarr. Issues and pull requests are welcome. We fix them on a best-effort basis, without a guaranteed response time.

Many thanks to [Arnaud](https://github.com/arnaud-coral) for the security reviews and the many detailed bug reports and fixes that shaped the 3.x versions.

Translations live in the `langs` directory (en_US, fr_FR, es_ES, de_DE). Corrections and new languages are welcome.

## History and credits

This module started as *Bank Import API* by **Florian DUFOURG** ([simple-soft.eu](https://simple-soft.eu)), a paid module on [Dolistore](https://www.dolistore.com) that imports bank transactions from APIs and CSV files. Being free software, it could be forked when it no longer kept up with recent Dolibarr versions. Thanks to Florian for the original work, which this fork still builds on. His name stays on the module as its original author.

The fork concentrates on Qonto: CSV import was removed, all third-party code replaced, the security hardened and many bugs fixed for Dolibarr 20 and later.

## En français

**Bank API (Qonto)** relie votre compte [Qonto](https://qonto.com) à [Dolibarr](https://www.dolibarr.org). Vos transactions Qonto s'affichent dans Dolibarr, et pour chacune, quelques clics suffisent pour créer et régler la facture client ou fournisseur, l'avoir, la charge sociale ou fiscale, la TVA ou le salaire correspondant. Vous pouvez aussi régler une facture, un emprunt ou une note de frais existant, ou rattacher la transaction à un paiement déjà saisi. La ligne bancaire est rapprochée, et les justificatifs déposés dans Qonto peuvent être copiés dans Dolibarr.

- **Prérequis** : Dolibarr 20 ou plus récent (utilisé au quotidien sur Dolibarr 24), PHP 8, module Banques | Caisses activé, clé d'API Qonto (identifiant et clé secrète).
- **Installation** : téléchargez le zip de la [dernière version](https://github.com/apio-sys/dolibarr-qonto-api/releases/latest), puis dans *Accueil > Configuration > Modules / Applications*, onglet **Déployer/Installer un module externe**, envoyez le zip et activez **Bank API (Qonto)**.
- **Configuration** : sur la fiche du compte bancaire, renseignez **BANK API** = `QONTO`, **ID API**, **KEY API** et l'**IBAN**. Le compte apparaît alors dans le menu *Banques | Caisses*. Donnez ensuite les droits du module aux utilisateurs concernés.
- **Sécurité** : le module ne fait que lire vos données Qonto et n'émet aucun paiement. La clé secrète est stockée chiffrée (Dolibarr 19 et plus).
- **Support** : maintenu par Apio systems, sans garantie de délai. Les [tickets](https://github.com/apio-sys/dolibarr-qonto-api/issues) et contributions sont bienvenus, en français comme en anglais.

Ce module est un fork libre de *Bank Import API* de Florian DUFOURG ([simple-soft.eu](https://simple-soft.eu)), que nous remercions pour son travail.

## License

Bank API (Qonto) is free software. You can use, study, share and modify it.

- The code is licensed under the GNU General Public License, version 3 or (at your option) any later version. See [COPYING](COPYING).
- The documentation (this README, the ChangeLog and other texts) is licensed under the GNU Free Documentation License, version 1.3 or any later version, with no Invariant Sections, no Front-Cover Texts and no Back-Cover Texts. See [COPYING.GFDL](COPYING.GFDL).

Copyright (C) 2019-2020 Florian DUFOURG\
Copyright (C) 2024-2026 Apio systems and contributors
