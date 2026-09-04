<?php
/**
 * Strict input filtering, allowlists, and CHD redaction for PlugnPay Remote API.
 *
 * PHP 8.2+ / AbanteCart 1.4.x.
 */
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
	exit;
}

class PnPFilter {
	const PAN_MIN = 13;
	const PAN_MAX = 19;
	const NAME_MAX = 64;
	const TEXT_MAX = 128;
	const DESC_MAX = 255;
	const LINE_ITEM_MAX = 15;

	/** @var array */
	public static $authorizeFieldAllowlist = array(
		'mode',
		'paymethod',
		'client',
		'authtype',
		'easycart',
		'shipinfo',
		'card-amount',
		'currency',
		'card-number',
		'card-exp',
		'card-name',
		'card-cvv',
		'card-company',
		'card-address1',
		'card-address2',
		'card-city',
		'card-state',
		'card-zip',
		'card-country',
		'phone',
		'email',
		'ipaddress',
		'acct_code',
		'dontsndmail',
		'publisher-email',
		'notify-email',
		'shipname',
		'address1',
		'address2',
		'city',
		'state',
		'zip',
		'country',
	);

	/** @var array */
	public static $responseFieldAllowlist = array(
		'FinalStatus',
		'success',
		'MErrMsg',
		'orderID',
		'orderid',
		'auth-code',
		'auth_code',
		'resp-code',
		'resp_code',
		'avs-code',
		'avs_code',
		'cvvresp',
	);

	/** @var array */
	public static $logResponseAllowlist = array(
		'FinalStatus',
		'orderID',
		'orderid',
		'resp-code',
		'resp_code',
		'avs-code',
		'avs_code',
		'cvvresp',
	);

	/** @var array */
	private static $sensitiveKeyNeedles = array(
		'card-number',
		'card_number',
		'card-cvv',
		'card_cvv',
		'card-cvv2',
		'card-exp',
		'card_exp',
		'publisher-password',
		'publisher_password',
		'cc_number',
		'cc_cvv',
		'cc_cvv2',
		'magstripe',
		'track1',
		'track2',
		'track-1',
		'track-2',
		'account-number',
		'pan',
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
	 * @param string $pan
	 * @return string
	 */
	public static function normalizePan($pan) {
		if (!is_scalar($pan)) {
			return '';
		}
		$pan = trim((string)$pan);
		if ($pan === '' || !preg_match('/^[0-9 -]+$/D', $pan)) {
			return '';
		}
		return self::digitsOnly($pan);
	}

	/**
	 * @param string $pan
	 * @return bool
	 */
	public static function isValidPan($pan) {
		$pan = self::normalizePan($pan);
		$len = strlen($pan);
		if ($len < self::PAN_MIN || $len > self::PAN_MAX) {
			return false;
		}
		return self::luhnCheck($pan);
	}

	/**
	 * @param string $number
	 * @return bool
	 */
	public static function luhnCheck($number) {
		$number = self::digitsOnly($number);
		if ($number === '') {
			return false;
		}
		$sum = 0;
		$alt = false;
		for ($i = strlen($number) - 1; $i >= 0; $i--) {
			$n = (int)$number[$i];
			if ($alt) {
				$n *= 2;
				if ($n > 9) {
					$n -= 9;
				}
			}
			$sum += $n;
			$alt = !$alt;
		}
		return ($sum % 10) === 0;
	}

	/**
	 * @param string $cvv
	 * @return string
	 */
	public static function normalizeCvv($cvv) {
		if (!is_scalar($cvv)) {
			return '';
		}
		$cvv = trim((string)$cvv);
		return preg_match('/^\d{3,4}$/D', $cvv) ? $cvv : '';
	}

	/**
	 * @param string $cvv
	 * @return bool
	 */
	public static function isValidCvv($cvv) {
		$cvv = self::normalizeCvv($cvv);
		$len = strlen($cvv);
		return ($len === 3 || $len === 4);
	}

	/**
	 * @param string $month
	 * @param string $year
	 * @param int    $nowMonth 1-12
	 * @param int    $nowYear  4-digit
	 * @return bool
	 */
	public static function isValidExpiry($month, $year, $nowMonth = 0, $nowYear = 0) {
		if (!is_scalar($month) || !is_scalar($year)) {
			return false;
		}
		$month = (int)$month;
		$yearDigits = self::digitsOnly($year);
		if (strlen($yearDigits) === 2) {
			$year = 2000 + (int)$yearDigits;
		} else {
			$year = (int)$yearDigits;
		}
		if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
			return false;
		}
		if ($nowMonth < 1 || $nowYear < 1) {
			$nowMonth = (int)date('n');
			$nowYear = (int)date('Y');
		}
		if ($year < $nowYear) {
			return false;
		}
		if ($year === $nowYear && $month < $nowMonth) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $month
	 * @param string $year
	 * @return string MM/YY
	 */
	public static function formatCardExp($month, $year) {
		if (!is_scalar($month) || !is_scalar($year)) {
			return '';
		}
		$month = sprintf('%02d', (int)$month);
		$yearDigits = self::digitsOnly($year);
		$yy = substr($yearDigits, -2);
		return $month . '/' . $yy;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function sanitizeCardName($name) {
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
	 * @param mixed $amount
	 * @return string
	 */
	public static function formatAmount($amount) {
		if (!is_scalar($amount) || !is_numeric($amount)) {
			return '0.00';
		}
		$amount = (float)$amount;
		if (!is_finite($amount) || $amount < 0 || $amount > 999999999.99) {
			return '0.00';
		}
		return number_format($amount, 2, '.', '');
	}

	/**
	 * @param mixed $amount
	 * @return int
	 */
	public static function toCents($amount) {
		return (int)round((float)self::formatAmount($amount) * 100);
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
	 * @param array $fields
	 * @return array
	 */
	public static function allowlistAuthorizeFields(array $fields) {
		$allowed = array_fill_keys(self::$authorizeFieldAllowlist, true);
		for ($i = 1; $i <= self::LINE_ITEM_MAX; $i++) {
			$allowed['item' . $i] = true;
			$allowed['cost' . $i] = true;
			$allowed['quantity' . $i] = true;
			$allowed['description' . $i] = true;
		}
		$out = array();
		foreach ($fields as $key => $value) {
			$key = (string)$key;
			if (!isset($allowed[$key])) {
				continue;
			}
			if ($value === null || $value === '') {
				continue;
			}
			if (!is_scalar($value)) {
				continue;
			}
			$out[$key] = (string)$value;
		}
		return $out;
	}

	/**
	 * @param array $parsed
	 * @return array
	 */
	public static function allowlistResponse(array $parsed) {
		$out = array();
		foreach (self::$responseFieldAllowlist as $key) {
			if (!array_key_exists($key, $parsed)) {
				continue;
			}
			$value = $parsed[$key];
			if (!is_scalar($value)) {
				continue;
			}
			$out[$key] = self::clip(strip_tags((string)$value), 255);
		}
		return $out;
	}

	/**
	 * @param array $response
	 * @return bool
	 */
	public static function isApproved(array $response) {
		$final = strtolower(trim(isset($response['FinalStatus']) ? (string)$response['FinalStatus'] : ''));
		return ($final === 'success');
	}

	/**
	 * @param array $response
	 * @return array
	 */
	public static function loggableResponse(array $response) {
		$out = array();
		foreach (self::$logResponseAllowlist as $key) {
			if (isset($response[$key]) && $response[$key] !== '') {
				$out[$key] = $response[$key];
			}
		}
		return self::redact($out);
	}

	/**
	 * Never persist PAN, SAD, or passwords — including last-4 masks.
	 *
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
			if (self::looksLikePan($str) || self::looksLikeCvvValue($keyStr, $str)) {
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
	 * @param string $value
	 * @return bool
	 */
	public static function looksLikePan($value) {
		$digits = self::digitsOnly($value);
		$len = strlen($digits);
		return ($len >= self::PAN_MIN && $len <= self::PAN_MAX && self::luhnCheck($digits));
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return bool
	 */
	public static function looksLikeCvvValue($key, $value) {
		$key = strtolower((string)$key);
		if (strpos($key, 'cvv') === false && strpos($key, 'cvc') === false && strpos($key, 'cid') === false) {
			return false;
		}
		$digits = self::digitsOnly($value);
		$len = strlen($digits);
		return ($len === 3 || $len === 4) && $digits === trim((string)$value);
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
