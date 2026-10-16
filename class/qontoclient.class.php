<?php
/* This program is free software; you can redistribute it and/or modify
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
 * \file        class/qontoclient.class.php
 * \ingroup     bankimportapi
 * \brief       Minimal client for the Qonto Business API (read-only endpoints used by the module)
 *
 * Replaces the unmaintained tavux/qonto-php library and its Guzzle 6 dependency,
 * using PHP curl only. Responses are decoded into plain objects so callers keep
 * the same property access ($org->bank_accounts[0]->iban, $tx->transactions, ...).
 */

/**
 * Error raised on network failure, non-2xx HTTP status or invalid JSON
 */
class QontoApiException extends Exception
{
}

/**
 * Class QontoApiClient
 */
class QontoApiClient
{
	const BASE_URL = 'https://thirdparty.qonto.com/v2';

	/** @var string */
	private $login;

	/** @var string */
	private $secretKey;

	/**
	 * @param string $login     Qonto API login (organisation slug)
	 * @param string $secretKey Qonto API secret key
	 */
	public function __construct($login, $secretKey)
	{
		$this->login = $login;
		$this->secretKey = $secretKey;
	}

	/**
	 * @param  string $id Organization id ('company_id' returns the organization of the credentials)
	 * @return stdClass   Organization with slug and bank_accounts[]
	 */
	public function getOrganization($id)
	{
		$response = $this->get('/organizations/'.rawurlencode($id));
		if (!isset($response->organization)) {
			throw new QontoApiException('Unexpected Qonto API response: no organization');
		}
		if (!isset($response->organization->bank_accounts) || !is_array($response->organization->bank_accounts)) {
			$response->organization->bank_accounts = array();
		}
		return $response->organization;
	}

	/**
	 * @param  array $parameters Query parameters (slug, iban, settled_at_from, settled_at_to, current_page, per_page, ...)
	 * @return stdClass          Object with transactions[] and meta
	 */
	public function listTransactions($parameters)
	{
		$response = $this->get('/transactions', array_filter($parameters, function ($v) {
			return $v !== null && $v !== '';
		}));
		if (!isset($response->transactions) || !is_array($response->transactions)) {
			$response->transactions = array();
		}
		return $response;
	}

	/**
	 * @param  string $id Attachment id
	 * @return stdClass   Attachment with url (temporary download link)
	 */
	public function getAttachment($id)
	{
		$response = $this->get('/attachments/'.rawurlencode($id));
		if (!isset($response->attachment)) {
			throw new QontoApiException('Unexpected Qonto API response: no attachment');
		}
		return $response->attachment;
	}

	/**
	 * Send a GET request to the Qonto API and decode the JSON response
	 *
	 * @param  string $path       Path below BASE_URL
	 * @param  array  $parameters Query parameters
	 * @return stdClass
	 */
	private function get($path, $parameters = array())
	{
		global $conf;

		if (empty($this->login) || empty($this->secretKey)) {
			throw new QontoApiException('Qonto credentials are not set');
		}
		if (!function_exists('curl_init')) {
			throw new QontoApiException('PHP curl extension is required for the Qonto API');
		}

		$url = self::BASE_URL.$path;
		if (!empty($parameters)) {
			$url .= '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: '.$this->login.':'.$this->secretKey,
			'Accept: application/json',
		));
		// Same proxy setup as Dolibarr's getURLContent()
		if (!empty($conf->global->MAIN_PROXY_USE)) {
			curl_setopt($ch, CURLOPT_PROXY, $conf->global->MAIN_PROXY_HOST.':'.$conf->global->MAIN_PROXY_PORT);
			if (!empty($conf->global->MAIN_PROXY_USER)) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $conf->global->MAIN_PROXY_USER.':'.$conf->global->MAIN_PROXY_PASS);
			}
		}

		$body = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($body === false) {
			throw new QontoApiException('Qonto API request failed: '.$curlError);
		}

		$data = json_decode($body);

		if ($httpCode < 200 || $httpCode >= 300) {
			$detail = '';
			if (is_object($data) && isset($data->errors) && is_array($data->errors)) {
				$messages = array();
				foreach ($data->errors as $err) {
					$messages[] = is_object($err) ? (isset($err->detail) ? $err->detail : (isset($err->message) ? $err->message : json_encode($err))) : (string) $err;
				}
				$detail = implode('; ', $messages);
			} elseif (is_object($data) && isset($data->message)) {
				$detail = $data->message;
			}
			throw new QontoApiException('Qonto API error HTTP '.$httpCode.' on '.$path.($detail !== '' ? ': '.$detail : ''), $httpCode);
		}

		if (!is_object($data)) {
			throw new QontoApiException('Invalid JSON response from Qonto API on '.$path);
		}

		return $data;
	}
}
