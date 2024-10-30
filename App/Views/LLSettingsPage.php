<?php
namespace LandingLionWP\Views;

use LandingLionWP\Services\Util;
use LandingLionWP\Config\App;
use LandingLionWP\Assets\css;
use LandingLionWP\Assets\js;
use LandingLionWP\Repository\PageMappingRepo;
use LandingLionWP\Models\PageMapping;

function ll_plugin_admin_settings_template()
{
    if (App::IsDevEnv())
    {
        ?>
        <div class="wrap">
            <iframe id="ll-plugin-iframe" scrolling="no" src="https://wp.dev.landinglion.com"></iframe>
        </div>
        <?php
    }
    else
    {
        ?>
        <div class="wrap">
            <iframe id="ll-plugin-iframe" scrolling="no" src="https://wp.landinglion.com"></iframe>
        </div>
        <?php
    }
}

function ll_plugin_create_admin_menu()
{
    $svg_icon = Util::GetSVGIcon();
    add_menu_page( 'Landing Lion Menu', 'Landing Lion', 'administrator', __FILE__, 'LandingLionWP\Views\ll_plugin_admin_settings_template', $svg_icon, '99.31337' );
}
add_action( 'admin_menu', 'LandingLionWP\Views\ll_plugin_create_admin_menu' );

function intercept_create_page_mapping()
{
    if ( !isset( $_POST['page'] ) )
    {
        wp_die( 'No Page in POST', array( 'response' => 500 ) );
        return;
    }
    else
    {
        $mapping_repo = new PageMappingRepo();
        $mapping = PageMapping::MapFromJSON($_POST['page']);

        Util::LogArray($_POST['page'], "Requsted Page: ");
        Util::LogArray($mapping->MapToJSON(), "PageMapping obj: ");

        $new_page = $mapping_repo->Create($mapping);
    }

    $mappings = array( 'page' => $new_page );
    wp_send_json( $mappings );
}
add_action( 'wp_ajax_ll_create_page_mapping', 'LandingLionWP\Views\intercept_create_page_mapping' );

function intercept_update_page_mapping()
{
    if ( !isset( $_POST['page'] ) )
    {
        wp_die( 'No Page in POST', array( 'response' => 500 ) );
        return;
    }
    else
    {
        $mapping_repo = new PageMappingRepo();
        $mapping = PageMapping::MapFromJSON($_POST['page']);
        $new_page = $mapping_repo->Update( $mapping );
    }

    $mappings = array( 'page' => $new_page );
    wp_send_json( $mappings );
}
add_action( 'wp_ajax_ll_update_page_mapping', 'LandingLionWP\Views\intercept_update_page_mapping' );

function intercept_delete_page_mapping()
{
    if ( !isset( $_POST['page'] ) )
    {
        wp_die( 'No Page in POST', array( 'response' => 500 ) );
        return;
    }
    else
    {
        $page = $_POST['page'];
        Util::deletePageMapping( $page );
    }

    $mapping_repo = new PageMappingRepo();
    $mappings = array( 'success' => $mapping_repo->Fetch( 'numeric' ) );
    wp_send_json( $mappings );
}
add_action( 'wp_ajax_ll_delete_page_mapping', 'LandingLionWP\Views\intercept_delete_page_mapping' );

function intercept_fetch_page_mappings()
{
    $mapping_repo = new PageMappingRepo();
    $mappings = array( 'pageMappings' => $mapping_repo->Fetch( 'numeric' ) );
    wp_send_json( $mappings );
}
add_action( 'wp_ajax_get_ll_page_mappings', 'LandingLionWP\Views\intercept_fetch_page_mappings' );

function intercept_fetch_wordpress_page_slugs()
{
    $slugs = array( 'pageSlugs' => Util::FetchAllPageSlugs() );
    wp_send_json( $slugs );
}
add_action( 'wp_ajax_get_wordpress_page_slugs', 'LandingLionWP\Views\intercept_fetch_wordpress_page_slugs' );


function ll_plugin_icon_styles()
{
    $style_path = plugins_url() . '/' . App::GetLLAppName() . '/App/Assets/css/ll-icon.admin-view.css';
    wp_enqueue_style( 'll_icon_admin_view', $style_path, $ver = null );
}
add_action( 'admin_enqueue_scripts', 'LandingLionWP\Views\ll_plugin_icon_styles' );

function ll_iframe_setup_scripts( $hook )
{
    $plugin_hook = "toplevel_page_". App::GetLLAppName() . "/App/Views/LLSettingsPage";
    Util::LogMessage("Hook param = $hook", __FILE__, __FUNCTION__);

    if ( $plugin_hook != $hook )
    {
        return;
    }

    $admin_ll_styles_path = plugins_url() . '/' . App::GetLLAppName() . '/App/Assets/css/ll-admin-view.css';
    $admin_ll_scripts_path =plugins_url() . '/' . App::GetLLAppName() . '/App/Assets/js/ll-admin-view.js';

    wp_enqueue_style( 'll_admin_settings_style', $admin_ll_styles_path, $ver = null );
    wp_enqueue_script( 'll_admin_settings_script', $admin_ll_scripts_path, 'jquery', $ver = null );
    ll_plugin_admin_localize_script();
}
add_action( 'admin_enqueue_scripts', 'LandingLionWP\Views\ll_iframe_setup_scripts' );

function ll_plugin_admin_localize_script()
{
    $plugin_config = array(
        'allowedOrigin' => App::LL_ALLOWED_MESSAGE_ORIGIN,
        'allowedDevOrigin' => App::LL_ALLOWED_MESSAGE_ORIGIN_DEV,
        'ajaxurl' => admin_url('admin-ajax.php', 'relative')
    );

    wp_localize_script( 'll_admin_settings_script', 'LLPluginConfig', $plugin_config );
}

?>
