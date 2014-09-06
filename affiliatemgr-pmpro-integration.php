<?php
/*
Plugin Name: Affiliates Manager Paid Membership Pro Integration
Plugin URI: https://wpaffiliatemanager.com
Description: Process an affiliate commission via Affiliates Manager after a Paid Membership Pro checkout.
Version: 1.0
Author: wp.insider
Author URI: https://wpaffiliatemanager.com
*/

//process affiliate and save id
function wpam_pmpro_after_checkout($user_id)
{
    WPAM_Logger::log_debug('Paid Membership Pro Integration - after checkout hook fired.');
    $strRefKey = NULL;
    if(isset( $_COOKIE[WPAM_PluginConfig::$RefKey])){
        $strRefKey = $_COOKIE[WPAM_PluginConfig::$RefKey];
    }
    if(isset($strRefKey))
    {
        WPAM_Logger::log_debug('Paid Membership Pro Integration - Tracking data present. Need to track affiliate commission. Tracking value: '.$strRefKey);
        $morder = new MemberOrder();	
        $morder->getLastMemberOrder($user_id);
        if(!empty($morder->total))
        {
            $sale_amt = $morder->total;
            $unique_transaction_id = $morder->code;
            $email = $morder->Email;
            $requestTracker = new WPAM_Tracking_RequestTracker();
            $requestTracker->handleCheckoutWithRefKey( $unique_transaction_id, $sale_amt, $strRefKey);
            WPAM_Logger::log_debug('Paid Membership Pro Integration - Commission tracked for transaction ID: '.$unique_transaction_id.'. Purchase amt: '.$sale_amt);
            //save affiliate id in order
            $morder->affiliate_id = $strRefKey;
            $morder->saveOrder();
        }       
    }
}
add_action("pmpro_after_checkout", "wpam_pmpro_after_checkout");
 
//for new orders (e.g. recurring orders via web hooks) check if a previous affiliate id was used and process
function wpam_pmpro_add_order($morder)
{
    WPAM_Logger::log_debug('Paid Membership Pro Integration - recurring payment hook fired.');
    if(!empty($morder->total))
    {
        $sale_amt = $morder->total;
        $unique_transaction_id = $morder->code;
        $muser = get_userdata($morder->user_id);
        $email = $muser->user_email;
        //need to get the last order before this
        $last_order = new MemberOrder();
        $last_order->getLastMemberOrder($morder->user_id);

        if(!empty($last_order->affiliate_id))
        {		
            //perform
            $referrer = $last_order->affiliate_id;			
            WPAM_Logger::log_debug('Paid Membership Pro Integration - Tracking data present. Need to track affiliate commission. Tracking value: '.$referrer);
            $requestTracker = new WPAM_Tracking_RequestTracker();
            $requestTracker->handleCheckoutWithRefKey( $unique_transaction_id, $sale_amt, $referrer);
            //update the affiliate id for this order
            global $wpa_pmpro_affiliate_id;  //review this field
            $wpa_pmpro_affiliate_id = $referrer;
        }		
    }
}
add_action("pmpro_add_order", "wpam_pmpro_add_order");
 
//after the order is saved update the affiliate id column again
function wpam_pmpro_added_order($morder)
{
    global $wpa_pmpro_affiliate_id; //review this field
    if(!empty($wpa_pmpro_affiliate_id))
    {
        $morder->affiliate_id = $wpa_pmpro_affiliate_id;
        $morder->saveOrder();				
    }
}
add_action("pmpro_added_order", "wpam_pmpro_added_order");
 
//show affiliate id on orders dashboard page
add_action("pmpro_orders_show_affiliate_ids", "__return_true");
