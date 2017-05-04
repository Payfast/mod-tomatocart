<?php
/**
 * /admin/includes/modules/payment/payfast.php
 *
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 * 
 * @author     Ron Darby
 * @version    1.0.0
 */

/**
 * The administration side of the PayFast payment module
 */

  class osC_Payment_payfast extends osC_Payment_Admin {

/**
 * The administrative title of the payment module
 *
 * @var string
 * @access private
 */
  var $_title;
  
/**
 * The code of the payment module
 *
 * @var string
 * @access private
 */

  var $_code = 'payfast';
  
/**
 * The developers name
 *
 * @var string
 * @access private
 */

  var $_author_name = 'ron.darby@payfast.co.za -- PayFast';
  
/**
 * The developers address
 *
 * @var string
 * @access private
 */  
  
  var $_author_www = 'https://www.payfast.co.za';
  
/**
 * The status of the module
 *
 * @var boolean
 * @access private
 */

  var $_status = false;
  
/**
 * Constructor
 */

  function osC_Payment_payfast() {
    global $osC_Language;
    
    $this->_title = $osC_Language->get('payment_payfast_title');
    $this->_description = $osC_Language->get('payment_payfast_description');
    $this->_method_title = $osC_Language->get('payment_payfast_method_title');
    $this->_status = (defined('MODULE_PAYMENT_PAYFAST_STATUS') && (MODULE_PAYMENT_PAYFAST_STATUS == '1') ? true : false);
    $this->_sort_order = (defined('MODULE_PAYMENT_PAYFAST_SORT_ORDER') ? MODULE_PAYMENT_PAYFAST_SORT_ORDER : null);
  }
  
/**
 * Checks to see if the module has been installed
 *
 * @access public
 * @return boolean
 */

  function isInstalled() {
    return (bool)defined('MODULE_PAYMENT_PAYFAST_STATUS');
  }
  
/**
 * Installs the module
 *
 * @access public
 * @see osC_Payment_Admin::install()
 */

  function install() {
    global $osC_Database, $osC_Language;
      
    parent::install();
    
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('* Enable PayFast Website Payments', 'MODULE_PAYMENT_PAYFAST_STATUS', '-1', 'Do you want to accept PayFast Website Payments payments?', '6', '0', 'osc_cfg_set_boolean_value(array(1, -1))', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('* PayFast Merchant ID', 'MODULE_PAYMENT_PAYFAST_MERCHANT_ID', '0', 'The seller Merchant ID provided by PayFast to accept payments.', '6', '0', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('* PayFast Merchant Key', 'MODULE_PAYMENT_PAYFAST_MERCHANT_KEY', '0', 'The seller Merchant Key provided by PayFast to accept payments.', '6', '0', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('* PayFast Secret PassPhrase', 'MODULE_PAYMENT_PAYFAST_PASSPHRASE', '0', 'DO NOT SET THIS UNLESS YOU HAVE SET IT UP IN THE SETTINGS SECTION OF YOUR PAYFAST ACCOUNT.', '6', '0', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYFAST_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYFAST_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '0', 'osc_cfg_use_get_zone_class_title', 'osc_cfg_set_zone_classes_pull_down_menu', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('* Set Processing Order Status', 'MODULE_PAYMENT_PAYFAST_PROCESSING_ORDER_STATUS_ID', '" . ORDERS_STATUS_PROCESSING . "', 'When the customer is returned to the Checkout Complete page from PayFast, this order status should be used', '6', '0', 'osc_cfg_set_order_statuses_pull_down_menu', 'osc_cfg_use_get_order_status_title', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('* Set PayFast Acknowledged Order Status', 'MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID', '" . ORDERS_STATUS_PAID . "', 'When the PayFast payment is successfully made, this order status should be used', '6', '0', 'osc_cfg_set_order_statuses_pull_down_menu', 'osc_cfg_use_get_order_status_title', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('* Gateway Server', 'MODULE_PAYMENT_PAYFAST_GATEWAY_SERVER', 'Sandbox', 'Use the sandbox or live gateway server for transactions?', '6', '0', 'osc_cfg_set_boolean_value(array(\'Live\', \'Sandbox\'))', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Debug ITN Information', 'MODULE_PAYMENT_PAYFAST_DEBUG', 'true', 'Debug the notification sent by PayFast, saved as payfast.log.', '6','0', 'osc_cfg_set_boolean_value(array(\'false\',\'true\'))', now())");
    $osC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Item Description', 'MODULE_PAYMENT_PAYFAST_ITEM_DESCRIPTION', 'Shipping, Handling, Discounts and Taxes Included', 'Item description used for PayFast processing.', '6', '0', now())");

  }

/**
 * Return the configuration parameter keys in an array
 *
 * @access public
 * @return array
 */

  function getKeys() {
    if (!isset($this->_keys)) {
      $this->_keys = array('MODULE_PAYMENT_PAYFAST_STATUS', 
                           'MODULE_PAYMENT_PAYFAST_MERCHANT_ID',
                           'MODULE_PAYMENT_PAYFAST_MERCHANT_KEY', 
                           'MODULE_PAYMENT_PAYFAST_PASSPHRASE', 
                           'MODULE_PAYMENT_PAYFAST_ZONE',
                           'MODULE_PAYMENT_PAYFAST_PROCESSING_ORDER_STATUS_ID',
                           'MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID', 
                           'MODULE_PAYMENT_PAYFAST_GATEWAY_SERVER',
                           'MODULE_PAYMENT_PAYFAST_DEBUG', 
                           'MODULE_PAYMENT_PAYFAST_SORT_ORDER',
                           'MODULE_PAYMENT_PAYFAST_ITEM_DESCRIPTION');
    }
  
    return $this->_keys;
 } 
}
?>
