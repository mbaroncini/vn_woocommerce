<?php

/*
  Plugin Name: Verde Natura - Woocommerce extension
  Description: Extends Woocommerce functionalities
  Author: Marco Baroncini
  Version: 0.1
 */


// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;


define('VNWC_FILE', __FILE__ );

require_once ('VnWc_Cron.php');

function vnwc_sendReminderEmailBeforeTrip()
{

    trigger_error('VNWC CRON started',E_USER_NOTICE );

    /**
     * Get partially paid orders
     * todo get only orders 30 days from trip
     */
    $args = array(
        'status' => 'partially-paid',
        'limit' => '-1',
        'return' => 'objects'
    );
    //https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
    $orders = wc_get_orders( $args );


    /**
     * only for tests
     * TODO: remove this line
     */
    //$orders = FALSE;//REMOVE THIS

    if ( is_array( $orders ) && ! empty( $orders ) )
    {
        /**
         * Get email senders
         */
        $wc_emails = WC()->mailer()->emails;
        if ( $wc_emails )
        {
            /**
             * Get only reminder email object provided by woocommerce-deposits plugin
             */
            $remaining_reminder_email = isset( $wc_emails['WC_Deposits_Email_Customer_Remaining_Reminder'] ) ? $wc_emails['WC_Deposits_Email_Customer_Remaining_Reminder'] : FALSE;
            if ( $remaining_reminder_email )
            {
                foreach ( $orders as $order )
                {
                    $order_id = $order->ID;

                    /**
                     * only for tests
                     * TODO: remove this line
                     */
                    $order_id = 44794; //test

                    //send email
                    $remaining_reminder_email->trigger($order_id);
                }

            }
        }
    }

}
new VnWc_Cron('vnwc_check_daily_partially_paid_orders','vnwc_sendReminderEmailBeforeTrip');

/**
 * Debug email content
 * TODO: remove this hook usage
 */
add_filter( 'wp_mail', function($wp_mail){
    $stop = 'here';
    $wp_mail['to'] = 'spartaco4404@yahoo.it';
    return $wp_mail;
} , 10 , 1 );



add_filter('woocommerce_deposits_cart_deposit_amount' , 'vnwc_disablePartialPaymentOnNearTrip' , 99 , 2 );
function vnwc_disablePartialPaymentOnNearTrip( $deposit_amount , $cart_total )
{
    /**
     * Max days supported for deposit payment
     */
    $days_interval = 30;//config var

    $wc = WC();

    /**
     * only for tests
     * TODO: remove this line
     */
    $wc->cart->add_discount( 15663971975130 );//test

    /**
     * get departure date from coupon
     */
    if ( ! empty( $wc->cart->applied_coupons ) ) {
        $coupons = $wc->cart->get_coupons();
        if ( is_array( $coupons ) && ! empty( $coupons ) )
        {
            $coupon = reset($coupons);
            /**
             * get coupon description (json with departure date info)
             */
            if ( $description = $coupon->get_description() )
            {
                $info = json_decode($description, true);
                if ( isset( $info["departureDate"] ) && $info["departureDate"] )
                {
                    /**
                     * calculate days between now and departure data
                     */
                    $diff_days = vnwc_getDaysBetween( $info["departureDate"] );

                    //departure date in wrong format, impossible create new DateTime object
                    if ( $diff_days === FALSE)
                    {
                        trigger_error("vn_woocommerce plugin error, impossible get days before departure");
                        return $deposit_amount;
                    }

                    if( $diff_days <= $days_interval )
                    {
                        //remove child theme hook in cart totals sections
                        remove_action( 'woocommerce_cart_totals_after_order_total','show_deposit_amount' );
                        //disable deposit
                        $deposit_amount = 0;
                    }


                }

            }
        }

    }

    return $deposit_amount;
}


function vnwc_getDaysBetween( $date , $date2 = 'now' )
{
    $result = FALSE;

    try {
        if ( ! $date instanceof DateTime )
            $date = new DateTime( $date );

        if ( ! $date2 instanceof DateTime )
            $date2 = new DateTime('now' , $date->getTimezone() );

        $date_time = $date->getTimestamp();
        $date2_time = $date2->getTimestamp();

        $diff = $date_time - $date2_time;

        $result = round( $diff / (60 * 60 * 24) );
    }
    catch( Exception $e )
    {
        trigger_error('vn_woocommerce plugin error, impossible compare dates provided: ' . $e->getMessage() );
    }

    return $result;
}


