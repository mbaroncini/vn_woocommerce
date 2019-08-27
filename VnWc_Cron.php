<?php

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;


class VnWc_Cron
{
    public $plugin_main_file = VNWC_FILE;

    public $hook_name;
    public $callback_name;

    public $recurrence;

    public $when_start;

    public $hook_params;

    function __construct( $hook_name , $callback_name , $recurrence = 'daily' , $when_start = 'now' , $args = array() )
    {
        if ( ! $hook_name )
            return;

        if ( ! $callback_name || ! function_exists( $callback_name ) )
            return;

        if ( $when_start == 'now' )
            $when_start = time();

        if ( ! is_array( $args ) )
            return;

        $this->hook_name = $hook_name;
        $this->callback_name = $callback_name;
        $this->when_start = $when_start;
        $this->recurrence = $recurrence;
        $this->hook_params = $args;

        $this->activationDeactivationHandler();

        add_action( $this->hook_name, array( $this , 'call_callback' ) );
    }

    protected function activationDeactivationHandler()
    {
        register_activation_hook($this->plugin_main_file, array( $this , 'cronActivation' ) );
        register_deactivation_hook($this->plugin_main_file, array( $this , 'cronDeactivation' ) );
    }

    function cronActivation()
    {
        $this->call_callback();

        if ( ! wp_next_scheduled ( $this->hook_name ) )
            wp_schedule_event($this->when_start, $this->recurrence, $this->hook_name, $this->hook_params );
    }

    function cronDeactivation()
    {
        wp_clear_scheduled_hook($this->hook_name );
    }

    /**
     * Call php function provided by user
     * @return mixed
     */
    public function call_callback()
    {
        $return_val = call_user_func( $this->callback_name , $this->hook_params );
        return $return_val;
    }

}
