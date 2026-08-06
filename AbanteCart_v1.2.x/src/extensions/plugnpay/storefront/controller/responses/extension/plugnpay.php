<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2020 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  Lincence details is bundled with this package in the file LICENSE.txt.
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
 * @property ModelExtensionPlugnpay $model_extension_plugnpay
 * @property ModelCheckoutOrder      $model_checkout_order
 */
class ControllerResponsesExtensionPlugnpay extends AController
{
    public $data = array();

    public function main()
    {
        $this->loadLanguage('plugnpay/plugnpay');
        $this->data['button_confirm'] = $this->language->get('button_confirm');
        $this->data['button_back'] = $this->language->get('button_back');

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if ($this->config->get('plugnpay_test')) {
            $this->data['action'] = 'https://cartdev.urlhitch.com/inputtest.cgi';
        } else {
            $this->data['action'] = 'https://pay1.plugnpay.com/payment/pay.cgi';
        }

        $this->data['publisher-name'] = $this->config->get('plugnpay_account');
        $this->data['currency']       = $order_info['currency'];
        $this->data['card-amount']    = $this->currency->format($order_info['total'], $order_info['currency'], $order_info['value'], false);
        $this->data['acct_code']      = $this->session->data['order_id'];
        $this->data['order-id']       = $this->session->data['order_id'];
        $this->data['card-name']      = $order_info['payment_firstname'].' '.$order_info['payment_lastname'];
        $this->data['card-addre']  = $order_info['payment_address_1'];
        $this->data['card-city']      = $order_info['payment_city'];
        $this->data['card-state']     = $order_info['payment_zone'];
        $this->data['card-zip']       = $order_info['payment_postcode'];
        $this->data['card-country']   = $order_info['payment_country'];
        $this->data['email']          = $order_info['email'];
        $this->data['phone']          = $order_info['telephone'];

        $this->data['shipinfo'] = '0';
        if ($order_info['shipping_lastname']) {
            $this->data['shipname'] = $order_info['shipping_firstname'].' '.$order_info['shipping_lastname'];
        } else {
            $this->data['shipname'] = $order_info['firstname'].' '.$order_info['lastname'];
        }

        if ($this->cart->hasShipping()) {
            $this->data['addre'] = $order_info['shipping_address_1'];
            $this->data['city']     = $order_info['shipping_city'];
            $this->data['state']    = $order_info['shipping_zone'];
            $this->data['zip']      = $order_info['shipping_postcode'];
            $this->data['country']  = $order_info['shipping_country'];
        } else {
            $this->data['addre'] = $order_info['payment_address_1'];
            $this->data['city']     = $order_info['payment_city'];
            $this->data['state']    = $order_info['payment_zone'];
            $this->data['zip']      = $order_info['payment_postcode'];
            $this->data['country']  = $order_info['payment_country'];
        }

        $this->data['easycart'] = '1';
        $this->data['products'] = array();

        $products = $this->cart->getProducts();

        $j = 0;
        foreach ($products as $product) {
            $j++;
            $this->data['products'][] = array(
                "item$j"        => $product['product_id'],
                "description$j" => $product['name'],
                "quantity$j"    => $product['quantity'],
                "cost$j"        => $this->currency->format($product['price'], $order_info['currency'], $order_info['value'], false),
            );
        }

        if ($this->config->get('plugnpay_test')) {
            $this->data['demo'] = 'Y';
        }

        $this->data['lang'] = $this->session->data['language'];

        if ($this->request->get['rt'] == 'checkout/guest_step_3') {
            $this->data['back'] = $this->html->getSecureURL('checkout/guest_step_2', '&mode=edit', true);
        } else {
            $this->data['back'] = $this->html->getSecureURL('checkout/payment', '&mode=edit', true);
        }

        $this->view->batchAssign($this->data);
        $this->processTemplate('responses/plugnpay.tpl');
    }

    public function callback()
    {
        if ($this->request->is_GET()) {
            redirect($this->html->getNonSecureURL('index/home'));
        }
        $post = $this->request->post;
        // hash check
        if (!md5(
            $post['orderID']
            .$this->config->get('plugnpay_account')
            .$post['acct_code']
            .$this->config->get('plugnpay_secret')) == strtolower($post['md5_hash'])
        ){
            exit;
        }

        $this->load->model('checkout/order');

        $order_id = (int)$this->request->post['acct_code'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            return null;
        }
        $this->load->model('extension/plugnpay');
        if ($post['message_type'] == 'ORDER_CREATED') {
            $this->model_checkout_order->confirm(
                (int)$post['orderID'],
                $this->config->get('plugnpay_order_status_id')
            );
        } elseif ($post['message_type'] == 'REFUND_ISSUED') {
            $order_status_id = $this->model_extension_plugnpay->getOrderStatusIdByName('failed');
            $this->model_checkout_order->update(
                (int)$post['orderID'],
                $order_status_id,
                'Status changed by PlugnPay SSv1 INS'
            );
        } elseif ($post['message_type'] == 'FRAUD_STATUS_CHANGED' && $post['fraud_status'] == 'pass') {
            $order_status_id = $this->model_extension_plugnpay->getOrderStatusIdByName('processing');
            $this->model_checkout_order->update(
                (int)$post['orderID'],
                $order_status_id,
                'Status changed by PlugnPay SSv1 INS'
            );
        } elseif ($post['message_type'] == 'SHIP_STATUS_CHANGED' && $post['ship_status'] == 'shipped') {
            $order_status_id = $this->model_extension_plugnpay->getOrderStatusIdByName('complete');
            $this->model_checkout_order->update(
                (int)$post['orderID'],
                $order_status_id,
                'Status changed by PlugnPay SSv1 INS'
            );
        } else {
            redirect($this->html->getSecureURL('checkout/confirm'));
        }
    }

    public function pending_payment()
    {
        $this->addChild('common/head', 'head', 'common/head.tpl');
        $this->addChild('common/footer', 'footer', 'common/footer.tpl');
        $this->document->setTitle('waiting for payment');
        $this->view->assign('text_message', 'waiting for payment confirmation');
        $this->view->assign('text_redirecting', 'redirecting');
        $this->view->assign('test_url', $this->html->getSecureURL('r/extension/plugnpay/is_confirmed'));
        $this->view->assign('success_url', $this->html->getSecureURL('checkout/success'));
        $this->processTemplate('responses/pending_ipn.tpl');
    }

    public function is_confirmed()
    {
        $order_id = (int)$this->session->data['order_id'];
        if (!$order_id) {
            $result = true;
        } else {
            $this->loadModel('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);
            //do nothing if order confirmed or it's not created with paypal standart
            if ((int)$order_info['order_status_id'] != 0
                || $order_info['payment_method_key'] != 'plugnpay'
            ) {
                $result = true;
            } else {
                $result = false;
            }
        }

        $this->load->library('json');
        $this->response->addJSONHeader();
        $this->response->setOutput(AJson::encode(array('result' => $result)));
    }
}
