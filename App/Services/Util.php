<?php

namespace LandingLionWP\Services;

use Exception;
use LandingLionWP\Config\App;

class Util
{

    public static function ArraySelectByKey( $input, $keep )
    {
        return array_intersect_key( $input, array_flip( $keep ) );
    }

    public static function ArrayFetch( $array, $index, $default = null )
    {
        if ( empty( $array ) ) {
            return $default;
        }
        return isset( $array[$index] ) ? $array[$index] : $default;
    }

    public static function StringContains( $string, $needle )
    {
        if ( strpos( $string, $needle ) !== false ) {
            return true;
        }
        return false;
    }

    public static function InsertPageMapping( $ll_static_url, $ll_page_id, $wp_page_url, $wp_page_slug, $wp_page_id, $wp_page_title, $ll_page_status, $page_status = 'publish' )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . App::MappingTableName();
        $url_no_protocol = $wp_page_url;
        $entry = $wpdb->get_results( "SELECT * FROM $table_name WHERE ll_static_url = '" . $ll_static_url . "'" );
        $new_mapping = array(
            'status' => 'ok',
            'll_page_id' => $ll_page_id,
            'll_page_status' => $ll_page_status,
            'll_static_url' => $ll_static_url,
            'wp_page_id' => $wp_page_id,
            'wp_page_status' => $page_status,
            'wp_page_title' => $wp_page_title,
            'wp_page_slug' => $wp_page_slug,
            'wp_page_url' => $url_no_protocol
        );


        if ( count( $entry ) < 1 ) {
            $result = $wpdb->insert( $table_name, $new_mapping );

            if ( $result ) {
                App::SyncRoutesCache();
                $mapping = $wpdb->get_row( "SELECT * FROM $table_name WHERE ll_page_id= '" . $ll_page_id . "'", ARRAY_A );
                Util::LogMessage( "Mapping ID :" . $mapping['ID'], __FILE__, __FUNCTION__ );

                return $mapping;
            }
            else {
                Util::LogMessage( "Insert Failed", __FILE__, __FUNCTION__ );
            }
        }
        else {
            Util::LogMessage( "Mapping exists in db", __FILE__, __FUNCTION__ );
        }

        return false;

    }

    public static function UpdateMappingAfterSavedPost($wp_page_id)
    {
        if( self::PageMappingExists($wp_page_id) )
        {
            $post_name = self::FindWpPostNameById($wp_page_id);

            if($post_name !== false)
            {
                self::UpdateMappingSlugByWpPageId($wp_page_id, $post_name);
            }
        }
    }

    public static function DeletePageMapping( $page_id )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . App::MappingTableName();
        Util::LogMessage( "Page Id to be deleted: $page_id", __FILE__, __FUNCTION__ );

        try
        {
            $wpdb->delete( $table_name, array( 'wp_page_id' => $page_id ) );
            App::SyncRoutesCache();
        } catch ( Exception $e ) {
            Util::LogMessage( $e->getMessage(), __FILE__, __FUNCTION__ );
        }
    }

    public static function FetchAllPageSlugs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'posts';
        $slug_list = [];
        $results = $wpdb->get_results( "SELECT DISTINCT post_name FROM $table_name WHERE post_type = 'post' or post_type = 'page'" );

        foreach ( $results as $result )
        {
            array_push( $slug_list, $result->post_name );
        }

        return $slug_list;
    }

    public static function FetchMappings( $index_type = 'url' )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . App::MappingTableName();
        $mappings = array();

        if ( $index_type === 'url' )
        {
            $results = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
            foreach ( $results as $result )
            {
                $page_url = rtrim( $result['wp_page_url'], "/" );
                $mappings[$page_url] = $result;
            }

            Util::LogArray($mappings, 'Fetched Mappings (url): ');
        }
        else if ( $index_type == 'numeric' ) {
            $results = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
            $i = 0;

            foreach ( $results as $result )
            {
                $mappings[$i] = $result;
                $i = $i + 1;
            }

            Util::LogArray( $mappings, 'Fetched Mappings (num): ' );
        }
        else
        {
            Util::LogMessage("No Mappings to Fetch", __FILE__, __FUNCTION__);
        }

        return $mappings;
    }

    public static function FindWpPostNameById( $wp_page_id)
    {
        global $wpdb;
        $table_name = 'wp_posts';
        $result = $wpdb->get_row("SELECT post_name from $table_name where ID = $wp_page_id");
        Util::LogArray($result, 'Find wp post name');

        if ($result !== null)
        {
            return $result->post_name;
        }

        return false;
    }

    public static function UpdateMappingSlugByWpPageId( $wp_page_id, $new_slug )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . App::MappingTableName();
        $wp_host_url = parse_url(home_url(), PHP_URL_HOST);
        $wp_page_url = $wp_host_url . "/" . $new_slug;
        Util::LogMessage( "Update Mapping Slug:: wp_page_id = $wp_page_id, wp_page_slug = $new_slug, wp_page_url = $wp_page_url", '', __FUNCTION__ );
        $new_values =  array('wp_page_slug' => $new_slug, 'wp_page_url' => $wp_page_url);

        $result = $wpdb->update($table_name,$new_values, array('wp_page_id' => $wp_page_id));

        if($result === false)
        {
            Util::LogMessage("Error when updating mapping slug/url", __FILE__, __FUNCTION__);
        }
        else
        {
            Util::LogMessage("Row updated", __FILE__, __FUNCTION__);
        }
    }

    public static function PageMappingExists( $wp_page_id )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . App::MappingTableName();
        $result = $wpdb->get_results( "SELECT * FROM $table_name WHERE wp_page_id = '$wp_page_id'", ARRAY_A );

        if($result !== null)
        {
            Util::LogMessage( "Mapping Exists", __FILE__, __FUNCTION__);
            return true;
        }
        else
        {
            Util::LogMessage( "Mapping Does Not Exists for wp_page_id = " . $wp_page_id, __FILE__, __FUNCTION__);
            return false;
        }
    }

    public static function LogMessage( $message, $file, $function )
    {
        if ( App::IsLoggingOn() || App::IsDevEnv() )
        {
            $file_name = basename( $file );
            error_log( "$file_name:: $function: $message" );
        }
    }

    public static function PrintRoutesCache( $prefix )
    {
        $routes_cache = App::GetRoutesCache();
        Util::LogMessage( "Routes Cache Size: " . count( $routes_cache ), __FUNCTION__, $prefix );

        foreach ( $routes_cache as $wp_slug => $page_mapping )
        {
            error_log( "$prefix:: KEY= " . $wp_slug . " Value= " . implode( ", ", $page_mapping ) );
        }
    }

    public static function LogArray( $array, $prefix )
    {
        if ( App::IsLoggingOn() || App::IsDevEnv() )
        {
            if ( is_array($array) )
            {
                foreach ( $array as $key => $value )
                {
                    if ( !is_array( $value ) && !is_object($value))
                    {
                        error_log( "$prefix:: KEY= " . $key . " Value= " . $value );
                    }
                    else if(is_object($value))
                    {
                        $object = print_r($value, true);
                        error_log( "$prefix:: KEY= " . $key . " Value= " . $object );
                    }
                    else
                    {
                        error_log( "$prefix:: KEY= " . $key . " Array = [ " . implode( "," . PHP_EOL . "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t    ", array_map(
                                                function ( $v, $k ) { return sprintf( "%s='%s'", $k, $v ); },
                                                $value,
                                                array_keys( $value ))) . " ]" );
                    }
                }
            }
            elseif( is_object($array))
            {
                $array = var_export($array, true);
                error_log("$prefix:: $array");
            }
            else
            {
                error_log( "$prefix:: $array" );
            }
        }
    }

    public static function GetSVGIcon( $base64 = true )
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="25.42" viewBox="0 0 24 25.42"><title>ll-wordpress-icon-default</title><g id="Layer_2" data-name="Layer 2"><g id="Layer_1-2" data-name="Layer 1"><path d="M22.28,10.8c0,.14,0,.28,0,.42a4.42,4.42,0,0,1-.17.77,5,5,0,0,1-.46,1,.74.74,0,0,0,0-.44,1.44,1.44,0,0,1,0-.55,2.12,2.12,0,0,1,.43-.86A1.46,1.46,0,0,0,22.28,10.8Zm-10.09-4A2.58,2.58,0,0,0,11,5.92c-.72-.26-2.64-.39-3,.34s-.13,3.08,2.87,4a5.33,5.33,0,0,0,1-1.65A7.85,7.85,0,0,0,12.2,6.84Zm1.43.5a3.41,3.41,0,0,1-1-.42,8.16,8.16,0,0,1-.37,1.82,5.74,5.74,0,0,1-1.08,1.8.42.42,0,0,1-.31.14,5.54,5.54,0,0,1-3-.8A12,12,0,0,0,5.4,14.16l0,.12c.84,1.57,2.06,2.11,2.06,4.49a3.46,3.46,0,0,1-2.28,3c-.54.22-1.08.28-1.08.78,0,1,1.66,1.15,2.06,1.15,2.71,0,4.37-1.7,5.4-3.75a12.07,12.07,0,0,0,1.23-5.18,7.78,7.78,0,0,0-.81-3.31A8.36,8.36,0,0,0,13.62,7.34Zm-6-1.25c.3-.66,1.22-.8,1.94-.8h.08A13.14,13.14,0,0,1,11,4.56a3,3,0,0,1,.45-1.9C7.7,3.6,4.52,6.5,4.61,11A13.13,13.13,0,0,1,7.45,7,2.13,2.13,0,0,1,7.59,6.09ZM24,10.6a6,6,0,0,1-.29,1.91A6.75,6.75,0,0,1,23,14a3.94,3.94,0,0,1-.46.59h0a10.05,10.05,0,0,1-.3,1.43,5.7,5.7,0,0,1-.34.87,7.5,7.5,0,0,1-.84,1.53,11,11,0,0,1-9,4.09h-.12a7.12,7.12,0,0,1-5.82,2.95c-2.26,0-3.77-1.15-3.77-2.86A2.08,2.08,0,0,1,3,21,6.76,6.76,0,0,1,.65,19.85a1,1,0,0,1,.41-1.72A1,1,0,0,1,1,17.74a7.18,7.18,0,0,1,.62-3.49,4.82,4.82,0,0,1-.86-.15,1,1,0,0,1,0-1.93,4,4,0,0,0,2.15-1.32A9.45,9.45,0,0,1,5.34,4.31,11.66,11.66,0,0,1,12.15.8,8,8,0,0,1,16.92.08a2.48,2.48,0,0,1,.9.3l.22,0a4.2,4.2,0,0,1,4.5,4.22A3.69,3.69,0,0,1,21.88,7l.16.21a1.71,1.71,0,0,1,.25.44l.15.07a2.72,2.72,0,0,1,1.48,2A5.87,5.87,0,0,1,24,10.6Zm-1,0a5.13,5.13,0,0,0-.06-.71A1.7,1.7,0,0,0,22,8.63a3.46,3.46,0,0,0-.61-.22.71.71,0,0,0-.14-.57l-.77-1a2.16,2.16,0,0,0,1.06-2.24,3.22,3.22,0,0,0-3.2-3.27h-.24a.91.91,0,0,0-.43.13,1.83,1.83,0,0,0-.88-.41A10.26,10.26,0,0,0,15.6,1a6,6,0,0,0-3.11.76A10.78,10.78,0,0,0,6.08,5,8.5,8.5,0,0,0,3.9,11.18a4.91,4.91,0,0,1-2.9,2,5.37,5.37,0,0,0,2.43,0A6.33,6.33,0,0,0,2,17.67s.54-1.93,2.15-1.95a1.27,1.27,0,0,1,1.16,1.92c-.63,1.31-2.37,1.61-3.95,1.46a8.42,8.42,0,0,0,4.5,1.46,3.84,3.84,0,0,1-.94.57l-.29.11c-.46.16-1.23.43-1.23,1.33,0,1.37,1.49,1.86,2.77,1.86a6.49,6.49,0,0,0,5.79-3.72,10.6,10.6,0,0,0,2.58-.82,4.26,4.26,0,0,1-2.37,1.62,10.12,10.12,0,0,0,8.16-3.68A6.79,6.79,0,0,0,21,16.42a4.53,4.53,0,0,0,.27-.69v0a9.06,9.06,0,0,0,.28-1.34,1.41,1.41,0,0,1,0-.21,1.32,1.32,0,0,1,.21-.27,3,3,0,0,0,.34-.44,5.71,5.71,0,0,0,.58-1.25,5.16,5.16,0,0,0,.2-.9A5,5,0,0,0,23,10.61ZM4.36,17.21a.4.4,0,0,0,0-.38.31.31,0,0,0-.26-.11h0c-.82,0-1.19,1.21-1.2,1.22a1,1,0,0,1,0,.13A1.89,1.89,0,0,0,4.36,17.21ZM19.13,9.87a.9.9,0,0,0,.06.27.88.88,0,0,0,.21.32c.3.3,1.06.42,1.32.59a1.1,1.1,0,0,1,.54.68.85.85,0,0,1-.15.69,7.05,7.05,0,0,1-3.55,1.75,1.46,1.46,0,0,1-1.13-.76c-.05-.09-.08-.19-.18-.25a.26.26,0,0,0-.39.21.5.5,0,0,0,.11.27,2.36,2.36,0,0,0,.44.54,1.75,1.75,0,0,0,1.3.44,6.36,6.36,0,0,0,2.81-1.19c.23-.14.79-.56,1-.39s-.16.36-.21.44a1.54,1.54,0,0,0-.3.42,1.57,1.57,0,0,0-.08.39,5.77,5.77,0,0,1-.54,1.95,3.68,3.68,0,0,1-1.19,1.3,3,3,0,0,1-1.08.44,1,1,0,0,0,.49-.51.36.36,0,0,0,.06-.16,2,2,0,0,1-.4.3l-.06,0L18,17.7l-.09,0a2.81,2.81,0,0,1-.42.17l-.09,0A1.87,1.87,0,0,1,17,18a3.57,3.57,0,0,1-.56,0l-.15,0a2.61,2.61,0,0,1-.39-.11h0a1.77,1.77,0,0,1-.78-.56.83.83,0,0,0,.73-.55.33.33,0,0,0,0-.18,1.45,1.45,0,0,1-1.36-.18,1.67,1.67,0,0,1-.53-1.13c0,.09.3.21.37.24a1.23,1.23,0,0,0,.48.11,1,1,0,0,0,1-1,1.6,1.6,0,0,0-1.06-1.33A4.18,4.18,0,0,1,12.9,12c-.05-.14-.1-.27-.16-.41a9.13,9.13,0,0,0,1.57-4.14l.21,0a5.17,5.17,0,0,0,1.14-.14A4.41,4.41,0,0,0,17.91,6.1a.09.09,0,0,1,.13,0,2.75,2.75,0,0,0,1.59.82l1.07,1.36s-.91-.3-1.06.44l.92.22h0a6.6,6.6,0,0,1,1.11.31,1.12,1.12,0,0,1,.43.39c0,.09,0,.33-.69.34a8.41,8.41,0,0,1-.91,0,9.91,9.91,0,0,1-1.31-.23C19.11,9.72,19.12,9.81,19.13,9.87ZM17.52,9a1.15,1.15,0,0,0-.69-.83c-.49-.14-1.65-.07-1.6,0a1,1,0,0,0,1.11.68A2.22,2.22,0,0,1,17.52,9ZM21,5.09c-.11.55-.4,1-.84,1a2.21,2.21,0,0,1-1.83-.8.43.43,0,0,0-.63,0,3.83,3.83,0,0,1-2.11,1.16C13.1,7,12,5.71,11.71,4.79a2.18,2.18,0,0,1,.79-2.42,6.29,6.29,0,0,1,4.26-.74c.43,0,.87.53,1,.54s.12-.26.42-.28a2.87,2.87,0,0,1,1.3.21A1.56,1.56,0,0,0,19,2a1,1,0,0,0-.63.21c-.49.39-.34,1.52.11,2.33a1.84,1.84,0,0,0,1.56,1,1.13,1.13,0,0,0,.74-.25A.8.8,0,0,0,21,5.09ZM17.64,3.8c0-.94-.23-1.94-2.14-1.94l-.62,0a2.86,2.86,0,0,0-2.13.87,1.68,1.68,0,0,0-.28,1.36A2.2,2.2,0,0,0,14.86,5.9H15C16.3,5.85,17.68,5,17.64,3.8Z" style="fill:#9ea3a8"/></g></g></svg>';

        if ( $base64 ) { return 'data:image/svg+xml;base64,' . base64_encode( $svg ); }

        return $svg;
    }

    public static function ParseWPUrlKey( $url )
    {
        $host = parse_url( $url, PHP_URL_HOST );
        $path = rtrim( parse_url( $url, PHP_URL_PATH ), "/" );

        return $host . $path;
    }

    public static function ParseWpHomeUrl()
    {
        $domainParts = explode(".", App::GetWPHostDomain());
        $domain = "";

        for($i = 0; $i < sizeof($domainParts); $i++)
        {
            if ($domainParts[$i] != 'www')
            {
                $domain = $domain . $domainParts[$i] . ".";
            }
        }

        return rtrim($domain, ".");
    }

}
