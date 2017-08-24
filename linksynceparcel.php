<?php
/**
 * Plugin Name: linksync eParcel
 * Plugin URI: http://www.linksync.com/integrate/woocommerce-eparcel-integration
 * Description: Manage your eParcel orders without leaving your WordPress WooCommerce store with linksync eParcel for WooCommerce.
 * Version: 1.2.6
 * Author: linksync
 * Author URI: http://www.linksync.com
 * License: GPLv2
 */

/*
Copyright 2014  linksync  (email : info@linksync.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Exit if accessed directly
 **/
if ( !defined('ABSPATH') )
{
	exit;
}

/**
 * Check if plugin is loaded
 **/
if (!class_exists('linksynceparcel'))
{
	return;
}

if (!function_exists('curl_init'))
	exit;


define( 'linksynceparcel_URL', plugin_dir_url( __FILE__ ) );
define( 'linksynceparcel_DIR', plugin_dir_path( __FILE__ ) );

$url = get_site_url();
$parseurl = parse_url($url);
$url = str_replace('www.', '', str_replace($parseurl['scheme'].'://', '', $url));
define( 'linksynceparcel_SITE_URL', $url );

define( 'linksynceparcel_UPLOAD_URL', WP_CONTENT_DIR );
define( 'linksynceparcel_LOG_DIR', WP_CONTENT_DIR .'/linksync_uploads/log/' );
define( 'linksynceparcel_LOG_URL', WP_CONTENT_URL .'/linksync_uploads/log/' );
define( 'linksynceparcel_UPLOAD_DIR', WP_CONTENT_DIR .'/linksync_uploads/label/' );
define( 'linksynceparcel_UPLOAD_BASEURL', WP_CONTENT_URL.'/linksync_uploads/label/' );

define( 'linksynceparcel_OLD_UPLOAD_DIR', WP_CONTENT_DIR .'/linksync/label/' );
define( 'linksynceparcel_OLD_UPLOAD_BASEURL', WP_CONTENT_URL.'/linksync/label/' );

ob_start();
/**
 * Check if WooCommerce is active
 **/
$exist_plugin = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ));
if(function_exists('is_multisite') && is_multisite()) {
	$exist_plugin = array_key_exists( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_site_option('active_sitewide_plugins') ));
	if(!$exist_plugin) {
		$exist_plugin = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ));
	}
}

if ( $exist_plugin )
{
	if(isset($_POST['wp_screen_options']))
	{
		$wp_screen_options = $_POST['wp_screen_options'];
		if(isset($wp_screen_options['option']) && ($wp_screen_options['option'] == 'consignment_per_page'))
		{
			$consignment_per_page = $wp_screen_options['value'];
			update_option( 'consignment_per_page', $consignment_per_page);
		}
	}
    add_action('plugins_loaded', 'linksynceparcel_init', 0);
	add_action( 'admin_enqueue_scripts', 'linksynceparcel_enqueue' );
}
//do_action('admin_enqueue_scripts');
function linksynceparcel_enqueue()
{
	if(isset($_GET['page']) && $_GET['page'] == 'linksynceparcel')
	{
		if(isset($_GET['reset']) && $_GET['reset'] == '1')
		{
			delete_option( 'consignment_per_page');
			wp_redirect(admin_url('admin.php?page=linksynceparcel'));
		}
		@wp_enqueue_style('admin-style',get_bloginfo('url').'/wp-content/plugins/woocommerce/assets/css/admin.css?ver=4.0');
		@wp_enqueue_script('jquery-ui-datepicker');
		@wp_enqueue_style('jquery-ui-style', getProtocol().'://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
	}
}


function getProtocol()
{
	$protocol = 'http';
	if( isset($_SERVER['HTTPS']) && (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') )
		$protocol = 'https';
	return $protocol;
}

include_once(linksynceparcel_DIR.'helpers/LinksynceparcelHelper.php');
include_once(linksynceparcel_DIR.'helpers/LinksynceparcelPluginUpdateChecker.php');
include_once(linksynceparcel_DIR.'helpers/LinksynceparcelScreenOption.php');
include_once(linksynceparcel_DIR.'includes/api/LinksyncApi.php');
include_once(linksynceparcel_DIR.'includes/api/LinksyncApiController.php');

// include_once(linksynceparcel_DIR.'helpers/LinksyncUserHelper.php');

function linksynceparcel_init()
{
	$linksynceparcel = new linksynceparcel(true);
	LinksynceparcelHelper::createUploadDirectory();
	LinksynceparcelHelper::upgradeTables();
	LinksynceparcelHelper::createNewTables();
	if ( is_admin() ) {
		// new LinksynceparcelPluginUpdater( __FILE__, 'linksync', 'woo_eparcel' );
        $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/linksync/woo_eparcel',
            __FILE__,
            'linksync-eparcel'
        );

		LinksynceparcelHelper::saveScreenOptions();
		LinksynceparcelHelper::saveOrderStatuses();
	}
}

$linksynceparcel_consignment_menu = '';

register_activation_hook( __FILE__, array( new linksynceparcel, 'activate_eparcel' ) );
register_deactivation_hook( __FILE__, array( new linksynceparcel, 'deactivate_eparcel' ) );
add_action( 'admin_footer', array( new linksynceparcel, 'add_to_admin_footer'),10 );
add_action( 'add_meta_boxes', array( new linksynceparcel, 'add_meta_boxes'));
add_action( 'woocommerce_process_shop_order_meta', array( new linksynceparcel, 'on_editorder'), 100);
add_action( 'wp_ajax_create_consignment_ajax', array(new linksynceparcel, 'create_consignment_ajax') );
add_action( 'wp_ajax_create_mass_consignment_ajax', array(new linksynceparcel, 'create_mass_consignment_ajax') );
add_action( 'admin_init', array( new linksynceparcel, 'change_order_status') );
add_action( 'init', array( new linksynceparcel, 'process_download_pdf'), 10);
add_action('linksyncgetlaidinfo', array( new linksynceparcel,'getlaidinfo' ));

function my_plugin_help($contextual_help, $screen_id, $screen)
{

	global $my_plugin_hook,$linksynceparcel_consignment_menu;

	if ($screen_id == 'toplevel_page_linksynceparcel')
	{
		$contextual_help = '<p>Thank you for using linksync eParcel. Should you need help using linksync eParcel for WooCommerce please read the documentation.<br><br>
		<a target="_blank" href="http://www.linksync.com/help/eparcel-woocommerce" class="button button-primary">linksync eParcel Documentation</a>
		</p>';
		add_action( "load-$linksynceparcel_consignment_menu", array( new linksynceparcel,'add_consignment_option'),100 );
		do_action("load-$linksynceparcel_consignment_menu");
	}
	return $contextual_help;
}
add_filter('contextual_help', 'my_plugin_help', 10, 3);

function add_my_contextual_help() {
}

if(isset($_GET['page']) && $_GET['page'] == 'linksynceparcel' && isset($_GET['subpage']) && isset($_GET['ajax']))
{
	add_action('linksynceparcel-'.$_GET['subpage'], array( new linksynceparcel, str_replace('-','_',$_GET['subpage']) ) );
	do_action('linksynceparcel-'.$_GET['subpage']);
}
else if(isset($_GET['page']) && $_GET['page'] == 'linksynceparcel' && isset($_GET['subpage']) && isset($_GET['view']) && $_GET['view'] == 'front')
{
	add_action('linksynceparcel-'.$_GET['subpage'], array( new linksynceparcel, str_replace('-','_',$_GET['subpage']) ) );
	do_action('linksynceparcel-'.$_GET['subpage']);
}

class linksynceparcel
{
	public $is_greater_than_21 = false;
	public function __construct($menu=false)
	{
		global $is_greater_than_21;
		$this->is_greater_than_21 = $is_greater_than_21;
		if($menu)
		{
			LinksynceparcelHelper::upgradeTables();
			add_action('admin_menu',array(&$this,'admin_menu'));
			add_action('init', array(&$this,'eParcel_startsession'), 1);

			add_screen_options_panel(
				'eParcel-default-settings',       //Panel ID
				'eParcel Settings',              //Panel title.
				'eparcel_default_settings_panel', //The function that generates panel contents.
				array('toplevel_page_linksynceparcel'),            //Pages/screens where the panel is displayed.
				'eparcel_save_new_defaults',      //The function that gets triggered when settings are submitted/saved.
				true                              //Auto-submit settings (via AJAX) when they change.
			);
		}
	}
	public function admin_menu()
	{
		global $pagenow;
		if( $pagenow == 'plugins.php' )
		{
			add_action( 'admin_notices', array($this,'in_plugin_update_message') );
			add_filter( 'plugin_action_links', array($this,'show_plugin_action_links'), 10, 5 );
			add_filter( 'plugin_row_meta', array($this, 'eparcel_plugin_meta_links'), 10, 2 );
		}
		include_once(linksynceparcel_DIR.'includes/admin/menu2.php');
		LinksynceparcelAdminMenu2::output($this);

		if(LinksynceparcelHelper::isSoapInstalled())
		{
			if( isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'shop_order' && isset($_REQUEST['trashed']) && $_REQUEST['trashed'] > 0  && isset($_REQUEST['ids']) && !empty($_REQUEST['ids']) )
			{
				$this->deleteTrashedOrderConsignments($_REQUEST['ids']);
			}
		}
		add_action( 'wcaba_custom_main_items', array($this, 'linksync_admin_bar_render') );
	}

	public function show_plugin_action_links( $actions, $plugin_file ) {
	   static $plugin;

		if (!isset($plugin))
			$plugin = plugin_basename(__FILE__);
			if ($plugin == $plugin_file) {
				$settings = array('settings' => '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=linksynceparcel&subpage=configuration') ) .'">' . __('Settings', 'General') . '</a>');
				$actions = array_merge($settings, $actions);
			}

		return $actions;
	}

	function eparcel_plugin_meta_links( $links, $file ) {
		$plugin = plugin_basename(__FILE__);
		// create link
		if ( $file == $plugin ) {
			return array_merge(
				$links,
				array( '<a target="_blank" href="http://www.linksync.com/help/eparcel-woocommerce">Docs</a>' )
			);
		}
		return $links;
	}

	public function in_plugin_update_message()
	{
		if(!LinksynceparcelHelper::isSoapInstalled())
		{
			$o = '<div class="error" style="border-left: 4px solid rgb(255, 192, 58);">';
			$o .= '<p>linksync eParcel - PHP Soap extension is not enabled on your server, contact your web hoster to enable this extension.</p>';
			$o .= '</div>';
			echo $o;
		}
		else
		{
			$currentVersion = '0.3.3';
			$result = LinksynceparcelApi::getVersionNumber();
			if($result)
			{
				$latestVersion = isset($result->version_number) ? $result->version_number : '0.0.7';
				update_option('linksynceparcel_last_version_check_time',time());
				update_option('linksynceparcel_version',$latestVersion);
				update_option('linksynceparcel_notsame',0);
				if( intval(str_replace('.','',$currentVersion)) < intval(str_replace('.','',$latestVersion)) )
				{
					update_option('linksynceparcel_notsame',1);
				}
			}
		}
	}

	public function network_propagate($pfunction, $networkwide) {
		global $wpdb;

		if (function_exists('is_multisite') && is_multisite()) {
			// check if it is a network activation - if so, run the activation function
			// for each blog id
			if ($networkwide) {
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				$blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);
					call_user_func(array($this, $pfunction), $networkwide);
				}
				switch_to_blog($old_blog);
				return;
			}
		}
		call_user_func(array($this, $pfunction), $networkwide);
	}

	public function on_activation($networkwide=false)
	{
		if ( ! wp_next_scheduled( 'linksynceparceltruncatelog' ) )
		{
			wp_schedule_event( time(), 'daily', 'linksynceparceltruncatelog' );
		}

        add_action( 'linksynceparceltruncatelog', array($this,'shrink_log'));

        if ( ! wp_next_scheduled( 'linksyncgetlaidinfo' ) )
        {
            wp_schedule_event( time(), 'daily', 'linksyncgetlaidinfo' );
        }

		LinksynceparcelHelper::saveDefaultConfiguration();
		LinksynceparcelHelper::createTables();
		LinksynceparcelHelper::createNewTables();

	}

    public function getlaidinfo()
    {
        $laid_info = LinksyncApiController::get_key_info();
        if (!empty($laid_info)) {
           LinksyncApiController::update_current_laid_info($laid_info);
           LinksynceparcelHelper::log("Get Laid info: " . json_encode($laid_info));
        }
    }

	public function on_deactivation($networkwide=false)
	{
		if ( ! wp_next_scheduled( 'linksynceparceltruncatelog' ) )
		{
			wp_clear_scheduled_hook( 'linksynceparceltruncatelog' );
		}

        if ( ! wp_next_scheduled( 'linksyncgetlaidinfo' ) )
        {
            wp_clear_scheduled_hook( 'linksyncgetlaidinfo' );
        }
	}

	public function activate_eparcel($networkwide) {
		$this->network_propagate('on_activation', $networkwide);
	}

	public function deactivate_eparcel($networkwide) {
		$this->network_propagate('on_deactivation', $networkwide);
	}

	public function add_to_admin_footer()
	{
		include_once(linksynceparcel_DIR.'views/admin/menu2.php');
		add_action('linksynceparcel-help_menu','add_my_contextual_help');
		do_action('linksynceparcel-help_menu');
	}
	public function add_meta_boxes()
	{
		if(LinksynceparcelHelper::isSoapInstalled())
		{
			if(isset($_GET['post']))
			{
				$order_id = (int)($_GET['post']);
				if(get_post_type($order_id) == 'shop_order') {
					if(LinksynceparcelHelper::getOrderChargeCode($order_id))
					{
						$post = get_post($order_id);

						if($post->post_type == 'shop_order')
						{
							$order = new WC_Order( $order_id );
							$address = get_post_meta($order_id);

							if($address['_shipping_country'][0] == 'AU')
							{
								if($this->is_greater_than_21)
								{
                                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
									if(!($order_status == 'wc-failed' || $order_status == 'wc-cancelled'))
									{
										$valid = LinksynceparcelHelper::getAddressValid($order_id);
										if(isset($valid->is_address_valid) && $valid->is_address_valid)
										{
											add_meta_box( 'linksynceparcel', 'linksync eParcel Shipments', array( $this, 'consignments_order_view'), 'shop_order', 'normal', 'high' );
										}
										else
										{
											add_meta_box('linksynceparcel_address', 'linksync eParcel Address Validation', array($this, 'address_order_meta_box'), 'shop_order', 'normal', 'high' );
										}
									}
								}
								else
								{
									if( !($order->status == 'failed' || $order->status == 'cancelled') )
									{
										$valid = LinksynceparcelHelper::getAddressValid($order_id);
										if(isset($valid->is_address_valid) && $valid->is_address_valid)
										{
											add_meta_box( 'linksynceparcel', 'linksync eParcel Shipments', array( $this, 'consignments_order_view'), 'shop_order', 'normal', 'high' );
										}
										else
										{
											add_meta_box('linksynceparcel_address', 'linksync eParcel Address Validation', array($this, 'address_order_meta_box'), 'shop_order', 'normal', 'high' );
										}
									}
								}
							} else {
								// International
								if($this->is_greater_than_21)
								{
                                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
									if(!($order_status == 'wc-failed' || $order_status == 'wc-cancelled'))
									{
										add_meta_box( 'linksynceparcel', 'linksync eParcel Shipments', array( $this, 'consignments_order_view'), 'shop_order', 'normal', 'high' );
									}
								}
								else
								{
									if( !($order->status == 'failed' || $order->status == 'cancelled') )
									{
										add_meta_box( 'linksynceparcel', 'linksync eParcel Shipments', array( $this, 'consignments_order_view'), 'shop_order', 'normal', 'high' );
									}
								}
							}
						}
					}
				}
			}
		}
	}
	public function consignments_order_view()
	{
		$error = get_option('linksynceparcel_order_view_error');
		$success = get_option('linksynceparcel_order_view_success');
		if($error)
		{
			LinksynceparcelHelper::addError($error);
			delete_option('linksynceparcel_order_view_error');
		}
		if($success)
		{
			LinksynceparcelHelper::addSuccess($success);
			delete_option('linksynceparcel_order_view_success');
		}
		include_once(linksynceparcel_DIR.'includes/admin/consignments/order_view.php');
		LinksynceparcelAdminConsignmentsOrderView::output();
	}
	public function address_order_meta_box()
	{
		if(LinksynceparcelHelper::isSoapInstalled())
		{
			$order_id = (int)($_GET['post']);
			if(get_post_type($order_id) == 'shop_order') {
				if(LinksynceparcelHelper::getOrderChargeCode($order_id))
				{
					$valid = LinksynceparcelHelper::isOrderAddressValid($order_id);
					if($valid != 1)
					{
						echo $valid;
					}
					else
					{
						add_meta_box( 'linksynceparcel', 'linksync eParcel Shipments', array( $this, 'consignments_order_view'), 'shop_order', 'normal', 'high' );
					}
				}
			}
		}
	}

	public function add_consignment_option()
	{
		$per_page = (int)get_option('consignment_per_page');
		if($per_page == 0)
		{
			$per_page = 20;
		}

		$args = array(
			'label' => __('Number of items per page'),
			'default' => $per_page,
			'option' => 'consignment_per_page'
		);
		add_screen_option( 'per_page', $args );
	}

	public function consignments()
	{
		if(!isset($_GET['subpage']))
		{
			if(isset($_GET['action']) && $_GET['action'] == 'delete_consignment')
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/delete.php');
				LinksynceparcelAdminConsignmentsDelete::save();
			}
			else if(isset($_GET['action']) && $_GET['action'] == 'delete_article')
			{
				include_once(linksynceparcel_DIR.'includes/admin/articles/delete.php');
				LinksynceparcelAdminArticlesDelete::save();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massAssignConsignment') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massAssignConsignment') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massAssignConsignment();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massUnassignConsignment') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massUnassignConsignment') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massUnassignConsignment();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massGenerateLabels') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massGenerateLabels') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massGenerateLabels();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massGenerateDocs') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massGenerateDocs') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massGenerateDocs();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massGenerateReturnLabels') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massGenerateReturnLabels') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massGenerateReturnLabels();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massDeleteConsignment') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massDeleteConsignment') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massDeleteConsignment();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massCreateConsignment') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massCreateConsignment') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massCreateConsignment();
			}
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'massMarkDespatched') || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'massMarkDespatched') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::massMarkDespatched();
			}
			else
			{
				$this->getlaidinfo();
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::output();
			}
		}
		else
		{
			add_action('linksynceparcel-'.$_GET['subpage'], array( $this, str_replace('-','_',$_GET['subpage']) ) );
			do_action('linksynceparcel-'.$_GET['subpage']);
		}
	}
	public function consignments_search() {
		include_once(linksynceparcel_DIR.'includes/admin/consignments/list.php');
		LinksynceparcelAdminConsignmentsList::output();
	}
	public function create_mass_consignment() {
		include_once(linksynceparcel_DIR.'includes/admin/consignments/create_mass.php');
		LinksynceparcelAdminConsignmentsCreateMass::save();
	}
	public function manifests() {
		if(isset($_REQUEST['action']))
		{
			if($_REQUEST['action'] == 'list-consignments')
			{
				include_once(linksynceparcel_DIR.'includes/admin/manifests/consignmentslist.php');
				LinksynceparcelAdminManifestsConsignmentsList::output();
			}
			else if($_REQUEST['action'] == 'generateLabel')
			{
				include_once(linksynceparcel_DIR.'includes/admin/manifests/list.php');
				LinksynceparcelAdminManifestsList::generateLabels();
			}
			else if(isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'generateLabel')
			{
				include_once(linksynceparcel_DIR.'includes/admin/manifests/list.php');
				LinksynceparcelAdminManifestsList::generateLabels();
			}
			else
			{
				include_once(linksynceparcel_DIR.'includes/admin/manifests/list.php');
				LinksynceparcelAdminManifestsList::output();
			}
		}
		else if(isset($_REQUEST['action2']) && $_REQUEST['action2'] == 'generateLabel')
		{
			include_once(linksynceparcel_DIR.'includes/admin/manifests/list.php');
			LinksynceparcelAdminManifestsList::generateLabels();
		}
		else
		{
			include_once(linksynceparcel_DIR.'includes/admin/manifests/list.php');
			LinksynceparcelAdminManifestsList::output();
		}
	}
	public function add_article()
	{
		include_once(linksynceparcel_DIR.'includes/admin/articles/add.php');
		if(isset($_GET['action']) && $_GET['action'] == 'save')
		{
			LinksynceparcelAdminArticlesAdd::save();
		}
		else
		{
			LinksynceparcelAdminArticlesAdd::output();
		}
	}
	public function edit_article()
	{
		include_once(linksynceparcel_DIR.'includes/admin/articles/edit.php');
		if(isset($_GET['action']) && $_GET['action'] == 'save')
		{
			LinksynceparcelAdminArticlesEdit::save();
		}
		else
		{
			LinksynceparcelAdminArticlesEdit::output();
		}
	}
	public function edit_consignment()
	{
		include_once(linksynceparcel_DIR.'includes/admin/consignments/edit.php');
		if(isset($_GET['action']) && $_GET['action'] == 'save')
		{
			LinksynceparcelAdminConsignmentsEdit::save();
		}
		else
		{
			LinksynceparcelAdminConsignmentsEdit::output();
		}
	}
	public function article_presets()
	{
		include_once(linksynceparcel_DIR.'includes/admin/article_presets.php');
		LinksynceparcelAdminArticlePresets::output();
	}
	public function assign_shipping_types()
	{
		include_once(linksynceparcel_DIR.'includes/admin/assign_shipping_types.php');
		LinksynceparcelAdminAssignShippingTypes::output();
	}
	public function configuration()
	{
		include_once(linksynceparcel_DIR.'includes/admin/configuration.php');
		LinksynceparcelAdminConfiguration::output();
	}
	public function sendlog()
	{
		include_once(linksynceparcel_DIR.'includes/admin/send_log.php');
		LinksynceparcelAdminSendLog::output();
	}
	public function update_label_as_printed()
	{
		$consignmentNumber = $_REQUEST['consignmentNumber'];
		$consignmentNumber = preg_replace('/[^0-9a-zA-Z]/', '', $consignmentNumber);
		$shipCountry = LinksynceparcelHelper::getCountryDeliveryConsignment($consignmentNumber);
		$pos = strpos($consignmentNumber, 'int');
		if ($pos === false) {
			$column = 'is_label_printed';
			LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,$column,1);
		} else {
			$consignmentNumber = str_replace('int', '', $consignmentNumber);
			LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_customdocs_printed',1);
		}
		exit;
	}
	public function update_return_label_as_printed()
	{
		$consignmentNumber = $_REQUEST['consignmentNumber'];
		$consignmentNumber = preg_replace('/[^0-9a-zA-Z]/', '', $consignmentNumber);
		LinksynceparcelHelper::updateConsignmentTable($consignmentNumber,'is_return_label_printed',1);
		exit;
	}
	public function on_neworder($order_id)
	{
		if(get_post_type($order_id) == 'shop_order') {
			if(LinksynceparcelHelper::isSoapInstalled())
			{
				if($order_id > 0)
				{
					$address = get_post_meta($order_id);
					if($address['_shipping_country'][0] == 'AU')
					{
						$order = new WC_Order( $order_id );

						if($this->is_greater_than_21)
						{
                            $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
							if(!($order_status == 'wc-failed' || $order_status == 'wc-cancelled'))
							{
								if(LinksynceparcelHelper::getOrderChargeCode($order_id))
								{
									LinksynceparcelHelper::isOrderAddressValid($order_id);
								}
							}
						}
						else
						{
                            $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
							if( !($order_status == 'failed' || $order_status == 'cancelled') )
							{
								if(LinksynceparcelHelper::getOrderChargeCode($order_id))
								{
									LinksynceparcelHelper::isOrderAddressValid($order_id);
								}
							}
						}
					}
				}
			}
		}
	}
	public function on_editorder($order_id)
	{
		if(LinksynceparcelHelper::isSoapInstalled())
		{
			if($order_id > 0)
			{
				$order = new WC_Order( $order_id );

				if($this->is_greater_than_21)
				{
                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
					if(!($order_status == 'wc-failed' || $order_status == 'wc-cancelled'))
					{
						if(LinksynceparcelHelper::getOrderChargeCode($order_id))
						{
							if(isset($_REQUEST['createConsignmentHidden']) && $_REQUEST['createConsignmentHidden'] == 1)
							{
								include_once(linksynceparcel_DIR.'includes/admin/consignments/create.php');
								$use_order_weight = (int)get_option('linksynceparcel_use_order_weight');
								$use_dimension = (int)get_option('linksynceparcel_use_dimension');
								if($use_order_weight == 1 && $use_dimension != 1)
								{
									LinksynceparcelAdminConsignmentsCreate::saveOrderWeight();
								}
								else if($use_order_weight != 1 && $use_dimension != 1)
								{
									LinksynceparcelAdminConsignmentsCreate::saveDefaultWeight();
								}
								else
								{
									LinksynceparcelAdminConsignmentsCreate::save();
								}

							}
							else
							{
								LinksynceparcelHelper::isOrderAddressValid($order_id,true,$_REQUEST);
								$valid = LinksynceparcelHelper::getAddressValid($order_id);
								if($valid->is_address_valid)
								{
									//resubmit consignments
								}
							}
						}
					}

                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
					if($order_status == 'wc-cancelled')
					{
						$this->cancelledOrderConsignments($order_id);
					}
				}
				else
				{
                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
					if( !($order_status == 'failed' || $order_status == 'cancelled') )
					{
						if(LinksynceparcelHelper::getOrderChargeCode($order_id))
						{
							if(isset($_REQUEST['createConsignmentHidden']) && $_REQUEST['createConsignmentHidden'] == 1)
							{
								include_once(linksynceparcel_DIR.'includes/admin/consignments/create.php');
								$use_order_weight = (int)get_option('linksynceparcel_use_order_weight');
								$use_dimension = (int)get_option('linksynceparcel_use_dimension');
								if($use_order_weight == 1 && $use_dimension != 1)
								{
									LinksynceparcelAdminConsignmentsCreate::saveOrderWeight();
								}
								else if($use_order_weight != 1 && $use_dimension != 1)
								{
									LinksynceparcelAdminConsignmentsCreate::saveDefaultWeight();
								}
								else
								{
									LinksynceparcelAdminConsignmentsCreate::save();
								}
							}
							else
							{
								LinksynceparcelHelper::isOrderAddressValid($order_id,true,$_REQUEST);
								$valid = LinksynceparcelHelper::getAddressValid($order_id);
								if($valid->is_address_valid)
								{
									//resubmit consignments
								}
							}
						}
					}

					if($order->get_status() == 'cancelled')
					{
						$this->cancelledOrderConsignments($order_id);
					}
				}
			}
		}
	}

	public function cancelledOrderConsignments($order_id)
	{
		try
		{
			$consignments = LinksynceparcelHelper::getOpenConsignments($order_id);
			if($consignments && count($consignments) > 0)
			{
				foreach ($consignments as $consignment)
				{
					$consignmentNumber = $consignment->consignment_number;

					try
					{
						$status = LinksynceparcelApi::deleteConsignment($consignmentNumber);
						$status = trim(strtolower($status));
						if($status == 'ok')
						{
							$filename = $consignmentNumber.'.pdf';
							$filepath = linksynceparcel_DIR.'assets/label/consignment/'.$filename;
							if(file_exists($filepath))
							{
								unlink($filepath);
							}

							$filepath2 = linksynceparcel_DIR.'assets/label/returnlabels/'.$filename;
							if(file_exists($filepath2))
							{
								unlink($filepath2);
							}

							LinksynceparcelHelper::deleteConsignment($consignmentNumber);
						}
					}
					catch (Exception $e)
					{

					}
				}

				LinksynceparcelHelper::getManifestNumber();
				LinksynceparcelHelper::deleteManifest();
			}
		}
		catch (Exception $e)
		{
			LinksynceparcelHelper::deleteManifest();
		}
	}

	public function deleteTrashedOrderConsignments($ids)
	{
		$ids = explode(',',$ids);
		if(is_array($ids))
		{
			try
			{
				foreach ($ids as $order_id)
				{
					$consignments = LinksynceparcelHelper::getConsignments($order_id);
					if($consignments && count($consignments) > 0)
					{
						foreach ($consignments as $consignment)
						{
							$consignmentNumber = $consignment->consignment_number;

							try
							{
								$status = LinksynceparcelApi::deleteConsignment($consignmentNumber);
								$status = trim(strtolower($status));
								if($status == 'ok')
								{
									$filename = $consignmentNumber.'.pdf';
									$filepath = linksynceparcel_DIR.'assets/label/consignment/'.$filename;
									if(file_exists($filepath))
									{
										unlink($filepath);
									}

									$filepath2 = linksynceparcel_DIR.'assets/label/returnlabels/'.$filename;
									if(file_exists($filepath2))
									{
										unlink($filepath2);
									}

									LinksynceparcelHelper::deleteConsignment($consignmentNumber);
								}
							}
							catch (Exception $e)
							{

							}
						}

						LinksynceparcelHelper::getManifestNumber();
						LinksynceparcelHelper::deleteManifest();
					}
				}

			}
			catch (Exception $e)
			{
				LinksynceparcelHelper::deleteManifest();
			}
		}
	}

	public function shrink_log()
	{
		LinksynceparcelHelper::log('shrink log started');
		$lines = 10000;
		$buffer = 4096;
		$file = linksynceparcel_LOG_DIR .'linksynceparcel.log';

		$output = '';
		$chunk = '';

		$f = @fopen($file, "rb");
		if ($f === false)
			return false;

		fseek($f, -1, SEEK_END);
		if (fread($f, 1) != "\n")
			$lines -= 1;

		while (ftell($f) > 0 && $lines >= 0)
		{
			$seek = min(ftell($f), $buffer);
			fseek($f, -$seek, SEEK_CUR);
			$output = ($chunk = fread($f, $seek)) . $output;
			fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
			$lines -= substr_count($chunk, "\n");
		}

		while ($lines++ < 0)
		{
			$output = substr($output, strpos($output, "\n") + 1);
		}
		fclose($f);
		$content = trim($output);
		$f = @fopen($file, "w");
		if ($f === false)
			return false;
		fwrite($f,$content);
		fclose($f);
		LinksynceparcelHelper::log('shrink log ended');
		exit;
	}

	public function linksync_admin_bar_render() {
		global $wp_admin_bar;
		$wp_admin_bar->add_menu( array(
			'parent' => 'ddw-woocommerce-admin-bar-default',
			'id' => 'linksync',
			'title' => __('linksync eParcel'),
			'href' => admin_url( 'admin.php?page=linksynceparcel'),
			'meta' => array('title' => __( 'linksync eParcel')),
		));
		$wp_admin_bar->add_menu( array(
			'parent' => 'linksync',
			'id' => 'linksync-consignments',
			'title' => __('Consignments'),
			'href' => admin_url( 'admin.php?page=linksynceparcel'),
			'meta' => array('title' => __( 'Consignments List')),
		));
		$wp_admin_bar->add_menu( array(
			'parent' => 'linksync',
			'id' => 'linksync-consignments-search',
			'title' => __('Consignments Search'),
			'href' => admin_url( 'admin.php?page=linksynceparcel&subpage=consignments-search'),
			'meta' => array('title' => __( 'Consignments Search List')),
		));
		$wp_admin_bar->add_menu( array(
			'parent' => 'linksync',
			'id' => 'linksync-manifests',
			'title' => __('Manifests'),
			'href' => admin_url( 'admin.php?page=linksynceparcel&subpage=manifests'),
			'meta' => array('title' => __( 'Manifests List')),
		));
		$wp_admin_bar->add_menu( array(
			'parent' => 'linksync',
			'id' => 'linksync-article-presets',
			'title' => __('Article Presets'),
			'href' => admin_url( 'admin.php?page=linksynceparcel&subpage=article-presets'),
			'meta' => array('title' => __( 'Article Presets')),
		));
		$wp_admin_bar->add_menu( array(
			'parent' => 'linksync',
			'id' => 'linksync-assign-shipping-types',
			'title' => __('Assign Shipping Types'),
			'href' => admin_url( 'admin.php?page=linksynceparcel&subpage=assign-shipping-types'),
			'meta' => array('title' => __( 'Assign Shipping Types')),
		));
		$wp_admin_bar->add_menu( array(
			'parent' => 'linksync',
			'id' => 'linksync-configuration',
			'title' => __('Configuration'),
			'href' => admin_url( 'admin.php?page=linksynceparcel&subpage=configuration'),
			'meta' => array('title' => __( 'linksync eParcel Configuration')),
		));
	}

	public function eParcel_startsession() {
		if(!session_id()) {
			session_start();
		}
	}

	/* Process AJAX */
	public function despatched_Manifest()
	{
		include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
		$response = LinksynceparcelAdminConsignmentsOrdersList::despatchManifestData();
		$error = $response['error'];
		$message = $response['msg'];
		if($error == 0) {
			LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$message);
		}
		if($error == 1) {
			LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$message);
		}
		if($error == 2 && is_array($message)) {
			foreach($message as $msg)
			{
				if($msg['error'] == 0) {
					LinksynceparcelHelper::addMessage('linksynceparcel_consignment_success',$msg['msg']);
				}
				if($msg['error'] == 1) {
					LinksynceparcelHelper::addMessage('linksynceparcel_consignment_error',$msg['msg']);
				}
			}
		}
		echo 2;
		exit;
	}

	public function create_consignment_ajax()
	{
		$order_id = $_POST['post_ID'];
		if(LinksynceparcelHelper::isSoapInstalled())
		{
			if($order_id > 0)
			{
				$order = new WC_Order( $order_id );

				if($this->is_greater_than_21)
				{
                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
					if(!($order_status == 'wc-failed' || $order_status == 'wc-cancelled'))
					{
						if(LinksynceparcelHelper::getOrderChargeCode($order_id))
						{
							include_once(linksynceparcel_DIR.'includes/admin/consignments/create.php');
							$use_order_weight = (int)get_option('linksynceparcel_use_order_weight');
							$use_dimension = (int)get_option('linksynceparcel_use_dimension');
							if($use_order_weight == 1 && $use_dimension != 1)
							{
								LinksynceparcelAdminConsignmentsCreate::saveOrderWeight();
							}
							else if($use_order_weight != 1 && $use_dimension != 1)
							{
								LinksynceparcelAdminConsignmentsCreate::saveDefaultWeight();
							}
							else
							{
								LinksynceparcelAdminConsignmentsCreate::save();
							}
						}
					}
                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
					if($order_status == 'wc-cancelled')
					{
						$this->cancelledOrderConsignments($order_id);
					}
				}
				else
				{
                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
					if( !($order_status == 'failed' || $order_status == 'cancelled') )
					{
						if(LinksynceparcelHelper::getOrderChargeCode($order_id))
						{
							include_once(linksynceparcel_DIR.'includes/admin/consignments/create.php');
							$use_order_weight = (int)get_option('linksynceparcel_use_order_weight');
							$use_dimension = (int)get_option('linksynceparcel_use_dimension');
							if($use_order_weight == 1 && $use_dimension != 1)
							{
								LinksynceparcelAdminConsignmentsCreate::saveOrderWeight();
								echo 'success';
							}
							else if($use_order_weight != 1 && $use_dimension != 1)
							{
								LinksynceparcelAdminConsignmentsCreate::saveDefaultWeight();
								echo 'success';
							}
							else
							{
								LinksynceparcelAdminConsignmentsCreate::save();
								echo 'success';
							}
						}
					}
                    $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->status;
					if($order_status == 'cancelled')
					{
						$this->cancelledOrderConsignments($order_id);
					}
				}
			}
		}
		exit;
	}

	public function create_mass_consignment_ajax() {
		$this->create_mass_consignment();
		exit;
 	}

	public function change_order_status()
	{
		if(!session_id()) {
			session_start();
		}

		global $is_greater_than_21;

        // manual
        // self::manual_change_status_manifest_orders('M000000057');

		$statuses = LinksynceparcelHelper::getListOrderStatuses();
		$changeState = get_option('linksynceparcel_change_order_status');

		$current_manifest = LinksynceparcelHelper::get_session_manifest(session_id());
		if(!empty($current_manifest)) {

			if(!empty($current_manifest['orders'])) {
				foreach($current_manifest['orders'] as $k => $orderid) {
					$order = new WC_Order($orderid);

					$current_status = '';

					if($is_greater_than_21)
					{
						foreach($statuses as $term_id => $status)
						{
                            $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
							if($term_id == $order_status)
							{
								$current_status = $term_id;
							}
						}

						if ($changeState && ($changeState !== $current_status))
						{
							$order->update_status($changeState);
						}
					}
					else
					{
						foreach($statuses as $status)
						{
							if($status->slug == $order->status)
							{
								$current_status = $status->term_id;
							}
						}

						if ($changeState && ($changeState !== $current_status))
						{
							foreach($statuses as $status)
							{
								if($status->term_id == $changeState)
								{
									$order->update_status($status->slug);
								}
							}
						}
					}
				}
			}

			if(!empty($current_manifest['manifestnumber'])) {
				$notifyCustomerOption = get_option('linksynceparcel_notify_customers');
				if($notifyCustomerOption == 1) {
					LinksynceparcelHelper::notifyCustomers($current_manifest['manifestnumber']);
				}
			}
			LinksynceparcelHelper::remove_manifest_session(session_id());
		}
	}

    public function manual_change_status_manifest_orders($manifest_number=false)
    {
        global $is_greater_than_21;

        $statuses = LinksynceparcelHelper::getListOrderStatuses();
        $changeState = get_option('linksynceparcel_change_order_status');

        if($manifest_number) {
        	$timestamp = time();
			$date = date('Y-m-d H:i:s', $timestamp);
			LinksynceparcelHelper::updateManifestTable($manifest_number,'despatch_date',$date);
			LinksynceparcelHelper::updateConsignmentTableByManifest($manifest_number,'despatched',1);
			LinksynceparcelHelper::updateConsignmentTableByManifest($manifest_number,'is_next_manifest',0);

            $results = LinksynceparcelHelper::getAllNonChangedStatusOrders($manifest_number);

            foreach($results as $k => $result) {
                $order = new WC_Order($result);

                $current_status = '';

                if($is_greater_than_21)
                {
                    foreach($statuses as $term_id => $status)
                    {
                        $order_status = method_exists($order, 'get_status') ? $order->get_status() : $order->post_status;
                        if($term_id == $order_status)
                        {
                            $current_status = $term_id;
                        }
                    }

                    if ($changeState && ($changeState !== $current_status))
                    {
                        $order->update_status($changeState);
                    }
                }
                else
                {
                    foreach($statuses as $status)
                    {
                        if($status->slug == $order->status)
                        {
                            $current_status = $status->term_id;
                        }
                    }

                    if ($changeState && ($changeState !== $current_status))
                    {
                        foreach($statuses as $status)
                        {
                            if($status->term_id == $changeState)
                            {
                                $order->update_status($status->slug);
                            }
                        }
                    }
                }
            }

            $notifyCustomerOption = get_option('linksynceparcel_notify_customers');
			if($notifyCustomerOption == 1) {
				LinksynceparcelHelper::notifyCustomers($current_manifest['manifestnumber']);
			}
        }
    }

	public function process_download_pdf()
	{
		if( isset($_GET['f_key']) && !empty($_GET['f_key'])) {
			$filename = false;
			switch($_GET['f_type']) {
				case 'consignment':
					$filename = linksynceparcel_UPLOAD_DIR .'consignment/'. $_GET['f_key'] .'.pdf';
					if(!file_exists($filename)) {
						$filename = linksynceparcel_OLD_UPLOAD_DIR .'consignment/'. $_GET['f_key'] .'.pdf';
					}
					if(!file_exists($filename)) {
						$filename = linksynceparcel_URL .'assets/label/consignment/'. $_GET['f_key'] .'.pdf';
					}
					break;

				case 'manifest':
					$filename = linksynceparcel_UPLOAD_DIR .'manifest/'. $_GET['f_key'] .'.pdf';
					if(!file_exists($filename)) {
						$filename = linksynceparcel_OLD_UPLOAD_DIR .'manifest/'. $_GET['f_key'] .'.pdf';
					}
					if(!file_exists($filename)) {
						$filename = linksynceparcel_URL .'assets/label/manifest/'. $_GET['f_key'] .'.pdf';
					}
					break;

				default:
					$filename = linksynceparcel_UPLOAD_DIR .'consignment/'. $_GET['f_key'] .'.pdf';
					if(!file_exists($filename)) {
						$filename = linksynceparcel_OLD_UPLOAD_DIR .'consignment/'. $_GET['f_key'] .'.pdf';
					}
					if(!file_exists($filename)) {
						$filename = linksynceparcel_URL .'assets/label/consignment/'. $_GET['f_key'] .'.pdf';
					}
					break;
			}

			if ( is_file($filename) ) {
				// required for IE & Safari
				if(ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off');	}

				header('Pragma: public'); 	// required
				header('Expires: 0');		// no cache
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Last-Modified: '.gmdate ('D, d M Y H:i:s', @filemtime ($filename)).' GMT');
				header('Cache-Control: private',false);
				header('Content-Type: application/pdf');
				header('Content-Disposition: attachment; filename="'.basename($filename).'"');
				header('Content-Transfer-Encoding: binary');
				header('Content-Length: '. @filesize($filename) );	// provide file size
				header('Connection: close');
				$this->readfileChunked( $filename );		// push it out
				die();
			}
		}
	}

	public function readfileChunked($filename, $retbytes=true){
		$chunksize = 1*(1024*1024);
		$buffer = '';
		$cnt = 0;
		$handle = fopen($filename, 'rb');
		if ($handle === false) {
			return false;
		}
		while (!feof($handle)) {
			$buffer = fread($handle, $chunksize);
			echo $buffer;
			ob_flush();
			flush();
			if ($retbytes) {
				$cnt += strlen($buffer);
			}
		}
		$status = fclose($handle);
		if ($retbytes && $status) {
			return $cnt;
		}
		return $status;
	}

	public function formatSizeUnits($bytes){
		if ($bytes >= 1073741824){
			 $bytes = number_format($bytes / 1073741824, 2) . ' GB';
		} elseif ($bytes >= 1048576) {
			 $bytes = number_format($bytes / 1048576, 2) . ' MB';
		} elseif ($bytes >= 1024) {
			$bytes = number_format($bytes / 1024, 2) . ' KB';
		} elseif ($bytes > 1) {
			$bytes = $bytes . ' bytes';
		} elseif ($bytes == 1) {
			$bytes = $bytes . ' byte';
		} else {
			$bytes = '0 bytes';
		}
		return $bytes;
	}
}

function success_notice($success,$error)
{
	if($success)
	{
    ?>
    <div class="updated">
        <p><?php echo $success ?></p>
    </div>
    <?php
	}
}
function error_notice($success,$error)
{
	if($error)
	{
    ?>
    <div class="error">
        <p><?php echo $error ?></p>
    </div>
    <?php
	}
}
?>
