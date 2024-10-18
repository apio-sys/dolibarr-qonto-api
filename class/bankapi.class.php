<?php
/* Copyright (C) 2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 * \file        class/bankapi.class.php
 * \ingroup     bankapi
 * \brief       This file is a CRUD class file for bankApi
 */

// Put here all includes required by your class file

dol_include_once('/compta/bank/class/account.class.php');

dol_include_once('/bankimportapi/lib/vendor/autoload.php');

dol_include_once('/core/class/commonobject.class.php');


use \Tavux\Qonto\QontoClient;

/**
 * Class for bankapi
 */
class bankApi extends CommonObject
{

	public $dolibarrBankId;
	public $bankLabel;
	public $bankName;
	public $idBankApi;
	public $keyBankApi;
	public $dateFrom;
	public $dateFromTmstp;
	
	public $iban;
	public $bic;
	public $balance;
	public $authorized_balance;
	public $rappro;
	
/*
	public $transaction=array(
		array(	'transaction_id'=>'',
				'emitted_at'=>'',
				'settled_at'=>'',
				'label'=>'',
				'reference'=>'',
				'amount'=>'',
				'currency'=>'',
				'local_amount'=>'',
				'local_currency'=>'',
				'vat_amount'=>'',
				'side'=>'',
				'operation_type'=>'',	
				'attachment_ids'=>'',
				),
	);	
*/
	
	public $error;
	public $errorMsg;
	
	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;
	}

	/**
	 * get Bank datas (transaction, soldes, IBAN)
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function getBankDatas($bankId,$dateFromTmstp,$dateToTmstp)
	{
		global $conf;
		
		$this->error = 0;
		$this->errorMsg = '';
		
		$bankObject = new Account($this->db);
		$result = $bankObject->fetch($bankId);
		
		if($result<0){
			$this->error = 1;
			$this->errorMsg = 'Impossible to load Dolibarr bank Object';
			
			return $this;
		}
		
		
		$this->bankName = $bankObject->array_options['options_bank_name_api'];
		$this->idBankApi = $bankObject->array_options['options_id_api'];
		$this->rappro = $bankObject->rappro;
		$this->bankLabel = $bankObject->ref;
		$this->dolibarrBankId = $bankId;
		$this->iban = str_replace(' ', '', $bankObject->iban);

		
		if(empty($this->idBankApi) && !empty($this->bankName)){
			$this->error = 1;
			$this->errorMsg = 'Bank has no id defined for API';
			
			return $this;
		}
		
		$this->keyBankApi = $bankObject->array_options['options_key_api'];

		if(empty($this->keyBankApi) && !empty($this->bankName)){
			$this->error = 1;
			$this->errorMsg = 'Bank has no key defined for API';
			
			return $this;
		}
			
		$this->dateFromTmstp = $dateFromTmstp;
		$this->dateFrom = date( 'Y-m-d\TH:i:sO', $this->dateFromTmstp );
		
		$this->dateToTmstp = $dateToTmstp;
		$this->dateTo = date( 'Y-m-d\TH:i:sO', $this->dateToTmstp );
		
		
		switch ($this->bankName) {
			case 'QONTO':
				$datas = $this->getQontoDatas();

				//print '<pre>'; print_r($datas); print '</pre>';exit;
				return $this;
				break;
			default:
				$this->getCsvDatas();
				return $this;
			
		}
	}
	
	
	/**
	 * get datas from QONTO API
	 * @return object
	 */
	private function getQontoDatas()
	{
		$qonto = new QontoClient($this->idBankApi, $this->keyBankApi);

		

		try {
			$organization = $qonto->getOrganization('company_id');

			

			//If more than one account, we find accound by iban
			if(count($organization->bank_accounts) > 0){

				//If iban not defined, send error
				if(empty($this->iban)){
					$this->error = 1;
					$this->errorMsg = "please, define iban of account in Dolibarr so we can find the account on Qonto";
					return $this;
				}

				foreach ($organization->bank_accounts as $key => $account) {

					$ibanTemp = str_replace(' ', '', $account->iban);

					if($ibanTemp == $this->iban){
						$qontoAccountIndex = $key;
						break;
					}
				}

				//If iban not found in Qonto send error
				if(!is_integer($qontoAccountIndex)){
					$this->error = 1;
					print "Iban of account ".$this->iban." was not founded in Qonto, please make sure there are no error on Iban";
					print '</br></br>';
					print '<pre>'; print_r($organization); print '</pre>';exit();
					return $this;
				}

			}else{
				$qontoAccountIndex = 0;
			}

			
			$transactions = $qonto->listTransactions(
				$organization->bank_accounts[$qontoAccountIndex]->slug, //slug required
				$organization->bank_accounts[$qontoAccountIndex]->iban, //iban required
				null, //status
				null, //updated_at_from
				null, //updated_at_to
				$this->dateFrom, //settled_at_from
				$this->dateTo, //settled_at_to
				null, //sort_by
				1, //current_page
				null //per_page
			);
			
			$this->organization = $organization;
			$this->iban = $organization->bank_accounts[$qontoAccountIndex]->iban;
			//$this->bic = $organization->bank_accounts[0]->iban;
			$this->balance = $organization->bank_accounts[$qontoAccountIndex]->balance;
			$this->authorized_balance = $organization->bank_accounts[$qontoAccountIndex]->authorized_balance;

			$totalDebit = 0;
			$totalCredit = 0;

			$i=0;

	
			$this->transaction = array();

			foreach($transactions->transactions as $key=>$val){
				if(!empty($val->transaction_id)){

				
					$i = $val->transaction_id;

					
					
					$this->transaction[$i]['transaction_id'] = $val->transaction_id;
					$this->transaction[$i]['emitted_at'] = strtotime($val->emitted_at);
					$this->transaction[$i]['settled_at'] = strtotime($val->settled_at);
					$this->transaction[$i]['label'] = $val->label;
					$this->transaction[$i]['reference'] = $val->reference;
					$this->transaction[$i]['amount'] = $val->amount;
					$this->transaction[$i]['currency'] = $val->currency;
					$this->transaction[$i]['local_amount'] = $val->local_amount;
					$this->transaction[$i]['local_currency'] = $val->local_currency;
					$this->transaction[$i]['vat_amount'] = $val->vat_amount;
					$this->transaction[$i]['side'] = $val->side;
					$this->transaction[$i]['operation_type'] = $val->operation_type;
					$this->transaction[$i]['card_last_digits'] = $val->card_last_digits;
					
					//print '<pre>'; print_r($this->transaction); print '</pre>';exit;
					
					switch ($val->operation_type) {
						case "card":
							$this->transaction[$i]['mode_reglement_id'] = 6;
							break;
						case "cheque":
							$this->transaction[$i]['mode_reglement_id'] = 7;
							break;
						case "income":
							$this->transaction[$i]['mode_reglement_id'] = 2;
							break;
						case "transfer":
							$this->transaction[$i]['mode_reglement_id'] = 2;
							break;
						case "direct_debit":
							$this->transaction[$i]['mode_reglement_id'] = 3;
							break;
						case "qonto_fee":
							$this->transaction[$i]['mode_reglement_id'] = 3;
							break;
						default:
							$this->transaction[$i]['mode_reglement_id'] = '';
					}

					$this->transaction[$i]['attachment_ids'] = $val->attachment_ids;


					if($val->side == "credit"){
						$this->totalCredit += $val->amount;
					}else{
						$this->totalDebit += $val->amount;
					}
				}
			}
			
			return $this;
			
		} catch (\GuzzleHttp\Exception\GuzzleException $e) {
			$this->error = 1;
			$this->errorMsg = $e->getMessage();
			return $this;
		}
	}
	
	
	/**
	 * get datas from QONTO API
	 * @return object
	 */
	private function getCsvDatas()
	{
		
		$sql = 'SELECT *';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'bankimportapi_csvlines';
		$sql .= ' WHERE fk_bank = '.$this->dolibarrBankId;
		$sql .= " AND emitted_at BETWEEN '".$this->db->idate($this->dateFromTmstp)."' AND '".$this->db->idate($this->dateToTmstp)."'";
		$sql .= ' ORDER BY emitted_at DESC';

		$resql = $this->db->query($sql);
		$num =  $this->db->num_rows($resql);
		
		$i = 0;

		while ($i < $num)
		{
			$obj = $this->db->fetch_object($resql);
			
			$refId = 'csv_'.$obj->rowid;
			
			$this->transaction[$refId]['transaction_id'] = $refId;
			$this->transaction[$refId]['emitted_at'] = strtotime($obj->emitted_at);
			$this->transaction[$refId]['settled_at'] = strtotime($obj->settled_at);
			$this->transaction[$refId]['label'] = $obj->label;
			$this->transaction[$refId]['reference'] = $obj->label_bis;
			$this->transaction[$refId]['amount'] = $obj->amount;
			$this->transaction[$refId]['vat_amount'] = $obj->vat_amount;
			$this->transaction[$refId]['side'] = $obj->side;
			$this->transaction[$refId]['operation_type'] = $obj->operation_type;
			$this->transaction[$refId]['mode_reglement_id'] = $obj->reglement_id;
			$this->transaction[$refId]['statement_name'] = $obj->statement_name;

			$i++;
		}
		
		return $this;
	}
	
	
	
	/**
	 * get attachement
	 *
	 * @param  attachment_id $attachment_id      id of attachement
	 * @return strinf             url of file
	 */
	public function getFile($bankId,$attachment_id)
	{
		global $conf;
		
		$this->error = 0;
		$this->errorMsg = '';
		
		$bankObject = new Account($this->db);
		$result = $bankObject->fetch($bankId);
		
		if($result<0){
			$this->error = 1;
			$this->errorMsg = 'Impossible to load Dolibarr bank Object';
			
			return $this;
		}
		
		$this->idBankApi = $bankObject->array_options['options_id_api'];
		$this->keyBankApi = $bankObject->array_options['options_key_api'];
		
		if(empty($attachment_id)){
			$this->error = 1;
			$this->errorMsg = 'attachment_id is empty';
		
			return 'attachment_id is empty';
		}
		
		if(empty($this->idBankApi)){
			$this->error = 1;
			$this->errorMsg = 'idBankApi is empty';
			
			return $this;
		}
		
		if(empty($this->keyBankApi)){
			$this->error = 1;
			$this->errorMsg = 'keyBankApi is empty';
			
			return $this;
		}
		
		
		
		try{
			$qonto = new QontoClient($this->idBankApi, $this->keyBankApi);
			$file = $qonto->getAttachment($attachment_id);
			return $file->url;
			//return $file;
			
		} catch (\GuzzleHttp\Exception\GuzzleException $e) {
			$this->error = 1;
			$this->errorMsg = $e->getMessage();
			
			return $this;
		}
		
		
		
		
	}

}
