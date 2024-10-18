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
	$sql .= ' f.datef as df, f.date_lim_reglement as dlr';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'facture_fourn as f';
	$sql .= " WHERE f.entity = ".$conf->entity;
	$sql .= ' AND f.fk_soc = '.$soc_id;
	//$sql .= ' AND f.paye = 0';
	$sql .= ' AND f.fk_statut <> 0'; // Statut=0 => non validee, Statut=2 => annulee
	$sql .= ' AND f.type IN (0,1,3,5)'; // Standard invoice, replacement, deposit, situation


	// Group by because we have a total
	$sql .= ' GROUP BY f.datef, f.ref, f.ref_supplier, f.rowid, f.type, f.total_ht, f.total_ttc, f.multicurrency_total_ttc, f.datef, f.date_lim_reglement';
	// Sort invoices by date and serial number: the older one comes first
	$sql .= ' ORDER BY f.datef DESC, f.ref ASC';

	//$retour['error']++;
	//$retour['message'] .= $sql;

	$resql = $db->query($sql);
	if ($resql)
	{
		
		$num = $db->num_rows($resql);
		if ($num > 0)
		{			
			
			$i = 0;

			$htmlList .= '<tr>';
			$htmlList .= '<td class="titlefield"><span class="fieldrequired">'.$langs->trans("ProviderInvoice").'</span></td>';

			$htmlList .= '<td>';
			$htmlList .=  '<select class="flat valignmiddle" name="fac_avoir" id="fac_avoir">';
			$htmlList .= '<option value="-1"></option>';

			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$htmlList .= '<option value="'.$objp->facid.'">';
				$htmlList .= $objp->ref;
				$htmlList .= ' | '.price($objp->total_ttc, 0, $langs, 0, -1, -1, $conf->currency);
				$htmlList .= ' ('.dol_print_date($db->jdate($objp->df), 'day').')';
				$htmlList .= '</option>';

				$i++;
			}


			$htmlList .= "</select>";
			$htmlList .= '</td>';
			$htmlList .= '</tr>';
		}else{
			$retour['error']++;
			$retour['message'] .= $langs->trans("NoProviderInvoice").'<br>';
		}
	}else{

		$retour['error']++;
		$retour['message'] .= 'Error SQL : '.$db->lasterror().'<br>';

	}

}


if(empty($retour['error'])){
	$retour['message'] = $htmlList;
	
	echo json_encode($retour);
}else{
	echo json_encode($retour);
}



