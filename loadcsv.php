<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2019 		Florian DUFOURG			<florian.dufourg@hotmail.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       importfrombankcsv/importfrombankcsvindex.php
 *	\ingroup    importfrombankcsv
 *	\brief      Page to import CSV file to importfrombankcsv object
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once __DIR__ . '/lib/bankimportapi.lib.php';
dol_include_once('/bankimportapi/class/csvlines.class.php');

// NO WARNING
error_reporting(E_ERROR | E_PARSE);

/*
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

require_once __DIR__ . '/class/line.class.php';
require_once __DIR__ . '/lib/importfrombankcsv.lib.php';
*/
// Load translation files required by the page
$langs->loadLangs(array("bankimportapi@bankimportapi", "other"));

$bankId = GETPOST("id_bank", 'int');
$action= GETPOST('action','none');
$refimport= GETPOST('ref_import','none');

// Securite acces client
if(empty($bankId))accessforbidden('ID bank not defined');

if(!$user->rights->bankimportapi->mybankimports->read) accessforbidden();

$now=dol_now();


/*
 * Actions
 */
  
//***********************************************************************************************
//DELETE STATEMENT CSV
//***********************************************************************************************
if ($action == 'remove_statement_lines' && !empty($refimport))
{
	
	$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'bankimportapi_csvlines WHERE ref_import ="'.$refimport.'"';
	$resql = $db->query($sql);
	
	$db->free($resql);
	
	$lien = dol_buildpath("/bankimportapi/loadcsv.php", 1).'?id_bank='.$bankId;
	
	header("Location: ".$lien);
}
 

$colonneUtile = array();
$catLigne = array();
$valLigne = array();
$tabObject = array();

//print '<pre>'; print_r($action); print '</pre>';exit();

//***********************************************************************************************
//TRANSFERT CSV TO DATABASE
//***********************************************************************************************
if ($action == 'import' && $refimport){
	foreach($_POST as $key => $val)
	{
		if (substr($key, 0, 9) == "selectCol" && $val > 0){
			$colonneUtile[intval(substr($key, 9))] = $val;
		}
		
		if (substr($key, 0, 10) == "selectLine" && !empty($val)){
			$catLigne[intval(substr($key, 10))] = $val;
		}
		
		if (substr($key, 0, 3) == "val"){
			$tabNulLigneCol = explode('_',substr($key, 3));
			
			$valLigne[intval($tabNulLigneCol[1])][intval($tabNulLigneCol[0])] = $val;
		}	
	}
	

		
	$error = 0;
	
	$compteurColAmountCreditDebit = 0;
	$compteurColAmountCredit = 0;
	$compteurColAmountDebit = 0;
	$compteurColDateOp = 0;
	$compteurColDateVal = 0;
	$compteurColLabel = 0;
	$compteurColReference = 0;
	$compteurColPaymentMode = 0;
	$compteurColVat = 0;

	foreach($colonneUtile as $key => $val){
		if($val == 1) $compteurColAmountCreditDebit++;
		if($val ==  1) $compteurColAmountCredit++;
		if($val ==  2) $compteurColAmountDebit++;
		if($val ==  3) $compteurColDateOp++;
		if($val ==  4) $compteurColLabel++;
		if($val ==  5) $compteurColReference++;
		if($val == 6) $compteurColDateVal++;
		if($val ==  7) $compteurColPaymentMode++;
		if($val ==  8) $compteurColVat++;
	}

	if ($compteurColAmountCreditDebit == 0 && $compteurColAmountCredit == 0){
		$error++;
		setEventMessages('SelectAmountColumn', null, 'errors');
	}
	
	if ($compteurColAmountDebit == 0 && $conf->global->BANKIMPORTAPI_AMOUNT_FORMAT == '2cols_1credit_1debit'){
		$error++;
		setEventMessages('SelectAmountColumn', null, 'errors');
	}
	
	if ($compteurColDateOp == 0){
		$error++;
		setEventMessages('AtLeastOneColumnDateOp', null, 'errors');
	}
		
	if ($compteurColLabel == 0){
		$error++;
		setEventMessages('AtLeastOneColumnLabel', null, 'errors');
	}
	
	if ($compteurColAmountCreditDebit > 1 || $compteurColAmountCredit > 1 || $compteurColAmountDebit > 1){
		$error++;
		setEventMessages('NoMoreThan2ColumnAmount', null, 'errors');
	}
	
	if ($compteurColDateOp > 1){
		$error++;
		setEventMessages('NoMoreThan1ColumnDateOp', null, 'errors');
	}
	
	if ($compteurColDateVal > 1){
		$error++;
		setEventMessages('NoMoreThan1ColumnDateVal', null, 'errors');
	}
		
	if ($compteurColLabel > 1){
		$error++;
		setEventMessages('NoMoreThan1ColumnLabel', null, 'errors');
	}
	
	if ($compteurColReference > 1){
		$error++;
		setEventMessages('NoMoreThan1ColumnLabelBis', null, 'errors');
	}
	
	if ($compteurColVat > 1){
		$error++;
		setEventMessages('NoMoreThan1ColumnVat', null, 'errors');
	}
	
	if ($compteurColPaymentMode > 1){
		$error++;
		setEventMessages('NoMoreThan1ColumnPaymentMode', null, 'errors');
	}
	
	$i = 0;
	if (empty($error)){
		
		foreach($valLigne as $key => $val)
		{

			
			if (!empty($catLigne[$key])){
			
				$amount = 0;
				$label = '';
				$label_bis = '';
				$sign = '';
				$dateOp_fmt = '';
				$OperationType = '';
				$dateVal_fmt = '';
				$id_reglement = '';
				$vatAmount = '';
			
				foreach($val as $k => $v)
				{
					if ($colonneUtile[$k]>0){
								
						$v = trim($v);					
						
						//Amount Crédit
						if($colonneUtile[$k]==1 && $v){
							
							if(substr($v, 0, 1)=='-'){
								$v = substr($v, 1);
								$sign = 'debit';
							}else{
								$sign = 'credit';
							}
							
							$v = str_replace(',','.',$v);
							$v = str_replace(' ','',$v);
							
							if (is_numeric($v)){
								
								if($conf->global->BANKIMPORTAPI_AMOUNT_FORMAT == '2cols_1credit_1debit') $sign = 'credit';
								$amount = floatval($v);
							}else{
								$error++;
								setEventMessages('ErrorAmount',  ['line '.$key.'('.$v.')'], 'errors');
							}
						}
						
						//Amount Débit
						if($colonneUtile[$k]==2 && $v){
							
							if(substr($v, 0, 1)=='-'){
								$v = substr($v, 1);
								$sign = 'debit';
							}
							
							$v = str_replace(',','.',$v);
							$v = str_replace(' ','',$v);
							
							if (is_numeric($v)){
								$sign = 'debit';
								$amount = floatval($v);
							}else{
								$error++;
								setEventMessages('ErrorAmount',  ['line '.$key.'('.$v.')'], 'errors');
							}
						}
						
						
						
						//Date opération
						if($colonneUtile[$k]==3){
																					
							$dateConverted = convertDatePost($v,$conf->global->BANKIMPORTAPI_DATE_FORMAT,'Y-m-d H:i:s');					
							
							if (validateDate($dateConverted)){
								$dateOp_fmt = $dateConverted;
							}else{
								$error++;
								setEventMessages('DateOpNotDate',  ['line '.$key.'('.$v.')'], 'errors');
							}
						}
						
						//Label
						if($colonneUtile[$k]==4 && !empty($v)){

							$label = utf8_decode(substr($v,0,245));
							
							if($conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT == 'payment_mode_same_col_label'){
								
								$listPayment = json_decode($conf->global->BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB, true);
								
								foreach($listPayment as $val)
								{
									$sizePrefixe = strlen($val);
									
									if(substr($label, 0, $sizePrefixe)==$val){
										//$label = substr($label, $sizePrefixe);
										$OperationType = trim($val);
										
										$id_reglement = array_search($val, $listPayment);
									} 
								}								
							}
			
						}
						
						//Reference
						if($colonneUtile[$k]==5 && !empty($v)){
							$label_bis = utf8_decode($v);
						}
						
						
						//Date valeur
						if($colonneUtile[$k]==6 && !empty($v)){
							$dateConverted = convertDatePost($v,$conf->global->BANKIMPORTAPI_DATE_FORMAT,'Y-m-d H:i:s');					
							
							if (validateDate($dateConverted)){
								$dateVal_fmt = $dateConverted;
							}else{
								$error++;
								setEventMessages('DateValNotDate',  ['line '.$key.'('.$v.')'], 'errors');
							}
						}
						
						//PaymentMode
						if($colonneUtile[$k]==7){
							if($v){
								$OperationType = trim($v);
								$listPayment = json_decode($conf->global->BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB, true);
								
								
								$id_reglement = array_search($v, $listPayment);
							}
						}
						
						//VAT option
						if($colonneUtile[$k]==8){
							
							if(substr($v, 0, 1)=='-'){
								$v = substr($v, 1);
							}
							
							$v = str_replace(',','.',$v);
							$v = str_replace(' ','',$v);
							
							if($v == ""){
								$vatAmount = '';
							}elseif($v == "0"){
								$vatAmount = "0";							
							}elseif (is_numeric($v)){
								$vatAmount = floatval($v);
							}
						}						
					}
				}
				
				//print 'i: '.$i.' label_bis: '.$label_bis.'<br>';

				$tabObject[$i] = new csvLines($db);
				$tabObject[$i]->ref_import = 'i_'.$now;
				$tabObject[$i]->statement_name =  $refimport;
				$tabObject[$i]->emitted_at = $dateOp_fmt;
				$tabObject[$i]->settled_at =  $dateVal_fmt;
				$tabObject[$i]->label = $label;
				$tabObject[$i]->label_bis = $label_bis;
				$tabObject[$i]->amount = $amount;
				$tabObject[$i]->vat_amount = $vatAmount;
				$tabObject[$i]->side = $sign;
				$tabObject[$i]->operation_type = $OperationType;
				$tabObject[$i]->reglement_id = $id_reglement;
				$tabObject[$i]->fk_bank = $bankId;
				
				//print '$tabObject[$i]->vat_amount: '.$tabObject[$i]->vat_amount.'<br>';
				//var_dump($tabObject[$i]);

				$i++;
			}
		}
	}
	
	if(empty($error)){
		
		foreach($tabObject as $key => $val)
		{			
			$res = $val->create($user);
			
			if($res <0){
				$error++;
				setEventMessages($val->error, $val->errors, 'errors');
				break;
			}
		}
		if(empty($error)) setEventMessages($langs->trans("FileImported"), $msg, 'mesgs');
		//if(empty($error))header("Location: ".dol_buildpath('/custom/bankimportapi/bankimportapiindex.php', 1).'?id_bank='.$bankId);
	}
	
}



/*
 * View
 */

llxHeader("", $langs->trans("ModuleImportPageTitre"));

print load_fiche_titre($langs->trans("ModuleImportPageTitre"), '', 'importfrombankcsv.png@importfrombankcsv');

$import = $_POST["import"];

$showUploadInput = 1;


//***********************************************************************************************
//SHOW CSV TABLE
//***********************************************************************************************
if(!empty($import))
{

	$error = 0;
		
	//*************************
	//CHECK PARAMETER & UPDATE CONSTANTES***********
	//*************************
	foreach($_POST as $key => $value) {
		
		if($key == 'date_format' && $value != $conf->global->BANKIMPORTAPI_DATE_FORMAT){
			
			$result=dolibarr_set_const($db, 'BANKIMPORTAPI_DATE_FORMAT', $value, 'chaine', 0, '', $conf->entity);
			
			if ($result < 0){
				$error++;
				setEventMessages('ErrorFileWhileUpdatingConstante: BANKIMPORTAPI_DATE_FORMAT',  null, 'errors');
			}
			$dateFormat = $value;
			
		}else{
			$dateFormat = $conf->global->BANKIMPORTAPI_DATE_FORMAT;
		}
		
		if($key == 'amount_format' && $value != $conf->global->BANKIMPORTAPI_AMOUNT_FORMAT){
			
			$result=dolibarr_set_const($db, 'BANKIMPORTAPI_AMOUNT_FORMAT', $value, 'chaine', 0, '', $conf->entity);
			
			if ($result < 0){
				$error++;
				setEventMessages('ErrorFileWhileUpdatingConstante: BANKIMPORTAPI_AMOUNT_FORMAT',  null, 'errors');
			}
			$amountFormat = $value;
			
		}else{
			$amountFormat = $conf->global->BANKIMPORTAPI_AMOUNT_FORMAT;
		}
		
		if($key == 'payment_mode_format' && $value != $conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT){
			
			$result=dolibarr_set_const($db, 'BANKIMPORTAPI_PAYMENTMODE_FORMAT', $value, 'chaine', 0, '', $conf->entity);
			
			if ($result < 0){
				$error++;
				setEventMessages('ErrorFileWhileUpdatingConstante: BANKIMPORTAPI_PAYMENTMODE_FORMAT',  null, 'errors');
			}
			$paymentModeFormat = $value;
			
		}else{
			$paymentModeFormat = $conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT;
		}
		
		
		if(substr($key, 0, strlen('id_payment_mode')) == 'id_payment_mode') {
			$paymentModeTab[substr($key, strlen('id_payment_mode_'))] = $value;
		}
	}
	
	if (empty($error)) {
		
		$jsonPaymentModeTab = json_encode($paymentModeTab);
		
		if($jsonPaymentModeTab != $conf->global->BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB){
		
			$result=dolibarr_set_const($db, 'BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB', $jsonPaymentModeTab, 'chaine', 0, '', $conf->entity);
		
			if ($result < 0){
				$error++;
				setEventMessages('ErrorFileWhileUpdatingConstante: BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB',  null, 'errors');
			}
		}
	}
	
	
	//*************************
	//CHECK FILE***********
	//*************************
	$fileType = $_FILES["file"]["type"];
	$mimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');
	
	if (!in_array($fileType,$mimes)){
		$error++;
		setEventMessages('ErrorFileType',  null, 'errors');
	}
	
	if (!$error){
		//Open File
		$fileName = $_FILES["file"]["tmp_name"];
		$handle = fopen($fileName, "r");
	}

	if (!$handle){
		$error++;
		setEventMessages('ErrorFileWhileOpening',  null, 'errors');
	}
	
	//*************************
	//CREATE TABLE***********
	//*************************
	if (empty($error)) {
		
		$showUploadInput = 0;
		$tabCsv = array();
		$row = 0;
		
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
			
			$num = count($data);
			
			for ($col=0; $col < $num; $col++) {
				
				$tabCsv[$row][$col] = utf8_encode($data[$col]);
			}
			
			$row++;
		}
		fclose($handle);
		
		$nbRow = $row;
		$nbCol = $col+1;	
		
		//*************************
		//PRINT TABLE***********
		//*************************
		// Explication
		print '<table class="centpercent notopnoleftnoright" style="margin-bottom: 6px;">';
		print '<tbody><tr><td class="nobordernopadding valignmiddle"><div class="titre inline-block">';
		print $langs->trans("SelectLinesAndColumns");
		
		if($nbRow*$nbCol > 1000){
			print '<br><span class="fas fa-exclamation-triangle pictowarning pictowarning" style="" title="Retard"></span>';
			print $langs->trans("ToMuchRowAndColumnsPhpUsuallyAllow1000var");
			print ' : ';
			print $nbRow.' rows x '.$nbCol.' columns = '.$nbRow*$nbCol;
		}
		
		print '</div></td></tr></tbody>';
		print '</table>';
		
		//Tableau
		print '<div class="div-table-responsive">';
		print '<form class="form-horizontal" method="post">';

		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<table class="tagtable liste">';
		//print '<tbody>';
		
		//ENTETE
		print '<tr>';
		print '<td></td><td></td>';
		
		for ($i=0; $i < $nbCol; $i++) {
			
			print '<td class="col'.$i.'">';
			print '<select class="categorieColonne" name="selectCol'.$i.'">';
				print '<option value="0"></option>';
				if($amountFormat == '2cols_1credit_1debit'){		//2 colonnes
					print '<option class="AmountCredit" style="font-weight: bold;" value="1">'.$langs->trans("Credit").'</option>';
					print '<option class="AmountDebit" style="font-weight: bold;" value="2">'.$langs->trans("Debit").'</option>';
					
				}else{
					print '<option class="AmountCreditDebit" style="font-weight: bold;" value="1">'.$langs->trans("Amount").'</option>';
				}
				print '<option class="DateOpOption" style="font-weight: bold;" value="3">'.$langs->trans("DateOpOption").' ('.$dateFormat.')</option>';
				print '<option class="LabelOption" style="font-weight: bold;" value="4">'.$langs->trans("LabelOption").'</option>';
				
				
				print '<option class="referenceOption" value="5">'.$langs->trans("MoreLabelOption").'</option>';
				print '<option class="DateValueOption" value="6">'.$langs->trans("DateValueOption").' ('.$dateFormat.')</option>';
				if($paymentModeFormat == '1col_payment_mode'){
					print '<option class="paymentModeOption" value="7">'.$langs->trans("paymentModeOption").'</option>';
				}
				print '<option class="vatOption" value="8">'.$langs->trans("VATAmount").'</option>';
			print '</select>';
			print '</td>';
		}
		print '</tr>';
		
			
		//DATA TABLE
		foreach($tabCsv as $row=>$tabVal){
			
			print '<tr>';
			print '<td>'.$row.'</td>';
			
			print '<td class="col'.$row.'">';
			print'<input class="categorieLigne" name="selectLine'.$row.'" type="checkbox" checked>';
			print '</td>';
			
			foreach($tabCsv[$row] as $col=>$Val){
				print '<td class="col'.$col.'"><input type="hidden" name="val'.$col.'_'.$row.'" value="'.$Val.'">'.utf8_decode($Val).'</td>';
			}
			
			print '</tr>';
			
			if(intval($row)*intval($col) > 2400){
				setEventMessages('ErrorToMuchRowAndCols',  null, 'errors');
				break;
			}
		}

		//print '</tbody>';
		print '</table><br>';
		
		
		// Bouton importer
		print '<table">';
		print '<div class="inline-block">';
		print '<input type="text" name="ref_import" value="'.$langs->trans("Statement").'20YYMM">';
		print '</div>';
		print '<div class="inline-block">';
		print'<button class="button" type="submit" name="action" value="import">'.$langs->trans("Import").'</button>';
		print '</div>';
		print '</table>';
		
		
		print '</form>';
		print '</div>';
	}

}


//***********************************************************************************************
//SHOW IMPORT CONFIG & LIST OF IMPORT
//***********************************************************************************************
if ($showUploadInput && !$errorConfig){

	?>
	
	
	<div class="fichecenter">
		<div class="fichethirdleft">
			<div class="div-table-responsive-no-min">
				<table class="noborder centpercent">
					<tbody>					
						<form class="form-horizontal" method="post" name="uploadCSV" enctype="multipart/form-data">

							<?php print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">'; ?>

							<tr class="liste_titre">
								<th colspan="2"><b><?php print $langs->trans("NewImport"); ?></b></th>
							</tr>
						
						
							<tr>
								<td class="valignmiddle nowrap"><?php print $langs->trans("File"); ?></td>
								<td class="valignmiddle nowrap">
									<input type="file" name="file" id="file" accept=".csv">
									<img src="<?php print dol_buildpath('/theme/md/img/info.png',1); ?>" alt="" title="<?php print $langs->trans("DelimiteurPointVirgule"); ?>" class="hideonsmartphone">
								</td>
							</tr>
							
							<tr>
								<td class="valignmiddle nowrap"><?php print $langs->trans("DateFormat"); ?></td>
								<td class="valignmiddle nowrap">
									<select name="date_format">
										<option value="dd/mm/yyyy"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "dd/mm/yyyy")?' selected':'';?>>dd/mm/yyyy</option>
										<option value="dd-mm-yyyy"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "dd-mm-yyyy")?' selected':'';?>>dd-mm-yyyy</option>
										<option value="dd.mm.yyyy"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "dd.mm.yyyy")?' selected':'';?>>dd.mm.yyyy</option>
										<option value="dd.mm.yy"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "dd.mm.yy")?' selected':'';?>>dd.mm.yy</option>
										<option value="yyyy/mm/dd"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "yyyy/mm/dd")?' selected':'';?>>yyyy/mm/dd</option>
										<option value="yyyy-mm-dd"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "yyyy-mm-dd")?' selected':'';?>>yyyy-mm-dd</option>
										<option value="mm-dd-yy"<?php print (!empty($conf->global->BANKIMPORTAPI_DATE_FORMAT) && $conf->global->BANKIMPORTAPI_DATE_FORMAT == "mm-dd-yy")?' selected':'';?>>mm-dd-yy</option>

									</select>

								</td>
							</tr>
							
							<tr>
								<td class="valignmiddle nowrap"><?php print $langs->trans("AmountFormat"); ?></td>
								<td class="valignmiddle nowrap">
									<select name="amount_format">
										<option value="2cols_1credit_1debit"<?php print (!empty($conf->global->BANKIMPORTAPI_AMOUNT_FORMAT) && $conf->global->BANKIMPORTAPI_AMOUNT_FORMAT == "2cols_1credit_1debit")?' selected':'';?>><?php print $langs->trans("2cols_1credit_1debit"); ?></option>
										<option value="1col_with_sign"<?php print (!empty($conf->global->BANKIMPORTAPI_AMOUNT_FORMAT) && $conf->global->BANKIMPORTAPI_AMOUNT_FORMAT == "1col_with_sign")?' selected':'';?>><?php print $langs->trans("1col_with_sign"); ?></option>
									</select>
								</td>
							</tr>
							
							<tr>
								<td class="valignmiddle nowrap"><?php print $langs->trans("PaymentModeType"); ?></td>
								<td class="valignmiddle nowrap">
									<select name="payment_mode_format">
										<option value="noPaymentMode"<?php print (!empty($conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT) && $conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT == "noPaymentMode")?' selected':'';?>><?php print $langs->trans("noPaymentMode"); ?></option>
										<option value="1col_payment_mode"<?php print (!empty($conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT) && $conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT == "1col_payment_mode")?' selected':'';?>><?php print $langs->trans("1col_payment_mode"); ?></option>
										<option value="payment_mode_same_col_label"<?php print (!empty($conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT) && $conf->global->BANKIMPORTAPI_PAYMENTMODE_FORMAT == "payment_mode_same_col_label")?' selected':'';?>><?php print $langs->trans("payment_mode_same_col_label"); ?></option>
									</select>
								</td>
							</tr>
							
											
							<?php
							
								 $sql = "SELECT id, code, libelle as label, type, active";
								 $sql .= " FROM ".MAIN_DB_PREFIX."c_paiement";
								 $sql .= " WHERE entity IN (".getEntity('c_paiement').")";
								 $sql .= " AND active = 1";
								$resql=$db->query($sql);
								
								if(!empty($conf->global->BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB)){
									
									$listPayment = json_decode($conf->global->BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB, true);
								}
								
								if ($resql)
								{
									$num = $db->num_rows($resql);
									if ($num)
									{	
										for($i=0;$i<$num;$i++)
										{
											$obj = $db->fetch_object($resql);
											print'<tr>';
											print '<td class="valignmiddle nowrap">'.$langs->trans('Translation').': '.$langs->trans($obj->label).'</td>';
											print '<td class="valignmiddle nowrap">';
											
											if(is_array($listPayment)){
												$defautValue = (array_key_exists($obj->id,$listPayment))?$listPayment[$obj->id]:'';
											}else{
												$defautValue = '';
											}
											
											print '<input type="text" name="id_payment_mode_'.$obj->id.'" value="'.$defautValue.'">';
											print '<img src="'.dol_buildpath('/theme/md/img/info.png',1).'" alt="" title="'.$langs->trans("RelationBetweenCsvAndDolibarr").'" class="hideonsmartphone">';
											print '</td>';
											print'</tr>';
										}
									}
								}

							?>
								
							<tr>
								<td colspan="2" class="valignmiddle nowrap">
									<button type="submit" id="submit" name="import"	class="button reposition" value="done"><?php print $langs->trans("ImportCsv"); ?></button>
								</td>
							</tr>
								
							<div id="labelError"></div>
						</form>
					</tbody>
				</table>
			</div>
		</div>
		<div class="fichetwothirdright">
			<div class="ficheaddleft">
				<div class="div-table-responsive-no-min">
					<table class="noborder centpercent">
						<tbody>
							<tr class="liste_titre">
								<th colspan="4"><b><?php print $langs->trans("Imported"); ?></b></th>
							</tr>
							<tr class="liste_titre">
								<th><?php print $langs->trans("Name"); ?></th>

								<th><?php print $langs->trans("Credit"); ?></th>

								<th><?php print $langs->trans("Debit"); ?></th>
								
								<th><?php print $langs->trans("Action"); ?></th>
							</tr>
												
							<?php

							if($db->type == "mysqli"){
								$sql = 'SELECT MIN(rowid), MIN(ref_import) AS ref_import, MIN(statement_name) AS statement_name, COUNT(rowid) AS nb_element';
								$sql .= ', MIN(DATE_FORMAT(date_creation,"%d/%m/%Y %H:%i")) AS date_import';
								$sql .= ', SUM(IF(side = "credit", amount, 0)) AS total_credit';
								$sql .= ', SUM(IF(side = "debit", amount, 0)) AS total_debit';
								$sql .= ' FROM '.MAIN_DB_PREFIX.'bankimportapi_csvlines';
								$sql .= ' WHERE fk_bank = '.$bankId;
								$sql .= ' GROUP BY ref_import';

							}else{
								$sql = 'SELECT MIN(rowid), MIN(ref_import) AS ref_import, MIN(statement_name) AS statement_name, COUNT(rowid) AS nb_element';
								$sql .= ", MIN(DATE_FORMAT(date_creation,'%d/%m/%Y %H:%i')) AS date_import";
								$sql .= ", SUM(case when (side = 'credit') then amount else 0 end) AS total_credit";
								$sql .= ", SUM(case when (side = 'debit') then amount else 0 end) AS total_debit";
								$sql .= ' FROM '.MAIN_DB_PREFIX.'bankimportapi_csvlines';
								$sql .= ' WHERE fk_bank = '.$bankId;
								$sql .= ' GROUP BY ref_import';
							}



							$resql = $db->query($sql);
							$num =  $db->num_rows($resql);
							
							if (!is_numeric($num))
							{
								dol_print_error($db);
								exit;
							}
							
							$i = 0;

							while ($i < $num)
							{
								$obj = $db->fetch_object($resql);
								
								print '<tr class="oddeven">';
								print '<td class="valignmiddle nowrap">';
								print '<b>'.$obj->statement_name.'</b> ('.$obj->date_import.')';
								print '</td>';
								print '<td class="valignmiddle nowrap">';
								print round($obj->total_credit,2).' € ';
								print '</td>';
								
								print '<td class="valignmiddle nowrap">';
								print round($obj->total_debit,2).' € ';
								print '</td>';
								
								print '<td class="valignmiddle nowrap">';
									$lien = dol_buildpath("/bankimportapi/loadcsv.php", 1).'?id_bank='.$bankId.'&ref_import='.$obj->ref_import.'&action=remove_statement_lines';
									print '<a href="'.$lien.'" class="link_delete">';
									print '<span class="fas fa-trash marginleftonly valignmiddle" style=" color: #444;" title="'.$langs->trans("Delete").' '.$obj1->ref_import.'"></span></a>';
								print '</td>';

								print '</tr>';
								
								$i++;
							}
							
							
							?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	
	<?php
	
}




llxFooter();
$db->close();

		
?>
<script type="text/javascript">

var amountFormat = "<?php print $amountFormat; ?>";
var dateFormat = "<?php print $dateFormat; ?>";
var errorForm = 0;

initApparence();

function initApparence(){

	var tabOptionsToHide = new Array();

	$('.categorieColonne').each(function( index ) {
		var className = $(this).parent().attr('class');
		
		
		if($( this ).val() == 0){	
			$('.'+className).css('color', 'LightGrey');
		}else{
			$('.'+className).css('color', '');
			
			tabOptionsToHide.push($(this ).find(':selected').attr('class'));
		}
	});

	$( ".categorieLigne" ).each(function( index ) {
		if ($(this).is(":checked"))
		{
			$(this).parent().parent().css('color', '');
		}else{
			$(this).parent().parent().css('color', 'LightGrey');
		}
	});
	
	$('.AmountCredit').show();
	$('.AmountDebit').show();
	$('.AmountCreditDebit').show();
	$('.DateOpOption').show();
	$('.LabelOption').show();
	$('.referenceOption').show();
	$('.DateValueOption').show();
	$('.paymentModeOption').show();
	$('.vatOption').show();
		
	tabOptionsToHide.forEach((className) => {
		$('.'+className).hide();
	});
	
}


$( "button[value='import']" ).click(function() {
	
	var y=document.createElement('span');
	var statementName = $( "input[name='ref_import']" ).val();
	var lastCharStatement = statementName.substr(statementName.length - 6);
	
	if(dateFormat == "yyyy/mm/dd" || dateFormat == "yyyy-mm-dd"){
		$lenghtDateNumbers = 4;
	}else{
		$lenghtDateNumbers = 2;
	}
	
	if(dateFormat == "dd/mm/yyyy" || dateFormat == "yyyy/mm/dd"){
		$dateSeparator = '/';
	}else if(dateFormat == "dd.mm.yyyy" || dateFormat == "dd.mm.yy"){
		$dateSeparator = '.';
	}else{
		$dateSeparator = '-';
	}


	var fct = $('.categorieColonne').each(function( index ) {
		var className = $(this).parent().attr('class');
		
		if($( this ).val() == 3 || $( this ).val() == 6){
			
			var fct = $('.'+className).find('input[type=hidden]').each(function( index ) {
				
				if($(this).parent().parent().find('.categorieLigne').is(":checked")){
					
					if(isNaN($(this).val().substring(0,$lenghtDateNumbers))){
						alert('"'+$(this).val()+'" '+<?php print '"'.$langs->trans("DateNotValid").'"'?>);
						errorForm = 1;
						
						event.preventDefault();
						return false;
					}
					
					if($(this).val().substring($lenghtDateNumbers,$lenghtDateNumbers+1) != $dateSeparator){
						alert('"'+$(this).val()+'" '+<?php print '"'.$langs->trans("DateNotValid").'"'?>);
						errorForm = 1;
						
						event.preventDefault();
						return false;
					}
					
				}
			});
			
			if (!fct) {
				
				event.preventDefault();
				return false;
			}
		}	

		if($( this ).val() == 1 && amountFormat != '2cols_1credit_1debit'){
			
			var fct = $('.'+className).find('input[type=hidden]').each(function( index ) {
				
				if($(this).parent().parent().find('.categorieLigne').is(":checked")){
					
					if($(this).val() == ""){
						alert(<?php print '"'.$langs->trans("AmountNotEmpty").'"'?>);
						errorForm = 1;
						
						event.preventDefault();
						return false;
					}
					
				}
			});
			
			if (!fct) {
				
				event.preventDefault();
				return false;
			}
		}
		
		if($( this ).val() == 4){
			
			var fct = $('.'+className).find('input[type=hidden]').each(function( index ) {
				
				if($(this).parent().parent().find('.categorieLigne').is(":checked")){
					
					if($(this).val() == ""){
						alert(<?php print '"'.$langs->trans("LabelNotEmpty").'"'?>);
						errorForm = 1;
						
						event.preventDefault();
						return false;
					}
					
				}
			});
			
			if (!fct) {
				
				event.preventDefault();
				return false;
			}
		}
	});
	
	if (!fct) {
		
		event.preventDefault();
		return false;
	}
	
	if(errorForm){
		event.preventDefault();
		return false;
	}

	if(statementName == "" ){
		y.innerHTML=<?php print '"'.$langs->trans("statementNameEmpty").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}

	
	if(lastCharStatement == "20YYMM" ){
		y.innerHTML=<?php print '"'.$langs->trans("statementNamePersonalize").'"'?>;
		alert(y.innerHTML);

		event.preventDefault();
		return;
	}
	
	var compteurColAmountCreditDebit = 0;
	var compteurColAmountCredit = 0;
	var compteurColAmountDebit = 0;
	var compteurColDateOp = 0;
	var compteurColDateVal = 0;
	var compteurColLabel = 0;
	var compteurColReference = 0;
	var compteurColPaymentMode = 0;
	var compteurColVat = 0;

	$( ".categorieColonne" ).each(function( i ) {
		if($(this).val() == 1){compteurColAmountCreditDebit++;}
		if($(this).val() == 1){compteurColAmountCredit++;}
		if($(this).val() == 2){compteurColAmountDebit++;}
		if($(this).val() == 3){compteurColDateOp++;}
		if($(this).val() == 4){compteurColLabel++;}
		if($(this).val() == 5){compteurColReference++;}
		if($(this).val() == 6){compteurColDateVal++;}
		if($(this).val() == 7){compteurColPaymentMode++;}
		if($(this).val() == 8){compteurColVat++;}
	});
	
	if(compteurColAmountCreditDebit == 0 && compteurColAmountCredit == 0){
		
		y.innerHTML=<?php print '"'.$langs->trans("SelectColumnAmount").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(amountFormat == '2cols_1credit_1debit' && compteurColAmountDebit == 0){
		
		y.innerHTML=<?php print '"'.$langs->trans("SelectColumnAmount").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColDateOp == 0){
		
		y.innerHTML=<?php print '"'.$langs->trans("AtLeastOneColumnDateOp").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColLabel == 0){
		
		y.innerHTML=<?php print '"'.$langs->trans("AtLeastOneColumnLabel").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColAmount > 1){
		
		y.innerHTML=<?php print '"'.$langs->trans("NoMoreThan2ColumnAmount").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColDateOp > 1){
		
		y.innerHTML=<?php print '"'.$langs->trans("NoMoreThan1ColumnDateOp").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColDateVal > 1){
		
		y.innerHTML=<?php print '"'.$langs->trans("NoMoreThan1ColumnDateVal").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColLabel > 1){
		
		y.innerHTML=<?php print '"'.$langs->trans("NoMoreThan1ColumnLabel").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
	
	if(compteurColLabelSec > 1){
		
		y.innerHTML=<?php print '"'.$langs->trans("NoMoreThan1ColumnLabelBis").'"'?>;
		alert(y.innerHTML);
		
		event.preventDefault();
		return;
	}
		
});


$( ".categorieLigne" ).on( "change", initApparence );
$( ".categorieColonne" ).on( "change", initApparence );

//Eliminate import of line from database
$( ".link_delete" ).click(function() {
	
	var y=document.createElement('span');
	y.innerHTML=<?php print '"'.$langs->trans("SureToDelete").'"'?>;
	
	if (confirm(y.innerHTML)){
		return;
	}else{
		event.preventDefault();
	}
});
 


</script>
