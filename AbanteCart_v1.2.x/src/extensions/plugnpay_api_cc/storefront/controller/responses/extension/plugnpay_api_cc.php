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
 */
class ControllerResponsesExtensionPlugnpayApiCc extends AController {
	public function main() {

		//init controller data
		$this->extensions->hk_InitData($this, __FUNCTION__);

		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');

		$data['action'] = $this->html->getSecureURL('extension/plugnpay_api_cc/send');

		//build submit form
		$form = new AForm();
		$form->setForm(array( 'form_name' => 'plugnpay' ));
		$data['form_open'] = $form->getFieldHtml(
			array(
				'type' => 'form',
				'name' => 'plugnpay',
				'attr' => 'class = "form-horizontal validate-creditcard"',
				'csrf' => true
			)
		);

		$data['text_credit_card'] = $this->language->get('text_credit_card');
		$data['text_wait'] = $this->language->get('text_wait');
		$data['entry_cc_owner'] = $this->language->get('entry_cc_owner');
		$data['cc_owner'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_owner',
				'value' => ''
			)
		);
		$data['entry_cc_number'] = $this->language->get('entry_cc_number');
		$data['cc_number'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_number',
				'attr' => 'autocomplete="off"',
				'value' => ''
			)
		);
		$data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
		$data['entry_cc_cvv2'] = $this->language->get('entry_cc_cvv2');
		$data['entry_cc_cvv2_short'] = $this->language->get('entry_cc_cvv2_short');
		$data['cc_cvv2_help_url'] = $this->html->getURL('r/extension/plugnpay_api_cc/cvv2_help');
		$data['cc_cvv2'] = $form->getFieldHtml(
			array(
				'type' => 'input',
				'name' => 'cc_cvv2',
				'value' => '',
				'style' => 'short input-mini',
				'attr' => ' size="3" autocomplete="off"'
			)
		);
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] = $this->language->get('button_back');

		$months = array();
		for ($i = 1; $i <= 12; $i++) {
			$months[ sprintf('%02d', $i) ] = strftime('%B', mktime(0, 0, 0, $i, 1, 2000));
		}
		$data['cc_expire_date_month'] = $form->getFieldHtml(
			array(
				'type' => 'selectbox',
				'name' => 'cc_expire_date_month',
				'value' => sprintf('%02d', date('m')),
				'options' => $months,
				'style' => 'short input-small'
			)
		);

		$today = getdate();
		$years = array();
		for ($i = $today['year']; $i < $today['year'] + 11; $i++) {
			$years[ strftime('%Y', mktime(0, 0, 0, 1, 1, $i)) ] = strftime('%Y', mktime(0, 0, 0, 1, 1, $i));
		}
		$data['cc_expire_date_year'] = $form->getFieldHtml(
			array(
				'type' => 'selectbox',
				'name' => 'cc_expire_date_year',
				'value' => sprintf('%02d', date('Y') + 1),
				'options' => $years,
				'style' => 'short input-small'
			)
		);

		if ($this->request->get['rt'] == 'checkout/guest_step_3') {
			$back_url = $this->html->getSecureURL('checkout/guest_step_2','&mode=edit',true);
		} else {
			$back_url = $this->html->getSecureURL('checkout/payment','&mode=edit',true);
		}

		$data['back'] = $this->html->buildElement(
				array(
					'type' => 'button',
					'name' => 'back',
					'text' => $this->language->get('button_back'),
					'style' => 'button',
					'href' => $back_url
		));
		$data['submit'] = $this->html->buildElement(
				array( 'type' => 'button',
						  'name' => 'plugnpay_button',
						  'text' => $this->language->get('button_confirm'),
						  'style' => 'button'
				 ));
		$this->view->batchAssign($data);

		//init controller data
		$this->extensions->hk_UpdateData($this, __FUNCTION__);

		//load creditcard input validation
		$this->document->addScriptBottom($this->view->templateResource('/javascript/credit_card_validation.js'));

		$this->processTemplate('responses/plugnpay_api_cc.tpl');
	}

	public function api() {
		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');

		$data['text_credit_card'] = $this->language->get('text_credit_card');

		$data['entry_cc_owner'] = $this->language->get('entry_cc_owner');
		$data['cc_owner'] = array( 'type' => 'input',
									 'name' => 'cc_owner',
									 'required' => true,
									 'value' => '' );

		$data['entry_cc_number'] = $this->language->get('entry_cc_number');
		$data['cc_number'] = array( 'type' => 'input',
									  'name' => 'cc_number',
									  'required' => true,
									  'value' => '' );

		$data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
		$data['entry_cc_cvv2'] = $this->language->get('entry_cc_cvv2');
		$data['entry_cc_cvv2_short'] = $this->language->get('entry_cc_cvv2_short');
		$data['cc_cvv2_help_url'] = $this->html->getURL('r/extension/plugnpay_api_cc/cvv2_help');

		$data['cc_cvv2'] = array( 'type' => 'input',
									'name' => 'cc_cvv2',
									'value' => '',
									'style' => 'short input-small',
									'required' => true,
									'attr' => ' size="3"');
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] = $this->language->get('button_back');

		$months = array();
		for ($i = 1; $i <= 12; $i++) {
			$months[ sprintf('%02d', $i) ] = strftime('%B', mktime(0, 0, 0, $i, 1, 2000));
		}
		$data['cc_expire_date_month'] =
			array( 'type' => 'selectbox',
				 'name' => 'cc_expire_date_month',
				 'value' => sprintf('%02d', date('m')),
				 'options' => $months,
				 'required' => true,
				 'style' => 'short input-small'
			);

		$today = getdate();
		$years = array();
		for ($i = $today['year']; $i < $today['year'] + 11; $i++) {
			$years[ strftime('%Y', mktime(0, 0, 0, 1, 1, $i)) ] = strftime('%Y', mktime(0, 0, 0, 1, 1, $i));
		}
		$data['cc_expire_date_year'] = array( 'type' => 'selectbox',
												'name' => 'cc_expire_date_year',
												'value' => sprintf('%02d', date('Y') + 1),
												'options' => $years,
												'required' => true,
												'style' => 'short input-small' );

		$data['process_rt'] = 'plugnpay_api_cc/send';

		$this->load->library('json');
		$this->response->setOutput(AJson::encode($data));
	}


	public function send() {
		$json = array();
		$data = array();

		if(!$this->csrftoken->isTokenValid()){
			$json['error'] = $this->language->get('error_unknown');
			$this->load->library('json');
			$this->response->setOutput(AJson::encode($json));
			return;
		}
		$url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$data['publisher_name'] = $this->config->get('plugnpay_api_cc_login');
		$data['publisher_password'] = $this->config->get('plugnpay_api_cc_key');
        $data['mode'] = 'auth';
        $data['convert'] = 'underscores';
		$data['client'] = 'AbanteCart API CC';
		$data['card_name'] = html_entity_decode($order_info['payment_firstname'], ENT_QUOTES, 'UTF-8') . ' ' . html_entity_decode($order_info['payment_lastname'], ENT_QUOTES, 'UTF-8');
		$data['card_company'] = html_entity_decode($order_info['payment_company'], ENT_QUOTES, 'UTF-8');
		$data['card_address1'] = html_entity_decode($order_info['payment_address_1'], ENT_QUOTES, 'UTF-8');
		$data['card_address2'] = html_entity_decode($order_info['payment_address_2'], ENT_QUOTES, 'UTF-8');
		$data['card_city'] = html_entity_decode($order_info['payment_city'], ENT_QUOTES, 'UTF-8');
		$data['card_state'] = html_entity_decode($order_info['payment_zone'], ENT_QUOTES, 'UTF-8');
		$data['card_zip'] = html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8');
		$data['card_country'] = html_entity_decode($order_info['payment_country'], ENT_QUOTES, 'UTF-8');
		$data['phone'] = $order_info['telephone'];
		$data['ipaddress'] = $this->request->getRemoteIP();
		$data['email'] = $order_info['email'];
		$data['acct_code'] = html_entity_decode($this->config->get('store_name'), ENT_QUOTES, 'UTF-8');
		$data['card_amount'] = $this->currency->format($order_info['total'], $order_info['currency'], 1.00000, FALSE);
		$data['currency'] = $this->currency->getCode();
		$data['paymethod'] = 'credit';
		$data['authtype'] = ($this->config->get('plugnpay_api_cc_method') == 'capture') ? 'authpostauth' : 'authonly';
		$data['card_number'] = str_replace(' ', '', $this->request->post['cc_number']);
		$data['card_exp'] = $this->request->post['cc_expire_date_month'] . '/' . $this->request->post['cc_expire_date_year'];
		$data['card_cvv'] = $this->request->post['cc_cvv2'];
		$data['order_id'] = $this->session->data['order_id'];

		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_PORT, 443);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		$response = curl_exec($curl);
		curl_close($curl);

		# NOTE: windows server users, you must have 'register_globals' ON in your php.ini for parse_str to work correctly.
        parse_str($response, $response_data);

//		if (!isset($order_id) || !isset($orderID) || !isset($card_amount)) {
//			$msg = 'PlugnPay.com returned a malformed response for cart';
//			if (isset($order_id))
//				$msg .= ' '.(int)$order_id;
//			Logger::addLog($msg, 4);
//			die("PlugnPay.com returned a malformed response, aborted. '$FinalStatus' '$MErrMsg' '$card_amount' '$orderID' '$order_id'");
//		}

		//build response message for records
		$message = '';
		if (has_value($response_data['auth-code'])) {
			$message .= 'Authorization Code: ' . $response_data['auth-code'] . "\n";
		}
		if (has_value($response_data['avs-code'])) {
			$message .= 'AVS Response: ' . $response_data['avs_code'] . "\n";
		}
		if (has_value($response_data['orderID'])) {
			$message .= 'Transaction ID: ' . $response_data['orderID'] . "\n";
		}
		if (has_value($response_data['cvvresp'])) {
			$message .= 'Card Code Response: ' . $response_data['cvvresp'] . "\n";
		}
		if (has_value($response_data['MErrMsg'])) {
			$message .= 'Cardholder Authentication Verification Response: ' . $response_data['MErrMsg'] . "\n";
		}

		if ($response_data['FinalStatus'] == 'success') {
			$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('config_order_status_id'));
			$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('plugnpay_api_cc_order_status_id'), $message, FALSE);

			$json['success'] = $this->html->getSecureURL('checkout/success');
		} else if (($response_data['FinalStatus'] == 'badcard') || ($response_data['FinalStatus'] == 'fraud')) {
			$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');
			
			//special case of declined payment. Count declined. If limit is set. 
			$this->session->data['decline_count'] = $this->session->data['decline_count'] + 1;
			$decline_limit = $this->config->get('plugnpay_api_cc_decline_limit');
			if (has_value($decline_limit) && $this->session->data['decline_count'] > $decline_limit) {

				$json['error'] = $this->language->get('warning_suspicious');
				$this->loadModel('account/customer');
				$customer_id = $this->customer->getId();
				$this->model_account_customer->editStatus($customer_id, 0);

				$link = $this->html->getSecureURL('sale/customer/update', '&s=' . ADMIN_PATH .'&customer_id='.$customer_id);
				$msg = new AMessage();
				//send message with unique title to prevent grouping message
				$msg->saveNotice(
								 $this->language->get('warning_suspicious_to_admin') . '. Customer ID: ' . $customer_id, 
								 sprintf($this->language->get('warning_suspicious_to_admin_body'), $link)
								);
			} else {
				$json['error'] = $this->language->get("warning_declined");
				//record this decline to history
				$message = 'Credit card was declined: ' . "<br>" . $message;
				$this->model_checkout_order->addHistory(
					$this->session->data['order_id'], 
					0, 
					$message 
				);
			} 
		} else if ($response_data['FinalStatus'] == 'pending') {
			//special case of success payment in review stage. Create order with pending status
			$new_order_status_id = $this->order_status->getStatusByTextId('pending');
			$this->model_checkout_order->confirm($this->session->data['order_id'], $new_order_status_id);
			$this->model_checkout_order->update($this->session->data['order_id'], $new_order_status_id, $message, FALSE);
			$json['success'] = $this->html->getSecureURL('checkout/success');
		} else {
			// assume 'problem' response
			$json['error'] = $response_data['MErrMsg'];
			//record this incident to history
			$message = 'Error processing credit card: '."<br>".$json['error']."<br>".$message;
			$this->model_checkout_order->addHistory(
				$this->session->data['order_id'], 
				0, 
				$message 
			);
		}

		if (isset($json['error'])) {
			if ($json['error']) {
				$csrftoken = $this->registry->get('csrftoken');
				$json['csrfinstance'] = $csrftoken->setInstance();
				$json['csrftoken'] = $csrftoken->setToken();
			}
		}
		$this->load->library('json');
		$this->response->setOutput(AJson::encode($json));
	}

	public function cvv2_help() {
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$this->loadLanguage('plugnpay_api_cc/plugnpay_api_cc');
		$image = '<img src="' . $this->view->templateResource('/image/securitycode.jpg') . '" alt="' . $this->language->get('entry_what_cvv2') . '" />';
		$this->view->assign('description', $image );

		//init controller data
		$this->extensions->hk_UpdateData($this,__FUNCTION__);
		$this->processTemplate('responses/content/content.tpl' );
	}
}
