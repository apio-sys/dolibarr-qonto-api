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
<!--Form link-->
<!---------------------------------------------------------------->
<div id="dialog_link_manu" class="dialog_box" style="display:none;" title="<?php print $langs->trans("CreateManually"); ?>">

	<div style="vertical-align: middle">
		<div class="inline-block floatleft">
			<i style="color:Orange;" class='fas fa-link'></i>
			<div class="info-label">Error</div>
			<div class="info-date">Error</div>
			<div class="info-montant">Error</div>
		</div>

	</div>
	<div class="underbanner clearboth"></div>
  
	<table>
		<input type="hidden" name="url_ajax" value="lib/ajax_link_manu.php">
	

		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("ExpenseReport"); ?> <i style="display: none;" class="fas fa-spinner fa-spin loading_spinner_2"></span></td>
			<td>
				<select class="flat" name="link_type">
				
				<?php
					print'<option value="no_link">'.$langs->trans("Nolink").'</option>';
					
					if(! empty($conf->don->enabled)) print'<option class="side_credit" value="payment_donation">'.$langs->trans("Donation").'</option>';
					if(! empty($conf->facture->enabled)) print'<option class="side_credit" value="payment">'.$langs->trans("paymentCustomer").'</option>';
					if(! empty($conf->facture->enabled)) print'<option class="side_debit" value="payment_supplier">'.$langs->trans("paymentSupplier").'</option>';
					if(! empty($conf->tax->enabled)) print'<option class="side_debit" value="tva">'.$langs->trans("Vat").'</option>';
					if(! empty($conf->tax->enabled)) print'<option class="side_debit" value="chargesociales">'.$langs->trans("chargesociales").'</option>';
					if(! empty($conf->salaries->enabled)) print'<option class="side_debit" value="payment_salary">'.$langs->trans("payment_salary").'</option>';
					if(! empty($conf->expensereport->enabled)) print'<option class="side_debit" value="payment_expensereport">'.$langs->trans("ExpenseReport").'</option>';
					if(! empty($conf->loan->enabled)) print'<option class="side_debit" value="payment_loan">'.$langs->trans("payment_loan").'</option>';
					
					print'<option class="side_debit" value="payment_various">'.$langs->trans("payment_various").'</option>';
				
				
				?>

				</select>

			</td>
		</tr>
		
		<tr id="payment_list_form"></tr>

	</table>
	
	
	<br>
	<div class="inline-block divButAction">
		<button class="button ImportLine" ><?php print $langs->trans("Link"); ?><i style="display: none;" class="fas fa-spinner fa-spin loading_spinner"></i></button>
	</div>

	<pre><div class="errorText"></div></pre>
</div>


<script>

//Open facture fournisseur dialog box
$( ".button_link_manu" ).click(function() {
	
	var idOfDialog = "#dialog_link_manu";
	
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
	var side = $(this).closest(".bankline").find('input[name="side"]').val();
	
	
	$(idOfDialog).find('input[name="id_line"]').val(currentTransactionId);
	$(idOfDialog).find('input[name="amount"]').val(amount);
	
	$('.info-label').html(label_and_content);
	$('.info-date').text(dateEmittedFormated);

	var infoOpType = (operation_type)?' ('+operation_type+')':'';
	$('.info-montant').text(amount+' '+currency+infoOpType);
	$('.releve_nom').val(nom_releve);
	
	if(mode_reglement_id == "")	$('.mode_reglement').show();
	else 	$('.mode_reglement').hide();
	
	if(side == "credit"){
		$('.side_credit').show();
		$('.side_debit').hide();
	}else{
		$('.side_credit').hide();
		$('.side_debit').show();
	}
	
	//remise à zero
	$(idOfDialog).find('select[name="link_type"] option[value="0"]').prop('selected', true);
	
});

//Get list of unpaid invoices
$('#dialog_link_manu').find('select[name="link_type"]').change(function() {
	
	var link_type = $(this).val();
	
	$( ".errorText" ).text('');
	$( "#payment_list_form" ).text('');
	
	if(link_type == 'no_link'){
		return;
	}
	
	//GET DATA OF LINE OF TRANSACTION
	var transactionLine = transactions[currentTransactionId];

	var jsonTransactionLine = JSON.stringify(transactionLine);

	//jsonTransactionLine.replace("'", '"');
	console.log(jsonTransactionLine);

	
	$.ajax({
		method: "POST",
		url : 'lib/ajax_get_payments_list.php',
		data: {
			link_type: link_type,
			transactionLine: jsonTransactionLine,
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
					$( ".errorText" ).append(data);
				}else{
				
					console.log(obj);
				
					if(obj.error){
						$( ".errorText" ).html(obj.message);
						$( ".ImportLine" ).hide();
						
					}else{
						$( "#payment_list_form" ).html(obj.message);
						$( ".ImportLine" ).show();
						$( "dialog_link_manu").show();
					}
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

</script>