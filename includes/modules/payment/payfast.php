<?php
/**
 * /includes/modules/payment/payfast.php
 *
 * Copyright (c) 2009-2012 PayFast (Pty) Ltd
 * 
 * LICENSE:
 * 
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 * 
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 * 
 * @author     Ron Darby
 * @copyright  2009-2012 PayFast (Pty) Ltd
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version    1.0.0
 */
include('payfast_common.inc');
  class osC_Payment_payfast extends osC_Payment {
    var $_title,
        $_code = 'payfast',
        $_status = false,
        $_sort_order,
        $_order_id,
        $_ignore_order_totals = array('sub_total', 'tax', 'total'),
        $_transaction_response,
        $pfHost = '';

    // class constructor
    function osC_Payment_payfast() {
      global $osC_Database, $osC_Language, $osC_ShoppingCart;
      define('PF_DEBUG',MODULE_PAYMENT_PAYFAST_DEBUG);
      $this->_title = $osC_Language->get('payment_payfast_title');
      $this->_method_title = $osC_Language->get('payment_payfast_method_title');
      $this->_sort_order = MODULE_PAYMENT_PAYFAST_SORT_ORDER;
      $this->_status = ((MODULE_PAYMENT_PAYFAST_STATUS == '1') ? true : false);
      
      if (MODULE_PAYMENT_PAYFAST_GATEWAY_SERVER == 'Live') {
        $this->pfHost = 'www.payfast.co.za';
        $this->form_action_url = 'https://'.$this->pfHost.'/eng/process';
      } else {
        $this->pfHost = 'sandbox.payfast.co.za';
        $this->form_action_url = 'https://'.$this->pfHost.'/eng/process';
      }
      
      if ($this->_status === true) {
        $this->order_status = MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID > 0 ? (int)MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID : (int)ORDERS_STATUS_PAID;

        if ((int)MODULE_PAYMENT_PAYFAST_ZONE > 0) {
          $check_flag = false;

          $Qcheck = $osC_Database->query('select zone_id from :table_zones_to_geo_zones where geo_zone_id = :geo_zone_id and zone_country_id = :zone_country_id order by zone_id');
          $Qcheck->bindTable(':table_zones_to_geo_zones', TABLE_ZONES_TO_GEO_ZONES);
          $Qcheck->bindInt(':geo_zone_id', MODULE_PAYMENT_PAYFAST_ZONE);
          $Qcheck->bindInt(':zone_country_id', $osC_ShoppingCart->getBillingAddress('country_id'));
          $Qcheck->execute();

          while ($Qcheck->next()) {
            if ($Qcheck->valueInt('zone_id') < 1) {
              $check_flag = true;
              break;
            } elseif ($Qcheck->valueInt('zone_id') == $osC_ShoppingCart->getBillingAddress('zone_id')) {
              $check_flag = true;
              break;
            }
          }

          if ($check_flag == false) {
            $this->_status = false;
          }
        }
      }
    }

    function selection() {
      return array('id' => $this->_code,
                   'module' => $this->_method_title);
    }
    
    function pre_confirmation_check() {
      global $osC_ShoppingCart;

      $cart_id = $osC_ShoppingCart->getCartID();
      if (empty($cart_id)) {
        $osC_ShoppingCart->generateCartID();
      }
    }

    function confirmation() {
      $this->_order_id = osC_Order::insert(ORDERS_STATUS_PREPARING);
    }

    function process_button() {
      global $osC_Customer, $osC_Currencies, $osC_ShoppingCart, $osC_Tax, $osC_Language;

      $process_button_string = '';
      $params = array('merchant_id' => MODULE_PAYMENT_PAYFAST_MERCHANT_ID,
                      'merchant_key' => MODULE_PAYMENT_PAYFAST_MERCHANT_KEY,                                            
                      'return_url' => HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . FILENAME_CHECKOUT .'?process&invoice='.$this->_order_id,
                      'cancel_url' => HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . FILENAME_CHECKOUT .'?checkout',
                      'notify_url' =>  HTTPS_SERVER . DIR_WS_HTTPS_CATALOG . FILENAME_CHECKOUT . '?callback&module=' . $this->_code
                      );
      if (MODULE_PAYMENT_PAYFAST_GATEWAY_SERVER == 'Sandbox') {
        $params['merchant_id'] = '10000100';
        $params['merchant_key'] = '46f0cd694581a';
      } 

      if ($osC_ShoppingCart->hasShippingAddress()) {
       
        $params['name_first'] = $osC_ShoppingCart->getShippingAddress('firstname');
        $params['name_last'] =  $osC_ShoppingCart->getShippingAddress('lastname');
        
      } else {
        $params['name_first'] = $osC_ShoppingCart->getBillingAddress('firstname');
        $params['name_last'] = $osC_ShoppingCart->getBillingAddress('lastname');
       
      }     

      $params['m_payment_id'] = $this->_order_id;
      $params['amount'] = $osC_Currencies->formatRaw($osC_ShoppingCart->getTotal());
      $params['item_name'] = STORE_NAME.' #'.$this->_order_id;
      $params['item_description'] = MODULE_PAYMENT_PAYFAST_ITEM_DESCRIPTION;
      $params['custom_str1'] = $osC_Customer->getID();
      $process_button_string = '';
      $secureString = '';
      foreach($params as $k=>$v){
        $secureString .= $k.'='.urlencode(trim($v)).'&';
      }
      $passphrase = MODULE_PAYMENT_PAYFAST_PASSPHRASE;
      if( !empty( $passphrase ) && MODULE_PAYMENT_PAYFAST_GATEWAY_SERVER != 'Sandbox' )
      {
          $secureString .= 'passphrase='.MODULE_PAYMENT_PAYFAST_PASSPHRASE;
      }
      else
      {
          $secureString = substr( $secureString, 0, -1 );
      }
      $params['signature'] = md5($secureString);
      foreach ($params as $key => $value) {
        $process_button_string .= osc_draw_hidden_field($key, $value);
      }
      //$process_button_string .= '<p>'.$secureString.'</p>';
      return $process_button_string;
    }

    function process() {
      global $osC_ShoppingCart, $osC_Database;
      
      $prep = explode('-', $_SESSION['prepOrderID']);
      if ($prep[0] == $osC_ShoppingCart->getCartID()) {
        $Qcheck = $osC_Database->query('select orders_status_id from :table_orders_status_history where orders_id = :orders_id');
        $Qcheck->bindTable(':table_orders_status_history', TABLE_ORDERS_STATUS_HISTORY);
        $Qcheck->bindInt(':orders_id', $prep[1]);
        $Qcheck->execute();
        
        $paid = false;
        if ($Qcheck->numberOfRows() > 0) {
          while($Qcheck->next()) {
            if ($Qcheck->valueInt('orders_status_id') == $this->order_status) {
              $paid = true;
            }
          }
        }
        
        if ($paid === false) {
          if (osc_not_null(MODULE_PAYMENT_PAYFAST_PROCESSING_ORDER_STATUS_ID)) {
            osC_Order::process($_GET['invoice'], MODULE_PAYMENT_PAYFAST_PROCESSING_ORDER_STATUS_ID, 'PayFast Processing Transaction');
          }
        }
      }
      
      unset($_SESSION['prepOrderID']);
    }

    function callback() {
      global $osC_Database, $osC_Currencies;
      
      
      
      $pfError = false;
      $pfErrMsg = '';
      $pfDone = false;
      $pfData = array();	   
      $pfParamString = '';
		
	  
				
		
        
        pflog( 'PayFast ITN call received' );
    
        //// Notify PayFast that information has been received
        if( !$pfError && !$pfDone )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }
    
        //// Get data sent by PayFast
        if( !$pfError && !$pfDone )
        {
            pflog( 'Get posted data' );
        
            // Posted variables from ITN
            $pfData = pfGetData();
        
            pflog( 'PayFast Data: '. print_r( $pfData, true ) );
        
            if( $pfData === false )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }
       
        //// Verify security signature
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify security signature' );
            $passphrase = MODULE_PAYMENT_PAYFAST_PASSPHRASE;
            $passphrase = !empty( $passphrase ) && MODULE_PAYMENT_PAYFAST_GATEWAY_SERVER != 'Sandbox' ? MODULE_PAYMENT_PAYFAST_PASSPHRASE : null;
        
            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString, $passphrase ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }
    
        //// Verify source IP (If not in debug mode)
        if( !$pfError && !$pfDone && !PF_DEBUG )
        {
            pflog( 'Verify source IP' );
        
            if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }
        //// Get internal cart
        if( !$pfError && !$pfDone )
        {
            // Get order data            
           if (isset($_POST['m_payment_id']) && is_numeric($_POST['m_payment_id']) && ($_POST['m_payment_id'] > 0)) {
          $Qcheck = $osC_Database->query('select orders_status, currency, currency_value from :table_orders where orders_id = :orders_id and customers_id = :customers_id');
          $Qcheck->bindTable(':table_orders', TABLE_ORDERS);
          $Qcheck->bindInt(':orders_id', $_POST['m_payment_id']);
          $Qcheck->bindInt(':customers_id', $_POST['custom_str1']);
          $Qcheck->execute();
            
          if ($Qcheck->numberOfRows() > 0) {
            $order = $Qcheck->toArray();
            
            $Qtotal = $osC_Database->query('select value from :table_orders_total where orders_id = :orders_id and class = "total" limit 1');
            $Qtotal->bindTable(':table_orders_total', TABLE_ORDERS_TOTAL);
            $Qtotal->bindInt(':orders_id', $_POST['m_payment_id']);
            $Qtotal->execute();
            
            $total = $Qtotal->toArray();          
            
            
            
          }  
            
            
            pflog( "Purchase:\n". print_r( $order, true )  );
            }
        }
        
        //// Verify data received
        if( !$pfError )
        {
            pflog( 'Verify data received' );
        
            $pfValid = pfValidData( $this->pfHost, $pfParamString );
        
            if( !$pfValid )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
                osC_Order::insertOrderStatusHistory($_POST['invoice'], $this->order_status, PF_ERR_BAD_ACCESS);
            }
        }
        
        //// Check data against internal order
        if( !$pfError && !$pfDone )
        {
           pflog( 'Check data against internal order' );
    
            // Check order amount
            if( !pfAmountsEqual( $pfData['amount_gross'],$total['value'] ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }          
            
        }
		
      if ($pfError) {
        osC_Order::insertOrderStatusHistory($_POST['m_payment_id'], $this->order_status, $pfErrMsg);
      }
        		
	   //// Check status and update order
        if( !$pfError && !$pfDone )
        {
            pflog( 'Check status and update order' );
    
            
            $transaction_id = $pfData['pf_payment_id'];
            $comments = '';
    		switch( $pfData['payment_status'] )
            {
                case 'COMPLETE':
                    pflog( '- Complete' );    
                    // Update the purchase status
                    $comments = 'PayFast ITN Verified [' . $_POST['payment_status'] . '] ';                    
                    break;    
    			case 'FAILED':
                    pflog( '- Failed' );    
                    $comments = 'PayFast ITN Verified [' . $_POST['payment_status'] . ']';     
        			break;     
    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }
            $comments .= '\n PayFast Transaction ID ['.$transaction_id.']';
            osC_Order::process($_POST['m_payment_id'], $this->order_status, $comments);
             
        } 
             
    }
  }
?>
