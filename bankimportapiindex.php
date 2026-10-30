<?php
/* Copyright (C) 2019  Florian DUFOURG    <florian.dufourg@gnl-solutions.com>
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
 *	\file       htdocs/custom/bankimportapi/bankimportapiindex.php
 *	\brief      Main page of dolimport api
 *	\ingroup    bankimportapi
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

require_once __DIR__ . '/lib/bankimportapi.lib.php';
require_once __DIR__ . '/class/importedline.class.php';
require_once __DIR__ . '/class/bankapi.class.php';

// NO WARNING
error_reporting(E_ERROR | E_PARSE);


$bankId = GETPOST("id_bank", 'int');
$action	= GETPOST('action', 'aZ09');
$transaction_id = GETPOST('transaction_id');

if(empty($bankId))accessforbidden('ID bank not defined');

if(!$user->rights->bankimportapi->mybankimports->read) accessforbidden();

// Load translation files required by the page
$langs->loadLangs(array("bankimportapi@bankimportapi", "other"));

$form = new form($db);

/*
 * Action
 */
 
 //FORGET PAYMENT
// Deletes the "imported" mark, so the transaction can be imported again: it needs the write permission and the CSRF
// token, which the core does not require for a GET action with this name
if ($action == 'forgetpaiement' && !empty($transaction_id))
{
	if (empty($user->rights->bankimportapi->mybankimports->write) || GETPOST('token', 'alpha') !== currentToken()) {
		accessforbidden();
	}

	$sql = "DELETE FROM ".MAIN_DB_PREFIX."bankimportapi_importedline";
	$sql .= " WHERE ref = '".$db->escape($transaction_id)."' AND fk_bank = ".((int) $bankId);
	$db->query($sql);

	header("Location: ".$_SERVER['PHP_SELF']."?id_bank=".((int) $bankId));
	exit;
}

/*
*TABLEAU DES DATES -> PERIODE
*/

$dateselect=dol_mktime(0, 0, 0, GETPOST('dateselectmonth', 'int'), GETPOST('dateselectday', 'int'), GETPOST('dateselectyear', 'int'));
$datestart=dol_mktime(0, 0, 0, GETPOST('datestartmonth', 'int'), GETPOST('datestartday', 'int'), GETPOST('datestartyear', 'int'));
$dateend=dol_mktime(0, 0, 0, GETPOST('dateendmonth', 'int'), GETPOST('dateendday', 'int'), GETPOST('dateendyear', 'int'));


$i = 0;
$now = dol_now();

if($datestart>0){
	$tmstpStart = $datestart;
}else{
	
	
	if(empty($conf->global->BANKIMPORTAPI_PERIOD_SHOWED_DAYS)) $period = 45;
	else $period = $conf->global->BANKIMPORTAPI_PERIOD_SHOWED_DAYS;
	// From the start of the first day: dol_now() alone cut that day at the time the page was opened
	$tmparray = dol_getdate(dol_now()-(24*3600*$period));
	$tmstpStart = dol_mktime(0, 0, 0, $tmparray['mon'], $tmparray['mday'], $tmparray['year']);

}

if($dateend>0){
	// Qonto is asked until the end of the end date: 00:00 of that day left all its transactions out
	$tmstpEnd = dol_mktime(23, 59, 59, GETPOST('dateendmonth', 'int'), GETPOST('dateendday', 'int'), GETPOST('dateendyear', 'int'));
	$tmstpEndToShow = $dateend;
}
else{
	
	$tmstpEnd = dol_now();
	$tmstpEndToShow = $tmstpEnd;
}

$datestartfiltre=$db->idate($tmstpStart);
$dateendToShow=$db->idate($tmstpEndToShow);
 
 
//Obtenir les informations bancaire depuis API
$bankApiObject = new bankApi($db);

$bankApiObject = $bankApiObject->getBankDatas($bankId,$tmstpStart,$tmstpEnd);


//Charger la table des imports réalisés
$sql = "SELECT t.rowid, t.ref, t.fk_bank, t.import_type, t.date_creation, t.ref_element";
$sql .= ' FROM '.MAIN_DB_PREFIX.'bankimportapi_importedline as t';
$sql .= " WHERE t.fk_bank = ".$bankId;
//$sql .= " AND t.date_creation > FROM_UNIXTIME(".$bankApiObject->dateFromTmstp.")";
$sql .= " ORDER BY t.rowid ASC";
$resql=$db->query($sql);

if ($resql)
{
	$num = $db->num_rows($resql);
	if ($num)
	{	
		for($i=0;$i<$num;$i++)
		{
			$obj = $db->fetch_object($resql);
			
			$ImportedLines['rowid'][$i] = $obj->rowid;
			$ImportedLines['ref'][$i] = $obj->ref;
			$ImportedLines['import_type'][$i] = $obj->import_type;
			$ImportedLines['date_creation'][$i] = $obj->date_creation;
			$ImportedLines['ref_element'][$i] = $obj->ref_element;	
		}
	}else{
		$ImportedLines['ref'] = array();
	}
}



/*
 * View
 */
llxHeader('', 'BANK API', 'bank');
dol_fiche_head();

if($conf->global->BANKIMPORTAPI_DEV_MODE > 0){
	print 'DEBUG ACTIVE : '.$conf->global->BANKIMPORTAPI_DEV_MODE;
}

if($conf->global->BANKIMPORTAPI_DEV_MODE == 1){
	print '<pre>'.dol_escape_htmltag(print_r($bankApiObject->getDebugInfo(), true)).'</pre>';exit();
}


$bank_rappro = $bankApiObject->rappro;

// "Create and pay" actions can be switched off in the module setup (only pay/link existing elements)
$createAllowed = bankimportapiCreateAllowed();
?>

<table class="centpercent notopnoleftnoright table-fiche-title">
	<tbody>
		<tr>
			<td>
			
				<form method="POST" id="searchFormList" action="">
					<?php
					print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
					print $form->selectDate($datestartfiltre, 'datestart', 0, 0, 1, '', 1, 0);
					print $form->selectDate($dateendToShow, 'dateend', 0, 0, 1, '', 1, 0);
					?>
					<input type="submit" class="button" value="<?php print $langs->trans("show") ?>">
				</form>
			
			</td>
			
			<?php 
			if(!empty($bankApiObject->bankLabel)){
				print '<td class="valignmiddle">';
					print '<div class="titre">'.$langs->trans("Bank").' : '.$bankApiObject->bankLabel.'</div>';
				print '</td>';
			}

			if(!empty($bankApiObject->balance)){
				print '<td class="valignmiddle">';
					print '<div class="titre">'.$langs->trans("Balance").' : '.$bankApiObject->balance.'</div>';
				print '</td>';
			}


			if(!empty($bankApiObject->totalCredit)){
				print '<td class="valignmiddle">';
					print '<div class="titre">'.$langs->trans("Credit").' : '.$bankApiObject->totalCredit.'</div>';
					print '<div class="titre">'.$langs->trans("Debit").' : '.$bankApiObject->totalDebit.'</div>';
				print '</td>';
			}
			
			?>

			<td>
			<button class="button" style="float:right" id="button_toggle"><?php print $langs->trans("HideImported") ?></button>
			</td>
		</tr>
	</tbody>
</table>


<?php

if (!empty($bankApiObject->transactionsTruncated)) {
	print '<div class="warning">'.$langs->trans("TransactionsListTruncated", count($bankApiObject->transaction)).'</div>';
}

if(empty($bankApiObject->transaction)){
	
	if(	$bankApiObject->error ){
		
		print dol_escape_htmltag($bankApiObject->errorMsg);
		print '<br>';
		print '<br>';
	}else{
		print $langs->trans("NoTransactionInPeriod");
	}
	
}else
{

	?>

	<script>
		//Déclaration des variables globales
		var bankId = "<?php echo $bankId ?>";
		var bank_rappro = "<?php echo $bank_rappro ?>";
		var transactions = <?php echo json_encode($bankApiObject->transaction) ?>;

		// Date helpers shared by the dialogs (create and pay dialogs may not be included)
		//Add 0 if only 1 digit
		function pad(s) { return (s < 10) ? '0' + s : s; }

		//convert date to dd/mm/yyyy
		function convertDate(myDate) {
		  var d = myDate;
		  return [pad(d.getDate()), pad(d.getMonth()+1), d.getFullYear()].join('/')
		}
		var currentTransactionId = 0;
		var default_vat = parseFloat("<?php echo $conf->global->BANKIMPORTAPI_DEFAUT_VAT; ?>");
	</script>


	<div class="div-table-responsive">
		<table class="tagtable liste">
			<tbody>
				<tr class="liste_titre">
					
					<th class="liste_titre"><b><?php print $langs->trans("Label"); ?></b></th>
					<th class="liste_titre"><b><?php print $langs->trans("Reference"); ?></b></th>
					<th class="liste_titre"><b><?php print $langs->trans("Date"); ?></b></th>
					<th class="liste_titre"><b><?php print $langs->trans("Amount"); ?></b></th>
					<th class="liste_titre"><b><?php print $langs->trans("Action"); ?></b></th>
				</tr>
			
				<?php

				foreach($bankApiObject->transaction as $key=>$val){
					
					$key = array_search($val['transaction_id'], $ImportedLines['ref']);
					if ($key !== false && $num) $isImported = 1;
					else $isImported = 0;
					
					$dateEmittedFormated = dol_print_date($val['emitted_at'],'%d/%m/%Y');
					$dateSettledFormated = dol_print_date($val['settled_at'],'%d/%m/%Y');
					// The settlement date is the one that counts (before it, a transaction can still be cancelled) and the one
					// Qonto filters the period on. The bank line keeps the emission date as operation date.
					$settledAt = empty($val['settled_at']) ? $val['emitted_at'] : $val['settled_at'];
					
					if (empty($val['statement_name'])){
						$nom_releve = $bankApiObject->bankLabel.'_'.dol_print_date($settledAt,'%Y').'-'.dol_print_date($settledAt,'%m');
					}else{
						$nom_releve = $val['statement_name'];
					}
					
					if ($isImported) print '<tr style="color:LightGray;" class="oddeven bankline imported">';
					else print '<tr class="oddeven bankline">';
					
					print'<input type="hidden" name="transaction_id" value="'.$val['transaction_id'].'">';
					print'<input type="hidden" name="label" value="'.$val['label'].'">';
					print'<input type="hidden" name="reference" value="'.$val['reference'].'">';
					print'<input type="hidden" name="dateEmittedFormated" value="'.$dateEmittedFormated.'">';
					print'<input type="hidden" name="dateSettledFormated" value="'.$dateSettledFormated.'">';
					print'<input type="hidden" name="amount" value="'.$val['amount'].'">';
					print'<input type="hidden" name="operation_type" value="'.$val['operation_type'].'">';
					print'<input type="hidden" name="mode_reglement_id" value="'.$val['mode_reglement_id'].'">';
					print'<input type="hidden" name="currency" value="'.$val['currency'].'">';
					print'<input type="hidden" name="vat_amount" value="'.$val['vat_amount'].'">';
					print'<input type="hidden" name="nom_releve" value="'.$nom_releve.'">';
					print'<input type="hidden" name="side" value="'.$val['side'].'">';
					print'<input type="hidden" name="note" value="'.$val['note'].'">';
					
					print'<td class="label_attachements">';
					
						print $val['label'];
						
						if(!empty($val['attachment_ids'])){
							foreach($val['attachment_ids'] as $v){
								print '<i style="margin-left:5px;cursor: pointer;" value="'.$v.'" class="fas fa-paperclip"></i>';
							}
						}

						if(empty($conf->global->BANKIMPORTAPI_HIDE_VAT_WARNING)){
							if(!is_null($val['vat_amount'])){
								print '<i title="VAT : '.$val['vat_amount'].'" style="margin-left:5px;color:green;" class="fa fa-check-circle"></i>';
							}else{
								print '<i title="NO VAT" style="margin-left:5px;color:red;" class="fas fa-exclamation-triangle"></i>';
								print $val['vat_amount'];
							}
						}
					
					print'</td>';
					
					
					print'<td>';
					
						print $val['reference'];
					
					print'</td>';
					
					print'<td>'.dol_print_date($settledAt,'%d/%m/%Y').'</td>';
					
					if($val['side'] == 'credit'){
						print'<td><strong>+ '.$val['amount'].' '.$val['currency'].'</strong></td>';
					} 
					else{
						print'<td>- '.$val['amount'].' '.$val['currency'].'</td>';
					}
					
					
					print'<td class="actions">';
					
					if($isImported){
						
						print flo_getNomUrl($ImportedLines['ref_element'][$key],$ImportedLines['import_type'][$key]);
					
						
						if (!empty($user->rights->bankimportapi->mybankimports->write)) {
							print '<a class="link_forget" href="'.bankimportapiForgetUrl($bankId, $val['transaction_id']).'">';
							print'<i style="margin-left:10px;" title="'.$langs->trans("Unlink").'" class="fas fa-unlink"></i>';
							print '</a>';
						}
						

					}elseif($val['side'] == 'credit'){
						if($createAllowed){
							if(! empty($conf->facture->enabled)) print'<i style="margin:5px;cursor: pointer;color:blue;" title="'.$langs->trans("CreateAndPayeInvoice").'" class="fas fa-file-invoice-dollar button_c_p_fclient"></i>';
							if(! empty($conf->facture->enabled)) print'<i style="margin:5px;cursor: pointer;color:blue;" title="'.$langs->trans("CreateAndPayeCreditNote").'" class="fas fa-hand-holding-usd button_c_p_creditnote"></i>';
							print'<br>';
						}
						if(! empty($conf->facture->enabled)) print'<i style="margin:5px;cursor: pointer;color:Coral;" title="'.$langs->trans("PayeInvoice").'" class="fas fa-file-invoice-dollar button_p_fclient"></i>';
					
					}else{
						if($createAllowed){
							if(! empty($conf->facture->enabled)) print'<i style="margin:5px;cursor: pointer;color:blue;" title="'.$langs->trans("CreateAndPayeInvoice").'" class="fas fa-file-invoice-dollar button_c_p_ffour"></i>';
							if(! empty($conf->tax->enabled)) print'<i style="margin:5px;cursor: pointer;color:blue;" title="'.$langs->trans("CreateAndPayeVAT").'" class="fas fa-percent button_c_p_tax"></i>';
							if(! empty($conf->salaries->enabled)) print'<i style="margin:5px;cursor: pointer;color:blue;" title="'.$langs->trans("CreateAndPayeSalary").'" class="fas fa-users button_c_p_salary"></i>';
							if(! empty($conf->tax->enabled)) print'<i style="margin:5px;cursor: pointer;color:blue;" title="'.$langs->trans("CreateAndPayeCharge").'" class="fas fa-user-shield button_c_p_charge"></i>';
							print'<br>';
						}
						
						if(! empty($conf->facture->enabled)) print'<i style="margin:5px;cursor: pointer;color:Coral;" title="'.$langs->trans("PayeInvoice").'" class="fas fa-file-invoice-dollar button_p_ffour"></i>';
						if(! empty($conf->loan->enabled)) print'<i style="margin:5px;cursor: pointer;color:Coral;" title="'.$langs->trans("PayeLoan").'" class="fas fa-coins button_p_loan"></i>';
						if(! empty($conf->expensereport->enabled)) print'<i style="margin:5px;cursor: pointer;color:Coral;" title="'.$langs->trans("PayeExpenseReport").'" class="fas fa-utensils button_p_expense_report"></i>';

					}
							
					if(!$isImported){
						print'<br>';
						print'<i style="margin:5px;cursor: pointer;color:orange;" title="'.$langs->trans("CreateManually").'" class="fas fa-link button_link_manu"></i>';
					}


					print'</td>';
					
					
					print '</tr>';
				}

				?>		
				

			</tbody>
		</table>
	</div>

	<?php

	if (!empty($conf->projet->enabled)) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
		$formproject = new FormProjets($db);
	}

	if ($createAllowed) {
		include './tpl/dialog_c_p_ffour.tpl.php';
		include './tpl/dialog_c_p_creditnote.tpl.php';
		include './tpl/dialog_c_p_vat.tpl.php';
		include './tpl/dialog_c_p_salary.tpl.php';
		include './tpl/dialog_c_p_charge.tpl.php';
		include './tpl/dialog_c_p_fclient.tpl.php';
	}
	include './tpl/dialog_p_ffour.tpl.php';
	include './tpl/dialog_p_loan.tpl.php';
	include './tpl/dialog_p_fclient.tpl.php';
	include './tpl/dialog_p_expense_report.tpl.php';
	include './tpl/dialog_link_manu.tpl.php';


	?>


	<script>

	//Variable PHP
	// The AJAX endpoints don't renew the token, so they check the current one, as for the core AJAX calls
	var csrfToken = "<?php echo currentToken(); ?>";

	//Importer with AJAX
	$( ".ImportLine" ).click(function() {	

		// Disabled during the request: a double click sent the import twice (the server refuses it too)
		var importButton = $(this);

		//GET DATAS OF DIALOG INPUTS
		var formTab = {};

		$(this).parent().parent().find('input,select').each(function() {
			formTab[$(this).attr('name')] = $(this).val();
		});
		
		//GET DATA OF LINE OF TRANSACTION
		var transactionLine = transactions[currentTransactionId];
		
		//GET LINE IN DOM
		var lineTr = $(".tagtable").find('input[value="'+currentTransactionId+'"]').parent();
		
		console.log(transactionLine);
		console.log(formTab);
		
		$( ".errorText" ).text('');
		
		$.ajax({
			method: "POST",
			url : formTab['url_ajax'],
			data: {
				bank_id: bankId,
				bank_rappro: bank_rappro,
				formTab: JSON.stringify(formTab),
				transactionLine: JSON.stringify(transactionLine),
				token: csrfToken,
			},
			beforeSend: function(){
			 importButton.prop('disabled', true);
			 $(".loading_spinner").show();
			},
			complete: function(){
			 $(".loading_spinner").hide();
			 importButton.prop('disabled', false);
			},
			success:  function(data){
				if(data){
					
					try {
						var obj = JSON.parse(data);
						var isJson = true;
					} catch (e) {
						var isJson = false;
					}
					
					if (!isJson){
						$( ".errorText" ).append(data);
					}else{
					
						if(obj.error == 0){
							
							//$( ".errorText" ).append(obj.message);
							
							lineTr.css("color","LightGray");
							
							lineTr.find('.actions').html(obj.message);
							
							//lineTr.find('tr').hide();
							
							$('.dialog_box').dialog();
							
							$('.dialog_box').dialog('close');
							
						}else{
							$( ".errorText" ).append('<strong>ERROR : </strong>');
							$( ".errorText" ).append(obj.message);
							console.log(obj);
						}
					}
					
				}else{
					alert('No return');
				}
			},
			fail: function(){
				alert ('Ajax request fail');
			}
		});
	});

	//Get attachement URL and open file in tab
	var showAttachement = function() {
				
		$.post(
			'lib/ajax_get_attachement_url.php',
			{
				id_attachement: $(this).attr('value'),
				bankId: bankId,
				token: csrfToken,
			},

			function(data){
				if(data){
					
					console.log(data);
					
					try {
						var obj = JSON.parse(data);
						var isJson = true;
					} catch (e) {
						var isJson = false;
					}
					
					if (!isJson){
						alert(data);

					}else{
						console.log(obj);
						
						//var win = window.open(obj, '',"width=800,height=500,resizable=yes,scrollbars=yes,status=yes");
						var win = window.open(obj);
						return false;
					}
					
					
		
				}else{
					alert('No return');
				}
			},
			'text'
		);
	}

	$( ".fa-paperclip" ).click(showAttachement);
	$('.info-label').on('click', '.fa-paperclip', showAttachement);

	//Hide or show lines
	$( "#button_toggle" ).click(function() {
		
		if ($(".imported").is(':visible')) {
			$( ".imported" ).hide();
			$( "#button_toggle" ).text(<?php print '"'.$langs->trans("ShowImported").'"'?>);		 
		} else {
			$( ".imported" ).show(); 
			$( "#button_toggle" ).text(<?php print '"'.$langs->trans("HideImported").'"'?>);	
		}  
	});

	//Eliminate import of line from database
	$( ".link_forget" ).click(function() {
		
		var y=document.createElement('span');
		y.innerHTML=<?php print '"'.$langs->trans("SureToUnlink").'"'?>;
		
		if (confirm(y.innerHTML)){
			return;
		}else{
			event.preventDefault();
		}
	});


	</script>

<?php
}
// End of page
llxFooter();
$db->close();