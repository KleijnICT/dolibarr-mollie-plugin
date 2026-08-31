<?php
/* Copyright (C) 2026 KleijnICT B.V. <https://kleijnict.nl>
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
 * \file    htdocs/mollie/class/mollieclient.class.php
 * \ingroup mollie
 * \brief   Thin curl client for the Mollie v2 API (no SDK, fully auditable).
 */

/**
 * Minimal Mollie v2 API client.
 *
 * Deliberately covers only the endpoints this module uses: create payment,
 * get payment, list methods. Every request is a straight curl call with
 * TLS verification, timeouts and structured error reporting.
 */
class MollieClient
{
	const API_BASE = 'https://api.mollie.com/v2';

	const CONNECT_TIMEOUT = 10;
	const RESPONSE_TIMEOUT = 30;

	/**
	 * @var string Bearer API key (test_... or live_...)
	 */
	private $apikey;

	/**
	 * @var string Last error message, translated where possible
	 */
	public $error = '';

	/**
	 * @var int HTTP status code of the last request (0 if transport failed)
	 */
	public $lasthttpcode = 0;

	/**
	 * Constructor
	 *
	 * @param string $apikey Mollie API key (test_... or live_...)
	 */
	public function __construct($apikey)
	{
		$this->apikey = trim($apikey);
	}

	/**
	 * Build a client from the module configuration, honouring the test/live switch.
	 *
	 * @return MollieClient|null Client, or null if the required key is not configured
	 */
	public static function createFromConf()
	{
		$live = getDolGlobalInt('MOLLIE_LIVE_ENABLED');
		$apikey = getDolGlobalString($live ? 'MOLLIE_LIVE_API_KEY' : 'MOLLIE_TEST_API_KEY');
		if (empty($apikey)) {
			return null;
		}
		return new self($apikey);
	}

	/**
	 * Whether this client uses a live key.
	 *
	 * @return bool
	 */
	public function isLive()
	{
		return strpos($this->apikey, 'live_') === 0;
	}

	/**
	 * Create a payment.
	 *
	 * @param array<string,mixed> $body           Payment payload (amount, description, redirectUrl, webhookUrl, metadata, ...)
	 * @param string              $idempotencykey Optional stable key so a retried call cannot create a second payment
	 * @return array<string,mixed>|null           Decoded payment object, or null on error (see $this->error)
	 */
	public function createPayment(array $body, $idempotencykey = '')
	{
		return $this->request('POST', '/payments', $body, $idempotencykey);
	}

	/**
	 * Fetch a payment by its Mollie id (tr_...).
	 *
	 * @param string $paymentid Mollie payment id
	 * @return array<string,mixed>|null Decoded payment object, or null on error
	 */
	public function getPayment($paymentid)
	{
		if (!preg_match('/^tr_[a-zA-Z0-9]+$/', $paymentid)) {
			$this->error = 'Invalid Mollie payment id';
			return null;
		}
		return $this->request('GET', '/payments/'.$paymentid);
	}

	/**
	 * List the payment methods currently enabled on the Mollie profile.
	 * Also serves as the connection test: it fails on a bad or revoked key.
	 *
	 * @return array<int,array<string,mixed>>|null Array of method objects, or null on error
	 */
	public function listMethods()
	{
		$res = $this->request('GET', '/methods');
		if ($res === null) {
			return null;
		}
		return isset($res['_embedded']['methods']) ? $res['_embedded']['methods'] : array();
	}

	/**
	 * Execute one HTTP request against the Mollie API.
	 *
	 * @param string                    $method         'GET' or 'POST'
	 * @param string                    $path           Path starting with '/', appended to API_BASE
	 * @param array<string,mixed>|null  $body           JSON body for POST
	 * @param string                    $idempotencykey Optional Idempotency-Key header value
	 * @return array<string,mixed>|null                 Decoded JSON response, or null on error
	 */
	private function request($method, $path, $body = null, $idempotencykey = '')
	{
		global $langs;

		$this->error = '';
		$this->lasthttpcode = 0;

		if (empty($this->apikey)) {
			$this->error = $langs->trans('MollieErrorNoApiKey');
			return null;
		}

		$headers = array(
			'Authorization: Bearer '.$this->apikey,
			'Accept: application/json',
		);
		if ($idempotencykey !== '') {
			// Mollie caps idempotency keys at 63 characters
			$headers[] = 'Idempotency-Key: '.dol_trunc($idempotencykey, 63, 'right', 'UTF-8', 1);
		}

		$ch = curl_init(self::API_BASE.$path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
		curl_setopt($ch, CURLOPT_TIMEOUT, self::RESPONSE_TIMEOUT);
		curl_setopt($ch, CURLOPT_USERAGENT, 'DolibarrMollieModule/'.(defined('DOL_VERSION') ? DOL_VERSION : 'dev'));

		if ($method === 'POST') {
			$json = json_encode($body);
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($ch);
		$curlerr = curl_error($ch);
		$this->lasthttpcode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		if ($response === false) {
			$this->error = $langs->trans('MollieErrorTransport', $curlerr);
			dol_syslog(__METHOD__.' '.$method.' '.$path.' transport error: '.$curlerr, LOG_ERR);
			return null;
		}

		$decoded = json_decode($response, true);

		if ($this->lasthttpcode >= 400) {
			// Mollie errors are structured: {status, title, detail}
			$detail = '';
			if (is_array($decoded)) {
				$detail = trim((isset($decoded['title']) ? $decoded['title'] : '').(isset($decoded['detail']) ? ': '.$decoded['detail'] : ''), ': ');
			}
			if ($detail === '') {
				$detail = 'HTTP '.$this->lasthttpcode;
			}
			$this->error = $langs->trans('MollieErrorApi', $this->lasthttpcode, $detail);
			dol_syslog(__METHOD__.' '.$method.' '.$path.' API error '.$this->lasthttpcode.': '.$detail, LOG_WARNING);
			return null;
		}

		if (!is_array($decoded)) {
			$this->error = $langs->trans('MollieErrorBadJson');
			dol_syslog(__METHOD__.' '.$method.' '.$path.' returned non-JSON body', LOG_ERR);
			return null;
		}

		return $decoded;
	}
}
