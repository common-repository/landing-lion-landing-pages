<?php
namespace LandingLionWP\Models;

class PageResponse
{
    public $content;
    public $client_domain;

    function __construct( $client_domain )
    {
        $this->client_domain = $client_domain;
        $this->content = '';
    }

    function AddContent( $string )
    {
        $this->content = $this->content . $string;
    }

    function FlushContent()
    {
        return $this->content;
    }

    function GetContentLength()
    {
        return strlen( $this->content );
    }
}
?>
