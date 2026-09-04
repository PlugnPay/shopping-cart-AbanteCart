<?php
/*------------------------------------------------------------------------------
  PlugnPay Smart Screens v2 — storefront payment controller for AbanteCart 1.4.x

  Redirects to https://pay1.plugnpay.com/pay/ (authorization-only).
  Pattern aligned with Zen Cart PlugnPaySs2 / PrestaShop plugnpayss2.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
	exit;
}

class ControllerResponsesExtensionPlugnpaySs2 extends AController {

	const GATEWAY_URL = 'https://pay1.plugnpay.com/pay/';

	public function main() {
		$this->extensions->hk_InitData($this, __FUNCTION__);
		$this->loadLanguage('plugnpay_ss2/plugnpay_ss2');
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Filter.php');

		$server = is_array($this->request->server) ? $this->request->server : array();
		if (!PnPSs2Filter::isHttpsRequest($server)) {
			$this->view->batchAssign(array(
				'error' => $this->language->get('error_unknown'),
			));
			$this->processTemplate('responses/plugnpay_ss2.tpl');
			return;
		}

		$this->load->model('checkout/order');

		$order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;
		$order_info = $order_id ? $this->model_checkout_order->getOrder($order_id) : null;
		if (!$order_info) {
			$this->view->batchAssign(array(
				'error' => $this->language->get('error_unknown'),
			));
			$this->processTemplate('responses/plugnpay_ss2.tpl');
			return;
		}
		if (!$this->isThisPaymentMethod($order_info) || (int)$order_info['order_status_id'] !== 0) {
			$this->view->batchAssign(array(
				'error' => $this->language->get('error_unknown'),
			));
			$this->processTemplate('responses/plugnpay_ss2.tpl');
			return;
		}

		$submit_data = $this->buildHostedFields($order_info);
		$this->storeExpectedReturn($submit_data, $order_id);
		$this->debugLog('Submit-Data', array(
			'order_id' => $order_id,
			'pt_transaction_amount' => isset($submit_data['pt_transaction_amount']) ? $submit_data['pt_transaction_amount'] : '',
			'pt_currency' => isset($submit_data['pt_currency']) ? $submit_data['pt_currency'] : '',
			'pt_gateway_account' => isset($submit_data['pt_gateway_account']) ? $submit_data['pt_gateway_account'] : '',
		));

		$form = new AForm();
		$form->setForm(array('form_name' => 'plugnpay_ss2'));
		$data = array();
		$data['text_wait'] = $this->language->get('text_redirecting');
		$data['text_redirecting'] = $this->language->get('text_redirecting');
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['auto_submit'] = true;

		$data['form']['form_open'] = $form->getFieldHtml(array(
			'type' => 'form',
			'name' => 'plugnpay_ss2',
			'action' => self::GATEWAY_URL,
			'attr' => 'id="plugnpay-ss2-hosted-form" class="form-horizontal"',
			'enctype' => 'application/x-www-form-urlencoded',
		));

		$data['form']['fields'] = array();
		foreach (PnPSs2Filter::allowlistHostedFields($submit_data) as $key => $value) {
			$data['form']['fields'][$key] = $form->getFieldHtml(array(
				'type' => 'hidden',
				'name' => $key,
				'value' => $value,
			));
		}

		$data['form']['submit'] = $form->getFieldHtml(array(
			'type' => 'submit',
			'name' => $this->language->get('button_confirm'),
		));

		$this->view->batchAssign($data);
		$this->extensions->hk_UpdateData($this, __FUNCTION__);
		$this->processTemplate('responses/plugnpay_ss2.tpl');
	}

	/**
	 * Return POST from Smart Screens (pb_success_url).
	 */
	public function callback() {
		$this->loadLanguage('plugnpay_ss2/plugnpay_ss2');
		$this->load->model('checkout/order');
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Filter.php');
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Logger.php');

		$server = is_array($this->request->server) ? $this->request->server : array();
		if (!PnPSs2Filter::isHttpsRequest($server)) {
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}
		if (!$this->request->is_POST()) {
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}

		$authorize = is_array($this->request->post) ? $this->request->post : array();
		unset($authorize['btn_submit_x'], $authorize['btn_submit_y']);
		$this->debugLog('Response-Data', PnPSs2Filter::allowlistStoreFields($authorize));

		$statusValue = isset($authorize['pi_response_status']) && is_scalar($authorize['pi_response_status'])
			? (string)$authorize['pi_response_status']
			: '';
		$status = strtolower(trim($statusValue));
		if (!in_array($status, array('success', 'badcard', 'fraud', 'problem'), true)) {
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}

		$order_id = $this->resolveOrderId();
		$order_info = $order_id ? $this->model_checkout_order->getOrder($order_id) : null;
		if (!$order_info) {
			$this->debugLog('Order resolve failed', array(
				'session_order_id' => isset($this->session->data['order_id']) ? $this->session->data['order_id'] : '',
			));
			$this->failToCheckout($this->language->get('error_session'));
			return;
		}

		$this->session->data['order_id'] = $order_id;
		if (!$this->isThisPaymentMethod($order_info)) {
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}

		if ($status === 'success') {
			$this->processSuccess($authorize, $order_id, $order_info);
			return;
		}

		if (!$this->verifyReturnBinding($authorize, $order_id)) {
			$this->failToCheckout($this->language->get('error_session'));
			return;
		}
		$this->clearExpectedReturn();
		$this->storeTransactionRow($status !== '' ? $status : 'error', $authorize, array(), $order_id);

		if ($status === 'badcard' || $status === 'fraud') {
			$this->failToCheckout($this->language->get('warning_declined'));
			return;
		}

		$this->failToCheckout($this->language->get('error_processing'));
	}

	/**
	 * @param array $order_info
	 * @return array
	 */
	protected function buildHostedFields($order_info) {
		$allowed_currencies = array('USD', 'CAD', 'GBP', 'EUR', 'AUD', 'NZD');
		$gateway_currency = PnPSs2Filter::sanitizeCurrency(
			(string)$this->config->get('plugnpay_ss2_currency'),
			$allowed_currencies
		);
		if ($gateway_currency === '') {
			$gateway_currency = 'USD';
		}

		$amount = (float)$this->currency->format($order_info['total'], $order_info['currency'], 1.00000, false);
		$currency = !empty($order_info['currency']) ? (string)$order_info['currency'] : '';
		$store_currency = PnPSs2Filter::sanitizeCurrency($currency, $allowed_currencies);
		if ($store_currency === '') {
			$store_currency = $gateway_currency;
		}

		if ($store_currency !== $gateway_currency && method_exists($this->currency, 'convert')) {
			$amount = round((float)$this->currency->convert($amount, $store_currency, $gateway_currency), 2);
			$currency = $gateway_currency;
		} else {
			$amount = round($amount, 2);
			$currency = $store_currency;
		}

		$order_id = (string)(int)$order_info['order_id'];
		$token = PnPSs2Filter::newReturnToken();
		$account = PnPSs2Filter::clip((string)$this->config->get('plugnpay_ss2_login'), 64);
		$amount_fmt = PnPSs2Filter::formatAmount($amount);
		$mac = PnPSs2Filter::returnMac($token, $order_id, $amount_fmt, $account, $currency);

		$success_url = $this->html->getSecureURL(
			'r/extension/plugnpay_ss2/callback'
		);

		$billing_country = !empty($order_info['payment_iso_code_2'])
			? PnPSs2Filter::sanitizeCountry($order_info['payment_iso_code_2'])
			: PnPSs2Filter::sanitizeCountry($order_info['payment_country']);

		$fields = array(
			'pt_gateway_account' => $account,
			'pt_transaction_amount' => $amount_fmt,
			'pt_currency' => $currency,
			'pt_currency_code' => $currency,
			'pb_post_auth' => 'no',
			'pt_account_code_1' => $order_id,
			'pt_billing_company' => PnPSs2Filter::sanitizeText($order_info['payment_company'], 64),
			'pt_payment_name' => PnPSs2Filter::sanitizeName(
				trim((string)$order_info['payment_firstname'] . ' ' . (string)$order_info['payment_lastname'])
			),
			'pt_billing_address_1' => PnPSs2Filter::sanitizeText($order_info['payment_address_1']),
			'pt_billing_city' => PnPSs2Filter::sanitizeText($order_info['payment_city'], 64),
			'pt_billing_state' => PnPSs2Filter::sanitizeText($order_info['payment_zone'], 64),
			'pt_billing_postal_code' => PnPSs2Filter::sanitizeText($order_info['payment_postcode'], 16),
			'pt_billing_country' => $billing_country,
			'pt_billing_phone_number' => PnPSs2Filter::sanitizePhone($order_info['telephone']),
			'pt_billing_email_address' => PnPSs2Filter::sanitizeEmail($order_info['email']),
			'pt_client_identifier' => 'AbanteCart_SS2',
			'pt_ip_address' => PnPSs2Filter::clip((string)$this->request->getRemoteIP(), 45),
			'pb_transition_type' => 'post',
			'pb_success_url' => $success_url,
			'pd_collect_shipping_information' => 'no',
			'pd_display_items' => 'no',
			'pt_custom_name_1' => 'abc_order_id',
			'pt_custom_value_1' => $order_id,
			'pt_custom_name_2' => 'abc_return_mac',
			'pt_custom_value_2' => $mac,
			'pt_custom_name_3' => 'abc_return_token',
			'pt_custom_value_3' => $token,
		);

		$this->session->data['plugnpay_ss2_return_token'] = $token;
		$this->session->data['plugnpay_ss2_return_mac'] = $mac;

		return PnPSs2Filter::allowlistHostedFields($fields);
	}

	/**
	 * @param array $submit_data
	 * @param int   $order_id
	 */
	protected function storeExpectedReturn(array $submit_data, $order_id) {
		$this->session->data['plugnpay_ss2_expected_amount'] = $submit_data['pt_transaction_amount'];
		$this->session->data['plugnpay_ss2_expected_account'] = $submit_data['pt_gateway_account'];
		$this->session->data['plugnpay_ss2_expected_currency'] = $submit_data['pt_currency'];
		$this->session->data['plugnpay_ss2_expected_order_id'] = (string)(int)$order_id;
		$this->session->data['plugnpay_ss2_submit_data'] = array(
			'pt_transaction_amount' => $submit_data['pt_transaction_amount'],
			'pt_currency' => $submit_data['pt_currency'],
			'pt_gateway_account' => $submit_data['pt_gateway_account'],
			'pt_account_code_1' => $submit_data['pt_account_code_1'],
		);
	}

	/**
	 * @param array $authorize
	 * @param int   $order_id
	 * @param array $order_info
	 */
	protected function processSuccess(array $authorize, $order_id, $order_info) {
		if (!$this->isThisPaymentMethod($order_info)) {
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}

		if (!$this->verifyReturnBinding($authorize, $order_id)) {
			$this->failToCheckout($this->language->get('error_session'));
			return;
		}

		$returned_amount = isset($authorize['pt_transaction_amount']) && is_scalar($authorize['pt_transaction_amount'])
			? (string)$authorize['pt_transaction_amount']
			: '';
		$expected_amount = isset($this->session->data['plugnpay_ss2_expected_amount'])
			? (string)$this->session->data['plugnpay_ss2_expected_amount']
			: '';
		$expected_currency = isset($this->session->data['plugnpay_ss2_expected_currency'])
			? strtoupper((string)$this->session->data['plugnpay_ss2_expected_currency'])
			: '';
		$expected_account = isset($this->session->data['plugnpay_ss2_expected_account'])
			? (string)$this->session->data['plugnpay_ss2_expected_account']
			: '';
		$submitted_data = isset($this->session->data['plugnpay_ss2_submit_data'])
			&& is_array($this->session->data['plugnpay_ss2_submit_data'])
			? $this->session->data['plugnpay_ss2_submit_data']
			: array();

		// A correctly session-bound callback is one-time, whether gateway proof
		// and transaction fields below validate or not.
		$this->clearExpectedReturn();

		if ($returned_amount === '' || $expected_amount === '' || !PnPSs2Filter::amountsEqual($returned_amount, $expected_amount)) {
			$this->debugLog('Amount mismatch', array(
				'expected' => $expected_amount,
				'returned' => PnPSs2Filter::formatAmount($returned_amount),
			));
			$this->failToCheckout($this->language->get('error_amount_mismatch'));
			return;
		}

		$returnedCurrencyValue = isset($authorize['pt_currency']) && is_scalar($authorize['pt_currency'])
			? (string)$authorize['pt_currency']
			: '';
		$returned_currency = strtoupper(preg_replace('/[^A-Za-z]/', '', $returnedCurrencyValue));
		if ($returned_currency === '' || $expected_currency === '' || $returned_currency !== $expected_currency) {
			$this->debugLog('Currency mismatch', array(
				'expected' => $expected_currency,
				'returned' => $returned_currency,
			));
			$this->failToCheckout($this->language->get('error_amount_mismatch'));
			return;
		}

		$returned_account = isset($authorize['pt_gateway_account']) && is_scalar($authorize['pt_gateway_account'])
			? trim((string)$authorize['pt_gateway_account'])
			: '';
		if ($returned_account === '' || $expected_account === '' || strcasecmp($returned_account, $expected_account) !== 0) {
			$this->debugLog('Account mismatch', array(
				'expected' => $expected_account,
				'returned' => $returned_account,
			));
			$this->failToCheckout($this->language->get('error_account_mismatch'));
			return;
		}

		$returned_order_id = isset($authorize['pt_account_code_1']) && is_scalar($authorize['pt_account_code_1'])
			? trim((string)$authorize['pt_account_code_1'])
			: '';
		if ($returned_order_id === '' || $returned_order_id !== (string)(int)$order_id) {
			$this->debugLog('Gateway order binding mismatch', array('order_id' => $order_id));
			$this->failToCheckout($this->language->get('error_session'));
			return;
		}

		$auth_code = $this->safeHistoryToken(isset($authorize['pt_authorization_code']) ? $authorize['pt_authorization_code'] : '');
		$txn_id = $this->safeHistoryToken(isset($authorize['pt_order_id']) ? $authorize['pt_order_id'] : '');
		$response_hash = '';
		if (isset($authorize['pt_transaction_response_hash']) && is_scalar($authorize['pt_transaction_response_hash'])) {
			$response_hash = (string)$authorize['pt_transaction_response_hash'];
		} elseif (isset($authorize['resphash']) && is_scalar($authorize['resphash'])) {
			$response_hash = (string)$authorize['resphash'];
		}
		if (!PnPSs2Filter::isValidResponseHash(
			(string)$this->config->get('plugnpay_ss2_response_hash'),
			$expected_account,
			$txn_id,
			$returned_amount,
			$response_hash
		)) {
			$this->debugLog('Gateway response hash verification failed', array('order_id' => $order_id));
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}

		$message = 'Credit Card authorization via PlugnPay Smart Screens v2. AUTH: ' . $auth_code
			. ' TransID/orderID: ' . $txn_id
			. ' (auth-only; settle in PlugnPay Admin)';

		$pending_id = 1;
		if (is_object($this->order_status) && method_exists($this->order_status, 'getStatusByTextId')) {
			$pending_id = (int)$this->order_status->getStatusByTextId('pending');
			if ($pending_id <= 0) {
				$pending_id = 1;
			}
		}

		if ((int)$order_info['order_status_id'] == 0) {
			$this->model_checkout_order->confirm($order_id, $pending_id);
			$this->model_checkout_order->update($order_id, $pending_id, $message, false);
			$this->storeTransactionRow('success', $authorize, $submitted_data, $order_id);
		}

		redirect($this->html->getSecureURL('checkout/finalize', '&order_id=' . (int)$order_id));
	}

	/**
	 * @param array $authorize
	 * @param int   $order_id
	 * @return bool
	 */
	protected function verifyReturnBinding(array $authorize, $order_id) {
		$expected_token = isset($this->session->data['plugnpay_ss2_return_token'])
			? (string)$this->session->data['plugnpay_ss2_return_token']
			: '';
		$expected_mac = isset($this->session->data['plugnpay_ss2_return_mac'])
			? (string)$this->session->data['plugnpay_ss2_return_mac']
			: '';
		$returned_token = $this->extractCustomValue($authorize, 'abc_return_token');
		$returned_mac = $this->extractCustomValue($authorize, 'abc_return_mac');

		if (!PnPSs2Filter::tokenEquals($expected_token, $returned_token)
			|| !PnPSs2Filter::tokenEquals($expected_mac, $returned_mac)
		) {
			$this->debugLog('Return token/mac mismatch', array('order_id' => $order_id));
			return false;
		}

		$expected_order = isset($this->session->data['plugnpay_ss2_expected_order_id'])
			? (int)$this->session->data['plugnpay_ss2_expected_order_id']
			: 0;
		if ($expected_order <= 0 || $expected_order !== (int)$order_id) {
			$this->debugLog('Order binding mismatch', array(
				'expected' => $expected_order,
				'resolved' => $order_id,
			));
			return false;
		}

		$recomputed = PnPSs2Filter::returnMac(
			$expected_token,
			(string)$order_id,
			isset($this->session->data['plugnpay_ss2_expected_amount']) ? $this->session->data['plugnpay_ss2_expected_amount'] : '',
			isset($this->session->data['plugnpay_ss2_expected_account']) ? $this->session->data['plugnpay_ss2_expected_account'] : '',
			isset($this->session->data['plugnpay_ss2_expected_currency']) ? $this->session->data['plugnpay_ss2_expected_currency'] : ''
		);
		if (!PnPSs2Filter::tokenEquals($expected_mac, $recomputed)) {
			$this->debugLog('Return mac recompute failed', array('order_id' => $order_id));
			return false;
		}

		return true;
	}

	/**
	 * Session-bound order id only (ignore attacker-controlled POST/GET ids).
	 *
	 * @return int
	 */
	protected function resolveOrderId() {
		if (!empty($this->session->data['plugnpay_ss2_expected_order_id'])) {
			return (int)$this->session->data['plugnpay_ss2_expected_order_id'];
		}
		if (!empty($this->session->data['order_id'])) {
			return (int)$this->session->data['order_id'];
		}
		return 0;
	}

	/**
	 * @param array  $response
	 * @param string $name
	 * @return string
	 */
	protected function extractCustomValue(array $response, $name) {
		$name = strtolower((string)$name);
		foreach ($response as $key => $value) {
			if (!preg_match('/^pt_custom_name_(\d+)$/i', (string)$key, $m)) {
				continue;
			}
			if (!is_scalar($value)) {
				continue;
			}
			if (strtolower(trim((string)$value)) !== $name) {
				continue;
			}
			$valueKey = 'pt_custom_value_' . $m[1];
			if (isset($response[$valueKey]) && !is_array($response[$valueKey])) {
				return trim((string)$response[$valueKey]);
			}
		}
		return '';
	}

	/**
	 * @param string $status
	 * @param array  $received
	 * @param array  $sent
	 * @param int    $order_id
	 */
	protected function storeTransactionRow($status, array $received = array(), array $sent = array(), $order_id = 0) {
		if ((string)$this->config->get('plugnpay_ss2_store_data') !== '1') {
			return;
		}

		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Logger.php');
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Filter.php');
		$sent_clean = PnPSs2Filter::allowlistStoreFields($sent);
		$recv_clean = PnPSs2Filter::allowlistStoreFields($received);

		$customer_id = $this->customer->isLogged() ? (string)(int)$this->customer->getId() : '0';
		$auth_code = $this->safeHistoryToken(isset($received['pt_authorization_code']) ? $received['pt_authorization_code'] : '');
		$txn_id = $this->safeHistoryToken(isset($received['pt_order_id']) ? $received['pt_order_id'] : '');
		$err = $this->safeHistoryToken((string)$status);

		$sql = "INSERT INTO `" . $this->db->table('plugnpay_ss2') . "` SET "
			. "`customer_id` = '" . $this->db->escape($customer_id) . "', "
			. "`order_id` = '" . $this->db->escape((string)(int)$order_id) . "', "
			. "`response_code` = '" . $this->db->escape(PnPSs2Filter::clip((string)$status, 32)) . "', "
			. "`response_text` = '" . $this->db->escape($err) . "', "
			. "`authorization_type` = '" . $this->db->escape($auth_code) . "', "
			. "`transaction_id` = '" . $this->db->escape($txn_id) . "', "
			. "`sent` = '" . $this->db->escape(json_encode($sent_clean)) . "', "
			. "`received` = '" . $this->db->escape(json_encode($recv_clean)) . "', "
			. "`time` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'";

		try {
			$this->db->query($sql);
		} catch (Exception $e) {
			$this->debugLog('DB store failed', array('error' => 'query failed'));
		}
	}

	/**
	 * @param string $message
	 * @param array  $context
	 */
	protected function debugLog($message, array $context = array()) {
		if ((string)$this->config->get('plugnpay_ss2_debugging') !== '1') {
			return;
		}
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Logger.php');
		$log_dir = defined('DIR_LOGS') ? DIR_LOGS : (defined('DIR_SYSTEM') ? DIR_SYSTEM . 'logs' : '');
		$logger = new PnPSs2Logger($log_dir, true);
		$logger->log($message, $context);
	}

	/**
	 * @param array $order_info
	 * @return bool
	 */
	protected function isThisPaymentMethod(array $order_info) {
		return isset($order_info['payment_method_key'])
			&& (string)$order_info['payment_method_key'] === 'plugnpay_ss2';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	protected function safeHistoryToken($value) {
		if (!is_scalar($value)) {
			return '';
		}
		return preg_replace('/[^A-Za-z0-9\-_]/', '', PnPSs2Filter::clip((string)$value, 64));
	}

	protected function clearExpectedReturn() {
		unset(
			$this->session->data['plugnpay_ss2_expected_amount'],
			$this->session->data['plugnpay_ss2_expected_account'],
			$this->session->data['plugnpay_ss2_expected_currency'],
			$this->session->data['plugnpay_ss2_expected_order_id'],
			$this->session->data['plugnpay_ss2_submit_data'],
			$this->session->data['plugnpay_ss2_return_token'],
			$this->session->data['plugnpay_ss2_return_mac']
		);
	}

	/**
	 * @param string $message
	 */
	protected function failToCheckout($message) {
		$this->session->data['error'] = $message;
		redirect($this->html->getSecureURL('checkout/fast_checkout'));
	}
}
