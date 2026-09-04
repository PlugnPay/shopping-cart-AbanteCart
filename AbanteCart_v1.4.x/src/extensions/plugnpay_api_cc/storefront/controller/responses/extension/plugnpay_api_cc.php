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
	exit;
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
				'attr' => 'autocomplete="cc-name" id="cc_owner" maxlength="64"'
			)
		);
		$data['entry_cc_number'] = $this->language->get('entry_cc_number');
		$data['cc_number'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_number',
				'placeholder' => $this->language->get('entry_cc_number'),
				'required' => true,
				'attr' => 'autocomplete="off" inputmode="numeric" maxlength="19" id="cc_number"',
				'value' => ''
			)
		);
		$data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
		$data['entry_cc_cvv2'] = $this->language->get('entry_cc_cvv2');
		$data['entry_cc_cvv2_short'] = $this->language->get('entry_cc_cvv2_short');
		$data['cc_cvv2_help_url'] = $this->html->getSecureURL('r/extension/plugnpay_api_cc/cvv2_help');
		$data['use_cvv'] = (string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0';
		$data['cc_cvv2'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_cvv2',
				'value' => '',
				'placeholder' => $this->language->get('entry_cc_cvv2_placeholder'),
				'required' => $data['use_cvv'],
				'attr' => 'maxlength="4" autocomplete="off" inputmode="numeric" id="cc_cvv2"'
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
		$data['cc_cvv2_help_url'] = $this->html->getSecureURL('r/extension/plugnpay_api_cc/cvv2_help');
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
		require_once(DIR_EXT . 'plugnpay_api_cc/core/PnPFilter.php');

		if (!$this->csrftoken->isTokenValid()) {
			$json['error'] = $this->language->get('error_unknown');
			$this->failJson($json);
			return;
		}

		if (!$this->request->is_POST()) {
			$json['error'] = $this->language->get('error_unknown');
			$this->failJson($json);
			return;
		}

		$server = is_array($this->request->server) ? $this->request->server : array();
		if (!PnPFilter::isHttpsRequest($server)) {
			$json['error'] = $this->language->get('error_unknown');
			$this->failJson($json);
			return;
		}

		$this->load->model('checkout/order');
		$order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;
		$order_info = $order_id ? $this->model_checkout_order->getOrder($order_id) : null;

		if (!$order_info) {
			$json['error'] = $this->language->get('error_unknown');
			$this->failJson($json);
			return;
		}

		if (!$this->isThisPaymentMethod($order_info)) {
			$json['error'] = $this->language->get('error_unknown');
			$this->failJson($json);
			return;
		}

		if ((int)$order_info['order_status_id'] !== 0) {
			$json['success'] = $this->html->getSecureURL('checkout/finalize', '&order_id=' . (int)$order_id);
			$this->outputJson($json);
			return;
		}

		$cc_number = PnPFilter::normalizePan(isset($this->request->post['cc_number']) ? $this->request->post['cc_number'] : '');
		$exp_month = isset($this->request->post['cc_expire_date_month'])
			&& is_scalar($this->request->post['cc_expire_date_month'])
			? (int)$this->request->post['cc_expire_date_month']
			: 0;
		$exp_year_full = isset($this->request->post['cc_expire_date_year']) ? $this->request->post['cc_expire_date_year'] : '';
		$cc_cvv = PnPFilter::normalizeCvv(isset($this->request->post['cc_cvv2']) ? $this->request->post['cc_cvv2'] : '');
		$cc_owner = PnPFilter::sanitizeCardName(isset($this->request->post['cc_owner']) ? $this->request->post['cc_owner'] : '');

		if (!PnPFilter::isValidPan($cc_number) || !PnPFilter::isValidExpiry($exp_month, $exp_year_full)) {
			$json['error'] = $this->language->get('error_cc_details');
			$this->failJson($json);
			return;
		}

		$use_cvv = (string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0';
		if ($use_cvv && !PnPFilter::isValidCvv($cc_cvv)) {
			$json['error'] = $this->language->get('error_cc_cvv');
			$this->failJson($json);
			return;
		}

		$amount = PnPFilter::formatAmount(
			$this->currency->format($order_info['total'], $order_info['currency'], 1.00000, false)
		);
		if (PnPFilter::toCents($amount) <= 0) {
			$json['error'] = $this->language->get('error_unknown');
			$this->failJson($json);
			return;
		}

		require_once(DIR_EXT . 'plugnpay_api_cc/core/PnPLogger.php');
		require_once(DIR_EXT . 'plugnpay_api_cc/core/PnPApi.php');

		$log_dir = defined('DIR_LOGS') ? DIR_LOGS : (defined('DIR_SYSTEM') ? DIR_SYSTEM . 'logs' : '');
		$debug = (string)$this->config->get('plugnpay_api_cc_debugging') === '1';
		$logger = new PnPLogger($log_dir, $debug);
		$api = new PnPApi(
			(string)$this->config->get('plugnpay_api_cc_login'),
			(string)$this->config->get('plugnpay_api_cc_key'),
			$logger
		);

		$card_exp = PnPFilter::formatCardExp($exp_month, $exp_year_full);
		$fields = $this->buildAuthorizeFields($order_info, $cc_number, $card_exp, $cc_cvv, $cc_owner);
		$response = $api->authorize($fields);
		unset($cc_number, $cc_cvv, $fields['card-number'], $fields['card-cvv'], $fields);

		if ($api->getCommErrNo() !== 0 || $api->getHttpCode() !== 200 || !$api->hasResponse()) {
			$json['error'] = $this->language->get('error_communication');
			$this->model_checkout_order->addHistory(
				$order_id,
				0,
				'PlugnPay Remote API communication error'
			);
			$this->failJson($json);
			return;
		}

		$auth_code = $this->safeHistoryToken(
			isset($response['auth-code']) ? $response['auth-code'] : (isset($response['auth_code']) ? $response['auth_code'] : '')
		);
		$txn_id = $this->safeHistoryToken(
			isset($response['orderID']) ? $response['orderID'] : (isset($response['orderid']) ? $response['orderid'] : '')
		);
		$avs = $this->safeHistoryToken(
			isset($response['avs-code']) ? $response['avs-code'] : (isset($response['avs_code']) ? $response['avs_code'] : '')
		);
		$cvvresp = $this->safeHistoryToken(isset($response['cvvresp']) ? $response['cvvresp'] : '');

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
			$message .= ' CVVresp: ' . $cvvresp;
		}

		if ($api->isApproved($response)) {
			$order_status_id = $this->getSuccessOrderStatusId();
			$this->model_checkout_order->confirm($order_id, $this->config->get('config_order_status_id'));
			$this->model_checkout_order->update($order_id, $order_status_id, $message, false);
			$json['success'] = $this->html->getSecureURL('checkout/finalize', '&order_id=' . (int)$order_id);
		} else {
			$final = strtolower(trim(isset($response['FinalStatus']) ? (string)$response['FinalStatus'] : ''));
			if ($final === 'fraud') {
				$customer_msg = $this->language->get('warning_fraud');
			} else {
				$customer_msg = $this->language->get('warning_declined');
			}

			if ($final === 'badcard' || $final === 'fraud') {
				$decline_key = 'plugnpay_api_cc_decline_' . $order_id;
				$this->session->data[$decline_key] = (isset($this->session->data[$decline_key]) ? (int)$this->session->data[$decline_key] : 0) + 1;
				$decline_limit = $this->config->get('plugnpay_api_cc_decline_limit');
				if (has_value($decline_limit) && $this->session->data[$decline_key] > (int)$decline_limit) {
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
			$history_final = $this->safeHistoryToken($final);
			$this->model_checkout_order->addHistory(
				$order_id,
				0,
				'Credit card declined/error. FinalStatus: ' . $history_final . ' | ' . $message
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
		$amount = PnPFilter::formatAmount(
			$this->currency->format($order_info['total'], $order_info['currency'], 1.00000, false)
		);
		$currency = !empty($order_info['currency'])
			? PnPFilter::clip(preg_replace('/[^A-Za-z]/', '', (string)$order_info['currency']), 3)
			: '';

		$card_name = $cc_owner !== ''
			? $cc_owner
			: PnPFilter::sanitizeCardName(
				trim((string)$order_info['payment_firstname'] . ' ' . (string)$order_info['payment_lastname'])
			);

		$billing_country = !empty($order_info['payment_iso_code_2'])
			? PnPFilter::sanitizeCountry($order_info['payment_iso_code_2'])
			: PnPFilter::sanitizeCountry($order_info['payment_country']);

		$fields = array(
			'mode' => 'auth',
			'paymethod' => 'credit',
			'authtype' => $this->getAuthType(),
			'easycart' => '1',
			'shipinfo' => '1',
			'card-amount' => $amount,
			'currency' => $currency,
			'card-number' => $cc_number,
			'card-exp' => $card_exp,
			'card-name' => $card_name,
			'card-company' => PnPFilter::sanitizeText($order_info['payment_company'], 64),
			'card-address1' => PnPFilter::sanitizeText($order_info['payment_address_1']),
			'card-address2' => PnPFilter::sanitizeText($order_info['payment_address_2']),
			'card-city' => PnPFilter::sanitizeText($order_info['payment_city'], 64),
			'card-state' => PnPFilter::sanitizeText($order_info['payment_zone'], 64),
			'card-zip' => PnPFilter::sanitizeText($order_info['payment_postcode'], 16),
			'card-country' => $billing_country,
			'phone' => PnPFilter::sanitizePhone($order_info['telephone']),
			'email' => PnPFilter::sanitizeEmail($order_info['email']),
			'ipaddress' => PnPFilter::clip((string)$this->request->getRemoteIP(), 45),
			'acct_code' => (string)(int)$order_info['order_id'],
			'dontsndmail' => ((string)$this->config->get('plugnpay_api_cc_emailcust') === 'no') ? 'no' : 'yes',
		);

		$pubemail = PnPFilter::sanitizeEmail((string)$this->config->get('plugnpay_api_cc_pubemail'));
		if ($pubemail !== '') {
			$fields['publisher-email'] = $pubemail;
			$fields['notify-email'] = $pubemail;
		}

		if ((string)$this->config->get('plugnpay_api_cc_use_cvv') !== '0' && $cc_cvv !== '') {
			$fields['card-cvv'] = $cc_cvv;
		}

		if (!empty($order_info['shipping_lastname']) || !empty($order_info['shipping_address_1'])) {
			$fields['shipname'] = PnPFilter::sanitizeCardName(
				trim((string)$order_info['shipping_firstname'] . ' ' . (string)$order_info['shipping_lastname'])
			);
			$fields['address1'] = PnPFilter::sanitizeText($order_info['shipping_address_1']);
			$fields['address2'] = PnPFilter::sanitizeText($order_info['shipping_address_2']);
			$fields['city'] = PnPFilter::sanitizeText($order_info['shipping_city'], 64);
			$fields['state'] = PnPFilter::sanitizeText($order_info['shipping_zone'], 64);
			$fields['zip'] = PnPFilter::sanitizeText($order_info['shipping_postcode'], 16);
			$fields['country'] = !empty($order_info['shipping_iso_code_2'])
				? PnPFilter::sanitizeCountry($order_info['shipping_iso_code_2'])
				: PnPFilter::sanitizeCountry($order_info['shipping_country']);
		} else {
			$fields['shipname'] = $card_name;
			$fields['address1'] = $fields['card-address1'];
			$fields['address2'] = $fields['card-address2'];
			$fields['city'] = $fields['card-city'];
			$fields['state'] = $fields['card-state'];
			$fields['zip'] = $fields['card-zip'];
			$fields['country'] = $fields['card-country'];
		}

		$products = $this->cart->getProducts();
		if (is_array($products) && !empty($products)) {
			$j = 1;
			foreach ($products as $product) {
				if ($j > PnPFilter::LINE_ITEM_MAX) {
					break;
				}
				$qty = isset($product['quantity']) ? (int)$product['quantity'] : 1;
				if ($qty < 1) {
					$qty = 1;
				}
				$fields['item' . $j] = PnPFilter::clip((string)(isset($product['product_id']) ? $product['product_id'] : ''), 32);
				$fields['cost' . $j] = PnPFilter::formatAmount(
					$this->currency->format($product['price'], $order_info['currency'], 1.00000, false)
				);
				$fields['quantity' . $j] = (string)$qty;
				$fields['description' . $j] = PnPFilter::sanitizeText(
					isset($product['name']) ? $product['name'] : '',
					PnPFilter::DESC_MAX
				);
				$j++;
			}
		}

		return PnPFilter::allowlistAuthorizeFields($fields);
	}

	/**
	 * @param array $order_info
	 * @return bool
	 */
	protected function isThisPaymentMethod(array $order_info) {
		return isset($order_info['payment_method_key'])
			&& (string)$order_info['payment_method_key'] === 'plugnpay_api_cc';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	protected function safeHistoryToken($value) {
		if (!is_scalar($value)) {
			return '';
		}
		return preg_replace('/[^A-Za-z0-9\-_]/', '', PnPFilter::clip((string)$value, 64));
	}

	/**
	 * @param array $json
	 */
	protected function failJson(array $json) {
		$this->attachCsrf($json);
		$this->outputJson($json);
	}

	/**
	 * @param array $json
	 */
	protected function attachCsrf(&$json) {
		$csrftoken = $this->registry->get('csrftoken');
		$json['csrfinstance'] = $csrftoken->setInstance();
		$json['csrftoken'] = $csrftoken->setToken();
	}
}
