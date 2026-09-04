<?php
/*------------------------------------------------------------------------------
  PlugnPay Smart Screens v2 — payment method model
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
	exit;
}

class ModelExtensionPlugnpaySs2 extends Model {
	public function getMethod($address) {
		$language = new ALanguage($this->registry, $this->language->getLanguageCode(), 0);
		$language->load($language->language_details['directory']);
		$language->load('plugnpay_ss2/plugnpay_ss2');
		require_once(DIR_EXT . 'plugnpay_ss2/core/PnPSs2Filter.php');

		if ($this->config->get('plugnpay_ss2_status')) {
			$query = $this->db->query(
				"SELECT * FROM `" . $this->db->table("zones_to_locations") . "` "
				. "WHERE location_id = '" . (int)$this->config->get('plugnpay_ss2_location_id') . "' "
				. "AND country_id = '" . (int)$address['country_id'] . "' "
				. "AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')"
			);

			if (!$this->config->get('plugnpay_ss2_location_id')) {
				$status = true;
			} elseif ($query->num_rows) {
				$status = true;
			} else {
				$status = false;
			}

			if (!$this->config->get('plugnpay_ss2_login')) {
				$status = false;
			}
			if (!$this->config->get('plugnpay_ss2_response_hash')) {
				$status = false;
			}

			if ($status) {
				$https_on = PnPSs2Filter::isHttpsRequest(is_array($this->request->server) ? $this->request->server : array());
				if (!$https_on) {
					$status = false;
				}
			}
		} else {
			$status = false;
		}

		$method_data = array();
		if ($status) {
			$method_data = array(
				'id' => 'plugnpay_ss2',
				'title' => $language->get('text_title'),
				'sort_order' => $this->config->get('plugnpay_ss2_sort_order')
			);
		}
		return $method_data;
	}
}
