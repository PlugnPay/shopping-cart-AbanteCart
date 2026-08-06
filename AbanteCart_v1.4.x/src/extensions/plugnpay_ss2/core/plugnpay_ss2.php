<?php
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

class ExtensionPlugnpaySs2 extends Extension {

	protected $registry;

	public function __construct() {
		$this->registry = Registry::getInstance();
	}

	public function onControllerPagesExtensionExtensions_UpdateData() {
		$that = $this->baseObject;
		$current_ext_id = $that->request->get['extension'];
		if (IS_ADMIN && $current_ext_id == 'plugnpay_ss2' && $this->baseObject_method == 'edit') {
			$html = '<a class="btn btn-white tooltips" target="_blank" href="https://www.plugnpay.com/" title="Visit PlugnPay.com">
	    				<i class="fa fa-external-link fa-lg"></i>
	    			</a>';
			$that->view->addHookVar('extension_toolbar_buttons', $html);
		}
	}
}
