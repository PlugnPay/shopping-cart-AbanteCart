CREATE TABLE IF NOT EXISTS `ac_plugnpay_ss2` (
  `id` int(25) unsigned NOT NULL auto_increment,
  `customer_id` varchar(30) NOT NULL default '',
  `order_id` varchar(30) NOT NULL default '',
  `response_code` varchar(32) NOT NULL default '',
  `response_text` varchar(255) NOT NULL default '',
  `authorization_type` varchar(25) NOT NULL default '',
  `transaction_id` varchar(255) NOT NULL default '',
  `sent` text,
  `received` text,
  `time` varchar(255) NOT NULL default '',
  `session_id` varchar(255) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
