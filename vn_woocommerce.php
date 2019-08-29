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

require_once('vn_calendars.php');

define('VNWC_FILE', __FILE__ );

/**
 * Woocommerce order meta key used by cron to sign orders with already sent reminder email
 */
define('VNWC_CRON_REMINDER_META_KEY','vnwc_cron_reminder');

/**
 * Woocommerce order meta key where is stored route departure date
 */
define('VNWC_ORDER_DEPARTURE_META_KEY','order_departure_date');

/**
 * This constant is used  :
 * - to set interval of days (before order departure) where isn't possible pay with deposit
 * - to send email reminder of second payment before set days before departure
 */
define('VNWC_REMINDER_AND_DEPOSIT_DAYS',30);


/**
 * DEBUG TOOLS
 * Debug email content
 * TODO: remove this hook usage

add_filter( 'wp_mail', function($wp_mail){
    $wp_mail['to'] = 'spartaco4404@yahoo.it';
    return $wp_mail;
} , 10 , 1 );
 */

/**
 * Email reminder cronjob
 */
require_once ('VnWc_Cron.php');
function vnwc_sendReminderEmailBeforeTrip()
{

    trigger_error('VNWC CRON started',E_USER_NOTICE );

    /**
     * Get partially paid orders
     * todo get only orders 30 days from trip
     */

    //calculate 30 days from now
    $days_from_now = VNWC_REMINDER_AND_DEPOSIT_DAYS;
    $now = time();
    $next_month = $now + ( 60 * 60 * 24 * $days_from_now );
    $formatted_date = date( 'Y-m-d' , $next_month );

    $args = array(
        'status' => 'partially-paid',
        'limit' => '-1',
        'return' => 'objects',
        VNWC_CRON_REMINDER_META_KEY => 0,
        'departure' => $formatted_date//departure on nexth month
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

                    $order_id = 44794; //test
                     * */

                    //send email
                    $remaining_reminder_email->trigger($order_id);
                    update_post_meta( $order_id , VNWC_CRON_REMINDER_META_KEY, 1 );
                }

            }
        }
    }

}
new VnWc_Cron('vnwc_check_daily_partially_paid_orders','vnwc_sendReminderEmailBeforeTrip');

/**
 * Add custom query vars to wc_get_orders() woocommerce funcion
 * @param array $query - Args for WP_Query.
 * @param array $query_vars - Query vars from WC_Order_Query.
 * @return array modified $query
 */
function vnwc_get_orders_before_departure_query_var( $query, $query_vars ) {

    /**
     * Get orders by departure date
     */
    if ( ! empty( $query_vars['departure'] ) ) {
        $query['meta_query'][] = array(
            'key' => VNWC_ORDER_DEPARTURE_META_KEY,
            'compare' => '=',
            'value' => $query_vars['departure']
        );
    }

    /**
     * Get orders by cron reminder meta
     */
    if ( ! empty( $query_vars[VNWC_CRON_REMINDER_META_KEY] ) ) {

        if ( $query_vars[VNWC_CRON_REMINDER_META_KEY] == 0 )
        {
           $m = array(
               'key' => VNWC_CRON_REMINDER_META_KEY,
               'compare' => 'NOT EXISTS'
        );
        }
        else
        {
            $m = array(
                'key' => VNWC_CRON_REMINDER_META_KEY,
                'compare' =>  '=',
                'value' => 1
        );
        }

        $query['meta_query'][] = $m;
    }

    return $query;
}
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'vnwc_get_orders_before_departure_query_var', 10, 2 );




add_filter('woocommerce_deposits_cart_deposit_amount' , 'vnwc_disablePartialPaymentOnNearTrip' , 99 , 2 );
function vnwc_disablePartialPaymentOnNearTrip( $deposit_amount , $cart_total )
{
    /**
     * Max days supported for deposit payment
     */
    $days_interval = VNWC_REMINDER_AND_DEPOSIT_DAYS;//config var

    $wc = WC();

    /**
     * only for tests
     * TODO: remove this line
     */
    //$wc->cart->add_discount( 15663971975130 );//test

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


/**
 * Utility function to calculate days between 2 dates
 * @param $date
 * @param string $date2 - default is today
 * @return bool|int - int on success or false on wrong formatted date provided, returns negative values if second date is more recent than first
 */
function vnwc_getDaysBetween( $date , $date2 = 'today' )
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


