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
 * \file    /bankimportapi/lib/ajax_import_ffour.php
 * \ingroup ban
 * \brief   Create provider invoice from Ajax
 */

define('NOCSRFCHECK', 1); //Allow ajax with same token

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory

dol_include_once('/loan/class/paymentloan.class.php');
dol_include_once('/bankimportapi/lib/bankimportapi.lib.php');

$langs->loadLangs(array("bankimportapi@bankimportapi", "other"));

$retour = array();
$retour['error'] = 0;
$retour['message'] = '';
$now = dol_now();


//-----------------------------------------------------------------
//GET DATAS FROM POST
//-----------------------------------------------------------------

$formTab = json_decode($_POST['formTab'], true);
$transactionLine = json_decode($_POST['transactionLine'], true);

$bankId = GETPOST('bank_id', 'int');
$bank_rappro = GETPOST('bank_rappro', 'int');

//echo json_encode($formTab);
//exit();

//-----------------------------------------------------------------
//PREPARE VARIABLES
//-----------------------------------------------------------------
$id_line = $transactionLine['transaction_id'];
$date_op = convertDatePost($transactionLine['emitted_at']);
if($date_op) $date_op_tms = strtotime($date_op);
$date_valeur = (empty($transactionLine['settled_at']))?$date_op:convertDatePost($transactionLine['settled_at']);
$label_banque = $transactionLine['label'];
$montant_ttc = $transactionLine['amount'];
$mode_reglement_id  = $transactionLine['mode_reglement_id'];

$emprunt_id = $formTab['emprunt_id'];
$amount_insurance = $formTab['amount_insurance'];
$amount_interest = $formTab['amount_interest'];

$period = (!empty($formTab['period']) && strlen($formTab['period']) == 10)?convertDatePost($formTab['period']):'';
$releve = $formTab['releve'];
$mode_reglement_id = (!empty($mode_reglement_id))?$mode_reglement_id:$formTab['mode_reglement_id'];


	
//-----------------------------------------------------------------
//CHECK DATAS
//-----------------------------------------------------------------
if (empty($bankId)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyBankid").'<br>';
}

if(!empty($bank_rappro) && empty($releve)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("ReleveNoot defined").'<br>';
}

if (empty($mode_reglement_id)){
	$retour['error']++;
	$retour['message'] = $langs->trans("fk_paiementNotDefined");
}

if (empty($id_line)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyRowid").'<br>';
}

if (!validateDate($date_valeur)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("DateBadFormat").'<br>';
}

if (!validateDate($date_op)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("DateBadFormat").'<br>';
}

$montant_ttc = floatval($montant_ttc);
if (empty($montant_ttc) || !is_double($montant_ttc) || $montant_ttc < 0){
	$retour['error']++;
	$retour['message'] .= $langs->trans("AmountNotValid").'<br>';
}




if (empty($emprunt_id) || $emprunt_id < 1){
	$retour['error']++;
	$retour['message'] .= $langs->trans("LoanNotValid");
}

if ($amount_interest != "" && $amount_interest != 0){
	$amount_interest = str_replace(',','.',$amount_interest);
	$amount_interest = floatval($amount_interest);
	if (empty($amount_interest) || !is_double($amount_interest) || $amount_interest < 0){
		$retour['error']++;
		$retour['message'] .= $langs->trans("InterestNotValid");
	}
}else{
	$amount_interest = 0;
}

if ($amount_insurance != "" && $amount_insurance != 0){
	$amount_insurance = str_replace(',','.',$amount_insurance);
	$amount_insurance = floatval($amount_insurance);
	if (empty($amount_insurance) || !is_double($amount_insurance) || $amount_insurance < 0){
		$retour['error']++;
		$retour['message'] .= $langs->trans("InsurancetNotValid");
	}
}else{
	$amount_insurance = 0;
}

$amount_capital = $montant_ttc - $amount_insurance - $amount_interest;

if($amount_capital < 0){
	$retour['error']++;
	$retour['message'] .= $langs->trans("InterestAndInsuranceValueTooHigh");
}


//-----------------------------------------------------------------
//CREATION
//-----------------------------------------------------------------
if (!$retour['error'])
{
	//----------------------------------------------------------------------------------------
	//Créer crédit
	//----------------------------------------------------------------------------------------
	// Create a line of payments
	$object = new PaymentLoan($db);
	$object->chid				= $emprunt_id;
	$object->datep 			= $date_op;
	$object->label             = 'Remboursement emprunt';
	$object->amount_capital	= $amount_capital;
	$object->amount_insurance	= $amount_insurance;
	$object->amount_interest	= $amount_interest;
	$object->paymenttype 		= $mode_reglement_id;
	
	$idloan = $object->create($user);
	if ($idloan < 0)
	{
		$retour['error']++;
		$retour['message'] .= $object->error;
	}
	
	if(!$error){
		$result = $object->addPaymentToBank($user, $emprunt_id, 'payment_loan', '(LoanPayment)', $bankId, '', '');
		if (! $result > 0)
		{
			$retour['error']++;
			$retour['message'] .= $object->error;
		}
		elseif(isset($line))
		{
			$line->fk_bank = $object->fk_bank;
			$line->update($user);
		}
	}
	
	//Get ID bank
	if (!$retour['error'])
	{
		$sql = "SELECT fk_bank FROM ".MAIN_DB_PREFIX."bank_url";
		$sql .= " WHERE url_id = ".$idloan;
		$sql .= ' AND type = "payment_loan"';
		
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		
		$idPayment = $obj->fk_bank;
	}
	
	//Rapprocher le relevé
	if(!$retour['error'] && $bank_rappro && $idPayment){
		
		$res=rapprocher($releve,$idPayment);
		
		if ($res['error'])
		{
			$retour['error']++;
			$retour['message'] .= $res['message'].'<br>';
		}
	}

	
	//Ajouter import_done == 1
	if(!$retour['error']){
		$ref_element = $idloan;
		$element_type = $object->element;
		
		$res = addImportedLine($id_line,$bankId,$element_type,$ref_element);
		
		if ($res['error'])
		{
			$retour['error']++;
			$retour['message'] .= $res['message'].'<br>';
		}else{
			$object->ref = $ref_element;
			$retour['message'] .= $object->getNomUrl(1);
			//$retour['message'] .= '<a class="link_forget" href="'.$_SERVER["PHP_SELF"].'?id_bank='.$bankId.'&action=forgetpaiement&transaction_id='.$id_line.'">';
			$retour['message'] .= '<a class="link_forget" href="'.dol_buildpath('/custom/bankimportapi/bankimportapiindex.php', 1).'?id_bank='.$bankId.'&action=forgetpaiement&transaction_id='.$id_line.'">';
			$retour['message'] .= '<i style="margin-left:10px;" title="'.$langs->trans("ForgetImport").'" class="fas fa-unlink"></i>';
			$retour['message'] .= '</a>';
		}
	}
}


echo json_encode($retour);