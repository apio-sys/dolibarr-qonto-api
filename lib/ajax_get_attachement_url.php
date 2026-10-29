<?php
/* Copyright (C) 2019 Florian Dufourg <florian.dufourg@gnl-solutions.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    gmao/lib/ajax_get_attachement.php
 * \ingroup gmao
 * \brief   Get file attachement from Qonto API
 */

define('NOTOKENRENEWAL', 1); // Keep the token of the page valid for its next calls

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory

// Access control (shared) - must run after main.inc.php has loaded $user.
require_once __DIR__ . '/security_ajax.inc.php';

dol_include_once('/bankimportapi/class/bankapi.class.php');

$bankId = GETPOST('bankId', 'int');
$attachmentId = GETPOST('id_attachement', 'aZ09');

$bankobject = new bankApi($db);

$url = $bankobject->getFile($bankId, $attachmentId);

// Only the URL or an error message may be sent back, never the bankApi object: it holds the Qonto credentials.
// text/plain because the page parses the JSON itself and shows the error with alert(), never as HTML.
header('Content-Type: text/plain; charset=UTF-8');

if ($url !== false && filter_var($url, FILTER_VALIDATE_URL) !== false) {
	echo json_encode($url);
} else {
	dol_syslog('ajax_get_attachement_url.php: attachment '.$attachmentId.' of bank account '.$bankId.' not retrieved: '.$bankobject->errorMsg, LOG_WARNING);
	echo ($bankobject->errorMsg !== '' ? $bankobject->errorMsg : 'Attachment URL not available');
}

