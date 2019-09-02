<?php


function dw_weekDayToWeekNumber( $days_of_week ){
    $r = array();
    $map = array('sun','mon', 'tue', 'wed', 'thu', 'fri', 'sat');
    foreach( $days_of_week as $i => $day_name )
    {
        if ( ( $j = array_search( $day_name , $map ) ) != -1  )
            $r[] = $j;
    }
    return $r;
}

function dy_OrderByMostRecentDate($a, $b)
{

    $day_a = isset( $a['properties']['all_dates'][0] ) ? DateTime::createFromFormat('Y-m-d',$a['properties']['all_dates'][0]) : FALSE;
    $day_b = isset( $b['properties']['all_dates'][0] ) ? DateTime::createFromFormat('Y-m-d',$b['properties']['all_dates'][0] ) : FALSE;

    if ( ! $day_a && $day_b )
        return 1;

    if ( $day_a && ! $day_b )
        return -1;

    if ( $day_a == $day_b ) {
        return 0;
    }

    return ($day_a < $day_b) ? -1 : 1;
}


define('VN_CALENDAR_MIN_DAYS_BF_DEPARTURE',7);

/**
 * Load WP CLI commands and utils
 */

if (defined('WP_CLI') && WP_CLI) {


    $vn_departure_json = function( $args, $assoc_args )
    {


        $t_start = time();
        // args
        $limit = -1;
        $r = array(
            'type' => 'CalendarDeparturesHome',
            'features' => array()
        );
        //$id = 43813;



        require_once('DaysOfWeek.php');


        $args = array(
            'post_type' => 'route',
            'post_status' => 'publish',
            'nopaging' => true,
            'suppress_filters' => false,
            'meta_query' => array(
                array(
                    'key' => 'wm_guided',
                    'value' => 1
                )
            )

        );

        if ( $id )
            $args['post__in'] = array( $id );

        global $sitepress;
        if ( $sitepress)
            $sitepress->switch_lang( 'it' );
        $routes = get_posts($args);


        $i=0;
        while( $i<count($routes) && ($i<$limit || $limit == -1 ))
        {

            $o = $routes[$i];
            $id = $o->ID;

            $translations = array();
            if ( $sitepress && ( $en_id = apply_filters( 'wpml_object_id', $id, 'route', false, 'en' ) ) )
                $translations['en'] = array(
                    'name' => get_the_title($en_id),
                    'image' => get_the_post_thumbnail_url($en_id),
                    'url' => get_the_permalink($en_id),
                );

            $fields = get_fields( $id );

            $d_dates = isset( $fields['departure_dates'] )
                && is_array( $fields['departure_dates'] )
                ? $fields['departure_dates'] : array();

            $d_periods = isset( $fields['departures_periods'] )
                && is_array( $fields['departures_periods'] )
                ? $fields['departures_periods'] : array();

            //var_dump( $d_dates );
            //var_dump( $d_periods );

            $dates = array();
            foreach ( $d_dates as $e )
            {
                if( isset( $e['date'] ) && $e['date'] ){
                    try{
                        $t = DateTime::createFromFormat( 'd/m/Y' , $e['date'] );
                        if ( $t instanceof DateTime )
                            $dates[] = $t;
                    }
                    catch (Exception $e )
                    {
                        continue;
                    }
                }

            }

            foreach ( $d_periods as $e )
            {
                // start stop week_days
                $sta = isset( $e['start'] ) ? $e['start'] : FALSE;
                $sto = isset( $e['stop'] ) ? $e['stop'] : FALSE;
                $w_d = isset( $e['week_days'] ) ? $e['week_days'] : FALSE;
                if ( ! $sto || ! $sta || ! $w_d )
                    continue;

                $d_o_w = new DaysOfWeek( $sta , $sto );
                $w_d_int = dw_weekDayToWeekNumber($w_d);
                $dates = array_merge($dates,$d_o_w->query_byDayOfWeek( $w_d_int , 'none' ));
            }

            $dates = array_unique( $dates , SORT_REGULAR );

            //removes departures in this week
            $dates = array_filter( $dates , function(DateTime $i){
                if ( $i->getTimestamp() < time() + 60 * 60 * 24 * VN_CALENDAR_MIN_DAYS_BF_DEPARTURE )
                    return false;
                return true;
            });

            //order single dates
            asort($dates);

            $post = array(
                'properties' => [
                    'id' => $id,
                    'name' => $o->post_title,
                    'image' => get_the_post_thumbnail_url($id),
                    'url' => get_the_permalink( $id ),
                    'locale' => "it",
                    'duration' => $fields['vn_durata'] ? $fields['vn_durata'] : '',//vn_durata
                    'price' => $fields['wm_route_price'] ? $fields['wm_route_price'] : 0,//wm_route_price
                    'wm_route_tax_activity_id' => $fields['wm_route_tax_activity'] ? (array) maybe_unserialize($fields['wm_route_tax_activity']) : array(),//wm_route_tax_activity (serialized)
                    'all_dates' => array_values( array_map( function(DateTime $i){ return $i->format('Y-m-d'); } , $dates) ),//calculated from departures_periods + departure_dates
                    'translations' => $translations
                ]

            );

            $r['features'][] = $post;
            $i++;
        }

        $temp = $r['features'];
        usort($temp, "dy_OrderByMostRecentDate");
        $r['features'] = $temp;



        file_put_contents('calendar_departures_all.json', json_encode($r));

        WP_CLI::line( "\n " . time() - $t_start . " seconds");
        WP_CLI::success( "######################## DONE ########################" );

    };

    WP_CLI::add_command( 'vn-departure-json', $vn_departure_json );


}