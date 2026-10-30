<?php
/* Copyright (C) 2020 SuperAdmin <florian.dufourg@gmail.com>
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
 * \file    bankimportapi/lib/bankimportapi.lib.php
 * \ingroup bankimportapi
 * \brief   Library files with common functions for BankImportApi
 */

dol_include_once('/bankimportapi/class/importedline.class.php');


/**
 * Whether the "create and pay" actions are enabled (setting BANKIMPORTAPI_ALLOW_CREATE).
 * On when the setting does not exist yet. Same test as in security_ajax.inc.php.
 *
 * @return bool
 */
function bankimportapiCreateAllowed()
{
	global $conf;
	return !isset($conf->global->BANKIMPORTAPI_ALLOW_CREATE) || (string) $conf->global->BANKIMPORTAPI_ALLOW_CREATE !== '0';
}


/**
 * Prepare admin pages header
 *
 * @return array
 */
function bankimportapiAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("bankimportapi@bankimportapi");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/bankimportapi/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath("/bankimportapi/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@bankimportapi:/bankimportapi/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@bankimportapi:/bankimportapi/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'bankimportapi');

	return $head;
}

//Check if date is valid date
function validateDate($date,$format = 'Y-m-d H:i:s'){
	$d = DateTime::createFromFormat($format,$date);
	return $d && $d->format($format) == $date;
}

//Convert date Post to date
function convertDatePost($date,$originFormat = 'dd/mm/yyyy',$outFormat = 'Y-m-d H:i:s'){
	
	$error = 0;
	$date = trim($date);
	$date = substr($date, 0, strlen($originFormat));

	if($originFormat == "dd/mm/yyyy"){
		$originFormat = 'd/m/Y';
		
	} else if($originFormat == "dd-mm-yyyy"){
		$originFormat = 'd-m-Y';

	} else if($originFormat == "dd.mm.yyyy"){
		$date = str_replace(".","-",$date);
		$originFormat = 'd-m-Y';
		
	} else if($originFormat == "dd.mm.yy"){
		$date = str_replace(".","-",$date);
		$originFormat = 'd-m-y';
		
	} else if($originFormat == "yyyy/mm/dd"){
		$originFormat = 'Y/m/d';
		
	} else if($originFormat == "yyyy-mm-dd"){
		$originFormat = 'Y-m-d';
		
	} else if($originFormat == "mm-dd-yy"){
		$originFormat = 'm-d-y';
		
	}
		
	if (isTimestamp($date)){
		$timestamp = $date;
	}else{
		$dtime = DateTime::createFromFormat($originFormat,$date);

		if(empty($dtime)){
			$error++;
		}else{
			// createFromFormat() keeps the current time of day: use noon, as the core forms do (dol_mktime(12, 0, 0, ...)),
			// so the day does not change with the timezone or when days are added across a DST change.
			$dtime->setTime(12, 0, 0);
			$timestamp = $dtime->getTimestamp();
		}
		
	}

	if(empty($error)){
		return date($outFormat, $timestamp);
	}else{
		return "";
	}

	
}


//Check if timestamp
/**
 * @param string $string
 * @return bool
 */
function isTimestamp($string)
{
    try {
        new DateTime('@' . $string);
    } catch(Exception $e) {
        return false;
    }
    return true;
}

//getNomUrl depending on type of element
/**
 * @param string $string
 * @return bool
 */
function flo_getNomUrl($idElement,$typeOfElement)
{
	
	global $db;


	
	switch ($typeOfElement) {
		case "payment_supplier":
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
			$paiement = new PaiementFourn($db);
			$paiement->fetch($idElement);
			return $paiement->getNomUrl(1,'','',1);
			break;
			
		case "tva":
			require_once DOL_DOCUMENT_ROOT.'/compta/tva/class/tva.class.php';
			$tva = new Tva($db);
			$tva->fetch($idElement);
			return $tva->getNomUrl(1);
			break;
			
		case "payment_salary":
			require_once DOL_DOCUMENT_ROOT.'/salaries/class/paymentsalary.class.php';
			$salaryPayment = new PaymentSalary($db);
			$salaryPayment->fetch($idElement);
			return $salaryPayment->getNomUrl(1);
			break;

			
		case "salaries":
			require_once DOL_DOCUMENT_ROOT.'/salaries/class/salary.class.php';
			$salary= new Salary($db);
			$salary->fetch($idElement);
			return $salary->getNomUrl(1);
			break;
			
		case "chargesociales":
			// "Create and pay" and "link" both store the id of the social contribution payment, not of the contribution
			require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/paymentsocialcontribution.class.php';
			$chargePayment = new PaymentSocialContribution($db);
			$chargePayment->fetch($idElement);
			return $chargePayment->getNomUrl(1);
			break;
			
		case "payment_loan":
			require_once DOL_DOCUMENT_ROOT.'/loan/class/paymentloan.class.php';
			$chargePayment = new PaymentLoan($db);
			$chargePayment->fetch($idElement);
			return $chargePayment->getNomUrl(1);
			break;
			
		case "payment":
			require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
			$chargePayment = new Paiement($db);
			$chargePayment->fetch($idElement);
			return $chargePayment->getNomUrl(1);
			break;
			
		case "payment_expensereport":
			require_once DOL_DOCUMENT_ROOT.'/expensereport/class/paymentexpensereport.class.php';
			$chargePayment = new PaymentExpenseReport($db);
			$chargePayment->fetch($idElement);
			return $chargePayment->getNomUrl(1);
			break;
			
		case "payment_donation":
			require_once DOL_DOCUMENT_ROOT.'/don/class/don.class.php';
			$chargePayment = new Don($db);
			$chargePayment->fetch($idElement);
			return $chargePayment->getNomUrl(1);
			break;
			
		case "payment_various":
			require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/paymentvarious.class.php';
			$chargePayment = new PaymentVarious($db);
			$chargePayment->fetch($idElement);
			return $chargePayment->getNomUrl(1);
			break;
			
	}
}


//Rapprochement
function rapprocher($releve,$bank_line_id){
	global $db, $user, $langs;

	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	// The core translates its BANK_STATEMENT_REGEX_RULE error during the call
	$langs->load('errors');

	$retour = array('error' => 0, 'message' => '');

	// The statement name comes from the browser: update_conciliation() escapes it, records who reconciled the line
	// and applies the BANK_STATEMENT_REGEX_RULE setting
	$line = new AccountLine($db);
	if ($line->fetch((int) $bank_line_id) <= 0) {
		$retour['error']++;
		$retour['message'] .= 'Error while "rapprochement" <br>';
		return $retour;
	}
	$line->num_releve = $releve;

	$transactionLevel = $db->transaction_opened;
	if ($line->update_conciliation($user, 0, 1) < 0) {
		// update_conciliation() returns without closing its transaction when the statement doesn't match BANK_STATEMENT_REGEX_RULE
		while ($db->transaction_opened > $transactionLevel) {
			$db->rollback();
		}
		$retour['error']++;
		$retour['message'] .= dol_escape_htmltag('Error while "rapprochement": '.($line->error ? $line->error : implode(', ', $line->errors))).'<br>';
		return $retour;
	}

	// Always an array: the callers read $res['error']
	return $retour;
}


/**
 * URL of the "forget" action of a transaction on the transactions page
 *
 * @param  int    $bankId        Dolibarr bank account id
 * @param  string $transactionId Qonto transaction id
 * @return string                URL with the CSRF token, which the action requires
 */
function bankimportapiForgetUrl($bankId, $transactionId)
{
	return dol_buildpath('/bankimportapi/bankimportapiindex.php', 1).'?id_bank='.((int) $bankId).'&action=forgetpaiement&transaction_id='.urlencode($transactionId).'&token='.newToken();
}

//Add line imported in database
function addImportedLine($id_line,$id_bank,$element_type,$ref_element){
	
	global $db,$user;

	// Always an array: the callers read $res['error']
	$retour = array('error' => 0, 'message' => '');

	$object = new importedline($db);

	$object->ref = $id_line;
	$object->fk_bank = $id_bank;
	$object->import_type = $element_type;
	$object->ref_element = $ref_element;
	$res = $object->create($user);

	if ($res < 0)
	{
		$retour['error']++;
		$retour['message'] .= dol_escape_htmltag($object->errorsToString()).'<br>';
		return $retour;
	}

	return $retour;
}

/**
 * Whether a Qonto transaction is already imported on a bank account
 *
 * The import endpoints check it before any write: a double click, a second tab, a second user or a retry
 * must not create a second payment and a second bank line for the same transaction.
 *
 * @param  string $id_line Qonto transaction id
 * @param  int    $id_bank Dolibarr bank account id
 * @return int             1 if imported, 0 if not, -1 on error (logged here: the callers only show a generic message)
 */
function isImportedLine($id_line, $id_bank)
{
	global $db;

	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."bankimportapi_importedline";
	$sql .= " WHERE fk_bank = ".((int) $id_bank);
	$sql .= " AND ref = '".$db->escape($id_line)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' SQL error: '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	$num = $db->num_rows($resql);
	$db->free($resql);

	return ($num > 0 ? 1 : 0);
}

/**
 * Ends the database transaction of an import: commit if every step succeeded, roll back otherwise
 *
 * An import is all or nothing: a failed step must not leave an element, a payment or a bank line without the
 * others, nor without the mark that keeps the transaction from being imported again. As the mark is written in
 * the same transaction, the unique key on the marks makes the second of two simultaneous imports of a
 * transaction fail on its mark and roll back entirely.
 *
 * @param  array  $retour  Result of the endpoint: 'error' count and 'message'
 * @param  int    $level   Transaction level before the $db->begin() of the import
 * @param  string $id_line Qonto transaction id
 * @param  int    $id_bank Dolibarr bank account id
 * @return array           $retour, with an error when the import could not be committed
 */
function bankimportapiImportEnd($retour, $level, $id_line, $id_bank)
{
	global $db, $langs;

	// A step that left its own transaction open would make commit() close only that one, and the import would be
	// lost when the connection closes
	if (!$retour['error'] && $db->transaction_opened == $level + 1 && $db->commit()) {
		return $retour;
	}

	if (!$retour['error']) {
		dol_syslog(__FUNCTION__.' import not committed, transaction level '.$db->transaction_opened.': '.$db->lasterror(), LOG_ERR);
		$retour['error']++;
		$retour['message'] = 'SQL error, see Dolibarr logs<br>';
	}
	while ($db->transaction_opened > $level) {
		$db->rollback();
	}

	// The other of two simultaneous imports of this transaction was committed first: say so, rather than the
	// database error of this one
	if (isImportedLine($id_line, $id_bank) > 0) {
		$retour['message'] = $langs->trans("TransactionAlreadyImported").'<br>';
	}

	return $retour;
}

/**
 * Copies the Qonto attachments of an imported transaction to the element of the import
 *
 * Files can't be rolled back: they are copied once the import is committed, and a failed copy is reported
 * without undoing the import.
 *
 * @param  array        $retour          Result of the endpoint: 'error' count and 'message'
 * @param  int          $bankId          Dolibarr bank account id
 * @param  CommonObject $object          Element created or paid by the import
 * @param  array        $transactionLine Qonto transaction sent by the page
 * @return array                         $retour, with a warning when the copy failed
 */
function bankimportapiCopyAttachments($retour, $bankId, $object, $transactionLine)
{
	global $conf, $langs;

	if ($retour['error'] || empty($conf->global->BANKIMPORTAPI_ALLOW_FILES_TRANSFERT) || empty($transactionLine['attachment_ids'])) {
		return $retour;
	}

	// Fetched again because an endpoint may change the object for its answer, and the copy builds the
	// directory from its ref
	$object->fetch($object->id);
	$res = transferFiles($bankId, $object, array_values((array) $transactionLine['attachment_ids']));
	if (!empty($res['error'])) {
		$retour['message'] .= '<br>'.$langs->trans("AttachmentsNotCopied").' '.$res['message'];
	}

	return $retour;
}


//Transfert Qonto Files to Dolibarr
function transferFiles($bankId,$object,$listOfIdsAttachement){
	global $db,$conf, $langs;

	// The failure branches below add to it: the callers read $res['error'] and $res['message']
	$retour = array('error' => 0, 'message' => '');

	//GetListOf URL from id attachement
	dol_include_once('/bankimportapi/class/bankapi.class.php');
	$bankobject = new bankApi($db);	
	$listOfLinks = array();

	foreach($listOfIdsAttachement as $k=>$v){

		$result = $bankobject->getFile($bankId,$v);

		if (filter_var($result, FILTER_VALIDATE_URL) != FALSE) {
			$listOfLinks[] = $result;
		}
	}

	if (is_array($listOfLinks) && !empty($listOfLinks) && is_object($object))
	{
		
		dol_include_once('/core/lib/files.lib.php');

		if($object->element == 'invoice_supplier'){			
			$object->fetch_thirdparty();
			$ref = dol_sanitizeFileName($object->ref);
			$upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($object->id, 2, 0, 0, $object, 'invoice_supplier').$ref;
			
		}elseif($object->element == 'facture'){
			$object->fetch_thirdparty();
			$upload_dir = $conf->facture->dir_output."/".dol_sanitizeFileName($object->ref);

		}elseif($object->element == 'salary'){
			$upload_dir = $conf->salaries->dir_output.'/'.dol_sanitizeFileName($object->id);
			
		}elseif($object->element == 'tva'){
			$upload_dir = $conf->tax->dir_output.'/vat/'.dol_sanitizeFileName($object->ref);

		}elseif($object->element == 'chargesociales'){
			$upload_dir = $conf->tax->dir_output.'/'.dol_sanitizeFileName($object->ref);

		}else{
			$retour['error']++;
			$retour['message'] .= 'Impossible to upload the file, The object is not an valide modulepart<br>';
			return $retour;
		}

		dol_syslog('dolimportAPI_add_file_process upload_dir='.$upload_dir, LOG_DEBUG);
		
		if(empty($retour['error'])){
			foreach($listOfLinks as $key=>$val){
				
				//Check if the file exist
				if (@fopen($val, 'r')){
					
					if (dol_mkdir($upload_dir) >= 0)
					{
						$nbok = 0;

						// Define $destfull (path to file including filename) and $destfile (only filename)
						$destfile=dol_basename($val);
						$destfile=strtok($destfile,'?');
						
						$destfull=$upload_dir . "/" . $destfile;

						// dol_sanitizeFileName the file name and lowercase extension
						$info = pathinfo($destfull);
						$destfull = $info['dirname'].'/'.dol_sanitizeFileName($info['filename'].($info['extension']!='' ? ('.'.strtolower($info['extension'])) : ''));
						$info = pathinfo($destfile);

						$destfile = dol_sanitizeFileName($info['filename'].($info['extension']!='' ? ('.'.strtolower($info['extension'])) : ''));

						// We apply dol_string_nohtmltag also to clean file names (this remove duplicate spaces) because
						// this function is also applied when we make try to download file (by the GETPOST(filename, 'alphanohtml') call).
						$destfile = dol_string_nohtmltag($destfile);
						$destfull = dol_string_nohtmltag($destfull);

						$resupload = file_put_contents( $destfull,file_get_contents($val));

						if($resupload){
							$nbok++;

						}else{
							$retour['error']++;
							$retour['message'] .= $langs->trans("ErrorFileNotUploaded").'<br>';
							return $retour;
						}

					}else{
						$retour['error']++;
						$retour['message'] .= 'Error using function dol_mkdir with parameter: '.$upload_dir;
						return $retour;

					}

				}else{
					$errArray = error_get_last();
					$errString = implode(' - ',$errArray);

					$retour['error']++;
					$retour['message'] .= 'ERROR using function @fopen : </br></br>'.$errString;
					return $retour;

				}
			}
		}
	}else{
		$retour['error']++;
		$retour['message'] .= 'Empty object or list of url<br>';
		return $retour;
	}
	
	if($retour['error']){
		return $retour;

	}else{
		return $destfull;
		
	}
	
}


/**
 * Fill the per-invoice multicurrency arrays of a supplier payment, like core fourn/facture/paiement.php does.
 * PaiementFourn::create() reads them for each invoice in $paiement->amounts.
 *
 * @param PaiementFourn $paiement
 * @return void
 */
function setSupplierPaymentMulticurrency($paiement){
	global $db;

	$paiement->multicurrency_code = array();
	$paiement->multicurrency_tx = array();

	foreach($paiement->amounts as $key => $val){
		$tmpinvoice = new FactureFournisseur($db);
		$tmpinvoice->fetch($key);
		$paiement->multicurrency_code[$key] = $tmpinvoice->multicurrency_code;
		$paiement->multicurrency_tx[$key] = $tmpinvoice->multicurrency_tx;
	}
}
