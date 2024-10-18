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


<!---------------------------------------------------------------->
<!--Form dialog_c_p_creditnote-->
<!---------------------------------------------------------------->
<div id="dialog_c_p_creditnote" class="dialog_box" style="display:none;" title="<?php print $langs->trans("CreateAndPayeCreditNote"); ?>">

	<div style="vertical-align: middle">
		<div class="inline-block floatleft">
			<i style="color:Coral;" class='fas fa-file-invoice'></i>
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
		<input type="hidden" name="url_ajax" value="lib/ajax_import_c_p_creditnote.php">
	
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Provider"); ?> <i style="display: none;" class="fas fa-spinner fa-spin loading_spinner_2"></span></td>
			<td><?php print $form->select_company('', 'socid_ajax_provider_invoices', 's.fournisseur=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth200'); ?></td>
		</tr>

	</table>

	</br>

	<table id="payment_creditnote_form"></table>

	<table>

		<tr class="ref_credit_note" style="display: none;">
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("InvoiceRef"); ?></span></td>
			<td><input type="text" autocomplete="off" size="40" name="ref_element" value="0"></td>
		</tr>

		<tr class="date_credit_note" style="display: none;">
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Date"); ?></span></td>
			<td><?php print $form->selectDate($date_op, 'datecreditnote', 0, 0, 1, '', 1, 0); ?></td>
		</tr>

		<?php if($conf->projet->enabled){?>
		<tr class="creditnote_project" style="display: none;">
			<td class="titlefield"><span><?php print $langs->trans("Project"); ?></span></td>
			<td>
				<?php 
					//print listSelectProject($db, $conf->global->BANKIMPORTAPI_ONLY_OPENED_PROJECTS, $conf->global->BANKIMPORTAPI_ID_PROJET_DEFAUT, 'minwidth200', 'projectid');
					$formproject->select_projects((empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS) ? $societe->id : -1), $conf->global->BANKIMPORTAPI_ID_PROJET_DEFAUT, 'projectid', 0, 0, 1, 1, 0, 0, 0, '', 0, 0, 'minwidth300 maxwidth500');
				?>
			</td>
		</tr>
		<?php }?>

		<tr class="montant_ht" style="display: none;">
			<td class="titlefield tdtop"><span class="fieldrequired"><?php print $langs->trans("HTAmount"); ?></span></td>			
			<td><input size="10" type="text" autocomplete="off" name="montant_ht" value="" autofocus=""></td>
		</tr>
		
		<tr class="montant_vat" style="display: none;">
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("VATAmount"); ?></span></td>
			<td><input size="10" type="text" autocomplete="off" name="montant_tva" value="auto" autofocus=""><span style="color:grey" class="vat_rate_info"></span></td>
		</tr>
		


		<tr class="detail_achat" style="display: none;">
			<td class="titlefield">
				<span><?php print $langs->trans("DetailsLine"); ?></span>
			</td>
			<td>
				<input class="minwidth300" type="text" autocomplete="off" name="detail_achat" value="" autofocus="">
			</td>
		</tr>
	</table>
	


	<br>
	<div class="inline-block divButAction">
		<button class="button ImportLine"><?php print $langs->trans("Import"); ?><i style="display: none;" class="fas fa-spinner fa-spin loading_spinner"></i></button>
	</div>

	<div class="errorText"></div>
</div>


<script>

//Open facture fournisseur dialog box
$( ".button_c_p_creditnote" ).click(function() {
	
	var idOfDialog = "#dialog_c_p_creditnote";
	
	$(idOfDialog).dialog({
		autoOpen: true,
			maxWidth:1000,
			maxHeight: 600,
			width: 1000,
			height: 600,
			modal: true,
	});
		
	$( ".errorText" ).text('');
		
	currentTransactionId = $(this).closest(".bankline").find('input[name="transaction_id"]').val();

	var currentTransactionLine = transactions[currentTransactionId];
	
	var label = $(this).closest(".bankline").find('input[name="label"]').val();
	var reference = $(this).closest(".bankline").find('input[name="reference"]').val();
	var dateEmittedFormated = $(this).closest(".bankline").find('input[name="dateEmittedFormated"]').val();
	var amount = $(this).closest(".bankline").find('input[name="amount"]').val();
	var currency = $(this).closest(".bankline").find('input[name="currency"]').val();
	var operation_type = $(this).closest(".bankline").find('input[name="operation_type"]').val();
	var mode_reglement_id = $(this).closest(".bankline").find('input[name="mode_reglement_id"]').val();
	var vat_amount = $(this).closest(".bankline").find('input[name="vat_amount"]').val();
	var label_and_content = $(this).closest(".bankline").find('.label_attachements').clone();
	var nom_releve = $(this).closest(".bankline").find('input[name="nom_releve"]').val();
	var note = $(this).closest(".bankline").find('input[name="note"]').val();
	
	
	$(idOfDialog).find('input[name="id_line"]').val(currentTransactionId);
	$(idOfDialog).find('input[name="amount"]').val(amount);
	$(idOfDialog).find('input[name="ref_element"]').val(label+' '+reference);
	$(idOfDialog).find('input[name="label"]').val(label+' '+reference);
	$(idOfDialog).find('input[name="datecreditnote"]').val(dateEmittedFormated);

	if(note == ""){
		$(idOfDialog).find('input[name="detail_achat"]').val(label+' '+reference);
	}else{
		$(idOfDialog).find('input[name="detail_achat"]').val(note);
	}
	
	$('.info-label').html(label_and_content);
	$('.info-date').text(dateEmittedFormated);

	var infoOpType = "";

	if(currentTransactionLine['operation_type'] == "card"){
		infoOpType = " (" + currentTransactionLine['operation_type'] + " xxxx xxxx xxxx " + currentTransactionLine['card_last_digits'] + ")";

	}else if(currentTransactionLine['operation_type']){
		infoOpType = " (" + currentTransactionLine['operation_type'] + ")";
	}

	$('.info-montant').text(amount+' '+currency+infoOpType);
	$('.releve_nom').val(nom_releve);
	
	if(mode_reglement_id == "")	$('.mode_reglement_creditnote').show();
	else 	$('.mode_reglement_creditnote').hide();
	
	
	//remise à zero
	$(idOfDialog).find('select[name="mode_reglement_creditnote_id"] option[value=""]').prop('selected', true);
	$(idOfDialog).find('select[name="projectid"] option[value="<?php print $conf->global->BANKIMPORTAPI_ID_PROJET_DEFAUT; ?>"]').prop('selected', true);
	
	var textOfSelect = $(idOfDialog).find('select[name="projectid"]  option:selected').text();
	$('#select2-projectid-container').text(textOfSelect);
	$('#select2-projectid-container').prop('title', textOfSelect);

	$('#socid').find('option[value="-1"]').prop('selected', true);
	$('#select2-socid-container').text('');
	$('#idprod').find('option[value="0"]').prop('selected', true);
	$('#select2-idprod-container').text('');
	
	$( "#payment_creditnote_form" ).html('');
	
	$('#socid_ajax_provider_invoices').find('option[value="-1"]').prop('selected', true);
	$('#select2-socid_ajax_provider_invoices-container').text('');

	$('.mode_reglement_creditnote').hide();
	$('.montant_ht').hide();
	$('.montant_vat').hide();
	$('.ref_credit_note').hide();
	$('.date_credit_note').hide();
	$('.detail_achat').hide();
	$('.creditnote_project').hide();
	

	//Calcul prix HT et TVA
	var vat_rate;
	var vat_rate_text;
	var ht_amount;
	
	if(vat_amount == ''){
		vat_rate = default_vat;
		ht_amount = (amount*100)/(100+vat_rate);
		ht_amount = ht_amount.toFixed(2);
		vat_amount = amount - ht_amount;
		vat_amount = vat_amount.toFixed(2);
		vat_rate_text = vat_rate+' % (default)';
	}else{
		ht_amount = amount - vat_amount;
		ht_amount = ht_amount.toFixed(2);
		vat_rate = (vat_amount / ht_amount)*100;
		vat_rate_text = vat_rate.toFixed(1)+' % (from QONTO)';
	}
	
	$(idOfDialog).find('input[name="montant_tva"]').val(vat_amount);
	
	$('.vat_rate_info').text(vat_rate_text);
	
	$(idOfDialog).find('input[name="montant_ht"]').val(ht_amount);
	
});

//Get list of invoices
$('#socid_ajax_provider_invoices').change(function() {
	
	var soc_id = $(this).val();
	
	//alert (soc_id);
	
	$.ajax({
		method: "GET",
		url : 'lib/ajax_get_provider_invoices.php',
		data: {
			soc_id: soc_id,
		},
		beforeSend: function(){
		 $(".loading_spinner_2").show();
		},
		complete: function(){
		 $(".loading_spinner_2").hide();
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
					$( ".errorText" ).append(obj.message);
				}else{
				
					$( "#payment_creditnote_form" ).html(obj.message);
					$('.mode_reglement_creditnote').show();
					$('.montant_ht').show();
					$('.montant_vat').show();
					$('.ref_credit_note').show();
					$('.date_credit_note').show();
					$('.detail_achat').show();
					$('.creditnote_project').show();
					
					
					
					//$( ".errorText" ).html(obj.message);
					//console.log(obj.message);
				}
				
			}else{
				$( ".errorText" ).append('No return');
			}
		},
		fail: function(){
			alert ('Ajax request fail');
		}
	});
});

function _elemToJson(selector)
{
	var subJson = {};
	$.map(selector.serializeArray(), function(n,i)
	{
		subJson[n["name"]] = n["value"];
	});

	return subJson;
}




</script>

<!-- END PHP TEMPLATE dialog_c_p_creditnote.tpl.php -->
