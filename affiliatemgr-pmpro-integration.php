<?php
/*
Plugin Name: Affiliates Manager Paid Membership Pro Integration
Plugin URI: https://wpaffiliatemanager.com
Description: Process an affiliate commission via Affiliates Manager after a Paid Membership Pro checkout.
Version: 1.0.3
Author: wp.insider, affmngr
Author URI: https://wpaffiliatemanager.com
*/
 
//show affiliate id on orders dashboard page
add_action("pmpro_orders_show_affiliate_ids", "__return_true");

//Save affiliate id before checkout
add_action('pmpro_before_send_to_paypal_standard', 'wpam_pmpro_save_aff_id_before_checkout', 10, 2);
add_action('pmpro_before_send_to_twocheckout', 'wpam_pmpro_save_aff_id_before_checkout', 10, 2);

function wpam_pmpro_save_aff_id_before_checkout($user_id, $morder) {
    WPAM_Logger::log_debug('Paid Membership Pro Integration - before checkout hook fired. user id: '.$user_id.', order id: '.$morder->code);
    $strRefKey = '';
    if(isset( $_COOKIE['wpam_id'])){
        $strRefKey = $_COOKIE['wpam_id'];
    }
    else if(isset( $_COOKIE[WPAM_PluginConfig::$RefKey])){
        $strRefKey = $_COOKIE[WPAM_PluginConfig::$RefKey];
    }
    //
    if(!empty($strRefKey)){
        //save affiliate id with the order
        WPAM_Logger::log_debug('Paid Membership Pro Integration - Tracking data present. Tracking value: '.$strRefKey);
        $morder->affiliate_id = $strRefKey;
        $morder->saveOrder();
        WPAM_Logger::log_debug('Paid Membership Pro Integration - Tracking data has been saved with the order');
    }
}

/* For handling membership recurring payments/refunds/cancellations */
add_action("pmpro_updated_order", "wpam_pmpro_updated_order");

function wpam_pmpro_updated_order($order) {
    WPAM_Logger::log_debug('Paid Membership Pro Integration - handling pmpro_updated_order hook');
    $payment_type = $order->payment_type;
    $status = $order->status;
    $sale_amt = $order->total;
    $strRefKey = $order->affiliate_id;
    $email = $order->Email;
    $first_name = $order->FirstName;
    $last_name = $order->LastName;

    $txn_id = $order->code; //actual txn_id
    $txn_id = $txn_id . "_" . date("Y-m-d"); //Add a date to txn_id to make it unique (handy when its a rebill notification for a subscription)

    WPAM_Logger::log_debug('Paid Membership Pro Integration - payment_type: '.$payment_type.', status: '.$status.', txn_id: '.$txn_id.', amount: '.$sale_amt);
    if(isset($strRefKey) && !empty($strRefKey)){
        WPAM_Logger::log_debug('Paid Membership Pro Integration - Tracking data is present. Tracking value: '.$strRefKey);
    }
    else{
        WPAM_Logger::log_debug('Paid Membership Pro Integration - Tracking data is not present. This is not an affiliate sale');
        return;
    }
    //check if commission can be awarded
    if ($status != "success") {
        WPAM_Logger::log_debug('Paid Membership Pro Integration - the order status is not set to success yet. The commission will be awarded when the status changes to success');
        return;
    }
    WPAM_Logger::log_debug('Paid Membership Pro Integration - Awarding commission for txn_id: '.$txn_id.', amount: '.$sale_amt);
    $requestTracker = new WPAM_Tracking_RequestTracker();
    $requestTracker->handleCheckoutWithRefKey($txn_id, $sale_amt, $strRefKey);
}
