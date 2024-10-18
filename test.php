<?php
/* Copyright (C) 2019  Florian DUFOURG    <florian.dufourg@gnl-solutions.com>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/custom/gmao/ovh_tel.php
 *	\brief      Page of ovh tel API
 *	\ingroup    gmao
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

dol_include_once('/bankimportapi/lib/bankimportapi.lib.php');

if(!$user->admin) accessforbidden();

/*
 * Action
 */

$object = new FactureFournisseur($db);
$object->fetch(702);

$listOfLinks = ['https://www.total.fr/sites/g/files/wompnd336/f/atoms/files/graisses.pdf',
				'http://master-eea.univ-tlse3.fr/wp-content/uploads/2018/01/Guide-BT-Schneider.pdf',
				'bla'];
				
$bankId = 2;
$listOfIdsAttachement[] = '52fc0adf-2b25-46e4-b766-9d466fdbcf84';
$listOfIdsAttachement[] = 'dd07e585-7212-4845-bc23-41fc524ce79d';
$results = transferFiles($bankId,$object,$listOfIdsAttachement);

/*
if (!empty($listOfLinks))
{
	dol_syslog('dolimportAPI_add_file_process upload_dir='.$upload_dir, LOG_DEBUG);
	
	foreach($listOfLinks as $key=>$val){
		
		//Check if the file exist
		if (@fopen($val, 'r')){
			
			if (dol_mkdir($upload_dir) >= 0)
			{
				$nbok = 0;

				// Define $destfull (path to file including filename) and $destfile (only filename)
				$destfull=$upload_dir . "/" . dol_basename($val);
				$destfile=dol_basename($val);


				// dol_sanitizeFileName the file name and lowercase extension
				$info = pathinfo($destfull);
				$destfull = $info['dirname'].'/'.dol_sanitizeFileName($info['filename'].($info['extension']!='' ? ('.'.strtolower($info['extension'])) : ''));
				$info = pathinfo($destfile);

				$destfile = dol_sanitizeFileName($info['filename'].($info['extension']!='' ? ('.'.strtolower($info['extension'])) : ''));

				// We apply dol_string_nohtmltag also to clean file names (this remove duplicate spaces) because
				// this function is also applied when we make try to download file (by the GETPOST(filename, 'alphanohtml') call).
				$destfile = dol_string_nohtmltag($destfile);
				$destfull = dol_string_nohtmltag($destfull);

				$resupload = file_put_contents( $destfull,file_get_contents($val));

				if($resupload)
				{
					$nbok++;
				}
				else
				{
					$langs->load("errors");
					if ($resupload < 0)	// Unknown error
					{
						setEventMessages($langs->trans("ErrorFileNotUploaded"), null, 'errors');
					}
					elseif (preg_match('/ErrorFileIsInfectedWithAVirus/', $resupload))	// Files infected by a virus
					{
						setEventMessages($langs->trans("ErrorFileIsInfectedWithAVirus"), null, 'errors');
					}
					else	// Known error
					{
						setEventMessages($langs->trans($resupload), null, 'errors');
					}
				}
				
				if ($nbok > 0)
				{
					$res = 1;
					setEventMessages($langs->trans("FileTransferComplete"), null, 'mesgs');
				}
			}
			
		}
	}	
}
*/
//$results = dol_basename($listOfLinks[0]);

/*
 * View
 */
llxHeader('', 'test', '', '',0, 0, '', '', '', '', '');

//Send SMS
/*
print '<form name="add" action="" method="post">';
print '<div class="inline-block divButAction">';

print '<button class="button" type="submit" name="action" value="test">TEST</button>';

print '</div>';
print '</form>';
print '</br>';
*/

print '<pre>';
print_r ($results);
print '</pre>';

llxFooter();


