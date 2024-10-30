<?php

namespace LandingLionWP\Config;

use LandingLionWP\Services\Util;
use LandingLionWP\Repository\PageMappingRepo;

class App
{
  const LL_PLUGIN_NAME = 'landing-lion-wpp';
  const LL_CACHE_TIMEOUT_ENV_KEY = 'LL_WP_ROUTES_CACHE_EXP';
  const LL_USER_AGENT = 'Landing Lion WP Plugin 0.4.0';
  const LL_VERSION = '0.4.0';
  const LL_HOOK_NAME = 'toplevel_page_landing-lion-wpp/App/Views/LLSettingsPage';

  //Option Keys
  const LL_ROUTES_CACHE_KEY = 'll-routes-cache';
  const LL_SCRIPTS_CACHE_KEY = 'll-scripts-cache';
  const LL_CLIENT_DOMAIN_KEY = 'll-client-domain';
  const LL_DOMAIN_ID_KEY = 'll-domain-id';
  const LL_PROXY_ERROR_MESSAGE_KEY = 'll-proxy-error-message';
  const LL_LOGGING_ON = 'll-logging-on';

  //plugin-scoped constants
  const URL_PURPOSE_VIEW_PAGE = 'll-view-page';
  const URL_PURPOSE_DEAD = 'dead';
  const LL_TABLE_NAME = 'll_page_mappings';
  const LL_PAGE_STATUSES = [ 'publish', 'trash', 'future', 'draft', 'pending', 'private', 'auto-draft', 'inherit' ];
  const LL_ALLOWED_MESSAGE_ORIGIN = 'https://wp.landinglion.com';
  const LL_ALLOWED_MESSAGE_ORIGIN_DEV = 'https://wp.dev.landinglion.com';
  const LL_PLUGIN_ENV = 'prod';
  const LL_DEBUG_MODE = false;

  public static function GetOptionKeys()
  {
      return array(
          App::LL_ROUTES_CACHE_KEY,
          App::LL_CLIENT_DOMAIN_KEY,
          App::LL_DOMAIN_ID_KEY,
          App::LL_PROXY_ERROR_MESSAGE_KEY,
          App::LL_LOGGING_ON
      );
  }

  public static function GetLLAppName()
  {
      $wp_host_url = parse_url( home_url(), PHP_URL_HOST );

      if ( ( $wp_host_url == "192.168.102.109" || $wp_host_url == "localhost") )
      {
          return 'landing-lion-wpp';
      }
      else
      {
          return 'landing-lion-landing-pages';
      }
  }

  public static function IsLoggingOn()
  {
      if ( App::LL_PLUGIN_ENV == 'dev' || App::LL_DEBUG_MODE )
      {
          return true;
      }
      else
      {
          return false;
      }
  }

  public static function IsDevEnv()
  {
      $wp_host_url = parse_url( home_url(), PHP_URL_HOST );
      $env = App::LL_PLUGIN_ENV;

      if ( ( $wp_host_url == "192.168.102.109" || $wp_host_url == "localhost") &&  $env == 'dev')
      {
          return true;
      }
      else
      {
          return false;
      }
  }

  public static function GetRoutesCache()
  {
      return get_option( App::LL_ROUTES_CACHE_KEY, array() );
  }

  public static function SetRoutesCache( $new_routes_cache )
  {
      update_option( App::LL_ROUTES_CACHE_KEY, $new_routes_cache );
  }

  public static function ClearRoutesCache()
  {
      update_option( App::LL_ROUTES_CACHE_KEY, array() );
  }

  public static function SyncRoutesCache()
  {
      $mapping_repo = new PageMappingRepo();
      $mappings = $mapping_repo->Fetch('url');
      update_option( App::LL_ROUTES_CACHE_KEY, $mappings );
      return self::GetRoutesCache();
  }

  public static function RemoveCacheEntry( $path )
  {
      $routes_cache = App::GetRoutesCache();
      if ( isset( $path, $routes_cache ) )
      {
          unset( $routes_cache[$path] );
      }

      self::SetRoutesCache( $routes_cache );
  }

  public static function MappingTableName()
  {
      return get_option( App::LL_TABLE_NAME, 'll_page_mappings' );
  }

  public static function GetClientDomain()
  {
      return get_option( App::LL_CLIENT_DOMAIN_KEY, '' );
  }

  public static function SetClientDomain( $client_domain )
  {
      update_option( App::LL_CLIENT_DOMAIN_KEY, $client_domain );
  }

  public static function GetWPHostDomain()
  {
      return parse_url( get_home_url(), PHP_URL_HOST );
  }

  public static function GetDomainWithPort()
  {
      $port = parse_url( get_home_url(), PHP_URL_PORT );
      $host = parse_url( get_home_url(), PHP_URL_HOST );

      return $port ? $host . ':' . $port : $host;
  }
}
?>
