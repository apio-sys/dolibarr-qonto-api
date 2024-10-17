<?php
/* 
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Need to have following variables defined:
 * $bankobject
 */

// Protection to avoid direct call of template
if (empty($bankApiObject) || !is_object($bankApiObject))
{
	print "Error, template page can't be called as URL";
	exit;
}

?>

<!-- BEGIN PHP TEMPLATE dialog_c_p_vat.tpl.php -->

<!---------------------------------------------------------------->
<!--Form import facture fournisseur-->
<!---------------------------------------------------------------->
<div id="dialog_c_p_vat" class="dialog_box" style="display:none;" title="<?php print $langs->trans("CreateAndPayeVAT"); ?>">

	<div style="vertical-align: middle">
		<div class="inline-block floatleft">
			<i style="color:Blue;" class='fas fa-percent'></i>
			<div class="info-label">Error</div>
			<div class="info-date">Error</div>
			<div class="info-montant">Error</div>
		</div>
		
		<div class="pagination paginationref">
			<ul class="right">
			<?php 
			if($bank_rappro){
				print '<input class="minwidth100 releve_nom" type="text"  placeholder="'.$langs->trans("ReleveRef").'" autocomplete="off" name="releve" value="" autofocus="">';
			}
			?>
			</ul>
		</div>

	</div>
	
	<div class="underbanner clearboth"></div>
  
	<table>
		<input type="hidden" name="url_ajax" value="lib/ajax_import_c_p_vat.php">
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Ref"); ?></span></td>
			<td><input type="text" autocomplete="off" size="40" name="ref_element" value="0"></td>
		</tr>
	
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("PeriodEndDate"); ?></span></td>
			<td>
				<?php
				/*
				$dateTms = dol_stringtotime($date_op);
				if(date('n',$dateTms)==1){
					$year = date('Y',$dateTms)-1;
					$month = 12;
				}else{
					$year = date('Y',$dateTms);
					$month = date('n',$dateTms)-1;
				}
				
				$dateLastDay = dol_get_last_day($year, $month);
				*/
				print $form->selectDate($dateLastDay, 'period', 0, 0, 1, 'charge', 1, 0);
				?>
			</td>
		</tr>
		
		<tr class="mode_reglement" style="display: none;">
			<td class="titlefield"><span><?php print $langs->trans("PaymentMode"); ?></span></td>
			<td><?php print $form->select_types_paiements(0, 'mode_reglement_id','',0,1,1); ?></td>
		</tr>

	</table>
	<br>
	<div class="inline-block divButAction">
		<button class="button ImportLine"><?php print $langs->trans("Import"); ?><i style="display: none;" class="fas fa-spinner fa-spin loading_spinner"></i></button>
	</div>

	<div class="errorText"></div>
</div>


<script>

$( ".button_c_p_tax" ).click(function() {
	
	var idOfDialog = "#dialog_c_p_vat";
	
	$(idOfDialog).dialog({
		autoOpen: true,
			maxWidth:650,
			maxHeight: 450,
			width: 650,
			height: 450,
			modal: true,
	});
		
	$( ".errorText" ).text('');
		
	currentTransactionId = $(this).closest(".bankline").find('input[name="transaction_id"]').val();
	
	var currentTransactionLine = transactions[currentTransactionId];
	
	console.log(currentTransactionLine);
	
	var mode_reglement_id = $(this).closest(".bankline").find('input[name="mode_reglement_id"]').val();
	var label_and_content = $(this).closest(".bankline").find('.label_attachements').clone();
	var nom_releve = $(this).closest(".bankline").find('input[name="nom_releve"]').val();
	
	var dateEmitted = new Date(currentTransactionLine['emitted_at']*1000);
	var dateEmittedFormated = convertDate(dateEmitted);


	$('.info-label').html(label_and_content);
	$('.info-date').text(dateEmittedFormated);

	var infoOpType = (currentTransactionLine['operation_type'])?' ('+currentTransactionLine['operation_type']+')':'';
	var currency = (currentTransactionLine['currency'])?' '+currentTransactionLine['currency']:'';
	$('.info-montant').text(currentTransactionLine['amount']+currency+infoOpType);
	$('.releve_nom').val(nom_releve);

	if(mode_reglement_id == "")	$('.mode_reglement').show();
	else 	$('.mode_reglement').hide();
	
	$(idOfDialog).find('select[name="mode_reglement_id"] option[value=""]').prop('selected', true);

	var optionalRef = (currentTransactionLine['reference'] != null)?currentTransactionLine['reference']:'';
	
	$(idOfDialog).find('input[name="ref_element"]').val(currentTransactionLine['label']+' '+optionalRef);

	var lastDayOfMonth  = new Date(dateEmitted.getFullYear(),dateEmitted.getMonth(),0);
	
	lastDayOfMonth = convertDate(lastDayOfMonth);

	$(idOfDialog).find('input[name="period"]').val(lastDayOfMonth);
	
});

//Add 0 if only 1 digit
function pad(s) { return (s < 10) ? '0' + s : s; }

//convert date to dd/mm/yyyy
function convertDate(myDate) {
  var d = myDate;
  return [pad(d.getDate()), pad(d.getMonth()+1), d.getFullYear()].join('/')
}

</script>

<!-- END PHP TEMPLATE dialog_c_p_ffour.tpl.php -->
