<?php
/*------------------------------------------------------------------------------
  AbanteCart payment extension driver — PlugnPay Smart Screens v2
------------------------------------------------------------------------------*/

if (!class_exists('ExtensionPlugnpaySs2')) {
	include_once('core/plugnpay_ss2.php');
}

$controllers = array(
	'storefront' => array('responses/extension/plugnpay_ss2'),
	'admin' => array(),
);

$models = array(
	'storefront' => array('extension/plugnpay_ss2'),
	'admin' => array(),
);

$languages = array(
	'storefront' => array(
		'plugnpay_ss2/plugnpay_ss2'),
	'admin' => array(
		'plugnpay_ss2/plugnpay_ss2'));

$templates = array(
	'storefront' => array(
		'responses/plugnpay_ss2.tpl'),
	'admin' => array());
