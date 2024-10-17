<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019       Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2020 SuperAdmin <florian.dufourg@gmail.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   bankimportapi     Module BankImportApi
 *  \brief      BankImportApi module descriptor.
 *
 *  \file       htdocs/bankimportapi/core/modules/modBankImportApi.class.php
 *  \ingroup    bankimportapi
 *  \brief      Description and activation file for module BankImportApi
 */

dol_include_once('/core/modules/DolibarrModules.class.php');

/**
 *  Description and activation class for module BankImportApi
 */
class modBankImportApi extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;
        $this->numero = 141160; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module
        $this->rights_class = 'bankimportapi';
        $this->family = "financial";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "dolimportDesc";
        $this->descriptionlong = "dolimportDescLong";
        $this->editor_name = 'Florian DUFOURG';
        $this->editor_url = 'https://simple-soft.eu';
        $this->version = '2.20';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';
        $this->module_parts = array(
            'triggers' => 1,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'theme' => 0,
            'css' => array(),
            'js' => array(),
            'moduleforexternal' => 0,
        );
        
		
        $this->dirs = array("/bankimportapi/temp");
        $this->config_page_url = array("setup.php@bankimportapi");
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
        $this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
        $this->langfiles = array("bankimportapi@bankimportapi");
        $this->phpmin = array(5, 5); // Minimum version of PHP required by module
        $this->need_dolibarr_version = array(11, -3); // Minimum version of Dolibarr required by module
        $this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
        $this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)

        // Constants
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        // Example: $this->const=array(1 => array('BANKIMPORTAPI_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
        //                             2 => array('BANKIMPORTAPI_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
        // );
        $this->const = array(
            1 => array('BANKIMPORTAPI_DATE_FORMAT', 'chaine', '1', 'AmountFormat', 1, 'allentities', 1),
			2 => array('BANKIMPORTAPI_AMOUNT_FORMAT', 'chaine', '1', 'DateFormat', 1, 'allentities', 1),
			3 => array('BANKIMPORTAPI_PAYMENTMODE_FORMAT', 'chaine', '1', 'PaymentModeFormat', 1, 'allentities', 1),
			4 => array('BANKIMPORTAPI_PAYMENT_MODE_TRANSLATION_TAB', 'chaine', '1', 'PaymentModeTranslationTab', 1, 'allentities', 1),
        );
		
        // Array to add new pages in new tabs
        $this->tabs = array();
		
		//$this->tabs[] = array('data'=>'bank_account:+import:Import:bankimportapi@bankimportapi:$conf->bankimportapi->enabled:/importfrombankcsv/mynewtab1.php?id=__ID__'); 

        // Some keys to add into the overwriting translation tables
        /*$this->overwrite_translation = array(
            'en_US:ParentCompany'=>'Parent company or reseller',
            'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
        )*/

        if (!isset($conf->bankimportapi) || !isset($conf->bankimportapi->enabled)) {
            $conf->bankimportapi = new stdClass();
            $conf->bankimportapi->enabled = 0;
        }


        // Permissions provided by this module
        $this->rights = array();
        $r = 0;
        // Add here entries to declare new permissions
        /* BEGIN MODULEBUILDER PERMISSIONS */
        $this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
        $this->rights[$r][1] = 'Read objects of BankImportApi'; // Permission label
        $this->rights[$r][4] = 'mybankimports'; // In php code, permission will be checked by test if ($user->rights->bankimportapi->level1->level2)
        $this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->bankimportapi->level1->level2)
        $r++;
        /* END MODULEBUILDER PERMISSIONS */

        // Main menu entries to add

		//$this->buildMenu();

    }

    /**
     *  Function called when module is enabled.
     *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *  It also creates data directories
     *
     *  @param      string  $options    Options when enabling module ('', 'noboxes')
     *  @return     int             	1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        $result = $this->_load_tables('/bankimportapi/sql/');
        if ($result < 0) return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')


        // Create extrafields during init
		dol_include_once('/core/class/extrafields.class.php');
		
        $extrafields = new ExtraFields($this->db);
		$result1=$extrafields->addExtraField('id_api', "ID API", 'varchar', 1, 100, 'bank_account', 0, 0, '', '', 1, '', 1, 0, '', '', 'bankimportapi@bankimportapi', '$conf->bankimportapi->enabled');
		$result2=$extrafields->addExtraField('key_api', "KEY API", 'varchar', 2, 100, 'bank_account', 0, 0, '', '', 1, '', 1, 0, '', '', 'bankimportapi@bankimportapi', '$conf->bankimportapi->enabled');

		$result3=$extrafields->addExtraField(
			'bank_name_api',	//$attrname
			"BANK API",	//$label
			'select',	//$type (text,varchar,html,int,double,date,price,select,sellist,radio...)
			4,	//$pos
			10,	//$size
			'bank_account',	//$elementtype
			0,	//$unique = 0
			0,	//$required = 0
			'',	//$default_value = ''
			array('options'=>array('QONTO'=>'QONTO')),	//$param / list,
			0,	//$alwayseditable = 0
			'',	//$perms
			1,	//visibility
			0,	//$help = ''
			'',	//$computed = ''
			0,	//$entity = ''
			'bankimportapi@bankimportapi',		//$langfile = ''
			'$conf->bankimportapi->enabled',		//$enabled = '1'
			0		//totalizable
		);

        /*
        $result3=$extrafields->addExtraField(
			'qonto_account_nb',	//$attrname
			"QONTO_ACCOUNT_NUMBER",	//$label
			'select',	//$type (text,varchar,html,int,double,date,price,select,sellist,radio...)
			3,	//$pos
			10,	//$size
			'bank_account',	//$elementtype
			0,	//$unique = 0
			0,	//$required = 0
			'',	//$default_value = ''
			array('options'=>array('0'=>'0', '1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4', '5'=>'5')),	//$param / list,
			0,	//$alwayseditable = 0
			'',	//$perms
			1,	//visibility
			'usually 0, if you do not know, let 0',	//$help = ''
			'',	//$computed = ''
			0,	//$entity = ''
			'bankimportapi@bankimportapi',		//$langfile = ''
			'$conf->bankimportapi->enabled',		//$enabled = '1'
			0		//totalizable
		);
        */


        // Permissions
        $this->remove($options);
		
		$resultMenu = $this->buildMenu();
		
		if (empty($resultMenu)) return -1;

        $sql = array();


        return $this->_init($sql, $options);
    }

    /**
     *  Function called when module is disabled.
     *  Remove from database constants, boxes and permissions from Dolibarr database.
     *  Data directories are not deleted
     *
     *  @param      string	$options    Options when enabling module ('', 'noboxes')
     *  @return     int                 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
	
	
    /**
     *  Function called to build the menu
	 *
     *  @return     int                 1 if OK, 0 if KO
     */
    public function buildMenu()
    {

		global $conf, $langs;
		
		$langs->loadLangs(array("bankimportapi@bankimportapi", "other"));

        $this->menu = array();
        $r = 0;

		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

		$sql = "SELECT b.rowid, b.label, b.courant, b.rappro, ef.bank_name_api as options_bank_name_api";
		$sql .= " FROM ".MAIN_DB_PREFIX."bank_account as b";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON (b.rowid = ef.fk_object)";		
		$sql .= " WHERE b.entity = ".$conf->entity;
		$sql .= " AND b.clos = 0";
		$sql .= " ORDER BY b.label";

		$resql = $this->db->query($sql);
		if ($resql)
		{
			$numr = $this->db->num_rows($resql);
			$i = 0;

			while ($i < $numr)
			{
				$objp = $this->db->fetch_object($resql);
				
				
				if(!empty($objp->options_bank_name_api)) $suffix = '(API)';
				else $suffix = '('.$langs->trans("List").')';
				
				$refMainMenu = 'import'.$r;
				
				$this->menu[$r++]=array(
					'fk_menu'=>'fk_mainmenu=bank',                          // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
					'type'=>'left',
					'titre'=>'<i class="fas fa-align-justify"></i> '.$objp->label.' '.$suffix,
					'mainmenu'=>'bank',
					'leftmenu'=>$refMainMenu,
					'url'=>'/bankimportapi/bankimportapiindex.php?id_bank='.$objp->rowid,
					'langs'=>'',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
					'position'=>1+$r,
					'enabled'=>'$conf->bankimportapi->enabled',  // Define condition to show or hide menu entry. Use '$conf->dashboard->enabled' if entry must be visible if module is enabled.
					'perms'=>'1',			                // Use 'perms'=>'$user->rights->dashboard->level1->level2' if you want your menu with a permission rules
					'target'=>'',
					'user'=>0,				                // 0=Menu for internal users, 1=external users, 2=both
				);
				
				
				if(empty($objp->options_bank_name_api)){
					$this->menu[$r++]=array(
						'fk_menu'=>'fk_mainmenu=bank,fk_leftmenu='.$refMainMenu,                          // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
						'type'=>'left',
						'titre'=>'<i class="fas fa-file-csv"></i> '.$langs->trans("LoadCsv"),
						'mainmenu'=>'bank',
						'leftmenu'=>$refMainMenu,
						'url'=>'/bankimportapi/loadcsv.php?id_bank='.$objp->rowid,
						'langs'=>'',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
						'position'=>1+$r,
						'enabled'=>'$conf->bankimportapi->enabled',  // Define condition to show or hide menu entry. Use '$conf->dashboard->enabled' if entry must be visible if module is enabled.
						'perms'=>'1',			                // Use 'perms'=>'$user->rights->dashboard->level1->level2' if you want your menu with a permission rules
						'target'=>'',
						'user'=>0,				                // 0=Menu for internal users, 1=external users, 2=both
					);

				}		
				
				$i++;
			}
			return 1;
		}
		else {
			dol_print_error($this->db);
			return 0;
		}
    }
	
	
	
	
}