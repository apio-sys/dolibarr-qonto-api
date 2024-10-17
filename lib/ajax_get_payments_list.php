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

define('NOCSRFCHECK', 1); //Allow ajax with same token

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory

dol_include_once('/bankimportapi/lib/bankimportapi.lib.php');

$langs->loadLangs(array("bankimportapi@bankimportapi", "other", 'bills', 'banks', 'companies'));


$link_type = GETPOST('link_type', 'alpha');

$transactionLine = json_decode($_POST['transactionLine'], true);

//-----------------------------------------------------------------
//PREPARE VARIABLES
//-----------------------------------------------------------------

$date_op = convertDatePost($transactionLine['emitted_at']);
if($date_op) $date_op_tms = strtotime($date_op);
$date_valeur = (empty($transactionLine['settled_at']))?$date_op:convertDatePost($transactionLine['settled_at']);
$label_banque = $transactionLine['label'];
$montant_ttc = $transactionLine['amount'];



$retour['error'] = 0;
//-----------------------------------------------------------------
//CHECK DATAS
//-----------------------------------------------------------------

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

if (empty($link_type)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyLinkType").'<br>';
}




if(empty($retour['error'])){
	
	$nbJour = 31;
	$dateRequest = $date_op_tms - (60*60*24*$nbJour);

/*
	$retour['error']++;
	$retour['message'] .= $dateRequest;
	echo json_encode($retour);
	return;
*/
	if($link_type == 'payment_supplier'){
		
		$sql = 'SELECT rowid, ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'paiementfourn';
		$sql .= ' WHERE entity = '.$conf->entity;
		$sql .= ' AND amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';

	}elseif($link_type == 'tva'){
		
		$sql = 'SELECT rowid, label as ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'tva';
		$sql .= ' WHERE entity = '.$conf->entity;
		$sql .= ' AND amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';

	}elseif($link_type == 'chargesociales'){	

		$sql = 'SELECT rowid, CONCAT("id: ",rowid) as ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'paiementcharge';
		$sql .= ' WHERE amount = '.$montant_ttc;
		$sql .= ' AND datep >= "'.strtotime($dateRequest).'"';

	}elseif($link_type == 'payment_loan'){	

		$sql = 'SELECT rowid, CONCAT("id: ",rowid) as ref, amount_capital+amount_insurance+amount_interest as amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'payment_loan';
		$sql .= ' WHERE amount_capital+amount_insurance+amount_interest = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';

	}elseif($link_type == 'payment'){	

		$sql = 'SELECT rowid, ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'paiement';
		$sql .= ' WHERE entity = '.$conf->entity;
		$sql .= ' AND amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';

	}elseif($link_type == 'payment_expensereport'){			
		
		$sql = 'SELECT rowid, CONCAT("id: ",rowid) as ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'payment_expensereport';
		$sql .= ' WHERE amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';
		
	}elseif($link_type == 'payment_donation'){			
		
		$sql = 'SELECT rowid, CONCAT("id: ",rowid) as ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'payment_donation';
		$sql .= ' WHERE amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';
		
	}elseif($link_type == 'payment_salary'){			
		
		$sql = 'SELECT rowid, CONCAT("id: ",rowid) as ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'payment_salary';
		$sql .= ' WHERE amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';
		
	}elseif($link_type == 'payment_various'){			
		
		$sql = 'SELECT rowid, label as ref, amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'payment_various';
		$sql .= ' WHERE amount = '.$montant_ttc;
		$sql .= ' AND datep > "'.strtotime($dateRequest).'"';
		
	}else{
		$retour['error']++;
		$retour['message'] .= $langs->trans("PaymentTypeNotDefine").'<br>';
	}
	

	if(empty($retour['error'])){
		$resql = $db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			
			$htmlList .= '<td class="titlefield"><span class="fieldrequired">'.$langs->trans("Payment").'</span></td>';
			
			if ($num == 1){
				
				$obj = $db->fetch_object($resql);
				
				$htmlList .= '<td>';
				$htmlList .= '<input type="hidden" name="payment_id" value="'.$obj->rowid.'">';
				$htmlList .= '<input type="text" size="50" title="id:'.$obj->rowid.'" value="'.$obj->ref.' ('.price($obj->amount).')" disabled>';
				$htmlList .= '</td>';
				
			}elseif($num > 1){
				
				$htmlList .= '<td>';
				$htmlList .= '<select class="flat" name="payment_id">';
				
				$i=0;
				while ($i < $num)
				{
					
					$obj = $db->fetch_object($resql);
					
					$htmlList .= '<option title="id:'.$obj->rowid.'" value="'.$obj->rowid.'">'.$obj->ref.' ('.price($obj->amount).')</option>';
					
					$i++;
				}
				
				$htmlList .= '</select>';
				$htmlList .= '</td>';
				
			}else{
				$retour['error']++;
				$retour['message'] .= $langs->trans("NoPaymentFound").'<br>';
			}
		}else{
			print_r($db);
			return;
		}
	}
}


if(empty($retour['error'])){
	$retour['message'] = $htmlList;
	
	echo json_encode($retour);
}else{
	echo json_encode($retour);
}



