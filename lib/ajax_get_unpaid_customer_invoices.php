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

dol_include_once('/compta/facture/class/facture.class.php');

$langs->loadLangs(array("bankimportapi@bankimportapi", "other", 'bills', 'banks', 'companies'));

$soc_id = GETPOST('soc_id', 'int');

if (empty($soc_id)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyBankid").'<br>';
}

$htmlList = '';


if(empty($retour['error'])){


	/*
	 * All unpayed supplier invoices
	 */
	$sql = 'SELECT f.rowid as facid, f.ref, f.total_ttc, f.multicurrency_code, f.multicurrency_total_ttc, f.type,';
	$sql .= ' f.datef as df, f.fk_soc as socid, f.date_lim_reglement as dlr';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'facture as f';
	$sql .= ' WHERE f.entity IN ('.getEntity('facture').')';
	$sql .= ' AND (f.fk_soc = '.$soc_id;
	// Can pay invoices of all child of myself
	if (!empty($conf->global->FACTURE_PAYMENTS_ON_SUBSIDIARY_COMPANIES)) {
		$sql .= ' OR f.fk_soc IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE parent = '.$facture->thirdparty->id.')';
	}
	$sql .= ') AND f.paye = 0';
	$sql .= ' AND f.fk_statut = 1'; // Statut=0 => not validated, Statut=2 => canceled
	$sql .= ' AND type IN (0,1,3,5)'; // Standard invoice, replacement, deposit, situation
	// Sort invoices by date and serial number: the older one comes first
	$sql .= ' ORDER BY f.datef ASC, f.ref ASC';

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		if ($num > 0)
		{			
			
			$arraytitle = $langs->trans('Invoice');
			$alreadypayedlabel = $langs->trans('Received');
			$multicurrencyalreadypayedlabel = $langs->trans('MulticurrencyReceived');
			$remaindertopay = $langs->trans('RemainderToTake');
			$multicurrencyremaindertopay = $langs->trans('MulticurrencyRemainderToTake');

			$i = 0;
			//$htmlList .='<tr><td colspan="3">';
			$htmlList .='<br>';
			$htmlList .='<table class="noborder centpercent">';

			$htmlList .='<tr class="liste_titre">';
			$htmlList .='<td>'.$arraytitle.'</td>';
			$htmlList .='<td class="center">'.$langs->trans('Date').'</td>';
			$htmlList .='<td class="center">'.$langs->trans('DateMaxPayment').'</td>';
			if (!empty($conf->multicurrency->enabled)) {
				$htmlList .='<td>'.$langs->trans('Currency').'</td>';
				$htmlList .='<td class="right">'.$langs->trans('MulticurrencyAmountTTC').'</td>';
				$htmlList .='<td class="right">'.$multicurrencyalreadypayedlabel.'</td>';
				$htmlList .='<td class="right">'.$multicurrencyremaindertopay.'</td>';
				$htmlList .='<td class="right">'.$langs->trans('MulticurrencyPaymentAmount').'</td>';
			}
			$htmlList .='<td class="right">'.$langs->trans('AmountTTC').'</td>';
			$htmlList .='<td class="right">'.$alreadypayedlabel.'</td>';
			$htmlList .='<td class="right">'.$remaindertopay.'</td>';
			$htmlList .='<td class="right">'.$langs->trans('PaymentAmount').'</td>';

			$htmlList .='<td align="right">&nbsp;</td>';
			$htmlList .="</tr>\n";

			$total = 0;
			$totalrecu = 0;
			$totalrecucreditnote = 0;
			$totalrecudeposits = 0;

			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$sign = 1;

				$soc = new Societe($db);
				$soc->fetch($objp->socid);

				$invoice = new Facture($db);
				$invoice->fetch($objp->facid);
				$paiement = $invoice->getSommePaiement();
				$creditnotes = $invoice->getSumCreditNotesUsed();
				$deposits = $invoice->getSumDepositsUsed();
				$alreadypayed = price2num($paiement + $creditnotes + $deposits, 'MT');
				$remaintopay = price2num($invoice->total_ttc - $paiement - $creditnotes - $deposits, 'MT');

				// Multicurrency Price
				if (!empty($conf->multicurrency->enabled)) {
					$multicurrency_payment = $invoice->getSommePaiement(1);
					$multicurrency_creditnotes = $invoice->getSumCreditNotesUsed(1);
					$multicurrency_deposits = $invoice->getSumDepositsUsed(1);
					$multicurrency_alreadypayed = price2num($multicurrency_payment + $multicurrency_creditnotes + $multicurrency_deposits, 'MT');
					$multicurrency_remaintopay = price2num($invoice->multicurrency_total_ttc - $multicurrency_payment - $multicurrency_creditnotes - $multicurrency_deposits, 'MT');
				}


				$htmlList .='<tr class="oddeven'.(($invoice->id == $facid) ? ' highlight' : '').'">';

				$htmlList .='<td class="nowraponall">';
				$htmlList .=$invoice->getNomUrl(1, '');
				if ($objp->socid != $facture->thirdparty->id) $htmlList .=' - '.$soc->getNomUrl(1).' ';
				$htmlList .="</td>\n";

				// Date
				$htmlList .='<td class="center">'.dol_print_date($db->jdate($objp->df), 'day')."</td>\n";

				// Due date
				if ($objp->dlr > 0)
				{
					$htmlList .='<td class="nowraponall center">';
					$htmlList .=dol_print_date($db->jdate($objp->dlr), 'day');

					if ($invoice->hasDelay())
					{
						$htmlList .=img_warning($langs->trans('Late'));
					}

					$htmlList .='</td>';
				}
				else
				{
					$htmlList .='<td align="center"></td>';
				}

				// Currency
				if (!empty($conf->multicurrency->enabled)) $htmlList .='<td class="center">'.$objp->multicurrency_code."</td>\n";

				// Multicurrency Price
				if (!empty($conf->multicurrency->enabled))
				{
					$htmlList .='<td class="right">';
					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency) $htmlList .=price($sign * $objp->multicurrency_total_ttc);
					$htmlList .='</td>';

					// Multicurrency Price
					$htmlList .='<td class="right">';
					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency)
					{
						$htmlList .=price($sign * $multicurrency_payment);
						if ($multicurrency_creditnotes) $htmlList .='+'.price($multicurrency_creditnotes);
						if ($multicurrency_deposits) $htmlList .='+'.price($multicurrency_deposits);
					}
					$htmlList .='</td>';

					// Multicurrency remain to pay
					$htmlList .='<td class="right">';
					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency) $htmlList .=price($sign * $multicurrency_remaintopay);
					$htmlList .='</td>';

					$htmlList .='<td class="right nowraponall">';

					// Add remind multicurrency amount
					$namef = 'multicurrency_amount_'.$objp->facid;
					$nameRemain = 'multicurrency_remain_'.$objp->facid;

					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency)
					{
						if ($action != 'add_paiement')
						{
							if (!empty($conf->use_javascript_ajax))
								$htmlList .=img_picto("Auto fill", 'rightarrow', "class='AutoFillAmout' data-rowname='".$namef."' data-value='".($sign * $multicurrency_remaintopay)."'");
							$htmlList .='<input type="text" class="maxwidth75 multicurrency_amount" name="'.$namef.'" value="'.$_POST[$namef].'">';
							$htmlList .='<input type="hidden" class="multicurrency_remain" name="'.$nameRemain.'" value="'.$multicurrency_remaintopay.'">';
						}
						else
						{
							$htmlList .='<input type="text" class="maxwidth75" name="'.$namef.'_disabled" value="'.$_POST[$namef].'" disabled>';
							$htmlList .='<input type="hidden" name="'.$namef.'" value="'.$_POST[$namef].'">';
						}
					}
					$htmlList .="</td>";
				}

				// Price
				$htmlList .='<td class="right">'.price($sign * $objp->total_ttc).'</td>';

				// Received or paid back
				$htmlList .='<td class="right">'.price($sign * $paiement);
				if ($creditnotes) $htmlList .='+'.price($creditnotes);
				if ($deposits) $htmlList .='+'.price($deposits);
				$htmlList .='</td>';

				// Remain to take or to pay back
				$htmlList .='<td class="right">'.price($sign * $remaintopay).'</td>';
				//$test= price(price2num($objp->total_ttc - $paiement - $creditnotes - $deposits));

				// Amount
				$htmlList .='<td class="right nowraponall">';

				// Add remind amount
				$namef = 'amount_'.$objp->facid;
				$nameRemain = 'remain_'.$objp->facid;

				if ($action != 'add_paiement')
				{
					if (!empty($conf->use_javascript_ajax))
						$htmlList .=img_picto("Auto fill", 'rightarrow', "class='AutoFillAmout' data-rowname='".$namef."' data-value='".($sign * $remaintopay)."'");
					$htmlList .='<input type="text" class="maxwidth75 amount" name="'.$namef.'" value="'.dol_escape_htmltag(GETPOST($namef)).'">';
					$htmlList .='<input type="hidden" class="remain" name="'.$nameRemain.'" value="'.$remaintopay.'">';
				}
				else
				{
					$htmlList .='<input type="text" class="maxwidth75" name="'.$namef.'_disabled" value="'.dol_escape_htmltag(GETPOST($namef)).'" disabled>';
					$htmlList .='<input type="hidden" name="'.$namef.'" value="'.dol_escape_htmltag(GETPOST($namef)).'">';
				}
				$htmlList .="</td>";

				$parameters = array();
				$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $objp, $action); // Note that $action and $object may have been modified by hook

				// Warning
				$htmlList .='<td align="center" width="16">';
				//$htmlList .="xx".$amounts[$invoice->id]."-".$amountsresttopay[$invoice->id]."<br>";
				if ($amounts[$invoice->id] && (abs($amounts[$invoice->id]) > abs($amountsresttopay[$invoice->id]))
					|| $multicurrency_amounts[$invoice->id] && (abs($multicurrency_amounts[$invoice->id]) > abs($multicurrency_amountsresttopay[$invoice->id])))
				{
					$htmlList .=' '.img_warning($langs->trans("PaymentHigherThanReminderToPay"));
				}
				$htmlList .='</td>';

				$htmlList .="</tr>\n";

				$total += $objp->total;
				$total_ttc += $objp->total_ttc;
				$totalrecu += $paiement;
				$totalrecucreditnote += $creditnotes;
				$totalrecudeposits += $deposits;
				$i++;
			}

			if ($i > 1)
			{
				// $htmlList .=total
				$htmlList .='<tr class="liste_total">';
				$htmlList .='<td colspan="3" class="left">'.$langs->trans('TotalTTC').'</td>';
				if (!empty($conf->multicurrency->enabled)) {
					$htmlList .='<td></td>';
					$htmlList .='<td></td>';
					$htmlList .='<td></td>';
					$htmlList .='<td></td>';
					$htmlList .='<td class="right" id="multicurrency_result" style="font-weight: bold;"></td>';
				}
				$htmlList .='<td class="right"><b>'.price($sign * $total_ttc).'</b></td>';
				$htmlList .='<td class="right"><b>'.price($sign * $totalrecu);
				if ($totalrecucreditnote) $htmlList .='+'.price($totalrecucreditnote);
				if ($totalrecudeposits) $htmlList .='+'.price($totalrecudeposits);
				$htmlList .='</b></td>';
				$htmlList .='<td class="right"><b>'.price($sign * price2num($total_ttc - $totalrecu - $totalrecucreditnote - $totalrecudeposits, 'MT')).'</b></td>';
				$htmlList .='<td class="right" id="result_customer_invoice" style="font-weight: bold;"></td>'; // Autofilled
				$htmlList .='<td align="center">&nbsp;</td>';
				$htmlList .="</tr>\n";
			}
			$htmlList .="</table>";
			//$htmlList .="</td></tr>\n";
		}else{
			$retour['error']++;
			$retour['message'] .= $langs->trans("NoUnpaidCustomerInvoice").'<br>';
		}
	}else{
		$retour['error']++;
		$retour['message'] .= 'Error SQL<br>';
	}
}


if(empty($retour['error'])){
	$retour['message'] = $htmlList;
	
	echo json_encode($retour);
}else{
	echo json_encode($retour);
}



