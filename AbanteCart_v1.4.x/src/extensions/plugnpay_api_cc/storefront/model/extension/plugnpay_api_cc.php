<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2024 Belavier Commerce LLC

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

class ModelExtensionPlugnpayApiCc extends Model {
	public function getMethod($address) {
		// Create language instance for admin-side calls (AbanteCart 1.4 pattern)
		$language = new ALanguage($this->registry, $this->language->getLanguageCode(), 0);
		$language->load($language->language_details['directory']);
		$language->load('plugnpay_api_cc/plugnpay_api_cc');

		if ($this->config->get('plugnpay_api_cc_status')) {
			$query = $this->db->query(
				"SELECT * FROM `" . $this->db->table("zones_to_locations") . "` "
				. "WHERE location_id = '" . (int)$this->config->get('plugnpay_api_cc_location_id') . "' "
				. "AND country_id = '" . (int)$address['country_id'] . "' "
				. "AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')"
			);

			if (!$this->config->get('plugnpay_api_cc_location_id')) {
				$status = true;
			} elseif ($query->num_rows) {
				$status = true;
			} else {
				$status = false;
			}

			// Require credentials
			if (!$this->config->get('plugnpay_api_cc_login') || !$this->config->get('plugnpay_api_cc_key')) {
				$status = false;
			}

			// Production only: require HTTPS on the storefront
			if ($status) {
				$https_on = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off');
				$config_ssl = (defined('HTTPS_SERVER') && strpos(HTTPS_SERVER, 'https:') === 0);
				if (!$https_on && !$config_ssl && !(bool)$this->config->get('config_ssl')) {
					$status = false;
				}
			}

			if ($status && !function_exists('curl_init')) {
				$status = false;
			}

		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$method_data = array(
				'id' => 'plugnpay_api_cc',
				'title' => $language->get('text_title'),
				'sort_order' => $this->config->get('plugnpay_api_cc_sort_order')
			);
		}

		return $method_data;
	}
}
