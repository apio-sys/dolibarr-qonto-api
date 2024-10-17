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
<!--Form paiement note de frais-->
<!---------------------------------------------------------------->
<div id="dialog_p_expense_report" class="dialog_box" style="display:none;" title="<?php print $langs->trans("PayeExpenseReport"); ?>">

	<div style="vertical-align: middle">
		<div class="inline-block floatleft">
			<i style="color:Coral;" class='fas fa-utensils'></i>
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
		<input type="hidden" name="url_ajax" value="lib/ajax_import_p_expense_report.php">
	
		<tr class="mode_reglement" style="display: none;">
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("PaymentMode"); ?></span></td>
			<td><?php print $form->select_types_paiements(0, 'mode_reglement_id','',0,1,1); ?></td>
		</tr>
		
		<tr>
			<td class="titlefield"><span class="fieldrequired"><?php print $langs->trans("ExpenseReport"); ?></span></td>
			<td>
				<?php
				
				
				//$sql = "SELECT d.rowid, d.ref, d.fk_user_author, d.total_ht, d.total_tva, d.total_ttc, d.fk_statut as status, d.date_debut, d.date_fin, d.date_create, d.tms as date_modif, d.date_valid, d.date_approve, d.note_private, d.note_public, u.rowid as id_user, u.firstname, u.lastname, u.login, u.email, u.statut, u.photo";
				$sql = "SELECT d.rowid, d.ref, d.total_ttc, d.date_debut, d.date_fin, u.login";
				$sql .= " FROM llx_expensereport as d, llx_user as u";
				$sql .= " WHERE d.fk_user_author = u.rowid";
				$sql .= " AND d.entity IN (".getEntity('expensereport').")";
				$sql .= " AND d.fk_statut IN (5)";
				$sql .= " ORDER BY d.date_debut DESC";
				
				$resql = $db->query($sql);
				
				$nums = $db->num_rows($resq);
				
				print '<select class="flat" name="expense_report_id">';
				print '<option value="">&nbsp;</option>';
				
				$i=0;
				while ($i < $nums)
				{
					
					$obj = $db->fetch_object($resq);
					if (empty($obj)) break;		// Should not happen
					
					print '<option value="'.$obj->rowid.'">'.$obj->ref.' - '.$obj->login.' ('.$langs->trans("Amount").' : '.price($obj->total_ttc).')</option>';
					
				}
				print '</select>';
				?>
			</td>
		</tr>

	</table>
	
	<div id="payment_expense_form"></div>
	<br>
	<div class="inline-block divButAction">
		<button class="button ImportLine"><?php print $langs->trans("Import"); ?><i style="display: none;" class="fas fa-spinner fa-spin loading_spinner"></i></button>
	</div>

	<div class="errorText"></div>
</div>


<script>

//Open facture fournisseur dialog box
$( ".button_p_expense_report" ).click(function() {
	
	var idOfDialog = "#dialog_p_expense_report";
	
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
	
	
	$(idOfDialog).find('input[name="id_line"]').val(currentTransactionId);
	$(idOfDialog).find('input[name="amount"]').val(amount);
	
	$('.info-label').html(label_and_content);
	$('.info-date').text(dateEmittedFormated);

	var infoOpType = (operation_type)?' ('+operation_type+')':'';
	$('.info-montant').text(amount+' '+currency+infoOpType);
	$('.releve_nom').val(nom_releve);
	
	if(mode_reglement_id == "")	$('.mode_reglement').show();
	else 	$('.mode_reglement').hide();
	
	
	//remise à zero
	$(idOfDialog).find('select[name="expense_report_id"] option[value=""]').prop('selected', true);
	
});

</script>