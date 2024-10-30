<?php
namespace LandingLionWP\Services;

use LandingLionWP\Factories;
use LandingLionWP\Config\App;
use LandingLionWP\Controllers\ProxyController;

class HTTP
{
    public static function DetermineUrlPurpose( $http_method, $url )
    {
        $routes_cache = App::SyncRoutesCache();
        Util::LogArray($routes_cache, "Routes Cache: ");
        if( !is_array($routes_cache))
        {
            Util::LogMessage('Routes Cache is not an array', __FILE__, __FUNCTION__);
            return 'No Mappings';
        }

        $host = parse_url( $url, PHP_URL_HOST );
        $path = rtrim( parse_url( $url, PHP_URL_PATH ), '/' );
        $url_no_protocol = $host . $path;
        $result = 'Not Set';

        if ( $http_method == 'GET' or $http_method == 'POST' )
        {
            if ( key_exists( $url_no_protocol, $routes_cache ) )
            {
                if ( key_exists( $url_no_protocol, $routes_cache ) and $routes_cache[$url_no_protocol]['ll_page_status'] == 0 )
                {
                    $result = App::URL_PURPOSE_VIEW_PAGE;
                }
                else if ( key_exists( 'll_page_status', $routes_cache[$url_no_protocol] ) and $routes_cache[$url_no_protocol]['ll_page_status'] == 1 )
                {
                    $result = App::URL_PURPOSE_DEAD;
                }
                else
                {
                    $routes_cache = App::SyncRoutesCache();
                    $result = key_exists( $url_no_protocol, $routes_cache ) ? App::URL_PURPOSE_VIEW_PAGE : 'No LL Page';
                }
            }
            else
            {
                $routes_cache = App::SyncRoutesCache();

                if ( key_exists( $url_no_protocol, $routes_cache ) and $routes_cache[$url_no_protocol]['ll_page_status'] == 0 )
                {
                    $result = App::URL_PURPOSE_VIEW_PAGE;
                }
                else if ( isset( $routes_cache[$url_no_protocol] ) and key_exists( 'll_page_status', $routes_cache[$url_no_protocol] ) and $routes_cache[$url_no_protocol]['ll_page_status'] == 1 )
                {
                    $result = App::URL_PURPOSE_DEAD;
                }
            }
        }
        else
        {
            $result = 'Not GET or POST';
        }

        Util::LogMessage( "$path Purpose Result: $result", __FILE__, __FUNCTION__ );

        return $result;
    }

    public static function DetermineProtocol( $server_global, $wp_is_ssl )
    {
        $forwarded_proto = Util::ArrayFetch( $server_global, 'HTTP_X_FORWARDED_PROTO' );
        $request_scheme = Util::ArrayFetch( $server_global, 'REQUEST_SCHEME' );
        $script_uri = Util::ArrayFetch( $server_global, 'SCRIPT_URI' );
        $script_uri_scheme = parse_url( $script_uri, PHP_URL_SCHEME );
        $https = Util::ArrayFetch( $server_global, 'HTTPS', 'off' );

        if (HTTP::IsValidProtocol( $forwarded_proto)) {
            return $forwarded_proto . '://'; }
        elseif ($wp_is_ssl || !is_null( $https ) && $https !== 'off') {
            return 'https://'; }
        elseif ( HTTP::IsValidProtocol( $request_scheme ) ) {
            return $request_scheme . '://'; }
        elseif ( HTTP::IsValidProtocol( $script_uri_scheme ) ) {
            return $script_uri_scheme . '://'; }
        else { return 'http://'; }
    }

    public static function StreamRequest( $target_url, $current_url, $cookie_string )
    {
        Util::LogMessage("Stream Request Start...", __FILE__, __FUNCTION__);
        $proxy_controller = new ProxyController();
        $proxy_controller->ProxyWpPage($current_url, $target_url, $cookie_string);
    }

    public static function IsLLPageLive( $target_url ){
        $curl = curl_init();

        if ( !defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        if ( !defined( 'DONOTCDN' ) ) {
            define( 'DONOTCDN', true );
        }

        if ( !defined( 'DONOTCACHEDB' ) ) {
            define( 'DONOTCACHEDB', true );
        }

        if ( !defined( 'DONOTMINIFY' ) ) {
            define( 'DONOTMINIFY', true );
        }

        if ( !defined( 'DONOTCACHEOBJECT' ) ) {
            define( 'DONOTCACHEOBJECT', true );
        }

        curl_setopt_array( $curl, array (
            CURLOPT_URL => $target_url,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_NOBODY => 1,
            CURLOPT_HEADER => 1,
            CURLOPT_RETURNTRANSFER => 1 ));

        $response = curl_exec( $curl );
        $response_info = curl_getinfo( $curl );
        $status_code = Util::ArrayFetch($response_info, 'http_code', false );

        curl_close( $curl );

        if( $status_code === 404)
        {
            Util::LogMessage("Page - $target_url - is dead ($status_code)", __FILE__, __FUNCTION__ );
            return false;
        }
        elseif( $status_code < 400)
        {
            Util::LogMessage("Page - $target_url - is live", __FILE__, __FUNCTION__ );
            return true;
        }
        else
        {
            Util::LogMessage("Page - $target_url - is dead ($status_code)", __FILE__, __FUNCTION__ );
            return false;
        }
    }

    private static function IsValidProtocol( $protocol )
    {
        return $protocol === 'http' || $protocol === 'https';
    }

    public static function ConvertToCookieString( $cookies )
    {
        $join_cookie_values = function ( $k, $v )
        {
            return $k . '=' . $v;
        };
        $cookie_strings = array_map( $join_cookie_values,
        array_keys( $cookies ),
        $cookies );

        return join( '; ', $cookie_strings );
    }

    //taken from: http://stackoverflow.com/a/13036310/322727
    private static function ConvertHeadersForCurl( $headers )
    {
        // map to curl-friendly format
        $req_headers = array();
        array_walk( $headers, function ( &$v, $k ) use ( &$req_headers )
        {
            array_push( $req_headers, $k . ": " . $v );
        } );

        return $req_headers;
    }
}
?>
