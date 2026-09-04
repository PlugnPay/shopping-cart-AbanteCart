<?php
/**
 * PlugnPay Remote API debug logger. Never stores PAN, SAD, or passwords.
 *
 * PHP 8.2+ / AbanteCart 1.4.x.
 */
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
	exit;
}

class PnPLogger {
	private $logDir;
	private $enabled;

	/**
	 * @param string $logDir
	 * @param bool   $enabled
	 */
	public function __construct($logDir, $enabled = false) {
		$this->logDir = rtrim((string)$logDir, '/\\');
		$this->enabled = (bool)$enabled
			&& $this->logDir !== ''
			&& is_dir($this->logDir)
			&& is_writable($this->logDir);
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
			if (!class_exists('PnPFilter', false)) {
				require_once(dirname(__FILE__) . '/PnPFilter.php');
			}
			$line .= "\n" . print_r(PnPFilter::redact($context), true);
		}
		$line .= "\n----------------------------------------\n";

		$file = $this->logDir . '/plugnpay_api_' . date('Ymd') . '.log';
		$created = !is_file($file);
		@file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
		if ($created && is_file($file)) {
			@chmod($file, 0600);
		}
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function sanitize(array $data) {
		if (!class_exists('PnPFilter', false)) {
			require_once(dirname(__FILE__) . '/PnPFilter.php');
		}
		return PnPFilter::redact($data);
	}
}
