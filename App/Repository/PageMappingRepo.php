<?php

namespace LandingLionWP\Repository;

use LandingLionWP\Config\App;
use LandingLionWP\Models\PageMapping;
use LandingLionWP\Services\Util;
use LandingLionWP\Repository\WPPageRepo;

class PageMappingRepo
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->base_prefix . App::MappingTableName();
        Util::LogMessage("Page Mapping Table Name: $this->table_name", __FILE__, __FUNCTION__);
    }

    public function Create ( PageMapping $page )
    {
        global $wpdb;
        $query = "SELECT * FROM $this->table_name WHERE ll_static_url='" . $page->ll_static_url . "' and wp_page_id='" . $page->wp_page_id . "'";
        $result = $wpdb->get_results( $query );

        if ( $result === false ) {
            Util::LogMessage( "Page mapping with matching static url and wp_page_id already exists", __FILE__, __FUNCTION__ );
            return false;
        }

        $page->wp_page_id = WPPageRepo::Create($page->wp_page_slug, $page->wp_page_title);
        $page->wp_page_status = 'publish';
        $new_mapping = $page->MapToDB();
        $entry = $wpdb->insert( $this->table_name, $new_mapping );

        if ( $entry and $entry > 0 )
        {
            $new_mapping_id = $wpdb->insert_id;
            $mapping = $wpdb->get_row( "SELECT * FROM $this->table_name WHERE ID = $new_mapping_id" );
            return $mapping;
        }

        return false;
    }

    public function Fetch( $index_type = 'url' )
    {
        global $wpdb;
        $mappings = array();

        if ($index_type === 'url')
        {
            $results = $wpdb->get_results( "SELECT * FROM $this->table_name ", ARRAY_A );
            $mappings = count( $results ) > 0 ? array() : false;

            foreach ( $results as $result )
            {
                $parsed_url = parse_url($result['wp_page_url']);
                $host = rtrim(parse_url($result['wp_page_url'], PHP_URL_HOST), ":");
                $map_key = rtrim($host . $parsed_url['path'], "/");
                $mapping = PageMapping::MapFromDB( $result );
                $mappings[$map_key] = $mapping->ToArray();
            }

            Util::LogArray($mappings, 'Fetched Mappings (url): ');
        }
        else if ($index_type === 'numeric')
        {
            $results = $wpdb->get_results( "SELECT * FROM $this->table_name ", ARRAY_A );
            $mappings = count( $results ) > 0 ? array() : false;
            $i = 0;

            foreach ( $results as $result )
            {
                $mapping = PageMapping::MapFromDB( $result );
                $mappings[$i] = $mapping->ToArray();
                $i = $i + 1;
            }

            Util::LogArray( $mappings, 'Fetched Mappings (num): ' );
        }

        return $mappings;
    }

    public function FindById( $id )
    {
        global $wpdb;
        $result = $wpdb->get_row( "SELECT * FROM $this->table_name WHERE ID = $id", ARRAY_A );
        $mapping = $result ? PageMapping::MapFromDB( $result ) : false;

        return $mapping;
    }

    public function FindByWPUrl( $url )
    {
        global $wpdb;
        $result = $wpdb->get_row( "SELECT * FROM $this->table_name WHERE wp_page_url = '" . $url . "'", ARRAY_A );
        $mapping = $result ? PageMapping::MapFromDB( $result ) : false;

        return $mapping;
    }

    public function FindByWPPageId( $id )
    {
        global $wpdb;
        $result = $wpdb->get_row( "SELECT * FROM $this->table_name WHERE wp_page_id = '" . $id . "'", ARRAY_A );
        $mapping = $result ? PageMapping::MapFromDB( $result ) : false;

        return $mapping;
    }

    public function Update( PageMapping $mapping )
    {
        global $wpdb;
        $result = $wpdb->update( $this->table_name, $mapping->MapToDB(), array( 'ID' => $mapping->id ) );
        if ( $result === false ) { return false; }

        return $this->Find( $mapping->id );
    }

    public function SetDeadStatus( $id )
    {
        global $wpdb;
        $result = $wpdb->update( $this->table_name, array( 'll_page_status' => 1 ), array( 'ID' => $id ) );
        if ( $result === false ) { return false; }

        return $this->Find( $id );
    }

    public function Trash( $id )
    {
        global $wpdb;
        $result = $wpdb->update( $this->table_name, array( 'status' => 'trash' ), array( 'ID' => $id ) );
        if ( $result === false ) { return false; }

        return $this->Find( $id );
    }

    public function TrashByWPPageId( $id )
    {
        global $wpdb;
        $result = $wpdb->update( $this->table_name, array( 'status' => 'trash' ), array( 'wp_page_id' => $id ) );
        if ( $result === false ) { return false; }

        return $this->FindByWPPageId( $id );
    }

    public function Restore( $id )
    {
        global $wpdb;
        $result = $wpdb->update( $this->table_name, array( 'status' => 'ok' ), array( 'ID' => $id ) );
        if ( $result === false ) { return false; }

        return $this->Find( $id );
    }

    public function Delete( $id )
    {
        global $wpdb;
        return $wpdb->delete( $this->table_name, array( 'ID' => $id ) );
    }
}
?>
