<?php
/*------------------------------------------------------------------------------
  PlugnPay Smart Screens v2 — payment method model
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

class ModelExtensionPlugnpaySs2 extends Model {
	public function getMethod($address) {
		$language = new ALanguage($this->registry, $this->language->getLanguageCode(), 0);
		$language->load($language->language_details['directory']);
		$language->load('plugnpay_ss2/plugnpay_ss2');

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

			// Strongly recommend HTTPS (required for reliable return session)
			if ($status) {
				$https_on = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off');
				$config_ssl = (defined('HTTPS_SERVER') && strpos(HTTPS_SERVER, 'https:') === 0);
				if (!$https_on && !$config_ssl && !(bool)$this->config->get('config_ssl')) {
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
