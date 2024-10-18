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
<!--Form import loan-->
<!---------------------------------------------------------------->
<div id="dialog_p_loan" class="dialog_box" style="display:none;" title="<?php print $langs->trans("PayeLoan"); ?>">

	<div style="vertical-align: middle">
		<div class="inline-block floatleft">
			<i style="color:Coral;" class='fas fa-coins'></i>
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
		<input type="hidden" name="url_ajax" value="lib/ajax_import_p_loan.php">
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Loan"); ?></span></td>
			<td>
				<?php
				
				$sql2 = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."loan";
				$sql2 .= " WHERE active = 1";
				
				$resq2 = $db->query($sql2);
				$nums2 = $db->num_rows($resq2);
				
				print '<select class="flat" name="emprunt_id">';
				print '<option value="">&nbsp;</option>';
				
				$i=0;
				while ($i < $nums2)
				{
					
					$obj = $db->fetch_object($resq2);
					if (empty($obj)) break;		// Should not happen
					
					print '<option value="'.$obj->rowid.'">'.$obj->label.'</option>';
					
				}
				print '</select>';
				?>
			</td>
		</tr>
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("IncludingInterest"); ?></span></td>
			<td><input type="text" autocomplete="off" size="10" name="amount_interest" value="0"></td>
		</tr>
	
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("IncludingInsurance"); ?></span></td>
			<td><input type="text" autocomplete="off" size="10" name="amount_insurance" value="0"></td>
		</tr>
		
		<tr class="mode_reglement" style="display: none;">
			<td class="titlefield"><span><?php print $langs->trans("PaymentMode"); ?></span></td>
			<td><?php print $form->select_types_paiements(0, 'mode_reglement_id','',0,1,1); ?></td>
		</tr>
		
	</table>
	
	<br>
	<table>
		<tr>
			<td class="titlefield"><?php print $langs->trans("amountCapital"); ?></td>
			<td><span id="amount_capital"></span></td>
		</tr>
	</table>
	
	<br>
	<div class="inline-block divButAction">
		<button class="button ImportLine"><?php print $langs->trans("Import"); ?><i style="display: none;" class="fas fa-spinner fa-spin loading_spinner"></i></button>
	</div>

	<div class="errorText"></div>
</div>


<script>

$( ".button_p_loan" ).click(function() {
	
	var idOfDialog = "#dialog_p_loan";
	
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
	
	var mode_reglement_id = $(this).closest(".bankline").find('input[name="mode_reglement_id"]').val();
	var label_and_content = $(this).closest(".bankline").find('.label_attachements').clone();
	var nom_releve = $(this).closest(".bankline").find('input[name="nom_releve"]').val();
	
	var dateEmitted = new Date(currentTransactionLine['emitted_at']*1000);
	var dateEmittedFormated = convertDate(dateEmitted);


	$('.info-label').html(label_and_content);
	$('.info-date').text(dateEmittedFormated);

	var infoOpType = "";

	if(currentTransactionLine['operation_type'] == "card"){
		infoOpType = " (" + currentTransactionLine['operation_type'] + " xxxx xxxx xxxx " + currentTransactionLine['card_last_digits'] + ")";

	}else if(currentTransactionLine['operation_type']){
		infoOpType = " (" + currentTransactionLine['operation_type'] + ")";
	}

	var currency = (currentTransactionLine['currency'])?' '+currentTransactionLine['currency']:'';
	$('.info-montant').text(currentTransactionLine['amount']+currency+infoOpType);
	$('.releve_nom').val(nom_releve);

	if(mode_reglement_id == "")	$('.mode_reglement').show();
	else 	$('.mode_reglement').hide();
	
	$(idOfDialog).find('select[name="mode_reglement_id"] option[value=""]').prop('selected', true);

	var optionalRef = (currentTransactionLine['reference'] != null)?currentTransactionLine['reference']:'';


	//Remise à zero
	$(idOfDialog).find('select[name="emprunt_id"] option[value=""]').prop('selected', true);
	
	$(idOfDialog).find('input[name="amount_interest"]').val(0);
	
	$(idOfDialog).find('input[name="amount_insurance"]').val(0);
	
	$('#amount_capital').text(' '+currentTransactionLine['amount']);
	
	
	
});

//AutoUpdate capital
$('#dialog_p_loan').find('input[name="amount_interest"]').on('touchstart keyup',function() {

	var interest_amount = $(this).val();
	
	var amount = transactions[currentTransactionId].amount;
	
	var insurance_amount = $('#dialog_p_loan').find('input[name="amount_insurance"]').val();
	
	var capital = amount - interest_amount - insurance_amount;
	
	capital = capital.toFixed(2);
	
	$('#amount_capital').text(capital);

});

//AutoUpdate capital and insurance
$('#dialog_p_loan').find('input[name="amount_insurance"]').on('touchstart keyup',function() {
	
	var insurance_amount = $(this).val();
	
	var amount = transactions[currentTransactionId].amount;
	
	var interest_amount = $('#dialog_p_loan').find('input[name="amount_interest"]').val();
	
	var capital = amount - interest_amount - insurance_amount;
	
	capital = capital.toFixed(2);
	
	$('#amount_capital').text(capital);

});
</script>

<!-- END PHP TEMPLATE dialog_c_p_ffour.tpl.php -->
