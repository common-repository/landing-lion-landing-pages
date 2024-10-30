<?php
/**
 * Plugin Name: Landing Lion Landing Pages
 * Plugin URI: http://integrations.landinglion.com/wordpress
 * Description: The Landing Lion plugin lets you easily add your published Landing Lion Pages to your WordPress site. This plugin not compatible with Multisite Wordpress only with Single Site Wordpress sites.
 * Version: 0.4.2
 * Author: Landing Lion
 * Author URI: https://landinglion.com/about
 * License: GPLv2
 */

namespace LandingLionWP;

use Exception;
use LandingLionWP\Controllers\ProxyController;
use LandingLionWP\Services\HTTP;
use LandingLionWP\Services\Util;
use LandingLionWP\Config\App;
use LandingLionWP\Repository\PageMappingRepo;

require_once dirname(__FILE__) . '/App/Config/App.php';
require_once dirname(__FILE__) . '/App/Services/Util.php';
require_once dirname(__FILE__) . '/App/Services/Http.php';
require_once dirname( __FILE__ ) . '/App/Views/LLSettingsPage.php';
require_once dirname(__FILE__) . '/App/Controllers/ProxyController.php';
require_once dirname(__FILE__) . '/App/Models/PageResponse.php';
require_once dirname(__FILE__) . '/App/Models/PageMapping.php';
require_once dirname(__FILE__) . '/App/Repository/PageMappingRepo.php';
require_once dirname(__FILE__) . '/App/Repository/WPPageRepo.php';

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

register_activation_hook( __FILE__, 'LandingLionWP\LLPluginSetup' );
register_activation_hook( __FILE__, function () {
    add_option( App::LL_ROUTES_CACHE_KEY, array() );
    add_option( App::LL_CLIENT_DOMAIN_KEY, '' );
    add_option( App::LL_DOMAIN_ID_KEY, '' );
    add_option( App::LL_PROXY_ERROR_MESSAGE_KEY, '' );
    add_option( App::LL_LOGGING_ON, App::IsLoggingOn() );
} );

//TODO change to uninstall hook
register_deactivation_hook( __FILE__, 'LandingLionWP\DeleteLLPluginData' );

register_deactivation_hook( __FILE__, function () {
    foreach ( App::GetOptionKeys() as $option_key ) {
        delete_option( $option_key );
    }
} );

add_action( 'init', 'LandingLionWP\ProxyUrl' );
add_action( 'before_delete_post', 'LandingLionWP\DeleteLLPageMapping' );
add_action( 'wp_trash_post', 'LandingLionWP\TrashLLPageMapping' );
add_action( 'save_post', 'LandingLionWP\UpdateLLPageMapping' );


function ProxyUrl()
{
    $mapping_repo = new PageMappingRepo();
    $routes_cache = App::SyncRoutesCache();
    $domain = App::GetWPHostDomain();
    $current_path = strtolower(Util::ArrayFetch( $_SERVER, 'REQUEST_URI' ));
    $http_method = Util::ArrayFetch( $_SERVER, 'REQUEST_METHOD' );
    $protocol = HTTP::DetermineProtocol( $_SERVER, is_ssl() );

    $url_split = explode( "?", $domain . $current_path);
    $url_no_protocol = sizeof($url_split) > 1 ? rtrim( $url_split[0], "/" ): rtrim($domain . $current_path, "/");
    $current_url = $protocol . $domain . $current_path;
    $query_params = sizeof($url_split) > 1 ? $url_split[1] : false;

    $target_mapping = Util::ArrayFetch($routes_cache, $url_no_protocol);
    $target_url = Util::ArrayFetch($target_mapping, 'll_static_url');

    $url_purpose = HTTP::DetermineUrlPurpose( $http_method, $current_url );

    if ( $url_purpose == App::URL_PURPOSE_VIEW_PAGE ) {
      $is_live = HTTP::IsLLPageLive( $target_url );
      $url_purpose = $is_live ? App::URL_PURPOSE_VIEW_PAGE : App::URL_PURPOSE_DEAD;
      Util::LogMessage("Checking if page is live... $is_live", __FILE__, __FUNCTION__ );
    }

    if ( $url_purpose == App::URL_PURPOSE_VIEW_PAGE ) {
        if ($target_url) {
            $target_url = $query_params ? "$target_url?$query_params" : $target_url;
            $cookie_keys = array( 'CampaignId', 'PageId', 'SessionId', 'VariantId', 'VisitorId', 'variantWeightIndex' );
            $cookies_to_forward = Util::ArraySelectByKey( $_COOKIE, $cookie_keys );
            $cookie_string = HTTP::ConvertToCookieString( $cookies_to_forward );

            Util::LogMessage( " target_url = $target_url", __FILE__, __FUNCTION__ );
            HTTP::StreamRequest( $target_url, $current_url, $cookie_string );
        }
        else{
            Util::LogMessage( " No target_url for current url", __FILE__, __FUNCTION__ );
        }

        exit( 0 );
    }
    else if ( $url_purpose == App::URL_PURPOSE_DEAD ) {
        Util::LogMessage( "Show 404 page", __FILE__, __FUNCTION__ );
        $host = parse_url( $current_url, PHP_URL_HOST );
        $path = rtrim( parse_url( $current_url, PHP_URL_PATH ), "/" );
        $page_url = $host . $path;
        $mapping = $mapping_repo->FindByWPUrl($page_url);

        LLShow404Page();
    }
    else {
        $path = rtrim(explode( "?", $current_path )[0], "/");
        Util::LogMessage( "$path not linked to LL page", __FILE__, __FUNCTION__ );
    }
}


function LLShow404Page()
{
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    get_template_part( 404 );
}

function LLPluginSetup()
{
    if (function_exists( 'is_multisite' ) && is_multisite())
    {
        Util::LogMessage("Multisite is enabled", __FILE__, __FUNCTION__);
        exit( sprintf( 'The Landing Lion plugin is only compatible with single site Wordpress installations.'));
    }
    else if (version_compare('5.6', phpversion()) > 0)
    {
        $php_version = phpversion();
        Util::LogMessage("Website php version is $php_version", __FILE__, __FUNCTION__);
        exit( sprintf("You are using version $php_version of php. The Landing Lion Plugin is only compatible with version 5.6 or greater of php. Please upgrade to use the Landing Lion Plugin."));
    }
    else
    {
        CreateLLMappingTable();
    }
}

function CreateLLMappingTable()
{
    global $wpdb;
    $table_name = $wpdb->prefix . App::MappingTableName();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
		ID bigint(20) not null auto_increment,
		status varchar(20) not null DEFAULT 'ok',
		ll_static_url varchar(255) not null,
		ll_page_id bigint(20) UNSIGNED not null,
		ll_page_status boolean not null DEFAULT FALSE,
		wp_page_id bigint(20) UNSIGNED not null,
		wp_page_title varchar(100) not null,
		wp_page_url varchar(255) not null,
		wp_page_slug varchar(200) not null,
		wp_page_status varchar(20) not null DEFAULT 'publish',
		PRIMARY KEY (ID)
		) $charset_collate;";

    dbDelta( $sql );
}

function DeleteLLPluginData()
{
    global $wpdb;
    $table_name = $wpdb->prefix . App::MappingTableName();

    try {
        $sql = "SELECT wp_page_id from $table_name";
        $results = $wpdb->get_results( $sql );

        Util::LogMessage( "delete mapped pages:: ID's = " . json_encode( $results ), __FILE__, __FUNCTION__ );

        foreach ( $results as $result ) {
            wp_delete_post( $result->wp_page_id, true );
        }
    } catch ( Exception $e ) {
        Util::LogMessage( $e->getMessage(), __FILE__, __FUNCTION__ );
    }

    DropLLMappingTable();

}

function DropLLMappingTable()
{
    global $wpdb;
    $table_name = $wpdb->prefix . App::MappingTableName();
    $sql = "drop table if exists $table_name;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    try {
        $wpdb->query( $sql );
    } catch ( Exception $e ) {
        Util::LogMessage( "Caught Exception when attempting to drop " . App::MappingTableName()
                              . " -- " . $e->getMessage(), __FILE__, __FUNCTION__ );
    }
}

function TrashLLPageMapping( $page_id )
{
    $mapping_repo = new PageMappingRepo();
    $mapping_repo->TrashByWPPageId( $page_id );
}

function DeleteLLPageMapping( $page_id )
{
    Util::DeletePageMapping( $page_id );
}

function UpdateLLPageMapping( $page_id )
{
    Util::UpdateMappingAfterSavedPost( $page_id );
}
?>
