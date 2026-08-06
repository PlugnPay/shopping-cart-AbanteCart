<?php
/**
 * PlugnPay Remote API debug logger with secret redaction.
 *
 * PHP 8.2+ / AbanteCart 1.4.x.
 */
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

class PnPLogger {
	/** @var array */
	private static $sensitiveKeys = array(
		'card-number',
		'card_number',
		'card-cvv',
		'card_cvv',
		'publisher-password',
		'publisher_password',
		'cc_number',
		'cc_cvv',
		'cc_cvv2',
	);

	private $logDir;
	private $enabled;

	/**
	 * @param string $logDir
	 * @param bool   $enabled
	 */
	public function __construct($logDir, $enabled = false) {
		$this->logDir = rtrim((string)$logDir, '/\\');
		$this->enabled = (bool)$enabled;
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		return $this->enabled;
	}

	/**
	 * @param string $message
	 * @param array  $context
	 */
	public function log($message, array $context = array()) {
		if (!$this->enabled) {
			return;
		}

		$line = date('Y-m-d H:i:s') . ' ' . $message;
		if (!empty($context)) {
			$line .= "\n" . print_r($this->sanitize($context), true);
		}
		$line .= "\n----------------------------------------\n";

		$file = $this->logDir . '/plugnpay_api_' . date('Ymd') . '.log';
		@file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function sanitize(array $data) {
		$out = array();
		foreach ($data as $key => $value) {
			$keyStr = (string)$key;
			if ($this->isSensitiveKey($keyStr)) {
				if (stripos($keyStr, 'number') !== false && is_string($value) && strlen($value) >= 4) {
					$out[$key] = str_repeat('X', max(0, strlen($value) - 4)) . substr($value, -4);
				} else {
					$out[$key] = '***REDACTED***';
				}
				continue;
			}
			if (is_array($value)) {
				$out[$key] = $this->sanitize($value);
			} else {
				$out[$key] = $value;
			}
		}
		return $out;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	private function isSensitiveKey($key) {
		$normalized = strtolower(str_replace('_', '-', $key));
		foreach (self::$sensitiveKeys as $sensitive) {
			if ($normalized === strtolower(str_replace('_', '-', $sensitive))) {
				return true;
			}
		}
		return false;
	}
}
