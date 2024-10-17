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

dol_include_once('/fourn/class/fournisseur.facture.class.php');
dol_include_once('/fourn/class/paiementfourn.class.php');
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



$socid = $formTab['socid_ajax_provider'];
$releve = $formTab['releve'];
$mode_reglement_id = (!empty($mode_reglement_id))?$mode_reglement_id:$formTab['mode_reglement_id'];

$totalPayment = 0;
$tab = array();

foreach($formTab as $key=>$val){
	
	if (substr($key, 0, 7) == 'amount_')
	{
		$cursorfacid = substr($key, 7);
		$amounts[$cursorfacid] = price2num(trim($val));
		
		
		if (!empty($amounts[$cursorfacid])) {
			$atleastonepaymentnotnull++;
			
			if (is_numeric($amounts[$cursorfacid])) {
				$totalPayment += $amounts[$cursorfacid];
				$tab[$cursorfacid] = $amounts[$cursorfacid];
			} else {
				$retour['error']++;
				$retour['message'] .= $langs->trans("AmountNotNumeric").'<br>';
			}
		}
	}
}

	
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

if ($socid < 1 || empty($socid)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("ProviderNotValid").'<br>';
}



if (empty($totalPayment) || strval($totalPayment) != strval($montant_ttc)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("AmountNotEqualAsInvoices");
	$retour['message'] .= '<br>totalPayment: ' . strval($totalPayment) . ' type: ' . gettype($totalPayment);
	$retour['message'] .= '<br>montant_ttc: ' . strval($montant_ttc) . ' type: ' . gettype($montant_ttc);
	//gettype
}


//-----------------------------------------------------------------
//CREATION
//-----------------------------------------------------------------
if (!$retour['error'])
{

	if($conf->global->BANKIMPORTAPI_DEV_MODE == 2){
		$retour['error']++;
	}

	//----------------------------------------------------------------------------------------
	//Transfert des fichiers
	//----------------------------------------------------------------------------------------
	if(!empty($conf->global->BANKIMPORTAPI_ALLOW_FILES_TRANSFERT)){

		if($conf->global->BANKIMPORTAPI_DEV_MODE == 2){
			$retour['message'] .= 'transfert file step1 </br>';
		}

		if(!empty($transactionLine['attachment_ids'])){

			if($conf->global->BANKIMPORTAPI_DEV_MODE == 2){
				$retour['message'] .= 'transfert file step2 </br>';
			}

			foreach($tab as $key => $val){
				$object=new FactureFournisseur($db);				
				$object->fetch($key);
		
				$listOfIdsAttachement = array();
				
				foreach($transactionLine['attachment_ids'] as $v){
					$listOfIdsAttachement[] = $v;
				}

				if($conf->global->BANKIMPORTAPI_DEV_MODE == 2){
					$retour['message'] .= 'transfert file step3 id:'.$key.' /bankid:'.$bankId.' /idAttach:'.implode("-",$listOfIdsAttachement).' </br>';
				}
				
				$res = transferFiles($bankId,$object,$listOfIdsAttachement);

				if(!empty($res['error'])){
					$retour['error']++;
					$retour['message'] .= $res['message'];
				}
			}
		}
	}

	//----------------------------------------------------------------------------------------
	//Réglement de la facture
	//----------------------------------------------------------------------------------------
	if(!$retour['error']){
		$paiement = new PaiementFourn($db);
		$paiement->datepaye     = $date_op_tms;
		$paiement->amounts      = $tab;   // Array of amounts
		$paiement->multicurrency_amounts = $tab;
		$paiement->paiementid   = $mode_reglement_id;
		
		$paiement_id = $paiement->create($user, 1);
		if ($paiement_id < 0)
		{
			$retour['error']++;
			$retour['message'] .= $paiement->error.'<br>';
		}
	}
	

	if(!$retour['error']){
		
		$paiement->fetch($paiement_id);
		$paiement->fetch_thirdparty();
		$bank_line_id=$paiement->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bankId, '', '');
		if ($bank_line_id < 0)
		{
			$retour['error']++;
			$retour['message'] .= $paiement->error.'<br>';
		}
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
		$ref_element = $paiement_id;
		$element_type = $paiement->element;
		
		$res = addImportedLine($id_line,$bankId,$element_type,$ref_element);
		
		if ($res['error'])
		{
			$retour['error']++;
			$retour['message'] .= $res['message'].'<br>';
		}else{
			$retour['message'] .= $paiement->getNomUrl(1);
			//$retour['message'] .= '<a class="link_forget" href="'.$_SERVER["PHP_SELF"].'?id_bank='.$bankId.'&action=forgetpaiement&transaction_id='.$id_line.'">';
			$retour['message'] .= '<a class="link_forget" href="'.dol_buildpath('/custom/bankimportapi/bankimportapiindex.php', 1).'?id_bank='.$bankId.'&action=forgetpaiement&transaction_id='.$id_line.'">';
			$retour['message'] .= '<i style="margin-left:10px;" title="'.$langs->trans("ForgetImport").'" class="fas fa-unlink"></i>';
			$retour['message'] .= '</a>';
		}
	}
}


echo json_encode($retour);