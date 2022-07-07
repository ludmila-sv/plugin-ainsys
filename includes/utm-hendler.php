<?php
namespace Ainsysconnector\Master;

use UtmCookie\UtmCookie;

/**
 * AINSYS utm hendler.
 *
 * @class 		AINSYS utm hendler
 * @version		1.0.0
 * @author 		AINSYS
 */

utm_hendler::init();

class utm_hendler{

    public static function init(){

        include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/vendor/xsuchy09/utm-cookie/src/UtmCookie/UtmCookie.php';

        $utm_source = \UtmCookie\UtmCookie::get('utm_source');
        if(isset($utm_source) && $utm_source != false && $utm_source != NULL){
            $referer = self::get_referer_url();
            $host = self::get_my_host_name();


            if(isset($referer) && isset($host) && $referer !== $host)
                \UtmCookie\UtmCookie::save(['utm_source' => $referer]);
        }

    }

    /**
     * Get user IP
     *
     * @return bool|string
     */
    public static function get_my_ip(){
        if(!empty($_SERVER['REMOTE_ADDR']))
            return $_SERVER['REMOTE_ADDR'];

        //CloudFlare
        if($_SERVER['HTTP_CF_CONNECTING_IP'])
            return $_SERVER['HTTP_CF_CONNECTING_IP'];

        return false;
    }

    /**
     * Get referer url.
     *
     * @return bool|string
     */
    public static function get_referer_url(){
        $referer = false;
        if(!empty($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        }

        return $referer ? str_replace('www.', '', $referer) : false;
    }

    /**
     * Get my server addres
     *
     * @return bool|string
     */
    public static function get_my_host_name(){
        $host = false;
        if(!empty($_SERVER['SERVER_NAME']))
            $host = $_SERVER['SERVER_NAME'];

        return $host ? str_replace('www.', '', $host) : false;
    }

    /**
     * Get roistat attibute
     *
     * @return string
     */
    public static function get_roistat(){
        return isset($_COOKIE['roistat_visit']) ? $_COOKIE['roistat_visit'] : '';
    }

}