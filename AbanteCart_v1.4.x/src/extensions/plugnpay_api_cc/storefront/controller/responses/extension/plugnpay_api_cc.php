<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2017 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  License details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

/**
 * Class ControllerResponsesExtensionPlugnpayApiCc
 *
 * PlugnPay Remote API payment (pnpremote.cgi).
 * Pattern aligned with Zen Cart PlugnPayApi / PrestaShop plugnpayapi modules.
 */
class ControllerResponsesExtensionPlugnpayApiCc extends AController {
	public function main() {

		//init controller data
		$this->extensions->hk_InitData($this, __FUNCTION__);

		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');

		// Match core payment modules (Authorize.Net / CardConnect): no r/ prefix for AJAX send
		$data['action'] = $this->html->getSecureURL('extension/plugnpay_api_cc/send');

		//build submit form
		$form = new AForm();
		$form->setForm(array('form_name' => 'plugnpay'));
		$data['form_open'] = $form->getFieldHtml(
			array(
				'type' => 'form',
				'name' => 'plugnpay',
				// Stacked layout for AbanteCart 1.4 fast checkout; novalidate + validateForm()
				'attr' => 'class="validate-creditcard" novalidate',
				'csrf' => true
			)
		);

		$data['text_credit_card'] = $this->language->get('text_credit_card');
		$data['text_wait'] = $this->language->get('text_wait');
		$data['text_close'] = $this->language->get('text_close');
		$data['error_unknown'] = $this->language->get('error_unknown');
		$data['entry_cc_owner'] = $this->language->get('entry_cc_owner');
		$data['entry_what_cvv2'] = $this->language->get('entry_what_cvv2');

		$cc_owner_default = '';
		if (isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
			if ($order_info) {
				$cc_owner_default = trim($order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']);
			}
		}

		$data['cc_owner'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_owner',
				'value' => $cc_owner_default,
				'placeholder' => $this->language->get('entry_cc_owner'),
				'required' => true,
				'attr' => 'autocomplete="cc-name" id="cc_owner"'
			)
		);
		$data['entry_cc_number'] = $this->language->get('entry_cc_number');
		$data['cc_number'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_number',
				'placeholder' => $this->language->get('entry_cc_number'),
				'required' => true,
				'attr' => 'autocomplete="cc-number" inputmode="numeric" id="cc_number"',
				'value' => ''
			)
		);
		$data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
		$data['entry_cc_cvv2'] = $this->language->get('entry_cc_cvv2');
		$data['entry_cc_cvv2_short'] = $this->language->get('entry_cc_cvv2_short');
		$data['cc_cvv2_help_url'] = $this->html->getURL('r/extension/plugnpay_api_cc/cvv2_help');
		$data['use_cvv'] = (string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0';
		$data['cc_cvv2'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_cvv2',
				'value' => '',
				'placeholder' => $this->language->get('entry_cc_cvv2_placeholder'),
				'required' => $data['use_cvv'],
				'attr' => 'maxlength="4" autocomplete="cc-csc" inputmode="numeric" id="cc_cvv2"'
			)
		);
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] = $this->language->get('button_back');

		$months = array();
		for ($i = 1; $i <= 12; $i++) {
			// Short labels avoid wrapping inside the fast-checkout pane
			$months[sprintf('%02d', $i)] = sprintf('%02d - %s', $i, date('M', mktime(0, 0, 0, $i, 1, 2000)));
		}
		$data['cc_expire_date_month'] = $form->getFieldHtml(
			array(
				'type' => 'selectbox',
				'name' => 'cc_expire_date_month',
				'value' => sprintf('%02d', date('m')),
				'options' => $months,
				'required' => true
			)
		);

		$today = getdate();
		$years = array();
		for ($i = $today['year']; $i < $today['year'] + 15; $i++) {
			$years[date('Y', mktime(0, 0, 0, 1, 1, $i))] = date('Y', mktime(0, 0, 0, 1, 1, $i));
		}
		$data['cc_expire_date_year'] = $form->getFieldHtml(
			array(
				'type' => 'selectbox',
				'name' => 'cc_expire_date_year',
				'value' => sprintf('%04d', date('Y') + 1),
				'options' => $years,
				'required' => true
			)
		);

		// AbanteCart 1.4.x defaults to fast checkout
		$back_url = $this->html->getSecureURL('checkout/fast_checkout');

		$data['back'] = $this->html->buildElement(
			array(
				'type' => 'button',
				'name' => 'back',
				'text' => $this->language->get('button_back'),
				'style' => 'button',
				'href' => $back_url
			));
		$data['submit'] = $this->html->buildElement(
			array(
				'type' => 'button',
				'name' => 'plugnpay_button',
				'text' => $this->language->get('button_confirm'),
				'style' => 'button'
			));
		$this->view->batchAssign($data);

		//init controller data
		$this->extensions->hk_UpdateData($this, __FUNCTION__);

		$this->processTemplate('responses/plugnpay_api_cc.tpl');
	}

	public function api() {
		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');

		$data['text_credit_card'] = $this->language->get('text_credit_card');

		$data['entry_cc_owner'] = $this->language->get('entry_cc_owner');
		$data['cc_owner'] = array(
			'type' => 'input',
			'name' => 'cc_owner',
			'required' => true,
			'value' => ''
		);

		$data['entry_cc_number'] = $this->language->get('entry_cc_number');
		$data['cc_number'] = array(
			'type' => 'input',
			'name' => 'cc_number',
			'required' => true,
			'value' => ''
		);

		$data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
		$data['entry_cc_cvv2'] = $this->language->get('entry_cc_cvv2');
		$data['entry_cc_cvv2_short'] = $this->language->get('entry_cc_cvv2_short');
		$data['cc_cvv2_help_url'] = $this->html->getURL('r/extension/plugnpay_api_cc/cvv2_help');
		$data['use_cvv'] = (string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0';

		$data['cc_cvv2'] = array(
			'type' => 'input',
			'name' => 'cc_cvv2',
			'value' => '',
			'required' => $data['use_cvv'],
			'attr' => 'maxlength="4" inputmode="numeric"'
		);
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] = $this->language->get('button_back');

		$months = array();
		for ($i = 1; $i <= 12; $i++) {
			$months[sprintf('%02d', $i)] = sprintf('%02d - %s', $i, date('M', mktime(0, 0, 0, $i, 1, 2000)));
		}
		$data['cc_expire_date_month'] =
			array(
				'type' => 'selectbox',
				'name' => 'cc_expire_date_month',
				'value' => sprintf('%02d', date('m')),
				'options' => $months,
				'required' => true
			);

		$today = getdate();
		$years = array();
		for ($i = $today['year']; $i < $today['year'] + 15; $i++) {
			$years[date('Y', mktime(0, 0, 0, 1, 1, $i))] = date('Y', mktime(0, 0, 0, 1, 1, $i));
		}
		$data['cc_expire_date_year'] = array(
			'type' => 'selectbox',
			'name' => 'cc_expire_date_year',
			'value' => sprintf('%04d', date('Y') + 1),
			'options' => $years,
			'required' => true
		);

		$data['process_rt'] = 'plugnpay_api_cc/send';

		$this->load->library('json');
		$this->response->setOutput(AJson::encode($data));
	}


	public function send() {
		$json = array();
		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');

		if (!$this->csrftoken->isTokenValid()) {
			$json['error'] = $this->language->get('error_unknown');
			$this->attachCsrf($json);
			$this->outputJson($json);
			return;
		}

		if (!$this->request->is_POST()) {
			$json['error'] = $this->language->get('error_unknown');
			$this->outputJson($json);
			return;
		}

		$this->load->model('checkout/order');
		$order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;
		$order_info = $order_id ? $this->model_checkout_order->getOrder($order_id) : null;

		if (!$order_info) {
			$json['error'] = $this->language->get('error_unknown');
			$this->outputJson($json);
			return;
		}

		$cc_number = preg_replace('/\D/', '', isset($this->request->post['cc_number']) ? $this->request->post['cc_number'] : '');
		$exp_month = isset($this->request->post['cc_expire_date_month']) ? sprintf('%02d', (int)$this->request->post['cc_expire_date_month']) : '';
		$exp_year_full = isset($this->request->post['cc_expire_date_year']) ? preg_replace('/\D/', '', $this->request->post['cc_expire_date_year']) : '';
		$exp_year = substr($exp_year_full, -2);
		$cc_cvv = isset($this->request->post['cc_cvv2']) ? (string)$this->request->post['cc_cvv2'] : '';
		$cc_owner = isset($this->request->post['cc_owner']) ? trim($this->request->post['cc_owner']) : '';

		if ($cc_number === '' || strlen($cc_number) < 13 || $exp_month === '00' || $exp_year === '') {
			$json['error'] = $this->language->get('error_cc_details');
			$this->attachCsrf($json);
			$this->outputJson($json);
			return;
		}

		$use_cvv = (string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0';
		if ($use_cvv && (strlen($cc_cvv) < 3 || strlen($cc_cvv) > 4)) {
			$json['error'] = $this->language->get('error_cc_cvv');
			$this->attachCsrf($json);
			$this->outputJson($json);
			return;
		}

		require_once(DIR_EXT . 'plugnpay_api_cc/core/PnPLogger.php');
		require_once(DIR_EXT . 'plugnpay_api_cc/core/PnPApi.php');

		$log_dir = defined('DIR_LOGS') ? DIR_LOGS : (defined('DIR_SYSTEM') ? DIR_SYSTEM . 'logs' : sys_get_temp_dir());
		$debug = (string)$this->config->get('plugnpay_api_cc_debugging') === '1';
		$logger = new PnPLogger($log_dir, $debug);
		$api = new PnPApi(
			(string)$this->config->get('plugnpay_api_cc_login'),
			(string)$this->config->get('plugnpay_api_cc_key'),
			$logger
		);

		$fields = $this->buildAuthorizeFields($order_info, $cc_number, $exp_month . '/' . $exp_year, $cc_cvv, $cc_owner);
		$response = $api->authorize($fields);

		if ($api->getCommErrNo() !== 0 || $api->getLastRawResponse() === '') {
			$json['error'] = $this->language->get('error_communication');
			$this->model_checkout_order->addHistory(
				$order_id,
				0,
				'PlugnPay Remote API communication error: ' . $api->getCommError()
			);
			$this->attachCsrf($json);
			$this->outputJson($json);
			return;
		}

		$auth_code = isset($response['auth-code']) ? $response['auth-code'] : (isset($response['auth_code']) ? $response['auth_code'] : '');
		$txn_id = isset($response['orderID']) ? $response['orderID'] : (isset($response['orderid']) ? $response['orderid'] : '');
		$avs = isset($response['avs-code']) ? $response['avs-code'] : (isset($response['avs_code']) ? $response['avs_code'] : '');
		$cvvresp = isset($response['cvvresp']) ? $response['cvvresp'] : '';

		$message = 'Credit Card payment via PlugnPay Remote API. AUTH: ' . $auth_code
			. ' TransID/orderID: ' . $txn_id;
		$authtype = $this->getAuthType();
		if ($authtype === 'authonly') {
			$message .= ' (auth-only; settle in PlugnPay Admin)';
		}
		if ($avs !== '') {
			$message .= ' AVS: ' . $avs;
		}
		if ($cvvresp !== '') {
			$message .= ' CVV: ' . $cvvresp;
		}

		if ($api->isApproved($response)) {
			$order_status_id = $this->getSuccessOrderStatusId();
			$this->model_checkout_order->confirm($order_id, $this->config->get('config_order_status_id'));
			$this->model_checkout_order->update($order_id, $order_status_id, $message, false);
			$json['success'] = $this->html->getSecureURL('checkout/finalize', '&order_id=' . (int)$order_id);
		} else {
			$final = isset($response['FinalStatus']) ? (string)$response['FinalStatus'] : '';
			$gateway_msg = isset($response['MErrMsg']) ? trim((string)$response['MErrMsg']) : '';

			if ($final === 'fraud') {
				$customer_msg = $this->language->get('warning_fraud');
			} else {
				$customer_msg = $this->language->get('warning_declined');
			}
			if ($gateway_msg !== '') {
				$customer_msg .= ' -- ' . $gateway_msg;
			}

			// Decline limit / lockout (AbanteCart-specific fraud guard)
			if ($final === 'badcard' || $final === 'fraud') {
				$this->session->data['decline_count'] = (isset($this->session->data['decline_count']) ? (int)$this->session->data['decline_count'] : 0) + 1;
				$decline_limit = $this->config->get('plugnpay_api_cc_decline_limit');
				if (has_value($decline_limit) && $this->session->data['decline_count'] > (int)$decline_limit) {
					$customer_msg = $this->language->get('warning_suspicious');
					$this->loadModel('account/customer');
					$customer_id = $this->customer->getId();
					if ($customer_id) {
						$this->model_account_customer->editStatus($customer_id, 0);
						$link = $this->html->getSecureURL('sale/customer/update', '&s=' . ADMIN_PATH . '&customer_id=' . $customer_id);
						$msg = new AMessage();
						$msg->saveNotice(
							$this->language->get('warning_suspicious_to_admin') . '. Customer ID: ' . $customer_id,
							sprintf($this->language->get('warning_suspicious_to_admin_body'), $link)
						);
					}
				}
			}

			$json['error'] = $customer_msg;
			$this->model_checkout_order->addHistory(
				$order_id,
				0,
				'Credit card declined/error: ' . $customer_msg . ' | ' . $message
			);
			$this->attachCsrf($json);
		}

		$this->outputJson($json);
	}

	/**
	 * @param array $json
	 */
	protected function outputJson(array $json) {
		$this->load->library('json');
		if (method_exists($this->response, 'addJSONHeader')) {
			$this->response->addJSONHeader();
		} else {
			$this->response->addHeader('Content-Type: application/json; charset=UTF-8');
		}
		$this->response->setOutput(AJson::encode($json));
	}

	public function cvv2_help() {
		//init controller data
		$this->extensions->hk_InitData($this, __FUNCTION__);

		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');
		$image = '<img src="' . $this->view->templateResource('/image/securitycode.jpg') . '" alt="' . $this->language->get('entry_what_cvv2') . '" />';
		$this->view->assign('description', $image);

		//init controller data
		$this->extensions->hk_UpdateData($this, __FUNCTION__);
		$this->processTemplate('responses/content/content.tpl');
	}

	/**
	 * @return string
	 */
	protected function getAuthType() {
		$authtype = (string)$this->config->get('plugnpay_api_cc_authtype');
		// Backward compatibility with older authorization/capture setting
		$method = (string)$this->config->get('plugnpay_api_cc_method');
		if ($authtype === '' && $method !== '') {
			if ($method === 'capture' || $method === 'authpostauth') {
				return 'authpostauth';
			}
			return 'authonly';
		}
		return ($authtype === 'authpostauth') ? 'authpostauth' : 'authonly';
	}

	/**
	 * @return int
	 */
	protected function getSuccessOrderStatusId() {
		if ($this->getAuthType() === 'authonly') {
			if (is_object($this->order_status) && method_exists($this->order_status, 'getStatusByTextId')) {
				$pending_id = (int)$this->order_status->getStatusByTextId('pending');
				if ($pending_id > 0) {
					return $pending_id;
				}
			}
			return 1;
		}
		$status = (int)$this->config->get('plugnpay_api_cc_order_status_id');
		return $status > 0 ? $status : (int)$this->config->get('config_order_status_id');
	}

	/**
	 * @param array  $order_info
	 * @param string $cc_number
	 * @param string $card_exp  MM/YY
	 * @param string $cc_cvv
	 * @param string $cc_owner
	 * @return array
	 */
	protected function buildAuthorizeFields($order_info, $cc_number, $card_exp, $cc_cvv, $cc_owner) {
		$amount = $this->currency->format($order_info['total'], $order_info['currency'], 1.00000, false);
		$amount = number_format((float)$amount, 2, '.', '');

		$card_name = $cc_owner !== ''
			? $cc_owner
			: trim(html_entity_decode($order_info['payment_firstname'], ENT_QUOTES, 'UTF-8') . ' '
				. html_entity_decode($order_info['payment_lastname'], ENT_QUOTES, 'UTF-8'));

		$billing_country = !empty($order_info['payment_iso_code_2'])
			? $order_info['payment_iso_code_2']
			: html_entity_decode($order_info['payment_country'], ENT_QUOTES, 'UTF-8');

		$fields = array(
			'mode' => 'auth',
			'paymethod' => 'credit',
			'authtype' => $this->getAuthType(),
			'easycart' => '1',
			'shipinfo' => '1',
			'card-amount' => $amount,
			'currency' => $this->currency->getCode(),
			'card-number' => $cc_number,
			'card-exp' => $card_exp,
			'card-name' => $card_name,
			'card-company' => html_entity_decode($order_info['payment_company'], ENT_QUOTES, 'UTF-8'),
			'card-address1' => html_entity_decode($order_info['payment_address_1'], ENT_QUOTES, 'UTF-8'),
			'card-address2' => html_entity_decode($order_info['payment_address_2'], ENT_QUOTES, 'UTF-8'),
			'card-city' => html_entity_decode($order_info['payment_city'], ENT_QUOTES, 'UTF-8'),
			'card-state' => html_entity_decode($order_info['payment_zone'], ENT_QUOTES, 'UTF-8'),
			'card-zip' => html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8'),
			'card-country' => $billing_country,
			'phone' => $order_info['telephone'],
			'email' => $order_info['email'],
			'ipaddress' => $this->request->getRemoteIP(),
			'acct_code' => (string)$order_info['order_id'],
			'dontsndmail' => ((string)$this->config->get('plugnpay_api_cc_emailcust') === 'no') ? 'no' : 'yes',
		);

		$pubemail = trim((string)$this->config->get('plugnpay_api_cc_pubemail'));
		if ($pubemail !== '') {
			$fields['publisher-email'] = $pubemail;
			$fields['notify-email'] = $pubemail;
		}

		if ((string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0' && $cc_cvv !== '') {
			$fields['card-cvv'] = $cc_cvv;
		}

		// Shipping
		if (!empty($order_info['shipping_lastname']) || !empty($order_info['shipping_address_1'])) {
			$fields['shipname'] = trim(
				html_entity_decode($order_info['shipping_firstname'], ENT_QUOTES, 'UTF-8') . ' '
				. html_entity_decode($order_info['shipping_lastname'], ENT_QUOTES, 'UTF-8')
			);
			$fields['address1'] = html_entity_decode($order_info['shipping_address_1'], ENT_QUOTES, 'UTF-8');
			$fields['address2'] = html_entity_decode($order_info['shipping_address_2'], ENT_QUOTES, 'UTF-8');
			$fields['city'] = html_entity_decode($order_info['shipping_city'], ENT_QUOTES, 'UTF-8');
			$fields['state'] = html_entity_decode($order_info['shipping_zone'], ENT_QUOTES, 'UTF-8');
			$fields['zip'] = html_entity_decode($order_info['shipping_postcode'], ENT_QUOTES, 'UTF-8');
			$fields['country'] = !empty($order_info['shipping_iso_code_2'])
				? $order_info['shipping_iso_code_2']
				: html_entity_decode($order_info['shipping_country'], ENT_QUOTES, 'UTF-8');
		} else {
			$fields['shipname'] = $card_name;
			$fields['address1'] = $fields['card-address1'];
			$fields['address2'] = $fields['card-address2'];
			$fields['city'] = $fields['card-city'];
			$fields['state'] = $fields['card-state'];
			$fields['zip'] = $fields['card-zip'];
			$fields['country'] = $fields['card-country'];
		}

		if ((float)$amount <= 0) {
			$fields['mode'] = 'checkcard';
			unset($fields['card-amount']);
		}

		// Line items
		$products = $this->cart->getProducts();
		if (is_array($products) && !empty($products)) {
			$j = 1;
			foreach ($products as $product) {
				$fields['item' . $j] = isset($product['product_id']) ? (string)$product['product_id'] : '';
				$fields['cost' . $j] = number_format(
					(float)$this->currency->format($product['price'], $order_info['currency'], 1.00000, false),
					2,
					'.',
					''
				);
				$fields['quantity' . $j] = isset($product['quantity']) ? (string)$product['quantity'] : '1';
				$fields['description' . $j] = substr(strip_tags(isset($product['name']) ? $product['name'] : ''), 0, 255);
				$j++;
			}
		}

		return $fields;
	}

	/**
	 * @param array $json
	 */
	protected function attachCsrf(&$json) {
		if (isset($json['error']) && $json['error']) {
			$csrftoken = $this->registry->get('csrftoken');
			$json['csrfinstance'] = $csrftoken->setInstance();
			$json['csrftoken'] = $csrftoken->setToken();
		}
	}
}
