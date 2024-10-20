<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020 SuperAdmin <florian.dufourg@gmail.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    bankimportapi/admin/setup.php
 * \ingroup bankimportapi
 * \brief   BankImportApi setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $langs, $user;


// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';



// Translations
$langs->loadLangs(array('admin', 'bankimportapi@bankimportapi','bills','banks','companies'));

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters=array(
	'BANKIMPORTAPI_ID_PROJET_DEFAUT'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_DEFAUT_VAT'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_HIDE_VAT_WARNING'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_ONLY_OPENED_PROJECTS'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_GENERATE_UNIQUE_REF'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_PERIOD_SHOWED_DAYS'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_ALLOW_FILES_TRANSFERT'=>array('css'=>'minwidth500','enabled'=>1),
	'BANKIMPORTAPI_DEV_MODE'=>array('css'=>'minwidth500','enabled'=>1),
);



/*
 * Actions
 */

if ((float) DOL_VERSION >= 6)
{
	include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';
}

$form=new Form($db);
$objectPaiement = new Paiement($db);

/*
 * View
 */

$page_name = "BANKIMPORTAPISetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_BANKIMPORTAPI@BANKIMPORTAPI');

// Configuration header
//$head = BANKIMPORTAPIAdminPrepareHead();
//dol_fiche_head($head, 'settings', '', -1, "BANKIMPORTAPI@BANKIMPORTAPI");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("BANKIMPORTAPISetupPage").'</span><br><br>';


if ($action == 'edit')
{
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="update">';
}
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';




	
	foreach ($arrayofparameters as $key => $val){
		
		if($key == 'BANKIMPORTAPI_LIST_PREFIXES'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:'CB ;VIR ';
			$modeSelect = '';
			$tempInput = '<input name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('PrefixToEliminate');
			$tempHelp = $langs->trans('HelpPrefixToEliminate');
			
		}elseif($key == 'BANKIMPORTAPI_ID_PROJET_DEFAUT'){
			if($conf->global->$key){
				$objProject = new Project($db);
				$objProject->fetch($conf->global->$key);
				$tempValue = $objProject->ref;
			}else{
				$tempValue = '';
			}
			$modeSelect = 'projet';
			$tempTitre = $langs->trans('DefautProjet');
			$tempHelp = $langs->trans('HelpDefautProjet');
			
		}elseif($key == 'BANKIMPORTAPI_DEFAUT_VAT'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:20;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('DefautVAT');
			$tempHelp = $langs->trans('HelpDefautVAT');
			
		}elseif($key == 'BANKIMPORTAPI_ONLY_OPENED_PROJECTS'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:0;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('SeeOnlyOpenedProject');
			$tempHelp = $langs->trans('HelpSeeOnlyOpenedProject');
			
		}elseif($key == 'BANKIMPORTAPI_ALLOW_FILES_TRANSFERT'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:0;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('AllowFileTransfertFromQonto');
			$tempHelp = $langs->trans('HelpAllowFileTransfertFromQonto');
			
		}elseif($key == 'BANKIMPORTAPI_GENERATE_UNIQUE_REF'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:0;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('GenerateUniqueRef');
			$tempHelp = $langs->trans('HelpGenerateUniqueRef');
			
		}elseif($key == 'BANKIMPORTAPI_PERIOD_SHOWED_DAYS'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:0;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('Period');
			$tempHelp = $langs->trans('HowManyDaysToShow');

		}elseif($key == 'BANKIMPORTAPI_HIDE_VAT_WARNING'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:0;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('HideVatWarning');
			$tempHelp = $langs->trans('Put1ToHideVatWarning');

		}elseif($key == 'BANKIMPORTAPI_DEV_MODE'){
			$tempValue = ($conf->global->$key)?$conf->global->$key:0;
			$modeSelect = '';
			$tempInput = '<input type="number" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth100':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans('DebugMode');
			$tempHelp = $langs->trans('Put1InCaseOfBugToHelpDevTeam');
			
			
		}else{
			$tempValue = $conf->global->$key;
			$modeSelect = '';
			$tempInput = '<input name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $tempValue . '">';
			$tempTitre = $langs->trans($key);
			$tempHelp = '';
		}

		
		
		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($tempTitre, $tempHelp);
		print '</td><td>';
		if ($action == 'edit')
		{
			if ($modeSelect == 'projet'){
				$formproject = new FormProjets($db);
				$formproject->select_projects((empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS)?$societe->id:-1), $conf->global->$key, $key, 0, 0, 1, 0);
			}elseif ($modeSelect == 'modePaiement'){
				$form->select_types_paiements($conf->global->$key, $key,'',0,1,1);
			}else{
				print $tempInput;
			}
		}else{
			print $tempValue;
		}
		print '</td></tr>';
	}


	print '</table>';

if ($action == 'edit')
{
	print '<br><div class="center">';
	print '<input class="button" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';

	print '</form>';
	print '<br>';
}
else
{
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
	print '</div>';
}	


// Page end
dol_fiche_end();

llxFooter();
$db->close();
