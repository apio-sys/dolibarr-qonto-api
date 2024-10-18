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

<!-- BEGIN PHP TEMPLATE dialog_c_p_ffour.tpl.php -->

<!---------------------------------------------------------------->
<!--Form import facture fournisseur-->
<!---------------------------------------------------------------->
<div id="dialog_c_p_ffour" class="dialog_box" style="display:none;" title="<?php print $langs->trans("CreateAndPayeInvoice"); ?>">

	<div style="vertical-align: middle">
		<div class="inline-block floatleft">
			<i style="color:Blue;" class='fas fa-file-invoice'></i>
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
		<input type="hidden" name="url_ajax" value="lib/ajax_import_c_p_ffour.php">
		<input type="hidden" name="detail_type" value="free">
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("InvoiceRef"); ?></span></td>
			<td><input type="text" autocomplete="off" size="40" name="ref_element" value="0"></td>
		</tr>
		
		<tr>
			<td class="titlefield"><span><?php print $langs->trans("Label"); ?></span></td>
			<td><input type="text" autocomplete="off" size="40" name="label" value="0"></td>
		</tr>
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Date"); ?></span></td>
			<td><?php print $form->selectDate($date_op, 'datefacturation', 0, 0, 1, '', 1, 0); ?></td>
		</tr>
		
		<?php if($conf->projet->enabled){?>
		<tr>
			<td class="titlefield"><span><?php print $langs->trans("Project"); ?></span></td>
			<td>
				<?php 
					//print listSelectProject($db, $conf->global->BANKIMPORTAPI_ONLY_OPENED_PROJECTS, $conf->global->BANKIMPORTAPI_ID_PROJET_DEFAUT, 'minwidth200', 'projectid');
					$formproject->select_projects((empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS) ? $societe->id : -1), $conf->global->BANKIMPORTAPI_ID_PROJET_DEFAUT, 'projectid', 0, 0, 1, 1, 0, 0, 0, '', 0, 0, 'minwidth300 maxwidth500');
				?>
			</td>
		</tr>
		<?php }?>

		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Provider"); ?></span></td>
			<td><?php print $form->select_company('', 'socid', 's.fournisseur=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth200'); ?></td>
		</tr>
		
		<tr>
			<td class="titlefield tdtop"><span class="fieldrequired"><?php print $langs->trans("HTAmount"); ?></span></td>			
			<td><input size="10" type="text" autocomplete="off" name="montant_ht" value="" autofocus=""></td>
		</tr>
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("VATAmount"); ?></span></td>
			<td><input size="10" type="text" autocomplete="off" name="montant_tva" value="auto" autofocus=""><span style="color:grey" class="vat_rate_info"></span></td>
		</tr>
		
		<tr class="mode_reglement" style="display: none;">
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("PaymentMode"); ?></span></td>
			<td><?php print $form->select_types_paiements(0, 'mode_reglement_id','',0,1,1); ?></td>
		</tr>

		<tr id="detailLine" >
			<td class="titlefield">
				<span><?php print $langs->trans("DetailsLine"); ?></span>
				<i style="cursor: pointer;" id="showProductLine" title="see product/service" class="fas fa-sync-alt"></i>
			</td>
			<td>
				<input class="minwidth300" type="text" autocomplete="off" name="detail_achat" value="" autofocus="">
			</td>
		</tr>
		
		<tr id="productLine" style="display: none;">
			<td class="titlefield">
				<span><?php print $langs->trans("Product/Service"); ?></span>
				<i style="cursor: pointer;" id="showDetailLine" title="see free detail line" class="fas fa-sync-alt"></i>
			</td>
			<td>
				<span class="prod_entry_mode_predef">
				<?php $form->select_produits(0, 'idprod', '', $conf->product->limit_size, '', 1, 2, '', 1, array(), '', '1', 0, 'minwidth300 maxwidth500', 0, '', ''); ?>
				</span>
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
$( ".button_c_p_ffour" ).click(function() {
	
	var idOfDialog = "#dialog_c_p_ffour";

	$(idOfDialog).dialog({
		autoOpen: true,
		maxWidth:700,
		maxHeight: 550,
		width: 700,
		height: 550,
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
	
	$('.info-label').html(label_and_content);
	$('.info-date').text(dateEmittedFormated);
	
	var infoOpType = "";

	if(currentTransactionLine['operation_type'] == "card"){
		infoOpType = " (" + currentTransactionLine['operation_type'] + " xxxx xxxx xxxx " + currentTransactionLine['card_last_digits'] + ")";

	}else if(currentTransactionLine['operation_type']){
		infoOpType = " (" + currentTransactionLine['operation_type'] + ")";
	}


	var infoOpType = "";

	if(operation_type == "card"){
		infoOpType = " (" + operation_type + " xxxx xxxx xxxx " + currentTransactionLine['card_last_digits'] + ")";

	}else if(currentTransactionLine['operation_type']){
		infoOpType = " (" + currentTransactionLine['operation_type'] + ")";
	}
	

	$('.info-montant').text(amount+' '+currency+infoOpType);
	$('.releve_nom').val(nom_releve);
	
	$(idOfDialog).find('input[name="ref_element"]').val(label+' '+reference);
	$(idOfDialog).find('input[name="label"]').val(label+' '+reference);
	$(idOfDialog).find('input[name="datefacturation"]').val(dateEmittedFormated);

	if(note == ""){
		$(idOfDialog).find('input[name="detail_achat"]').val(label+' '+reference);
	}else{
		$(idOfDialog).find('input[name="detail_achat"]').val(note);
	}
	
	
	if(mode_reglement_id == "")	$('.mode_reglement').show();
	else 	$('.mode_reglement').hide();
	
	
	//remise à zero
	$(idOfDialog).find('select[name="mode_reglement_id"] option[value=""]').prop('selected', true);
	$(idOfDialog).find('select[name="projectid"] option[value="<?php print $conf->global->BANKIMPORTAPI_ID_PROJET_DEFAUT; ?>"]').prop('selected', true);
	
	var textOfSelect = $(idOfDialog).find('select[name="projectid"]  option:selected').text();
	$('#select2-projectid-container').text(textOfSelect);
	$('#select2-projectid-container').prop('title', textOfSelect);

	$('#socid').find('option[value="-1"]').prop('selected', true);
	$('#select2-socid-container').text('');
	
	$('#idprod').find('option[value="0"]').prop('selected', true);
	$('#select2-idprod-container').text('');
	

	
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

//Auto fill HT input
$('#dialog_c_p_ffour').find('input[name="montant_ht"]').on('touchstart keyup',function() {
	
	var ht_amount = $(this).val();
	
	//var amount = $('#dialog_c_p_ffour').find('input[name="amount"]').val();
	
	var amount = transactions[currentTransactionId].amount;
	
	var vat_amount = amount - ht_amount;
	vat_amount = vat_amount.toFixed(2);
	
	vat_rate = (vat_amount / ht_amount)*100;
	vat_rate_text = vat_rate.toFixed(2)+' %';
	
	$('#dialog_c_p_ffour').find('input[name="montant_tva"]').val(vat_amount);
	
	$('.vat_rate_info').text(vat_rate_text);

});

//Auto fill TVA input

$('#dialog_c_p_ffour').find('input[name="montant_tva"]').on('touchstart keyup',function() {

	var vat_amount = $(this).val();
	
	var amount = transactions[currentTransactionId].amount;
	
	var ht_amount = amount - vat_amount;
	ht_amount = ht_amount.toFixed(2);
	
	vat_rate = (vat_amount / ht_amount)*100;
	vat_rate_text = vat_rate.toFixed(2)+' %';
	
	$('#dialog_c_p_ffour').find('input[name="montant_ht"]').val(ht_amount);
	
	$('.vat_rate_info').text(vat_rate_text);

});

//Show line or product
var showProductLineHideDetails = function() {
	
	$('#dialog_c_p_ffour').find('input[name="detail_type"]').val('product');
	
	$( "#detailLine" ).hide();
	
	$( "#productLine" ).show();
}

var showDetailsLineHideProduct = function() {
	
	$('#dialog_c_p_ffour').find('input[name="detail_type"]').val('free');
	
	$( "#detailLine" ).show();
	
	$( "#productLine" ).hide();
}

$( "#showProductLine" ).click(showProductLineHideDetails);
$( "#showDetailLine" ).click(showDetailsLineHideProduct);



</script>

<!-- END PHP TEMPLATE dialog_c_p_ffour.tpl.php -->
