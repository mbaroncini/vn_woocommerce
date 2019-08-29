<?php


class DaysOfWeek
{
    private $start;
    private $end;
    private $input_format = "d/m/Y";
    private $d_by_weekday = array();
    private $d = array();
    private $one_day;
    private $errors = array();

    function __construct($start,$end)
    {
        try{
            $this->start = $this->import_arg($start);
            $this->end = $this->import_arg($end);

            if ( $this->end < $this->start )
                throw new Exception('End of interval provided is lower than start.');

            $this->one_day = new DateInterval('P1D');

            $this->init();

        }
        catch (Exception $e){
            $error = "Invalid arguments provided.";
            $this->error_handler( $e , $error );
        }
    }

    /**
     * Create DateTime object
     * @param $data
     * @return bool|DateTime
     */
    private function import_arg( $data ){
        $format = $this->input_format;
        return DateTime::createFromFormat($format, $data);
    }

    /**
     * Calculate the day number of week for each day in interval start->end
     */
    protected function init(){

        $start = $this->start;
        $end = $this->end;

        //week number
        $w_n = $start->format('w');
        $i = $start ;

        while( $i < $end )
        {
            if ( ! isset( $this->d_by_weekday[$w_n] ) )
                $this->d_by_weekday[$w_n] = array();

            $tmp = clone $i;
            $this->d_by_weekday[$w_n][] = $tmp;
            $this->d[] = $tmp;

            if ( $w_n > 5 )
                $w_n = 0;
            else
                $w_n++;

            $i = $i->add($this->one_day);
        }

        ksort( $this->d_by_weekday);

    }

    /**
     * Error handler, trigger a warning and register it
     * @param bool $message
     * @param $exception - Exception object
     */
    protected function error_handler( $exception , $message = FALSE ){
        $error = "Error on Days of Weeks Constructor. " . ( $message ? $message : '' ) . $exception->getMessage();
        $this->errors[] = $error;
        trigger_error($error);
    }

    /**
     * Get all days divided in their week day
     * @return array - DateTime objects
     */
    public function get_daysOfWeek()
    {
        return $this->d_by_weekday;
    }

    public function get_start(){
        return $this->start;
    }

    public function get_end(){
        return $this->end;
    }

    public function get_errors(){
        return $this->errors;
    }

    public function has_errors(){
        return count( $this->errors ) != 0 ;
    }

    public function get_allDays(){
        return $this->d;
    }

    /**
     * Return days indexed by their week day number
     * @param $week_days - array of int
     * @param string $order_by
     * @return array - DateTime objects
     *
     * todo fix wrong atribute orderby asc/desc
     */
    public function query_byDayOfWeek( $week_days , $order_by = 'day_of_week' )
    {
        $r = array();
        if ( ! is_array( $week_days ) )
        {
            $week_days = array( $week_days );
        }

        switch ($order_by)
        {
            case 'day_of_week':
                foreach ( $week_days as $int )
                {
                    if ( isset($this->d_by_weekday[$int]) )
                        $r[$int] = array_values( $this->d_by_weekday[$int] );
                }
                break;
            /**
            case 'asc':
                foreach ( $week_days as $int )
                {
                    if ( isset($this->d_by_weekday[$int]) )
                        $r = array_merge( $r , array_values( $this->d_by_weekday[$int] ) );
                }
                ksort($r);
                break;
            case 'desc':
                foreach ( $week_days as $int )
                {
                    if ( isset($this->d_by_weekday[$int]) )
                        $r = array_merge( $r , array_values( $this->d_by_weekday[$int] ) );
                }
                ksort($r);
                $r = array_reverse($r);
                break;
             **/
            case 'none':
                foreach ( $week_days as $int )
                {
                    if ( isset($this->d_by_weekday[$int]) )
                        $r = array_merge($r,$this->d_by_weekday[$int]);
                }
                break;
            default:
                break;
        }


        return $r;
    }




}