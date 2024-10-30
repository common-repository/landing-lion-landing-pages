<?php
namespace LandingLionWP\Models;

use LandingLionWP\Services\Util;

class PageMapping
{
    public $id;
    public $status; // 'ok' or 'trash'
    public $ll_page_id;
    public $ll_page_status; // 1 (dead) or 0 (active)
    public $ll_static_url;
    public $wp_page_id;
    public $wp_page_status; // trash or publish
    public $wp_page_title; // title of wp_page that user will see
    public $wp_page_url; // GetWPHostDomain + page path (domain.com/page-path)
    public $wp_page_slug; // page path (page-path)

    public function __construct( $ll_page_id,
                                $ll_static_url,
                                $wp_page_id,
                                $wp_page_status,
                                $wp_page_title,
                                $wp_page_slug,
                                $wp_page_url,
                                $status = 'ok',
                                $ll_page_status = 0 )
    {
        $this->ll_page_id = $ll_page_id;
        $this->ll_static_url = $ll_static_url;
        $this->wp_page_id = $wp_page_id;
        $this->wp_page_status = $wp_page_status;
        $this->wp_page_title = $wp_page_title;
        $this->wp_page_slug = $wp_page_slug;
        $this->wp_page_url = $wp_page_url;
        $this->ll_page_status = $ll_page_status;
        $this->status = $status;
    }

    public static function MapFromDB( $db_entry )
    {
        return new PageMapping(
            $ll_page_id = $db_entry['ll_page_id'],
            $ll_static_url = $db_entry['ll_static_url'],
            $wp_page_id = $db_entry['wp_page_id'],
            $wp_page_status = $db_entry['wp_page_status'],
            $wp_page_title = $db_entry['wp_page_title'],
            $wp_page_slug = $db_entry['wp_page_slug'],
            $wp_page_url = $db_entry['wp_page_url'],
            $status = $db_entry['status'],
            $ll_page_status = $db_entry['ll_page_status'] );
    }

    public function MapToDB()
    {
        return array(
            'ID' => $this->id,
            'status' => $this->status,
            'll_page_id' => $this->ll_page_id,
            'll_page_status' => $this->ll_page_status,
            'll_static_url' => $this->ll_static_url,
            'wp_page_id' => $this->wp_page_id,
            'wp_page_status' => $this->wp_page_status,
            'wp_page_title' => $this->wp_page_title,
            'wp_page_slug' => $this->wp_page_slug,
            'wp_page_url' => $this->wp_page_url
        );
    }

    public function ToArray()
    {
        return array(
            'ID' => $this->id,
            'status' => $this->status,
            'll_page_id' => $this->ll_page_id,
            'll_page_status' => $this->ll_page_status,
            'll_static_url' => $this->ll_static_url,
            'wp_page_id' => $this->wp_page_id,
            'wp_page_status' => $this->wp_page_status,
            'wp_page_title' => $this->wp_page_title,
            'wp_page_slug' => $this->wp_page_slug,
            'wp_page_url' => $this->wp_page_url
        );
    }

    public static function MapFromJSON( $mapping )
    {
        return new PageMapping(
            $ll_page_id = Util::ArrayFetch($mapping, 'LLPageId'),
            $ll_static_url = Util::ArrayFetch($mapping, 'LLStaticUrl'),
            $wp_page_id = Util::ArrayFetch($mapping, 'WPPageId'),
            $wp_page_status = Util::ArrayFetch($mapping, 'WPPageStatus'),
            $wp_page_title = Util::ArrayFetch($mapping, 'WPPageTitle'),
            $wp_page_slug = Util::ArrayFetch($mapping, 'WPPageSlug'),
            $wp_page_url = Util::ArrayFetch($mapping, 'WPPageUrl'),
            $status = Util::ArrayFetch($mapping, 'status', 'ok'),
            $ll_page_status = Util::ArrayFetch($mapping, 'LLPageStatus', 0) );
    }

    public function MapToJSON()
    {
        $ll_page_status_name = $this->ll_page_status ? 'dead' : 'active';

        return array(
            'ID' => $this->id,
            'status' => $this->status,
            'LLPageStatus' => $this->ll_page_status,
            'LLPageId' => $this->ll_page_id,
            'LLStaticUrl' => $this->ll_static_url,
            'WPPageId' => $this->wp_page_id,
            'WPPageStatus' => $this->wp_page_status,
            'WPPageTitle' => $this->wp_page_title,
            'WPPageSlug' => $this->wp_page_slug,
            'WPPageUrl' => $this->wp_page_url
        );
    }
}
?>
