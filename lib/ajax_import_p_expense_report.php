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
if (! $res) $res=@include("../../../../main.inc.php");		// For "custom" directory

dol_include_once('/expensereport/class/expensereport.class.php');
dol_include_once('/expensereport/class/paymentexpensereport.class.php');
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



$socid = $formTab['socid_ajax_client'];
$releve = $formTab['releve'];
$mode_reglement_id = (!empty($mode_reglement_id))?$mode_reglement_id:$formTab['mode_reglement_id'];

$expense_report_id = $formTab['expense_report_id'];
	
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

if (empty($expense_report_id)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyExpenseReportId").'<br>';
}

if (!$retour['error']){
	$expensereport = new ExpenseReport($db);
	$result = $expensereport->fetch($expense_report_id);
	if (! $result)
	{
		$retour['error']++;
		$retour['message'] .= $expensereport->error.'<br>';
	}
}

if ($expensereport->total_ttc != $montant_ttc){
	$retour['error']++;
	$retour['message'] .= $langs->trans("amountDoesNotFitWithExpenseReport").'<br>';
}



//-----------------------------------------------------------------
//CREATION
//-----------------------------------------------------------------
if (!$retour['error'])
{

	//----------------------------------------------------------------------------------------
	//Réglement de la facture
	//----------------------------------------------------------------------------------------
	
	$tab = array();
	$tab[$user->id] = $montant_ttc;
	
	$payment = new PaymentExpenseReport($db);
	$payment->fk_expensereport = $expensereport->id;
	$payment->datepaid       = $date_op;
	$payment->amounts        = $tab;   // Tableau de montant
	$payment->total          = $montant_ttc;
	$payment->fk_typepayment = $mode_reglement_id;
	$payment->fk_user_creat = $user->fk_user_creat;
	
	
	$idPaymentER = $payment->create($user);
	if ($idPaymentER < 0)
	{
		$retour['error']++;
		$retour['message'] .= $payment->error.'<br>';
	}
	
	if (!$retour['error'])
	{
		$result=$payment->addPaymentToBank($user, 'payment_expensereport', '(ExpenseReportPayment)', $bankId, '', '');
		if (! $result > 0)
		{
			$retour['error']++;
			$retour['message'] .= $payment->error.'<br>';
		}
	}
	
	if (!$retour['error'])
	{
		$payment->fetch($idPaymentER);
		if ($expensereport->total_ttc - $payment->amount == 0) {
			$result = $expensereport->set_paid($expensereport->id, $user);
			if (!$result > 0) {
				$retour['error']++;
				$retour['message'] .= $payment->error.'<br>';
			}
		}
	}
	

	//Get ID bank
	if (!$retour['error'])
	{
		$sql = "SELECT fk_bank FROM ".MAIN_DB_PREFIX."bank_url";
		$sql .= " WHERE url_id = ".$idPaymentER;
		$sql .= ' AND type = "payment_expensereport"';
		
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		
		$bank_line_id = $obj->fk_bank;
	}

	
	//Rapprocher le relevé
	if(!$retour['error'] && $bank_rappro){
		
		$res=rapprocher($releve,$bank_line_id);
		
		if ($res['error'])
		{
			$retour['error']++;
			$retour['message'] .= $res['message'].'<br>';
		}
	}

	
	//Ajouter import_done == 1
	if(!$retour['error']){
		$ref_element = $idPaymentER;
		$element_type = $payment->element;
		
		$res = addImportedLine($id_line,$bankId,$element_type,$ref_element);
		
		if ($res['error'])
		{
			$retour['error']++;
			$retour['message'] .= $res['message'].'<br>';
		}else{
			$retour['message'] .= $payment->getNomUrl(1);
			//$retour['message'] .= '<a class="link_forget" href="'.$_SERVER["PHP_SELF"].'?id_bank='.$bankId.'&action=forgetpaiement&transaction_id='.$id_line.'">';
			$retour['message'] .= '<a class="link_forget" href="'.dol_buildpath('/custom/bankimportapi/bankimportapiindex.php', 1).'?id_bank='.$bankId.'&action=forgetpaiement&transaction_id='.$id_line.'">';
			$retour['message'] .= '<i style="margin-left:10px;" title="'.$langs->trans("ForgetImport").'" class="fas fa-unlink"></i>';
			$retour['message'] .= '</a>';
		}
	}
}


echo json_encode($retour);