<?php
/**
 * Strict filtering, return-token checks, and CHD redaction for Smart Screens v2.
 *
 * PHP 8.2+ / AbanteCart 1.4.x.
 */
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
	exit;
}

class PnPSs2Filter {
	const TEXT_MAX = 128;
	const NAME_MAX = 64;

	/** @var array */
	public static $hostedFieldAllowlist = array(
		'pt_gateway_account',
		'pt_transaction_amount',
		'pt_currency',
		'pt_currency_code',
		'pb_post_auth',
		'pt_account_code_1',
		'pt_billing_company',
		'pt_payment_name',
		'pt_billing_address_1',
		'pt_billing_city',
		'pt_billing_state',
		'pt_billing_postal_code',
		'pt_billing_country',
		'pt_billing_phone_number',
		'pt_billing_email_address',
		'pt_client_identifier',
		'pt_ip_address',
		'pb_transition_type',
		'pb_success_url',
		'pd_collect_shipping_information',
		'pd_display_items',
		'pt_custom_name_1',
		'pt_custom_value_1',
		'pt_custom_name_2',
		'pt_custom_value_2',
		'pt_custom_name_3',
		'pt_custom_value_3',
	);

	/** @var array */
	public static $callbackStoreAllowlist = array(
		'pi_response_status',
		'pt_transaction_amount',
		'pt_currency',
		'pt_currency_code',
		'pt_authorization_code',
		'pt_order_id',
		'pt_gateway_account',
		'pt_account_code_1',
	);

	/** @var array */
	private static $sensitiveKeyNeedles = array(
		'card-number',
		'card_number',
		'card-cvv',
		'card_cvv',
		'card-exp',
		'card_exp',
		'cc-exp',
		'cc_exp',
		'expiry',
		'expire',
		'pt_card_number',
		'cc_number',
		'cc_cvv',
		'publisher-password',
		'verification-hash',
		'response-hash',
		'resphash',
		'magstripe',
		'track1',
		'track2',
		'pan',
		'abc_return_token',
		'session',
	);

	/**
	 * @param string $value
	 * @return string
	 */
	public static function digitsOnly($value) {
		if (!is_scalar($value)) {
			return '';
		}
		return preg_replace('/\D/', '', (string)$value);
	}

	/**
	 * @param mixed $amount
	 * @return string
	 */
	public static function formatAmount($amount) {
		$cents = self::parseAmountToCents($amount, true);
		if ($cents === null) {
			return '0.00';
		}
		return number_format($cents / 100, 2, '.', '');
	}

	/**
	 * @param mixed $amount
	 * @return int
	 */
	public static function toCents($amount) {
		$cents = self::parseAmountToCents($amount, true);
		return $cents === null ? 0 : $cents;
	}

	/**
	 * @param mixed $a
	 * @param mixed $b
	 * @return bool
	 */
	public static function amountsEqual($a, $b) {
		$aCents = self::parseAmountToCents($a, false);
		$bCents = self::parseAmountToCents($b, false);
		return $aCents !== null && $bCents !== null && $aCents === $bCents;
	}

	/**
	 * Parse a non-negative decimal amount without accepting exponent notation,
	 * non-finite values, trailing text, or array-shaped input.
	 *
	 * @param mixed $amount
	 * @param bool  $roundExtraDecimals
	 * @return int|null
	 */
	private static function parseAmountToCents($amount, $roundExtraDecimals) {
		if (!is_scalar($amount)) {
			return null;
		}
		$amount = trim((string)$amount);
		$pattern = $roundExtraDecimals
			? '/^\d{1,9}(?:\.\d{1,6})?$/D'
			: '/^\d{1,9}(?:\.\d{1,2})?$/D';
		if ($amount === '' || !preg_match($pattern, $amount)) {
			return null;
		}
		$value = (float)$amount;
		if (!is_finite($value) || $value < 0 || $value > 999999999.99) {
			return null;
		}
		return (int)round($value * 100);
	}

	/**
	 * @param string $value
	 * @param int    $max
	 * @return string
	 */
	public static function clip($value, $max) {
		if (!is_scalar($value)) {
			return '';
		}
		$value = (string)$value;
		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, (int)$max, 'UTF-8');
		}
		return substr($value, 0, (int)$max);
	}

	/**
	 * @param string $value
	 * @param int    $max
	 * @return string
	 */
	public static function sanitizeText($value, $max = self::TEXT_MAX) {
		if (!is_scalar($value)) {
			return '';
		}
		$value = trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
		$value = strip_tags($value);
		$value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
		return self::clip($value, $max);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function sanitizeName($name) {
		if (!is_scalar($name)) {
			return '';
		}
		$name = trim(html_entity_decode((string)$name, ENT_QUOTES, 'UTF-8'));
		$name = strip_tags($name);
		$name = preg_replace('/[^\p{L}\p{N} .,\-\']/u', '', $name);
		$name = preg_replace('/\s+/', ' ', $name);
		return self::clip($name, self::NAME_MAX);
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public static function sanitizeEmail($email) {
		if (!is_scalar($email)) {
			return '';
		}
		$email = trim((string)$email);
		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return '';
		}
		return self::clip($email, 254);
	}

	/**
	 * @param string $phone
	 * @return string
	 */
	public static function sanitizePhone($phone) {
		if (!is_scalar($phone)) {
			return '';
		}
		$phone = preg_replace('/[^\d+\-\.\(\) ]/', '', (string)$phone);
		return self::clip(trim($phone), 32);
	}

	/**
	 * @param string $country
	 * @return string
	 */
	public static function sanitizeCountry($country) {
		if (!is_scalar($country)) {
			return '';
		}
		$country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$country));
		if (strlen($country) === 2) {
			return $country;
		}
		return self::clip($country, 64);
	}

	/**
	 * @param string $currency
	 * @param array  $allowed
	 * @return string
	 */
	public static function sanitizeCurrency($currency, array $allowed) {
		if (!is_scalar($currency)) {
			return '';
		}
		$currency = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$currency));
		$allowed = array_map('strtoupper', $allowed);
		if (in_array($currency, $allowed, true)) {
			return $currency;
		}
		return '';
	}

	/**
	 * @param array $fields
	 * @return array
	 */
	public static function allowlistHostedFields(array $fields) {
		$out = array();
		foreach (self::$hostedFieldAllowlist as $key) {
			if (!array_key_exists($key, $fields)) {
				continue;
			}
			$value = $fields[$key];
			if ($value === null || $value === '' || !is_scalar($value)) {
				continue;
			}
			$out[$key] = (string)$value;
		}
		return $out;
	}

	/**
	 * @param array $received
	 * @return array
	 */
	public static function allowlistStoreFields(array $received) {
		$out = array();
		foreach (self::$callbackStoreAllowlist as $key) {
			if (!isset($received[$key]) || !is_scalar($received[$key])) {
				continue;
			}
			$out[$key] = self::clip(strip_tags((string)$received[$key]), 255);
		}
		return $out;
	}

	/**
	 * Verify PlugnPay's outbound response hash using the server-only secret
	 * configured in Security Administration. SHA-256 is preferred; 32-character
	 * MD5 hashes remain supported for legacy PlugnPay account configurations.
	 *
	 * @param mixed $secret
	 * @param mixed $account
	 * @param mixed $orderId
	 * @param mixed $amount
	 * @param mixed $providedHash
	 * @return bool
	 */
	public static function isValidResponseHash($secret, $account, $orderId, $amount, $providedHash) {
		foreach (array($secret, $account, $orderId, $amount, $providedHash) as $value) {
			if (!is_scalar($value)) {
				return false;
			}
		}

		$secret = (string)$secret;
		$account = trim((string)$account);
		$orderId = trim((string)$orderId);
		$amount = trim((string)$amount);
		$providedHash = strtolower(trim((string)$providedHash));

		if ($secret === '' || $account === ''
			|| !preg_match('/^\d{1,25}$/D', $orderId)
			|| self::parseAmountToCents($amount, false) === null
		) {
			return false;
		}

		$algorithm = '';
		if (preg_match('/^[a-f0-9]{64}$/D', $providedHash)) {
			$algorithm = 'sha256';
		} elseif (preg_match('/^[a-f0-9]{32}$/D', $providedHash)) {
			$algorithm = 'md5';
		} else {
			return false;
		}

		$expectedHash = hash($algorithm, $secret . $account . $orderId . self::formatAmount($amount));
		return self::tokenEquals($expectedHash, $providedHash);
	}

	/**
	 * @return string
	 */
	public static function newReturnToken() {
		return bin2hex(random_bytes(32));
	}

	/**
	 * HMAC over order_id|amount|account|currency using the one-time token as key.
	 *
	 * @param string $token
	 * @param string $orderId
	 * @param string $amount
	 * @param string $account
	 * @param string $currency
	 * @return string
	 */
	public static function returnMac($token, $orderId, $amount, $account, $currency) {
		$payload = (string)$orderId . '|' . self::formatAmount($amount) . '|' . (string)$account . '|' . strtoupper((string)$currency);
		return hash_hmac('sha256', $payload, (string)$token);
	}

	/**
	 * @param string $known
	 * @param string $given
	 * @return bool
	 */
	public static function tokenEquals($known, $given) {
		$known = (string)$known;
		$given = (string)$given;
		if ($known === '' || $given === '') {
			return false;
		}
		if (function_exists('hash_equals')) {
			return hash_equals($known, $given);
		}
		return ($known === $given);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public static function redact(array $data) {
		$out = array();
		foreach ($data as $key => $value) {
			$keyStr = (string)$key;
			if (self::isSensitiveKey($keyStr)) {
				$out[$key] = '***REDACTED***';
				continue;
			}
			if (is_array($value)) {
				$out[$key] = self::redact($value);
				continue;
			}
			if (!is_scalar($value) && $value !== null) {
				$out[$key] = '***REDACTED***';
				continue;
			}
			$str = (string)$value;
			$digits = self::digitsOnly($str);
			$len = strlen($digits);
			if ($len >= 13 && $len <= 19) {
				$out[$key] = '***REDACTED***';
				continue;
			}
			$out[$key] = $str;
		}
		return $out;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function isSensitiveKey($key) {
		$normalized = strtolower(str_replace('_', '-', (string)$key));
		foreach (self::$sensitiveKeyNeedles as $needle) {
			$needle = strtolower(str_replace('_', '-', $needle));
			if ($normalized === $needle || strpos($normalized, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $server
	 * @return bool
	 */
	public static function isHttpsRequest(array $server) {
		$https = isset($server['HTTPS']) ? (string)$server['HTTPS'] : '';
		if ($https !== '' && strtolower($https) !== 'off' && $https !== '0') {
			return true;
		}
		$scheme = isset($server['REQUEST_SCHEME']) ? strtolower((string)$server['REQUEST_SCHEME']) : '';
		if ($scheme === 'https') {
			return true;
		}
		$port = isset($server['SERVER_PORT']) ? (int)$server['SERVER_PORT'] : 0;
		return ($port === 443);
	}
}
