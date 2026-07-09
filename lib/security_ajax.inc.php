<?php
/* Copyright (C) 2026 Data Rooster <arnaud@datarooster.io>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/security_ajax.inc.php
 * \ingroup bankimportapi
 * \brief   Shared access-control guard for the BankImportApi AJAX endpoints.
 *
 * MUST be required AFTER main.inc.php: it relies on $user (which main.inc.php
 * loads, and whose presence also guarantees the caller is authenticated).
 *
 * By default it requires the module 'read' permission. A state-changing
 * endpoint (create/pay/reconcile/link) MUST set
 *     $bankimportapiRequireWrite = true;
 * before including this file, so the dedicated 'write' permission is required
 * instead. Any failing check stops the request with accessforbidden().
 */

// Defensive: refuse to run if the Dolibarr environment was not loaded first.
if (!defined('DOL_DOCUMENT_ROOT') || !isset($user) || !is_object($user)) {
	if (!headers_sent()) {
		http_response_code(403);
	}
	die('Forbidden');
}

if (!empty($bankimportapiRequireWrite)) {
	// State-changing endpoints: require the dedicated write permission.
	if (empty($user->rights->bankimportapi->mybankimports->write)) {
		accessforbidden();
	}
} else {
	// Read-only endpoints: require the read permission.
	if (empty($user->rights->bankimportapi->mybankimports->read)) {
		accessforbidden();
	}
}
