<?php
/**
 * PlugnPay Remote API client (pnpremote.cgi).
 *
 * PHP 8.2+ / AbanteCart 1.4.x.
 *
 * @see https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/
 */
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

class PnPApi {
	const ENDPOINT = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

	private $publisherName;
	private $publisherPassword;
	/** @var PnPLogger|null */
	private $logger;
	private $lastRawResponse = '';
	private $commErrNo = 0;
	private $commError = '';
	private $commInfo = array();

	/**
	 * @param string         $publisherName
	 * @param string         $publisherPassword
	 * @param PnPLogger|null $logger
	 */
	public function __construct($publisherName, $publisherPassword, $logger = null) {
		$this->publisherName = (string)$publisherName;
		$this->publisherPassword = (string)$publisherPassword;
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	public function getLastRawResponse() {
		return $this->lastRawResponse;
	}

	/**
	 * @return string
	 */
	public function getCommError() {
		return $this->commError;
	}

	/**
	 * @return int
	 */
	public function getCommErrNo() {
		return (int)$this->commErrNo;
	}

	/**
	 * @return array
	 */
	public function getCommInfo() {
		return $this->commInfo;
	}

	/**
	 * Authorization / sale. Caller should set authtype to authonly or authpostauth.
	 *
	 * @param array $fields
	 * @return array
	 */
	public function authorize(array $fields) {
		$payload = array_merge(array(
			'mode' => 'auth',
			'paymethod' => 'credit',
			'client' => 'abantecart_api',
		), $fields);

		return $this->request($payload);
	}

	/**
	 * @param array $response
	 * @return bool
	 */
	public function isApproved(array $response) {
		$final = strtolower(isset($response['FinalStatus']) ? (string)$response['FinalStatus'] : '');
		$success = strtolower(isset($response['success']) ? (string)$response['success'] : '');

		return ($final === 'success' || $success === 'yes');
	}

	/**
	 * @param array $fields
	 * @return array
	 */
	public function request(array $fields) {
		if (!function_exists('curl_init')) {
			$this->commErrNo = -1;
			$this->commError = 'PHP cURL extension is not available';
			$this->lastRawResponse = '';
			if ($this->logger) {
				$this->logger->log('cURL missing', array('error' => $this->commError));
			}
			return array(
				'FinalStatus' => 'problem',
				'success' => 'no',
				'MErrMsg' => $this->commError,
			);
		}

		$payload = array_merge(array(
			'publisher-name' => $this->publisherName,
			'publisher-password' => $this->publisherPassword,
		), $fields);

		foreach ($payload as $key => $value) {
			if ($value === null || $value === '') {
				unset($payload[$key]);
			}
		}

		$body = http_build_query($payload);

		if ($this->logger) {
			$this->logger->log('Request to PlugnPay', array(
				'endpoint' => self::ENDPOINT,
				'fields' => $payload,
			));
		}

		$ch = curl_init(self::ENDPOINT);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded',
		));

		$raw = curl_exec($ch);
		$this->commErrNo = (int)curl_errno($ch);
		$this->commError = (string)curl_error($ch);
		$info = curl_getinfo($ch);
		$this->commInfo = is_array($info) ? $info : array();
		curl_close($ch);

		$this->lastRawResponse = is_string($raw) ? $raw : '';

		if ($this->lastRawResponse === '' || $this->commErrNo !== 0) {
			$response = array(
				'FinalStatus' => 'problem',
				'success' => 'no',
				'MErrMsg' => $this->commError !== ''
					? $this->commError
					: 'Empty response from PlugnPay (check cURL connectivity / firewall)',
			);
			if ($this->logger) {
				$this->logger->log('Communication failure', array(
					'errno' => $this->commErrNo,
					'error' => $this->commError,
					'info' => $this->commInfo,
				));
			}
			return $response;
		}

		$response = $this->parseResponse($this->lastRawResponse);

		if ($this->logger) {
			$this->logger->log('Response from PlugnPay', array(
				'FinalStatus' => isset($response['FinalStatus']) ? $response['FinalStatus'] : '',
				'success' => isset($response['success']) ? $response['success'] : '',
				'orderID' => isset($response['orderID']) ? $response['orderID'] : (isset($response['orderid']) ? $response['orderid'] : ''),
				'auth-code' => isset($response['auth-code']) ? $response['auth-code'] : (isset($response['auth_code']) ? $response['auth_code'] : ''),
				'resp-code' => isset($response['resp-code']) ? $response['resp-code'] : (isset($response['resp_code']) ? $response['resp_code'] : ''),
				'MErrMsg' => isset($response['MErrMsg']) ? $response['MErrMsg'] : '',
				'avs-code' => isset($response['avs-code']) ? $response['avs-code'] : (isset($response['avs_code']) ? $response['avs_code'] : ''),
				'cvvresp' => isset($response['cvvresp']) ? $response['cvvresp'] : '',
			));
		}

		return $response;
	}

	/**
	 * @param string $raw
	 * @return array
	 */
	private function parseResponse($raw) {
		$parsed = array();
		parse_str($raw, $parsed);

		$out = array();
		foreach ($parsed as $key => $value) {
			if (is_array($value)) {
				$out[(string)$key] = implode(',', $value);
			} else {
				$out[(string)$key] = (string)$value;
			}
		}

		return $out;
	}
}
