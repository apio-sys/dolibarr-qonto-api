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
<!--Form paiement facture fournisseur-->
<!---------------------------------------------------------------->
<div id="dialog_p_ffour" class="dialog_box" style="display:none;" title="<?php print $langs->trans("PayeInvoice"); ?>">

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
		<input type="hidden" name="url_ajax" value="lib/ajax_import_p_ffour.php">
	
		<tr class="mode_reglement" style="display: none;">
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("PaymentMode"); ?></span></td>
			<td><?php print $form->select_types_paiements(0, 'mode_reglement_id','',0,1,1); ?></td>
		</tr>

		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("Provider"); ?> <i style="display: none;" class="fas fa-spinner fa-spin loading_spinner_2"></span></td>
			<td><?php print $form->select_company('', 'socid_ajax_provider', 's.fournisseur=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth200'); ?></td>
		</tr>

	</table>
	
	<div id="payment_four_form"></div>
	<br>
	<div class="inline-block divButAction">
		<button class="button ImportLine"><?php print $langs->trans("Import"); ?><i style="display: none;" class="fas fa-spinner fa-spin loading_spinner"></i></button>
	</div>

	<div class="errorText"></div>
</div>


<script>

//Open facture fournisseur dialog box
$( ".button_p_ffour" ).click(function() {
	
	var idOfDialog = "#dialog_p_ffour";
	
	$(idOfDialog).dialog({
		autoOpen: true,
			maxWidth:1000,
			maxHeight: 500,
			width: 1000,
			height: 500,
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

	$('.info-montant').text(amount+' '+currency+infoOpType);
	$('.releve_nom').val(nom_releve);
	
	if(mode_reglement_id == "")	$('.mode_reglement').show();
	else 	$('.mode_reglement').hide();
	
	
	//remise à zero
	$(idOfDialog).find('select[name="mode_reglement_id"] option[value=""]').prop('selected', true);
	
	$( "#payment_four_form" ).html('');
	
	$('#socid_ajax_provider').find('option[value="-1"]').prop('selected', true);
	$('#select2-socid_ajax_provider-container').text('');
	
});

//Get list of unpaid invoices
$('#socid_ajax_provider').change(function() {
	
	var soc_id = $(this).val();
	
	//alert (soc_id);
	
	$.ajax({
		method: "GET",
		url : 'lib/ajax_get_unpaid_provider_invoices.php',
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
				
					$( "#payment_four_form" ).html(obj.message);
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

function callForFfourResult(imgId)
{
	var json = {};
	var form = $("#payment_four_form");

	json["invoice_type"] = $("#invoice_type").val();
	json["amountPayment"] = $("#amountpayment").attr("value");
	json["amounts"] = _elemToJson(form.find("input.amount"));
	json["remains"] = _elemToJson(form.find("input.remain"));

	if (imgId != null) {
		json["imgClicked"] = imgId;
	}

	$.post("<?php print dol_buildpath('/compta/ajaxpayment.php', 1); ?>", json, function(data)
	{
		json = $.parseJSON(data);

		form.data(json);

		for (var key in json)
		{
			if (key == "result")	{
				if (json["makeRed"]) {
					$("#result_provider_invoice").addClass("error");
				} else {
					$("#result_provider_invoice").removeClass("error");
				}
				json[key]=json["label"]+" "+json[key];
				$("#result_provider_invoice").text(json[key]);
			} else {console.log(key);
				form.find("input[name*=\""+key+"\"]").each(function() {
					$(this).attr("value", json[key]);
				});
			}
		}
	});
}

$("#payment_four_form").on('click touchstart','.AutoFillAmout', function(){
	
	var valueLine = $(this).data("value");
	
	var amount = transactions[currentTransactionId].amount;
	
	if(valueLine > amount){
		$("input[name="+$(this).data('rowname')+"]").val(amount);
	}else{
		$("input[name="+$(this).data('rowname')+"]").val(valueLine);
	}
	
	callForFfourResult();
});

$("#payment_four_form").on('touchstart keyup','input.amount', function(){
	callForFfourResult();
});


</script>

<!-- END PHP TEMPLATE dialog_c_p_ffour.tpl.php -->
