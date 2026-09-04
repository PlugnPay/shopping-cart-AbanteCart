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
	exit;
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
	private $httpCode = 0;
	private $responseReceived = false;

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
	 * @return bool
	 */
	public function hasResponse() {
		return $this->responseReceived;
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
	 * @return int
	 */
	public function getHttpCode() {
		return (int)$this->httpCode;
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
		if (!class_exists('PnPFilter', false)) {
			require_once(dirname(__FILE__) . '/PnPFilter.php');
		}
		return PnPFilter::isApproved($response);
	}

	/**
	 * @param array $fields
	 * @return array
	 */
	public function request(array $fields) {
		if (!class_exists('PnPFilter', false)) {
			require_once(dirname(__FILE__) . '/PnPFilter.php');
		}

		$this->responseReceived = false;
		$this->lastRawResponse = '';

		if (!function_exists('curl_init')) {
			$this->commErrNo = -1;
			$this->commError = 'PHP cURL extension is not available';
			$this->lastRawResponse = '';
			$this->httpCode = 0;
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
		$payload = PnPFilter::allowlistAuthorizeFields($payload);
		$payload['publisher-name'] = $this->publisherName;
		$payload['publisher-password'] = $this->publisherPassword;

		foreach ($payload as $key => $value) {
			if ($value === null || $value === '') {
				unset($payload[$key]);
			}
		}

		$body = http_build_query($payload);

		if ($this->logger) {
			$this->logger->log('Request to PlugnPay', array(
				'endpoint' => self::ENDPOINT,
				'mode' => isset($payload['mode']) ? $payload['mode'] : '',
				'authtype' => isset($payload['authtype']) ? $payload['authtype'] : '',
				'card-amount' => isset($payload['card-amount']) ? $payload['card-amount'] : '',
				'currency' => isset($payload['currency']) ? $payload['currency'] : '',
				'acct_code' => isset($payload['acct_code']) ? $payload['acct_code'] : '',
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
		if (defined('CURL_SSLVERSION_TLSv1_2')) {
			curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
		}
		if (defined('CURLPROTO_HTTPS')) {
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
			curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded',
		));

		$raw = curl_exec($ch);
		$this->commErrNo = (int)curl_errno($ch);
		$this->commError = (string)curl_error($ch);
		$info = curl_getinfo($ch);
		$this->commInfo = is_array($info) ? $info : array();
		$this->httpCode = isset($info['http_code']) ? (int)$info['http_code'] : 0;
		curl_close($ch);

		$this->lastRawResponse = is_string($raw) ? $raw : '';

		if ($this->lastRawResponse === '' || $this->commErrNo !== 0 || $this->httpCode !== 200) {
			$response = array(
				'FinalStatus' => 'problem',
				'success' => 'no',
				'MErrMsg' => 'Empty or invalid response from PlugnPay',
			);
			if ($this->logger) {
				$this->logger->log('Communication failure', array(
					'errno' => $this->commErrNo,
					'http_code' => $this->httpCode,
				));
			}
			$this->lastRawResponse = '';
			return $response;
		}

		$response = $this->parseResponse($this->lastRawResponse);
		$this->responseReceived = true;
		$this->lastRawResponse = '';

		if ($this->logger) {
			$this->logger->log('Response from PlugnPay', PnPFilter::loggableResponse($response));
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
		if (!is_array($parsed)) {
			$parsed = array();
		}
		return PnPFilter::allowlistResponse($parsed);
	}
}
