<?php
/**
 * Plugin Name: linksync eParcel
 * Plugin URI: http://www.linksync.com/integrate/woocommerce-eparcel-integration
 * Description: Manage your eParcel orders without leaving your WordPress WooCommerce store with linksync eParcel for WooCommerce.
 * Version: 1.1.1-beta.8
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
define( 'linksynceparcel_LOG_DIR', WP_CONTENT_DIR .'/linksync/log/' );
define( 'linksynceparcel_LOG_URL', WP_CONTENT_URL .'/linksync/log/' );
define( 'linksynceparcel_UPLOAD_DIR', WP_CONTENT_DIR .'/linksync/label/' );
define( 'linksynceparcel_UPLOAD_BASEURL', WP_CONTENT_URL.'/linksync/label/' );

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

function linksynceparcel_init()
{
	$linksynceparcel = new linksynceparcel(true);	
	LinksynceparcelHelper::createUploadDirectory();
	LinksynceparcelHelper::upgradeTables();
	LinksynceparcelHelper::createNewTables();
	if ( is_admin() ) {
		// new LinksynceparcelPluginUpdater( __FILE__, 'linksync', 'woo_eparcel' );
		$className = PucFactory::getLatestClassVersion('PucGitHubChecker');
		$myUpdateChecker = new $className(
			'https://github.com/linksync/woo_eparcel',
			__FILE__,
			'master'
		);
		LinksynceparcelHelper::saveScreenOptions();
	}
}

$linksynceparcel_consignment_menu = '';

register_activation_hook( __FILE__, array( new linksynceparcel, 'activate_eparcel' ) );
register_deactivation_hook( __FILE__, array( new linksynceparcel, 'deactivate_eparcel' ) );
add_action( 'admin_footer', array( new linksynceparcel, 'add_to_admin_footer'),10 );
add_action( 'add_meta_boxes', array( new linksynceparcel, 'add_meta_boxes'));
add_action( 'woocommerce_checkout_order_processed', array( new linksynceparcel,'on_neworder') );
add_action( 'woocommerce_process_shop_order_meta', array( new linksynceparcel, 'on_editorder'), 100);

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
		
		LinksynceparcelHelper::saveDefaultConfiguration();
		LinksynceparcelHelper::createTables();
		LinksynceparcelHelper::createNewTables();
		
	}
	public function on_deactivation($networkwide=false)
	{
		if ( ! wp_next_scheduled( 'linksynceparceltruncatelog' ) )
		{
			wp_clear_scheduled_hook( 'linksynceparceltruncatelog' );
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
								if(!($order->post_status == 'wc-failed' || $order->post_status == 'wc-cancelled'))
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
								if(!($order->post_status == 'wc-failed' || $order->post_status == 'wc-cancelled'))
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
			else if( (isset($_REQUEST['action']) && $_REQUEST['action'] == 'despatchManifest') )
			{
				include_once(linksynceparcel_DIR.'includes/admin/consignments/orderslist.php');
				LinksynceparcelAdminConsignmentsOrdersList::despatchManifest();
			}
			else
			{
						
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
						if(!($order->post_status == 'wc-failed' || $order->post_status == 'wc-cancelled'))
						{
							if(LinksynceparcelHelper::getOrderChargeCode($order_id))
							{
								LinksynceparcelHelper::isOrderAddressValid($order_id);
							}
						}
					}
					else
					{
						if( !($order->status == 'failed' || $order->status == 'cancelled') )
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
	public function on_editorder($order_id)
	{
		if(LinksynceparcelHelper::isSoapInstalled())
		{
			if($order_id > 0)
			{
				$order = new WC_Order( $order_id );
				
				if($this->is_greater_than_21)
				{
					if(!($order->post_status == 'wc-failed' || $order->post_status == 'wc-cancelled'))
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
					
					if($order->post_status == 'wc-cancelled')
					{
						$this->cancelledOrderConsignments($order_id);
					}
				}
				else
				{
					if( !($order->status == 'failed' || $order->status == 'cancelled') )
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
					
					if($order->status == 'cancelled')
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
		$file = linksynceparcel_DIR.'/log/linksynceparcel.log';
		
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