<?php
namespace LandingLionWP\Controllers;

use LandingLionWP\Services\Util;
use LandingLionWP\Config\App;
use LandingLionWP\Models\PageResponse;

class ProxyController
{
    private $curl_agent;
    private $curl_opt_array;

    public function __construct()
    {
        $this->curl_agent = curl_init();
        $this->SetCacheOptions();
    }

    public function ProxyWpPage( $current_url, $target_url, $cookie_string )
    {
        Util::LogMessage( "Begin Proxy Page......", __FILE__, __FUNCTION__ );
        $client_host = parse_url( $target_url, PHP_URL_HOST );
        $client_domain = "https://$client_host";
        $page_response = new PageResponse( $client_domain );
        App::SetClientDomain($client_domain);

        if (App::IsDevEnv())
        {
            $curl_opt_array = array(
                CURLOPT_URL => $target_url,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_COOKIE => $cookie_string,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HEADERFUNCTION => $this->StreamHeaders(),
                CURLOPT_WRITEFUNCTION => $this->StreamCurlResponse( $client_domain, $page_response ),
                CURLOPT_CAINFO => "/etc/ssl/certs/LLrootCA.pem"
            );
        }
        else {
            $curl_opt_array = array(
                CURLOPT_URL => $target_url,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_COOKIE => $cookie_string,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => $this->StreamHeaders(),
                CURLOPT_WRITEFUNCTION => $this->StreamCurlResponse( $client_domain, $page_response )
            );
        }

        $curl = $this->curl_agent;
        curl_setopt_array( $curl, $curl_opt_array );

        Util::LogMessage( "Client LL Domain set to: $client_domain", __FILE__, __FUNCTION__ );
        Util::LogMessage( "Current WP Url: $current_url", __FILE__, __FUNCTION__ );
        Util::LogMessage( "Target LL Url: $target_url", __FILE__, __FUNCTION__ );

        $response = curl_exec( $curl );
        $response_info = curl_getinfo( $curl );
        $status_code = isset($response_info['http_code']) ? $response_info['http_code'] : false;

        if ($status_code === 404) {
            $this->HandleLLPage404($current_url);
        }
        else if ($response){
            header( "Content-Length:" . $page_response->GetContentLength(), true );
            echo ($page_response->FlushContent());
            Util::LogMessage("Proxying successful to: $target_url", __FILE__, __FUNCTION__);
        }
        else {
            Util::LogMessage("Proxy Error:: Received status code ($status_code) for url: $targer_url");
            Util::LogMessage("Curl Error Message: " . curl_error($curl));
        }

        curl_close($curl);
        return;
    }

    private function StreamHeaders()
    {
        return function ( $curl, $header_string ) {
            $http_status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
            http_response_code( $http_status_code );

            if ( Util::StringContains( $header_string, "Cache-Control" ) ) {
                header( 'Cache-Control: max-age=0; private', true );
            }
            elseif ( Util::StringContains( $header_string, "Set-Cookie" ) ) {
                $domain = Util::ParseWpHomeUrl();
                preg_match( '/^Set-Cookie:\s*([^=]*)=([^;]*);/mi', $header_string, $matches );

                setcookie( $matches[1], $matches[2], 0, "/", $domain );
            }
            elseif ( Util::StringContains( $header_string, "Location" ) ) {

            }
            else {
                header( $header_string, true );
            }

            return strlen( $header_string );
        };
    }

    private function StreamCurlResponse( $client_domain, $page_response )
    {
        return function ( $curl, $string ) use ( $client_domain, $page_response ) {
            $this->SaveCurlResponse( $string, $client_domain, $page_response );
            return strlen( $string );
        };
    }

    private function SaveCurlResponse( $string, $client_domain, PageResponse $page_response )
    {
        if ( strpos( $string, "<head>" ) !== false ) {
            $new_string = str_ireplace( "<head>", '<head><base href="' . $client_domain . '">', $string );
            $page_response->AddContent( $new_string );
        }
        else {
            $page_response->AddContent( $string );
        }
    }

    public static function HandleLLPage404( $redirect_url )
    {
        $host = parse_url( $redirect_url, PHP_URL_HOST );
        $path = rtrim( parse_url( $redirect_url, PHP_URL_PATH ), "/" );
        $page_url = $host . $path;

        //get ll_static_url from db
        $mapping_repo = new PageMappingRepo();
        $mapping = $mapping_repo->FindByWPUrl( $page_url );

        if (!is_null($mapping))
        {
            //redirect client to wp 404 page
            if ( wp_redirect( $redirect_url ) ) {
                Util::LogMessage( "Handle 404:: Redirecting to WP page", __FILE__, __FUNCTION__ );
                exit;
            }
        }
    }

    private function SetCacheOptions()
    {
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
    }
}
?>
