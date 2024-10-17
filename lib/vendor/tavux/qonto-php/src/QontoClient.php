<?php

namespace Tavux\Qonto;

use GuzzleHttp\Client;
use Tavux\Qonto\Models\Attachment;
use Tavux\Qonto\Models\BankAccount;
use Tavux\Qonto\Models\Label;
use Tavux\Qonto\Models\Labels;
use Tavux\Qonto\Models\Membership;
use Tavux\Qonto\Models\Memberships;
use Tavux\Qonto\Models\Meta;
use Tavux\Qonto\Models\Organization;
use Tavux\Qonto\Models\Transaction;
use Tavux\Qonto\Models\Transactions;

/**
 * Class QontoClient
 * @package Tavux\Qonto
 *
 */
class QontoClient
{

    const QONTO_URL = 'https://thirdparty.qonto.com/v2';

    /**
     * @var Client $guzzle_client
     */
    private static $guzzle_client;

    /**
     * @var string $login
     */
    private $login;

    /**
     * @var string $secret_key
     */
    private $secret_key;

    /**
     * QontoClient constructor
     * @see https://api-doc.qonto.eu/2.0/welcome/authentication
     *
     * @param string $login
     * @param string $secret_key
     */
    public function __construct($login, $secret_key)
    {
        $this->setCredentials($login, $secret_key);
        if(self::$guzzle_client === null){
            self::$guzzle_client = new Client();
        }
    }

    /**
     * Change credentials to connect to Qonto API
     * @see https://api-doc.qonto.eu/2.0/welcome/authentication
     *
     * @param string $login
     * @param string $secret_key
     */
    public function setCredentials($login, $secret_key){
        $this->login = $login;
        $this->secret_key = $secret_key;
    }

    /**
     * @param string $url
     * @param array $parameters
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendRequest($url, array $parameters = []){
        if(! $this->login || ! $this->secret_key){
            trigger_error("Qonto credentials not filled !");
            return null;
        }

        $url = self::QONTO_URL.$url;

        $response = self::$guzzle_client->request('GET', $url, [
            'query' => $parameters,
            'headers' => [
                'Authorization' => $this->login.':'.$this->secret_key
            ],
        ])->getBody();

        return \GuzzleHttp\json_decode($response, true);
    }

    /**
     *
     * @param string $slug
     * @param string $iban
     * @param array $status
     * @param string $updated_at_from
     * @param string $updated_at_to
     * @param string $settled_at_from
     * @param string $settled_at_to
     * @param string $sort_by
     * @param integer $current_page
     * @param integer $per_page
     * @return Transactions
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function listTransactions(
        $slug,
        $iban=null,
        $status=null,
        $updated_at_from=null,
        $updated_at_to=null,
        $settled_at_from=null,
        $settled_at_to=null,
        $sort_by=null,
        $current_page=null,
        $per_page=null
    ){
        $parameters = [];

        if($slug){
            $parameters['slug'] = $slug;
        }
        if($iban){
            $parameters['iban'] = $iban;
        }
        if($status){
            $parameters['status'] = $status;
        }
        if($updated_at_from){
            $parameters['updated_at_from'] = $updated_at_from;
        }
        if($updated_at_to){
            $parameters['updated_at_to'] = $updated_at_to;
        }
        if($settled_at_from){
            $parameters['settled_at_from'] = $settled_at_from;
        }
        if($settled_at_to){
            $parameters['settled_at_to'] = $settled_at_to;
        }
        if($sort_by){
            $parameters['sort_by'] = $sort_by;
        }
        if($current_page){
            $parameters['current_page'] = $current_page;
        }
        if($per_page){
            $parameters['per_page'] = $per_page;
        }

        $response = $this->sendRequest('/transactions', $parameters);

        $result = new Transactions();

        $transactions = [];

        foreach ($response['transactions'] as $_transaction){
            $transactions[] = new Transaction($_transaction);
        }

        $result->transactions = $transactions;
        if(isset($response['meta'])){
            $result->meta = new Meta($response['meta']);
        }

        return $result;
    }

    /**
     * @param integer $current_page
     * @param integer $per_page
     * @return Labels
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function listLabels($current_page=null, $per_page=null){

        $parameters = [];

        if($current_page){
            $parameters['current_page'] = $current_page;
        }
        if($per_page){
            $parameters['per_page'] = $per_page;
        }
        $response = $this->sendRequest('/labels', $parameters);

        $result = new Labels();
        $result->labels = [];

        if(isset($response['meta'])){
            $result->meta = new Meta($response['meta']);
        }

        foreach ($response['labels'] as $_label){
            $result->labels[] = new Label($_label);
        }

        return $result;
    }

    /**
     * @param integer $current_page
     * @param integer $per_page
     * @return Memberships
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function listMemberships($current_page=null, $per_page=null){

        $parameters = [];

        if($current_page){
            $parameters['current_page'] = $current_page;
        }
        if($per_page){
            $parameters['per_page'] = $per_page;
        }
        $response = $this->sendRequest('/memberships', $parameters);

        $result = new Memberships();
        $result->memberships = [];

        if(isset($response['meta'])){
            $result->meta = new Meta($response['meta']);
        }

        foreach ($response['memberships'] as $_membership){
            $result->memberships[] = new Membership($_membership);
        }

        return $result;
    }

    /**
     * @param int $id
     * @return Attachment
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAttachment($id){
        $response = $this->sendRequest('/attachments/'.$id);

        return new Attachment($response['attachment']);
    }

    /**
     * @param int $id
     * @return Organization
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOrganization($id){
        $response = $this->sendRequest('/organizations/'.$id);
        $org = new Organization();
        $org->slug = $response['organization']['slug'];
        $org->bank_accounts = [];

        foreach ($response['organization']['bank_accounts'] as $bank_account){
            $org->bank_accounts[] = new BankAccount($bank_account);
        }

        return $org;
    }
}
