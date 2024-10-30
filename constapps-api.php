<?php
/**
 * Plugin Name: ConstApps Mobile App Builder
 * Plugin URI: https://github.com/ConstApps/constapps-wordpress-api
 * Description: The ConstApps Settings and APIs for supporting the ConstApps built mobile application to be connected to Wordpress
 * Version: 1.0.0
 * Author: ConstApps
 * Author URI: https://constapps.com
 *
 * Text Domain: ConstApps-Mobile-App-Builder
 */

defined('ABSPATH') or wp_die( 'No script kiddies please!' );

include plugin_dir_path(__FILE__)."includes/RenameGenerate.php";

class ConstappsCheckOut
{
    public $version = '1.0.0';

    public function __construct()
    {
        define('CONSTAPPS_CHECKOUT_VERSION', $this->version);
        define('CONSTAPPS_PLUGIN_FILE', __FILE__);
        include_once (ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('woocommerce/woocommerce.php') == false) {
            return 0;
        }

        $path = get_template_directory()."/templates";
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $templatePath = plugin_dir_path(__FILE__)."includes/ConstappsTemplate.php";
        if (!copy($templatePath,$path."/ConstappsTemplate.php")) {
            return 0;
        }

        $order = filter_has_var(INPUT_GET, 'order') && strlen(filter_input(INPUT_GET, 'order')) > 0 ? true : false;
        if ($order) {
            add_filter('woocommerce_is_checkout', '__return_true');
        }

        include_once plugin_dir_path(__FILE__)."controllers/Orders.php";

        add_action('wp_print_scripts', array($this, 'handle_received_order_page'));

        //add meta box shipping location in order detail
        add_action( 'add_meta_boxes', 'mv_add_meta_boxes' );
        if ( ! function_exists( 'mv_add_meta_boxes' ) )
        {
            function mv_add_meta_boxes()
            {
                add_meta_box( 'mv_other_fields', __('Shipping Location','woocommerce'), 'mv_add_other_fields_for_packaging', 'shop_order', 'side', 'core' );
            }
        }
        // Adding Meta field in the meta container admin shop_order pages
        if ( ! function_exists( 'mv_add_other_fields_for_packaging' ) )
        {
            function mv_add_other_fields_for_packaging()
            {
                global $post;
                echo '<div class="mapouter"><div class="gmap_canvas"><iframe width="600" height="500" id="gmap_canvas" src="'.$post->post_excerpt.'" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe><a href="https://www.embedgooglemap.net/blog/best-wordpress-themes/">embedgooglemap.net</a></div><style>.mapouter{position:relative;text-align:right;height:500px;width:600px;}.gmap_canvas {overflow:hidden;background:none!important;height:500px;width:600px;}</style></div>';
            }
        }
    }

    public function handle_received_order_page()
    {
        if (is_order_received_page()) {
            wp_enqueue_style('constapps-order-custom-style');
        }

    }
}

$constappsCheckOut = new ConstappsCheckOut();

// use JO\Module\Templater\Templater;
include plugin_dir_path(__FILE__)."includes/Templater/Templater.php";

add_action('plugins_loaded', 'load_constapps_templater');
function load_constapps_templater()
{

    // add our new custom templates
    $my_templater = new Templater(
        array(
            // YOUR_PLUGIN_DIR or plugin_dir_path(__FILE__)
            'plugin_directory' => plugin_dir_path(__FILE__),
            // should end with _ > prefix_
            'plugin_prefix' => 'plugin_prefix_',
            // templates directory inside your plugin
            'plugin_template_directory' => 'templates',
        )
    );
    $my_templater->add(
        array(
            'page' => array(
                'ConstappsTemplate.php' => 'Page Custom Template',
            ),
        )
    )->register();
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Define for the API User wrapper which is based on json api user plugin
///////////////////////////////////////////////////////////////////////////////////////////////////

if (!is_plugin_active('json-api/json-api.php') && !is_plugin_active('json-api-master/json-api.php')) {
    // add_action('admin_notices', 'pim_draw_notice_json_api');
    return;
}

add_filter('json_api_controllers', 'registerJsonApiController');
add_filter('json_api_constapps_user_controller_path', 'setConstappsUserControllerPath');
add_action('init', 'json_apiCheckAuthCookie', 100);

add_action( 'rest_api_init', 'my_register_route' );
function my_register_route() {
    register_rest_route( 'order', 'verify', array(
                    'methods' => 'GET',
                    'callback' => 'check_payment'
                )
            );
}
function check_payment() {
    return true;
}



// Add menu Setting
add_action('admin_menu', 'constapps_plugin_setup_menu');

function constapps_plugin_setup_menu(){
        add_menu_page( 'Constapps Api', 'Constapps Api', 'manage_options', 'constapps-plugin', 'constapps_init' );
}

function constapps_init(){
    load_template( dirname( __FILE__ ) . '/includes/ConstappsAdmin.php' );
}

function registerJsonApiController($aControllers)
{
    $aControllers[] = 'Constapps_User';
    return $aControllers;
}

function setConstappsUserControllerPath()
{
    return plugin_dir_path(__FILE__) . '/controllers/User.php';
}

function json_apiCheckAuthCookie()
{
    global $json_api;

    if ($json_api->query->cookie) {
        $user_id = wp_validate_auth_cookie($json_api->query->cookie, 'logged_in');
        if ($user_id) {
            $user = get_userdata($user_id);
            wp_set_current_user($user->ID, $user->user_login);
        }
    }
    add_checkout_page();
}

function add_checkout_page() {
    $page = get_page_by_title('Constapps Checkout');
    if($page == null || strpos($page->post_name,"constapps-checkout") === false || $page->post_status != "publish") {
        $my_post = array(
            'post_type' => 'page',
            'post_name' => 'constapps-checkout',
            'post_title'    => 'Constapps Checkout',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page'
        );

        // Insert the post into the database
        $page_id = wp_insert_post( $my_post );
        update_post_meta( $page_id, '_wp_page_template', 'templates/ConstappsTemplate.php' );
    }

}

/**
 * Register the constapps caching endpoints so they will be cached.
 */
function wprc_add_constapps_endpoints( $allowed_endpoints ) {
    if ( ! isset( $allowed_endpoints[ 'constapps/v1' ] ) || ! in_array( 'cache', $allowed_endpoints[ 'constapps/v1' ] ) ) {
        $allowed_endpoints[ 'constapps/v1' ][] = 'cache';
    }
    return $allowed_endpoints;
}
add_filter( 'wp_rest_cache/allowed_endpoints', 'wprc_add_constapps_endpoints', 10, 1);
