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

$retour = array();
$retour['error'] = 0;
$retour['message'] = '';
$now = dol_now();

$langs->loadLangs(array("bankimportapi@bankimportapi", "other"));

//-----------------------------------------------------------------
//GET DATAS FROM POST
//-----------------------------------------------------------------

$formTab = json_decode($_POST['formTab'], true);
$transactionLine = json_decode($_POST['transactionLine'], true);
$bankId = GETPOST('bank_id', 'int');
$bank_rappro = GETPOST('bank_rappro', 'int');


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
$datefacturation = (!empty($formTab['datefacturation']) && strlen($formTab['datefacturation']) == 10)?convertDatePost($formTab['datefacturation']):'';
$projectid = (empty($conf->projet->enabled))?null:$formTab['projectid'];
$label = $formTab['label'];
$montant_ht = $formTab['montant_ht'];
$montant_tva = $formTab['montant_tva'];
$socid = $formTab['socid'];
$releve = $formTab['releve'];
$mode_reglement_id = (!empty($mode_reglement_id))?$mode_reglement_id:$formTab['mode_reglement_id'];


if($formTab['detail_type'] == "product"){
	$idprod = $formTab['idprod'];
	$detail_achat = '';
	
}else{
	$detail_achat = $formTab['detail_achat'];
	$idprod =  0;
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

if (!validateDate($datefacturation)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("DateBadFormat").'<br>';
}

if (empty($ref_element)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyRefElement").'<br>';
}

if(empty($idprod) && empty($detail_achat)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyidLineDetails").'<br>';
}

if ($socid < 1){
	$retour['error']++;
	$retour['message'] .= $langs->trans("ProviderNotValid").'<br>';
}

if ($montant_tva == "" || $montant_tva == 'auto'){
	$montant_ht = str_replace(',','.',$montant_ht);
	$montant_ht = floatval($montant_ht);
	$montant_ht = round($montant_ht,3);

	if (empty($montant_ht) || !is_double($montant_ht) || $montant_ht < 0){
		$retour['error']++;
		$retour['message'] .= $langs->trans("AmountNotValid").'<br>';
	}
	$montant_tva = $montant_ttc - $montant_ht;
}else{
	$montant_tva = str_replace(',','.',$montant_tva);
	$montant_tva = floatval($montant_tva);
	$montant_tva = round($montant_tva,3);

	if (!is_double($montant_tva) || $montant_tva < 0){
		$retour['error']++;
		$retour['message'] .= $langs->trans("AmountNotValid").'<br>';
	}
	$montant_ht = $montant_ttc - $montant_tva;
}

if($montant_ht > $montant_ttc){
	$retour['error']++;
	$retour['message'] .= $langs->trans("HTNotHigherThanTTC").'<br>';
}

if($montant_tva > $montant_ttc){
	$retour['error']++;
	$retour['message'] .= $langs->trans("TVANotHigherThanTTC").'<br>';
}

//-----------------------------------------------------------------
//CREATION
//-----------------------------------------------------------------
if (!$retour['error'])
{
	//----------------------------------------------------------------------------------------
	//Créer facture fournisseur
	//----------------------------------------------------------------------------------------
	$object=new FactureFournisseur($db);
	$msg = array();
	
	if($conf->global->BANKIMPORTAPI_GENERATE_UNIQUE_REF == 1){
		$ref_supplier = $ref_element.' (#'.substr($now,6).')';
	}else{
		$ref_supplier = $ref_element;
	}
	
	$object->ref_supplier  = $ref_supplier;
	$object->libelle = $label;
	
	$object->socid         = $socid;
	$object->date          = $datefacturation;
	$object->date_echeance = $datefacturation;

	$object->mode_reglement_id = $mode_reglement_id;
	$object->fk_project    = $projectid;
	$object->fk_account    = $bankId;

	$idFac = $object->create($user);
			
	if($idFac > 0){
		
		$desc = ($detail_achat)?$detail_achat:$ref_element;
		$pu = $montant_ttc;
		$tva_tx = round((($montant_tva)/$montant_ht)*100,1);
		$qty = 1;
		$type = 1;
		
		$result=$object->addline(
			$desc,
			$pu,
			$tva_tx,
			$localtax1_tx,
			$localtax2_tx,
			$qty,
			$idprod,
			$remise_percent,
			$date_start,
			$date_end,
			0,
			$tva_npr,
			$price_base_type,
			$type,
			-1,
			0,
			$array_options,
			$productsupplier->fk_unit,
			0,
			$productsupplier->fourn_multicurrency_unitprice,
			''
		);
		
		
		//----------------------------------------------------------------------------------------
		//Validation de la facture
		//----------------------------------------------------------------------------------------
		if($result > 0){
			
			$object->fetch($idFac);
			$object->fetch_thirdparty();
			
			$res = $object->validate($user,'',0);
			
			if($res <0){
				$retour['error']++;

				if($object->error && is_array($object->error)){
					$retour['message'] .= implode(' | ',$object->error) . "<br>";
				}else{
					$retour['message'] .= $object->error.'<br>';
				}

			}
		}else{
			$retour['error']++;

			if($object->error && is_array($object->error)){
				$retour['message'] .= implode(' | ',$object->error) . "<br>";
			}else{
				$retour['message'] .= $object->error.'<br>';
			}
		}
	}else{
		$retour['error']++;

		if($object->error && is_array($object->error)){
			$retour['message'] .= implode(' | ',$object->error) . "<br>";
		}else{
			$retour['message'] .= $object->error.'<br>';
		}
	}
	
	//----------------------------------------------------------------------------------------
	//Transfert des fichiers
	//----------------------------------------------------------------------------------------
	if(!empty($conf->global->BANKIMPORTAPI_ALLOW_FILES_TRANSFERT && !$retour['error'])){
		
		if(!empty($transactionLine['attachment_ids'])){
			$listOfIdsAttachement = array();
			
			foreach($transactionLine['attachment_ids'] as $v){
				$listOfIdsAttachement[] = $v;
			}
			
			$res = transferFiles($bankId,$object,$listOfIdsAttachement);

			if(!empty($res['error'])){
				$retour['error']++;
				$retour['message'] .= $res['message'];
			}
		}
	}

	//Delete invoice if file did not load
	if($retour['error']){
		$object->delete($user);
	}
		

	//----------------------------------------------------------------------------------------
	//Réglement de la facture
	//----------------------------------------------------------------------------------------
	if(!$retour['error']){

		$tab = array();
		$tab[$idFac] = $montant_ttc;
	
		$paiement = new PaiementFourn($db);
		$paiement->datepaye     = $date_op_tms;
		$paiement->amounts      = $tab;   // Array of amounts
		$paiement->multicurrency_amounts = $tab;
		$paiement->paiementid   = $mode_reglement_id;
		
		$paiement_id = $paiement->create($user, 1);
		if ($paiement_id < 0)
		{
			$retour['error']++;

			if($paiement->error && is_array($paiement->error)){
				$retour['message'] .= implode(' | ',$paiement->error) . "<br>";
			}else{
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

				if($paiement->error && is_array($paiement->error)){
					$retour['message'] .= implode(' | ',$paiement->error) . "<br>";
				}else{
					$retour['message'] .= $paiement->error.'<br>';
				}
			}
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
		
		if ($res['errorFile'])
		{
			$retour['error']++;
			$retour['message'] .= $res['message'].'<br>';
		}elseif ($res['error'])
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