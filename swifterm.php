<?php
/**
 * Plugin Name: SwiftERM
 * Plugin URI: http://www.swifterm.com
 * Description: SwiftERM intelligent eMail remarketing for wordpress.
 * Version: 1.2.9
 * Author: SwiftERM
 * Author URI: http://www.swifterm.com
 * License: GPL2
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (! class_exists('swifterm')) :
	/**
	 * Main SwiftERM for WooCommerce API Class
	 *
	 * @class swift
	 * @version	1.2.9
	 */
	class swifterm {

		const OPTION_API_KEY = 'swifterm_api_key';

		const VERSION = '1.2.9';

		const SWIFTERM_ORDER_TABLE = 'swift_orders';

		const SWIFTERM_CART_NAME = 'swifterm_cart';

		const SWIFTERM_ORDER_NAME = 'swifterm_order';

		public static function _is_curl() {
			return function_exists('curl_version');
		}

		public function __construct() {
			$this->includes();

			if (!session_id()) {
				session_start();
			}

			$this->generate_user_id();

			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_menu', array($this, 'add_menu'));
			add_action('admin_enqueue_scripts', array($this, 'load_wp_admin_styles'));

			//webtracking code embed
			add_action('wp_footer', array($this, 'webtracking_footer'), 10, 1);
			add_action('woocommerce_after_single_product', array($this, 'webtracking_product'));
			add_action('woocommerce_add_to_cart', array($this, 'webtracking_add_to_cart'));
			add_action( 'woocommerce_order_status_pending', array($this, 'webtracking_process_order'), 10, 1);
			add_action( 'woocommerce_order_status_on-hold', array($this, 'webtracking_process_order'), 10, 1);
			add_action( 'woocommerce_order_status_processing', array($this, 'webtracking_process_order'), 10, 1);
			add_action('woocommerce_order_status_completed', array($this, 'webtracking_process_order'), 10, 1);
		}

		function request_is_frontend_ajax() {

			$script_filename = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';

			$ajax_cart_en = ('yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ));
			if ($ajax_cart_en && isset($_REQUEST['wc-ajax'])) {
				return true;
			}
			//Try to figure out if frontend AJAX request... If we are DOING_AJAX; let's look closer
			else if((defined('DOING_AJAX') && DOING_AJAX))
			{
				$ref = '';
				if ( ! empty( $_REQUEST['_wp_http_referer'] ) )
					$ref = wp_unslash( $_REQUEST['_wp_http_referer'] );
				elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) )
					$ref = wp_unslash( $_SERVER['HTTP_REFERER'] );

				//If referer does not contain admin URL and we are using the admin-ajax.php endpoint, this is likely a frontend AJAX request
				if(((strpos($ref, admin_url()) === false) && (basename($script_filename) === 'admin-ajax.php')))
					return true;
			}

			//If no checks triggered, we end up here - not an AJAX request.
			return false;
		}

		public function includes() {
			include_once 'includes/SwiftAPI/SwiftAPI.php';
			include_once 'includes/libXML/xml.php';
			include_once 'includes/SwiftAPI/SwiftAPI_Request_Ping.php';

		}

		function webtracking_process_order($order_id){
			if (get_option(self::OPTION_API_KEY)) {
				$order = new WC_Order( $order_id );
				$items = $order->get_items();
				$_product = array();

				$_pf = new WC_Product_Factory();

				foreach ($items as $item) {
					$product = $_pf->get_product($item['product_id']);
					$_product[] = new SwiftAPI_Product($item['product_id'], $item['qty'], wc_get_price_including_tax($product));
				}

				$_SESSION[self::SWIFTERM_ORDER_NAME] = base64_encode(serialize(new SwiftAPI_Request_Order($_SERVER['HTTP_HOST'], $this->generate_user_id(), $this->get_swifterm_email($order->get_billing_email()), $order->get_billing_first_name(), $order->get_billing_last_name(), $_product, $order_id, null, null, $order->get_date_completed(), get_option(self::OPTION_API_KEY))));
			}
		}

		function webtracking_footer(){

			if (get_option(self::OPTION_API_KEY)) {

				if (!$this->request_is_frontend_ajax()) {
				    if (version_compare(PHP_VERSION, '7.1.0', '<'))
				    {            
					if (isset($_SESSION[self::SWIFTERM_ORDER_NAME])) {
						$script = SwiftAPI::Script(unserialize(base64_decode($_SESSION[self::SWIFTERM_ORDER_NAME])), hex2bin(get_option(self::OPTION_API_KEY)));
						unset($_SESSION[self::SWIFTERM_ORDER_NAME]);
						echo $script;
					}
					else if (isset($_SESSION[self::SWIFTERM_CART_NAME])) {
						$script = SwiftAPI::Script(unserialize(base64_decode($_SESSION[self::SWIFTERM_CART_NAME])), hex2bin(get_option(self::OPTION_API_KEY)));
						unset($_SESSION[self::SWIFTERM_CART_NAME]);
						echo $script;
					}
					else if (is_front_page()) {
						$request = new SwiftAPI_Request_Home($_SERVER['HTTP_HOST'], $this->generate_user_id(), $_SERVER['REQUEST_URI'], $this->get_swifterm_email(), NULL, NULL, get_option(self::OPTION_API_KEY));
						echo SwiftAPI::Script($request, hex2bin(get_option(self::OPTION_API_KEY)));
					}
					else if (is_home()) {
						$request = new SwiftAPI_Request_Home($_SERVER['HTTP_HOST'], $this->generate_user_id(), $_SERVER['REQUEST_URI'], $this->get_swifterm_email(), NULL, NULL, get_option(self::OPTION_API_KEY));
						echo SwiftAPI::Script($request, hex2bin(get_option(self::OPTION_API_KEY)));
					}
				    }
				    else
				    {
					if (isset($_SESSION[self::SWIFTERM_ORDER_NAME])) {
						$script = SwiftAPI::Script_No_Encryption(unserialize(base64_decode($_SESSION[self::SWIFTERM_ORDER_NAME])), hex2bin(get_option(self::OPTION_API_KEY)));
						unset($_SESSION[self::SWIFTERM_ORDER_NAME]);
						echo $script;
					}
					else if (isset($_SESSION[self::SWIFTERM_CART_NAME])) {
						$script = SwiftAPI::Script_No_Encryption(unserialize(base64_decode($_SESSION[self::SWIFTERM_CART_NAME])), hex2bin(get_option(self::OPTION_API_KEY)));
						unset($_SESSION[self::SWIFTERM_CART_NAME]);
						echo $script;
					}
					else if (is_front_page()) {
						$request = new SwiftAPI_Request_Home($_SERVER['HTTP_HOST'], $this->generate_user_id(), $_SERVER['REQUEST_URI'], $this->get_swifterm_email(), NULL, NULL, get_option(self::OPTION_API_KEY));
						echo SwiftAPI::Script_No_Encryption($request, hex2bin(get_option(self::OPTION_API_KEY)));
					}
					else if (is_home()) {
						$request = new SwiftAPI_Request_Home($_SERVER['HTTP_HOST'], $this->generate_user_id(), $_SERVER['REQUEST_URI'], $this->get_swifterm_email(), NULL, NULL, get_option(self::OPTION_API_KEY));
						echo SwiftAPI::Script_No_Encryption($request, hex2bin(get_option(self::OPTION_API_KEY)));
					}
				    }				    

				}

			}

		}

		function webtracking_add_to_cart() {
			global $woocommerce;

			if (get_option(self::OPTION_API_KEY)) {

				if ( sizeof( WC()->cart->get_cart() ) > 0 ) {

					$_product = array();
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
						$_product[] = new SwiftAPI_Product($values['product_id'], $values['quantity'], wc_get_price_including_tax($values['data']));
					}
					$_SESSION[self::SWIFTERM_CART_NAME] = base64_encode(serialize(new SwiftAPI_Request_Cart($_SERVER['HTTP_HOST'], $this->generate_user_id(), $_product, $this->get_swifterm_email(), NULL, NULL, get_option(self::OPTION_API_KEY))));
				}

			}
		}

		function webtracking_product() {
			global $post;
			if (get_option(self::OPTION_API_KEY)) {
				$request = new SwiftAPI_Request_Product($_SERVER['HTTP_HOST'], $this->generate_user_id(), $post->ID, $this->get_swifterm_email(), NULL, NULL, get_option(self::OPTION_API_KEY));
				if (version_compare(PHP_VERSION, '7.1.0', '<'))
				    echo SwiftAPI::Script($request, hex2bin(get_option(self::OPTION_API_KEY)));
				else
				    echo SwiftAPI::Script_No_Encryption($request, hex2bin(get_option(self::OPTION_API_KEY)));				
			}
		}

		public function generate_user_id($refresh = false) {
			if (!isset($_SESSION['swifterm_user_id']) || $refresh) {
				$_SESSION['swifterm_user_id'] = SwiftAPI::UserID();
			}
			return $_SESSION['swifterm_user_id'];
		}

		public function get_swifterm_email($email = null) {

			if (!isset($_SESSION['swifterm_email']) || !is_null($email)) {
				$_SESSION['swifterm_email'] = $email;
			}
			return isset($_SESSION['swifterm_email']) ? $_SESSION['swifterm_email'] : '';
		}

		public static function activate() {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			global $wpdb;

			$q = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}". self::SWIFTERM_ORDER_TABLE."` (
				    `entity_id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				    `swift_order_id` int(10) unsigned NOT NULL,
				    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
			    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

			dbDelta($q);

			// make sure to start clean without old information remaining from after previous deactivation
			$wpdb->query("DELETE FROM {$wpdb->prefix}swift_orders");
		}

		public static function uninstall() {
			global $wpdb;
			$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}". self::SWIFTERM_ORDER_TABLE."`");
		}

		public function admin_init() {
			register_setting('swifterm', self::OPTION_API_KEY, array($this, 'swifterm_api_changed'));
			add_settings_section('swifterm_main', '', array($this, 'settings_section'), __FILE__);
			add_settings_field(self::OPTION_API_KEY, 'SwiftERM Key', array($this, 'setting_swifterm_api'), __FILE__, 'swifterm_main');
		}

		public function load_wp_admin_styles() {
			wp_register_style('swifterm', plugins_url('swifterm/assets/css/swifterm.css'));
			wp_enqueue_style('swifterm');
		}

		public function add_menu() {
			add_options_page('SwiftERM API Settings', 'SwiftERM API Settings', 'manage_options', 'swifterm', array($this, 'plugin_settings_page'));
		}

		public function plugin_settings_page() {
			if (!current_user_can('manage_options')) {
				wp_die(__('you do not have sufficient permissions to access this page.'));
			}

			echo '<div class="wrap">';
			echo '<form method="post" action="options.php">';
			settings_fields('swifterm');
			do_settings_sections(__FILE__);
			@submit_button();
			echo '</form>';
			echo '</div>';
		}

		function settings_section() {
			include (sprintf("%s/templates/settings.php", dirname(__FILE__)));
		}

		function setting_swifterm_api() {
			$d = self::OPTION_API_KEY;
			$id = get_option($d);
			echo "<input type='text' maxlength='64' name='{$d}' id='{$d}' value='{$id}' />";
		}

		function swifterm_api_changed($input) {

			$input = trim($input);
			// we need to check if it is 64 chars long
			// and whether it is a hexidecimal string
			if (!(ctype_xdigit($input) && strlen($input) == 64)) {
				add_settings_error('general', 'settings_updated', __('Invalid string input.'), 'error');
				$input = '';
			}
			else {
				try {
					$this->swift_ping_swifterm($input);
				}
				catch (Exception $ex) {
					add_settings_error('general', 'settings_updated', __('An error occured confirming your key. Please contact support'), 'error');
					$input = '';
				}
			}
			return $input;
		}

		private function swift_ping_swifterm($key) {
			$domain = $_SERVER['HTTP_HOST'];
			$user = SwiftApi::UserID();
			if (version_compare(PHP_VERSION, '7.1.0', '<'))
			    $url = 'https:'.SwiftApi::SWIFTAPI_CRM_URL;
			else
			    $url = 'https:'.SwiftApi::SWIFTAPI_V4_CRM_URL;
			
			$request = new SwiftAPI_Request_Ping($domain, $user, $key);
			if (swifterm::_is_curl()) {
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/x-www-form-urlencoded"));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_POST, 1);
				
				if (version_compare(PHP_VERSION, '7.1.0', '<'))
				    curl_setopt($curl, CURLOPT_POSTFIELDS, SwiftAPI::Query($request, hex2bin($key)));
				else
				    curl_setopt($curl, CURLOPT_POSTFIELDS, SwiftAPI::Query_No_Encryption($request, hex2bin($key)));
				
				$result = curl_exec($curl);
			}
			else {
				if (version_compare(PHP_VERSION, '7.1.0', '<'))
			        {        
				    $options = array (
					'https' => array(
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'method'  => 'POST',
						'content' => SwiftAPI::Query($request, hex2bin($key))
					)
				    );
				}
				else
				{
				    $options = array (
					'https' => array(
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'method'  => 'POST',
						'content' => SwiftAPI::Query_No_Encryption($request, hex2bin($key))
					)
				    );
				}

				$context  = stream_context_create($options);
				$result = file_get_contents($url, false, $context);
			}

			return $result;
		}

	}

endif;

if (class_exists('swifterm')) {
	register_activation_hook(__FILE__, array('swifterm', 'activate'));
	register_uninstall_hook(__FILE__, array('swifterm', 'uninstall'));

	$swifterm = new swifterm();
}
