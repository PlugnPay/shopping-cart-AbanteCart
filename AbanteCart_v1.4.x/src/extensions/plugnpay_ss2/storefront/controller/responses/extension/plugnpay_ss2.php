<?php
/*------------------------------------------------------------------------------
  PlugnPay Smart Screens v2 — storefront payment controller for AbanteCart 1.4.x

  Redirects to https://pay1.plugnpay.com/pay/ (authorization-only).
  Pattern aligned with Zen Cart PlugnPaySs2 / PrestaShop plugnpayss2.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

class ControllerResponsesExtensionPlugnpaySs2 extends AController {

	const GATEWAY_URL = 'https://pay1.plugnpay.com/pay/';

	public function main() {
		$this->extensions->hk_InitData($this, __FUNCTION__);
		$this->loadLanguage('plugnpay_ss2/plugnpay_ss2');
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

		$submit_data = $this->buildHostedFields($order_info);
		$this->storeExpectedReturn($submit_data, $order_id);
		$this->debugLog('Submit-Data', $submit_data);

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
		foreach ($submit_data as $key => $value) {
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
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Logger.php');

		$authorize = $this->request->post;
		unset($authorize['btn_submit_x'], $authorize['btn_submit_y']);
		$this->debugLog('Response-Data', $authorize);

		$status = strtolower(trim(isset($authorize['pi_response_status']) ? (string)$authorize['pi_response_status'] : ''));
		if ($status === '') {
			$this->failToCheckout($this->language->get('error_processing'));
			return;
		}

		$order_id = $this->resolveOrderId($authorize);
		$order_info = $order_id ? $this->model_checkout_order->getOrder($order_id) : null;
		if (!$order_info) {
			$this->debugLog('Order resolve failed', array(
				'session_order_id' => isset($this->session->data['order_id']) ? $this->session->data['order_id'] : '',
				'custom_abc_order_id' => $this->extractCustomValue($authorize, 'abc_order_id'),
			));
			$this->failToCheckout($this->language->get('error_session'));
			return;
		}

		// Keep session order id in sync after cross-site return
		$this->session->data['order_id'] = $order_id;

		if ($status === 'success') {
			$this->processSuccess($authorize, $order_id, $order_info);
			return;
		}

		$gateway_msg = trim(isset($authorize['pi_error_message']) ? (string)$authorize['pi_error_message'] : '');
		$this->storeTransactionRow($status !== '' ? $status : 'error', $authorize, array(), $order_id);

		if ($status === 'badcard' || $status === 'fraud') {
			$msg = $this->language->get('warning_declined');
			if ($gateway_msg !== '') {
				$msg = $gateway_msg . ' ' . $msg;
			}
			$this->failToCheckout($msg);
			return;
		}

		$this->failToCheckout($gateway_msg !== '' ? $gateway_msg : $this->language->get('error_processing'));
	}

	/**
	 * @param array $order_info
	 * @return array
	 */
	protected function buildHostedFields($order_info) {
		$gateway_currency = (string)$this->config->get('plugnpay_ss2_currency');
		if ($gateway_currency === '') {
			$gateway_currency = 'USD';
		}

		$amount = (float)$this->currency->format($order_info['total'], $order_info['currency'], 1.00000, false);
		$currency = !empty($order_info['currency']) ? (string)$order_info['currency'] : $this->currency->getCode();

		// Pre-convert when store currency differs from gateway-supported currency
		if (strtoupper($currency) !== strtoupper($gateway_currency) && method_exists($this->currency, 'convert')) {
			$amount = round((float)$this->currency->convert($amount, $currency, $gateway_currency), 2);
			$currency = $gateway_currency;
		} else {
			$amount = round($amount, 2);
		}

		$session_id = session_id();
		$order_id = (string)$order_info['order_id'];

		// Session restore on cross-site return POST (AbanteCart shared session via GET session_id)
		$success_url = $this->html->getSecureURL(
			'extension/plugnpay_ss2/callback',
			'&session_id=' . urlencode($session_id) . '&order_id=' . urlencode($order_id)
		);

		$billing_country = !empty($order_info['payment_iso_code_2'])
			? $order_info['payment_iso_code_2']
			: html_entity_decode($order_info['payment_country'], ENT_QUOTES, 'UTF-8');

		return array(
			'pt_gateway_account' => (string)$this->config->get('plugnpay_ss2_login'),
			'pt_transaction_amount' => number_format($amount, 2, '.', ''),
			'pt_currency' => $currency,
			'pt_currency_code' => $currency,
			'pb_post_auth' => 'no',
			'pt_account_code_1' => $order_id,
			'pt_billing_company' => html_entity_decode($order_info['payment_company'], ENT_QUOTES, 'UTF-8'),
			'pt_payment_name' => trim(
				html_entity_decode($order_info['payment_firstname'], ENT_QUOTES, 'UTF-8') . ' '
				. html_entity_decode($order_info['payment_lastname'], ENT_QUOTES, 'UTF-8')
			),
			'pt_billing_address_1' => html_entity_decode($order_info['payment_address_1'], ENT_QUOTES, 'UTF-8'),
			'pt_billing_city' => html_entity_decode($order_info['payment_city'], ENT_QUOTES, 'UTF-8'),
			'pt_billing_state' => html_entity_decode($order_info['payment_zone'], ENT_QUOTES, 'UTF-8'),
			'pt_billing_postal_code' => html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8'),
			'pt_billing_country' => $billing_country,
			'pt_billing_phone_number' => $order_info['telephone'],
			'pt_billing_email_address' => $order_info['email'],
			'pt_client_identifier' => 'AbanteCart_SS2',
			'pt_ip_address' => $this->request->getRemoteIP(),
			'pb_transition_type' => 'post',
			'pb_success_url' => $success_url,
			'pd_collect_shipping_information' => 'no',
			'pd_display_items' => 'no',
			'pt_custom_name_1' => 'abcsession',
			'pt_custom_value_1' => $session_id,
			'pt_custom_name_2' => 'abc_order_id',
			'pt_custom_value_2' => $order_id,
		);
	}

	/**
	 * @param array $submit_data
	 * @param int   $order_id
	 */
	protected function storeExpectedReturn(array $submit_data, $order_id) {
		$this->session->data['plugnpay_ss2_expected_amount'] = $submit_data['pt_transaction_amount'];
		$this->session->data['plugnpay_ss2_expected_account'] = $submit_data['pt_gateway_account'];
		$this->session->data['plugnpay_ss2_expected_session'] = $submit_data['pt_custom_value_1'];
		$this->session->data['plugnpay_ss2_expected_order_id'] = (string)$order_id;
		$this->session->data['plugnpay_ss2_submit_data'] = $submit_data;
	}

	/**
	 * @param array $authorize
	 * @param int   $order_id
	 * @param array $order_info
	 */
	protected function processSuccess(array $authorize, $order_id, $order_info) {
		$returned_amount = number_format((float)(isset($authorize['pt_transaction_amount']) ? $authorize['pt_transaction_amount'] : 0), 2, '.', '');
		$expected_amount = isset($this->session->data['plugnpay_ss2_expected_amount'])
			? (string)$this->session->data['plugnpay_ss2_expected_amount']
			: '';

		if (!isset($authorize['pt_transaction_amount']) || $authorize['pt_transaction_amount'] === '') {
			$this->debugLog('Missing returned amount', array('order_id' => $order_id));
			$this->failToCheckout($this->language->get('error_amount_mismatch'));
			return;
		}

		if ($expected_amount !== '' && $returned_amount !== $expected_amount) {
			$this->debugLog('Amount mismatch', array(
				'expected' => $expected_amount,
				'returned' => $returned_amount,
			));
			$this->failToCheckout($this->language->get('error_amount_mismatch'));
			return;
		}

		// If session expected amount was lost, compare against current order total (gateway currency)
		if ($expected_amount === '') {
			$gateway_currency = (string)$this->config->get('plugnpay_ss2_currency');
			if ($gateway_currency === '') {
				$gateway_currency = 'USD';
			}
			$order_amount = (float)$this->currency->format($order_info['total'], $order_info['currency'], 1.00000, false);
			$order_currency = !empty($order_info['currency']) ? (string)$order_info['currency'] : $this->currency->getCode();
			if (strtoupper($order_currency) !== strtoupper($gateway_currency) && method_exists($this->currency, 'convert')) {
				$order_amount = round((float)$this->currency->convert($order_amount, $order_currency, $gateway_currency), 2);
			} else {
				$order_amount = round($order_amount, 2);
			}
			$order_amount_fmt = number_format($order_amount, 2, '.', '');
			if ($returned_amount !== $order_amount_fmt) {
				$this->debugLog('Amount mismatch (no session)', array(
					'returned' => $returned_amount,
					'order' => $order_amount_fmt,
				));
				$this->failToCheckout($this->language->get('error_amount_mismatch'));
				return;
			}
		}

		$returned_account = trim(isset($authorize['pt_gateway_account']) ? (string)$authorize['pt_gateway_account'] : '');
		$expected_account = isset($this->session->data['plugnpay_ss2_expected_account'])
			? (string)$this->session->data['plugnpay_ss2_expected_account']
			: (string)$this->config->get('plugnpay_ss2_login');
		if ($expected_account !== '' && $returned_account !== '' && strcasecmp($returned_account, $expected_account) !== 0) {
			$this->debugLog('Account mismatch', array(
				'expected' => $expected_account,
				'returned' => $returned_account,
			));
			$this->failToCheckout($this->language->get('error_account_mismatch'));
			return;
		}

		$returned_session = $this->extractReturnedSessionId($authorize);
		$expected_session = isset($this->session->data['plugnpay_ss2_expected_session'])
			? (string)$this->session->data['plugnpay_ss2_expected_session']
			: session_id();
		$current_session = session_id();
		if ($returned_session === ''
			|| ($expected_session !== '' && !hash_equals($expected_session, $returned_session))
			|| ($current_session !== '' && !hash_equals($current_session, $returned_session))
		) {
			$this->debugLog('Session mismatch', array(
				'expected' => $expected_session,
				'current' => $current_session,
				'returned' => $returned_session,
			));
			$this->failToCheckout($this->language->get('error_session'));
			return;
		}

		$auth_code = isset($authorize['pt_authorization_code']) ? (string)$authorize['pt_authorization_code'] : '';
		$txn_id = isset($authorize['pt_order_id']) ? (string)$authorize['pt_order_id'] : '';

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

		// Avoid double-confirm if already progressed
		if ((int)$order_info['order_status_id'] == 0) {
			$this->model_checkout_order->confirm($order_id, $pending_id);
		}
		$this->model_checkout_order->update($order_id, $pending_id, $message, false);

		$this->storeTransactionRow('success', $authorize, isset($this->session->data['plugnpay_ss2_submit_data']) ? $this->session->data['plugnpay_ss2_submit_data'] : array(), $order_id);

		unset(
			$this->session->data['plugnpay_ss2_expected_amount'],
			$this->session->data['plugnpay_ss2_expected_account'],
			$this->session->data['plugnpay_ss2_expected_session'],
			$this->session->data['plugnpay_ss2_expected_order_id'],
			$this->session->data['plugnpay_ss2_submit_data']
		);

		redirect($this->html->getSecureURL('checkout/success'));
	}

	/**
	 * @param array $authorize
	 * @return int
	 */
	protected function resolveOrderId(array $authorize) {
		$custom = $this->extractCustomValue($authorize, 'abc_order_id');
		if ($custom !== '' && ctype_digit($custom)) {
			return (int)$custom;
		}
		if (!empty($this->request->get['order_id']) && ctype_digit((string)$this->request->get['order_id'])) {
			return (int)$this->request->get['order_id'];
		}
		if (!empty($this->session->data['plugnpay_ss2_expected_order_id'])) {
			return (int)$this->session->data['plugnpay_ss2_expected_order_id'];
		}
		if (!empty($this->session->data['order_id'])) {
			return (int)$this->session->data['order_id'];
		}
		$acct = isset($authorize['pt_account_code_1']) ? (string)$authorize['pt_account_code_1'] : '';
		if ($acct !== '' && ctype_digit($acct)) {
			return (int)$acct;
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
			if (strtolower(trim((string)$value)) !== $name) {
				continue;
			}
			$valueKey = 'pt_custom_value_' . $m[1];
			if (isset($response[$valueKey])) {
				return trim((string)$response[$valueKey]);
			}
		}
		return '';
	}

	/**
	 * @param array $response
	 * @return string
	 */
	protected function extractReturnedSessionId(array $response) {
		$fromCustom = $this->extractCustomValue($response, 'abcsession');
		if ($fromCustom !== '') {
			return $fromCustom;
		}
		if (isset($response['abcsession'])) {
			return trim((string)$response['abcsession']);
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
		if ((string)$this->config->get('plugnpay_ss2_store_data') === '0') {
			return;
		}

		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Logger.php');
		$logger = new PnPSs2Logger('', true);
		$sent_clean = $logger->sanitize($sent);
		$recv_clean = $logger->sanitize($received);

		$customer_id = $this->customer->isLogged() ? (string)$this->customer->getId() : '0';
		$auth_code = isset($received['pt_authorization_code']) ? (string)$received['pt_authorization_code'] : '';
		$txn_id = isset($received['pt_order_id']) ? (string)$received['pt_order_id'] : '';
		$err = isset($received['pi_error_message']) ? (string)$received['pi_error_message'] : '';

		$sql = "INSERT INTO `" . $this->db->table('plugnpay_ss2') . "` SET "
			. "`customer_id` = '" . $this->db->escape($customer_id) . "', "
			. "`order_id` = '" . $this->db->escape((string)$order_id) . "', "
			. "`response_code` = '" . $this->db->escape((string)$status) . "', "
			. "`response_text` = '" . $this->db->escape(substr($err !== '' ? $err : $status, 0, 255)) . "', "
			. "`authorization_type` = '" . $this->db->escape($auth_code) . "', "
			. "`transaction_id` = '" . $this->db->escape($txn_id) . "', "
			. "`sent` = '" . $this->db->escape(print_r($sent_clean, true)) . "', "
			. "`received` = '" . $this->db->escape(print_r($recv_clean, true)) . "', "
			. "`time` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "', "
			. "`session_id` = '" . $this->db->escape(session_id()) . "'";

		try {
			$this->db->query($sql);
		} catch (Exception $e) {
			$this->debugLog('DB store failed', array('error' => $e->getMessage()));
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
		$log_dir = defined('DIR_LOGS') ? DIR_LOGS : (defined('DIR_SYSTEM') ? DIR_SYSTEM . 'logs' : sys_get_temp_dir());
		$logger = new PnPSs2Logger($log_dir, true);
		$logger->log($message, $context);
	}

	/**
	 * @param string $message
	 */
	protected function failToCheckout($message) {
		$this->session->data['error'] = $message;
		$rt = isset($this->request->get['rt']) ? (string)$this->request->get['rt'] : '';
		if (strpos($rt, 'fast_checkout') !== false) {
			redirect($this->html->getSecureURL('checkout/fast_checkout'));
		}
		redirect($this->html->getSecureURL('checkout/payment', '&mode=edit', true));
	}
}
