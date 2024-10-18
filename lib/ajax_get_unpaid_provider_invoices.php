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

dol_include_once('/fourn/class/fournisseur.facture.class.php');

$langs->loadLangs(array("bankimportapi@bankimportapi", "other", 'bills', 'banks', 'companies'));

$soc_id = GETPOST('soc_id', 'int');

if (empty($soc_id)){
	$retour['error']++;
	$retour['message'] .= $langs->trans("EmptyBankid").'<br>';
}

$htmlList .= '';

if(empty($retour['error'])){


	/*
	 * All unpayed supplier invoices
	 */
	$sql = 'SELECT f.rowid as facid, f.ref, f.ref_supplier, f.type, f.total_ht, f.total_ttc,';
	$sql .= ' f.multicurrency_code, f.multicurrency_tx, f.multicurrency_total_ht, f.multicurrency_total_tva, f.multicurrency_total_ttc,';
	$sql .= ' f.datef as df, f.date_lim_reglement as dlr,';
	$sql .= ' SUM(pf.amount) as am, SUM(pf.multicurrency_amount) as multicurrency_am';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'facture_fourn as f';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'paiementfourn_facturefourn as pf ON pf.fk_facturefourn = f.rowid';
	$sql .= " WHERE f.entity = ".$conf->entity;
	$sql .= ' AND f.fk_soc = '.$soc_id;
	$sql .= ' AND f.paye = 0';
	$sql .= ' AND f.fk_statut = 1'; // Statut=0 => non validee, Statut=2 => annulee
	$sql .= ' AND f.type IN (0,1,3,5)'; // Standard invoice, replacement, deposit, situation


	// Group by because we have a total
	$sql .= ' GROUP BY f.datef, f.ref, f.ref_supplier, f.rowid, f.type, f.total_ht, f.total_ttc, f.multicurrency_total_ttc, f.datef, f.date_lim_reglement';
	// Sort invoices by date and serial number: the older one comes first
	$sql .= ' ORDER BY f.datef ASC, f.ref ASC';

	$resql = $db->query($sql);
	if ($resql)
	{
			
		
		$num = $db->num_rows($resql);
		if ($num > 0)
		{			
			
			$i = 0;
			$htmlList .= '<br>';

			$htmlList .= '<div class="div-table-responsive-no-min">';
			$htmlList .= '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

			$htmlList .= '<tr class="liste_titre">';
			$htmlList .= '<td>'.$langs->trans('Invoice').'</td>';
			$htmlList .= '<td>'.$langs->trans('RefSupplier').'</td>';
			$htmlList .= '<td class="center">'.$langs->trans('Date').'</td>';
			$htmlList .= '<td class="center">'.$langs->trans('DateMaxPayment').'</td>';
			if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td>'.$langs->trans('Currency').'</td>';
			if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td class="right">'.$langs->trans('MulticurrencyAmountTTC').'</td>';
			if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td class="right">'.$langs->trans('MulticurrencyAlreadyPaid').'</td>';
			if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td class="right">'.$langs->trans('MulticurrencyRemainderToPay').'</td>';
			$htmlList .= '<td class="right">'.$langs->trans('AmountTTC').'</td>';
			$htmlList .= '<td class="right">'.$langs->trans('AlreadyPaid').'</td>';
			$htmlList .= '<td class="right">'.$langs->trans('RemainderToPay').'</td>';
			$htmlList .= '<td class="center">'.$langs->trans('PaymentAmount').'</td>';
			if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td class="center">'.$langs->trans('MulticurrencyPaymentAmount').'</td>';
			$htmlList .= '</tr>';

			$total = 0;
			$total_ttc = 0;
			$totalrecu = 0;
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$sign = 1;
				if ($objp->type == FactureFournisseur::TYPE_CREDIT_NOTE) $sign = -1;

				$invoice = new FactureFournisseur($db);
				$invoice->fetch($objp->facid);

				//$invoicesupplierstatic->ref = $objp->ref;
				//$invoicesupplierstatic->id = $objp->facid;

				$paiement = $invoice->getSommePaiement();
				$creditnotes = $invoice->getSumCreditNotesUsed();
				$deposits = $invoice->getSumDepositsUsed();
				$alreadypayed = price2num($paiement + $creditnotes + $deposits, 'MT');
				$remaintopay = price2num($invoice->total_ttc - $paiement - $creditnotes - $deposits, 'MT');

				// Multicurrency Price
				if (!empty($conf->multicurrency->enabled))
				{
					$multicurrency_payment = $invoice->getSommePaiement(1);
					$multicurrency_creditnotes = $invoice->getSumCreditNotesUsed(1);
					$multicurrency_deposits = $invoice->getSumDepositsUsed(1);
					$multicurrency_alreadypayed = price2num($multicurrency_payment + $multicurrency_creditnotes + $multicurrency_deposits, 'MT');
					$multicurrency_remaintopay = price2num($invoice->multicurrency_total_ttc - $multicurrency_payment - $multicurrency_creditnotes - $multicurrency_deposits, 'MT');
				}

				$htmlList .= '<tr class="oddeven'.(($invoice->id == $facid) ? ' highlight' : '').'">';

				// Ref
				$htmlList .= '<td class="nowraponall">';
				$htmlList .= $invoice->getNomUrl(1);
				$htmlList .= '</td>';

				// Ref supplier
				$htmlList .= '<td>'.$objp->ref_supplier.'</td>';

				// Date
				if ($objp->df > 0)
				{
					$htmlList .= '<td class="center nowraponall">';
					$htmlList .= dol_print_date($db->jdate($objp->df), 'day').'</td>';
				}
				else
				{
					$htmlList .= '<td class="center"><b>!!!</b></td>';
				}

				// Date Max Payment
				if ($objp->dlr > 0)
				{
					$htmlList .= '<td class="center nowraponall">';
					$htmlList .= dol_print_date($db->jdate($objp->dlr), 'day');

					if ($invoice->hasDelay())
					{
						$htmlList .= img_warning($langs->trans('Late'));
					}

					$htmlList .= '</td>';
				}
				else
				{
					$htmlList .= '<td class="center"><b>--</b></td>';
				}

				// Multicurrency
				if (!empty($conf->multicurrency->enabled))
				{
					// Currency
					$htmlList .= '<td class="center">'.$objp->multicurrency_code."</td>\n";

					$htmlList .= '<td class="right">';
					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency)
					{
						$htmlList .= price($objp->multicurrency_total_ttc);
					}
					$htmlList .= '</td>';

					$htmlList .= '<td class="right">';
					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency)
					{
						$htmlList .= price($objp->multicurrency_am);
					}
					$htmlList .= '</td>';

					$htmlList .= '<td class="right">';
					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency)
					{
						$htmlList .= price($objp->multicurrency_total_ttc - $objp->multicurrency_am);
					}
					$htmlList .= '</td>';
				}

				$htmlList .= '<td class="right">'.price($sign * $objp->total_ttc).'</td>';

				$htmlList .= '<td class="right">'.price($sign * $objp->am);
				if ($creditnotes) $htmlList .= '+'.price($creditnotes);
				if ($deposits) $htmlList .= '+'.price($deposits);
				$htmlList .= '</td>';

				$htmlList .= '<td class="right">'.price($sign * $remaintopay).'</td>';

				// Amount
				$htmlList .= '<td class="center nowraponall">';

				$namef = 'amount_'.$objp->facid;
				$nameRemain = 'remain_'.$objp->facid;

				if ($action != 'add_paiement')
				{
					if (!empty($conf->use_javascript_ajax))
						$htmlList .= img_picto("Auto fill", 'rightarrow', "class='AutoFillAmout' data-rowname='".$namef."' data-value='".($sign * $remaintopay)."'");
						$htmlList .= '<input type="hidden" class="remain" name="'.$nameRemain.'" value="'.$remaintopay.'">';
						$htmlList .= '<input type="text" size="8" class="amount" name="'.$namef.'" value="'.dol_escape_htmltag(GETPOST($namef)).'">';
				}
				else
				{
					$htmlList .= '<input type="text" size="8" name="'.$namef.'_disabled" value="'.dol_escape_htmltag(GETPOST($namef)).'" disabled>';
					$htmlList .= '<input type="hidden" name="'.$namef.'" value="'.dol_escape_htmltag(GETPOST($namef)).'">';
				}
				$htmlList .= "</td>";

				// Multicurrency Price
				if (!empty($conf->multicurrency->enabled))
				{
					$htmlList .= '<td class="right">';

					// Add remind multicurrency amount
					$namef = 'multicurrency_amount_'.$objp->facid;
					$nameRemain = 'multicurrency_remain_'.$objp->facid;

					if ($objp->multicurrency_code && $objp->multicurrency_code != $conf->currency)
					{
						if ($action != 'add_paiement')
						{
							if (!empty($conf->use_javascript_ajax))
								$htmlList .= img_picto("Auto fill", 'rightarrow', "class='AutoFillAmout' data-rowname='".$namef."' data-value='".($sign * $multicurrency_remaintopay)."'");
								$htmlList .= '<input type=hidden class="multicurrency_remain" name="'.$nameRemain.'" value="'.$multicurrency_remaintopay.'">';
								$htmlList .= '<input type="text" size="8" class="multicurrency_amount" name="'.$namef.'" value="'.$_POST[$namef].'">';
						}
						else
						{
							$htmlList .= '<input type="text" size="8" name="'.$namef.'_disabled" value="'.$_POST[$namef].'" disabled>';
							$htmlList .= '<input type="hidden" name="'.$namef.'" value="'.$_POST[$namef].'">';
						}
					}
					$htmlList .= "</td>";
				}

				$htmlList .= "</tr>\n";
				$total += $objp->total_ht;
				$total_ttc += $objp->total_ttc;
				$totalrecu += $objp->am;
				$totalrecucreditnote += $creditnotes;
				$totalrecudeposits += $deposits;
				$i++;
			}
			if ($i > 1)
			{
				// $htmlList .= total
				$htmlList .= '<tr class="liste_total">';
				$htmlList .= '<td colspan="4" class="left">'.$langs->trans('TotalTTC').':</td>';
				if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td>&nbsp;</td>';
				if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td>&nbsp;</td>';
				if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td>&nbsp;</td>';
				if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td>&nbsp;</td>';
				$htmlList .= '<td class="right"><b>'.price($sign * $total_ttc).'</b></td>';
				$htmlList .= '<td class="right"><b>'.price($sign * $totalrecu);
				if ($totalrecucreditnote) $htmlList .= '+'.price($totalrecucreditnote);
				if ($totalrecudeposits) $htmlList .= '+'.price($totalrecudeposits);
				$htmlList .=	'</b></td>';
				$htmlList .= '<td class="right"><b>'.price($sign * price2num($total_ttc - $totalrecu - $totalrecucreditnote - $totalrecudeposits, 'MT')).'</b></td>';
				$htmlList .= '<td class="center" id="result_provider_invoice" style="font-weight: bold;"></td>'; // Autofilled
				if (!empty($conf->multicurrency->enabled)) $htmlList .= '<td class="right" id="multicurrency_result" style="font-weight: bold;"></td>';
				$htmlList .= "</tr>\n";
			}
			$htmlList .= "</table>\n";

			$htmlList .= "</div>";
		}else{
			$retour['error']++;
			$retour['message'] .= $langs->trans("NoUnpaidProviderInvoice").'<br>';
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



