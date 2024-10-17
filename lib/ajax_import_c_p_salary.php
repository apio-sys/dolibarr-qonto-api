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
 * \file    /bankimportapi/lib/ajax_import_c_p_salary.php
 * \ingroup ban
 * \brief   Create provider salary from Ajax
 */

define('NOCSRFCHECK', 1); //Allow ajax with same token

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory

dol_include_once('/salaries/class/paymentsalary.class.php');
dol_include_once('/salaries/class/salary.class.php');
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

$ref_element = $formTab['ref_element'];
$projectid = (empty($conf->projet->enabled))?null:$formTab['projectid'];
$fk_user = $formTab['fk_user'];
$period_start_salary = (!empty($formTab['period_start_salary']) && strlen($formTab['period_start_salary']) == 10)?convertDatePost($formTab['period_start_salary']):'';
$period_end_salary = (!empty($formTab['period_end_salary']) && strlen($formTab['period_end_salary']) == 10)?convertDatePost($formTab['period_end_salary']):'';
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



if (empty($ref_element)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyRefElement").'<br>';
}

if (empty($fk_user) || $fk_user < 1){
	$retour['error']++;
	$retour['message'] .= $langs->trans("UserUndefine").'<br>';
}

if (!validateDate($period_start_salary)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("DateBadFormat").'<br>';
}

if (!validateDate($period_end_salary)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("DateBadFormat").'<br>';
}


//-----------------------------------------------------------------
//CREATION
//-----------------------------------------------------------------
if (!$retour['error'])
{
	//----------------------------------------------------------------------------------------
	//Créer salaire
	//----------------------------------------------------------------------------------------

	$object = new Salary($db);

	$type_payment = $mode_reglement_id;
	$object->amount=$montant_ttc;
	$object->accountid=$bankId;
	$object->fk_user=$fk_user;
	$object->datev=$date_valeur;
	$object->datep=$date_op;
	$object->label=$ref_element;
	$object->datesp=strtotime($period_start_salary);
	$object->dateep=strtotime($period_end_salary);
	$object->type_payment=$mode_reglement_id;

	$object->fk_user_author=$user->id;
	$object->fk_project= $projectid;

	// Set user current salary as ref salary for the payment
	$fuser = new User($db);
	$fuser->fetch(GETPOST("fk_user", "int"));
	$object->salary = $fuser->salary;



	$idSalary=$object->create($user);
	
	if (empty($idSalary) || $idSalary < 0)
	{
		$retour['error']++;
		$retour['message'] .= $object->error.'<br>';
	}
	
	if(!empty($idSalary) && !$retour['error']){

		$object->fetch($idSalary);

		$paiement = new PaymentSalary($db);
		$paiement->chid         = $idSalary;
		$paiement->datepaye     = $date_op;
		$paiement->datev		= $date_valeur;
		$paiement->amounts      = array($idSalary=>$montant_ttc); // Tableau de montant
		$paiement->paiementtype = $mode_reglement_id;

		$paymentid = $paiement->create($user, 1);

		if ($paymentid < 0) {
			$retour['error']++;
			$retour['message'] .= $paiement->error.'<br>';
		}

		if (!$retour['error']) {

			$paimentToBankRes = $paiement->addPaymentToBank($user, 'payment_salary', '(SalaryPayment)', $bankId, '', '');

			if (!($paimentToBankRes > 0)) {
				$retour['error']++;
				$retour['message'] .= $paiement->error.'<br>';
			}
		}

	}


	//----------------------------------------------------------------------------------------
	//Transfert des fichiers
	//----------------------------------------------------------------------------------------
	if(!empty($conf->global->BANKIMPORTAPI_ALLOW_FILES_TRANSFERT) && !$retour['error']){
		
		if(!empty($transactionLine['attachment_ids'])){
			$listOfIdsAttachement = array();
			
			foreach($transactionLine['attachment_ids'] as $v){
				$listOfIdsAttachement[] = $v;
			}

			$retFile = transferFiles($bankId,$object,$listOfIdsAttachement);
		}
	}
	
	if(!empty($retFile['error'])){
		$retour = $retFile;
	} 




	
	//Get ID bank
	if (!$retour['error'])
	{
		$sql = "SELECT fk_bank FROM ".MAIN_DB_PREFIX."bank_url";
		$sql .= " WHERE url_id = ".$paymentid;
		$sql .= ' AND type = "payment_salary"';
		
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
		$ref_element = $paymentid;
		$element_type = $paiement->element;
		
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