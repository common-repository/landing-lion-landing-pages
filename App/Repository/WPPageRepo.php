<?php
namespace LandingLionWP\Repository;

use LandingLionWP\Services\Util;

class WPPageRepo
{
    public static function Create( $name, $title, $type = 'page', $content = '', $template = '', $status = 'publish' )
    {
        $post = get_page_by_title( $name, 'OBJECT', $type );

        if ( !isset( $post ) )
        {
            $post_id = wp_insert_post( array(
                'post_name' => wp_strip_all_tags( $name ),
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => $status,
                'post_type' => $type,
                'page_template' => $template));

            return $post_id;
        }
        else
        {
            Util::LogMessage( "Page With Name (" . $name . ") Already Exists", __FILE__, __FUNCTION__ );
            return false;
        }
    }

    public static function FindByPageUrl( $page_url )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . App::MappingTableName();
        Util::LogMessage( "Find Mapping:: page_url = $page_url", __FILE__, __FUNCTION__ );

        $result = $wpdb->get_results( "SELECT * FROM $table_name WHERE wp_page_url = '$page_url'", ARRAY_A );

        if (array_key_exists( 0, $result ))
        {
            Util::LogMessage( $result[0], "Find Mapping");
            return $result[0];
        }
        else { return; }
    }
}
 ?>
