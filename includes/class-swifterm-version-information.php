<?php

class Swifterm_Version_Information {
	
	private $swifterm_data;
	
	public function __construct() {
		$this->swifterm_data = array();
		$this->get_site_info();
		$this->get_plugins_info();
		$this->get_theme_info();
		$this->output_data();
	}
	
	private function output_data() {
		
		$key = hex2bin(get_option(swifterm::OPTION_API_KEY));
		
		if (!is_bool($key) && !is_null($key)) {
			$api_version = new SwiftAPI_Request_Version($_SERVER['HTTP_HOST'], $this->swifterm_data);
			if (version_compare(PHP_VERSION, '7.1.0', '<'))
			    echo SwiftAPI::Encode($api_version, hex2bin(get_option(swifterm::OPTION_API_KEY)));
			else
			    echo SwiftAPI::Encode_No_Encryption($api_version, hex2bin(get_option(swifterm::OPTION_API_KEY)));			
		}
		else {
			echo json_encode($this->swifterm_data);
		}
	}
	
	private function get_site_info() {
		
		global $wp_version;
		
		$this->swifterm_data['https'] = false;
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			$this->swifterm_data['https'] = true;
		}
		
		$this->swifterm_data['plugin_version'] = swifterm::VERSION;
		$this->swifterm_data['host'] =  $_SERVER['HTTP_HOST'];
		$this->swifterm_data['wordpress_version'] =  $wp_version;
		$this->swifterm_data['php'] = phpversion();
	}
	
	private function get_theme_info() {
		$this->swifterm_data['themes'] = wp_get_theme()->get('Name');;
	}
	
	private function get_plugins_info() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = array_keys(get_plugins());
		$active_plugins = get_option('active_plugins');
		$plugin_output = array();
		
		foreach($all_plugins as $plugin_name) {
			if (array_search($plugin_name, $active_plugins) !== false) {
				$plugin_output[$plugin_name] = 'yes';
			}
			else {
				$plugin_output[$plugin_name] = 'no';
			}
		}
		
		$this->swifterm_data['modules'] = $plugin_output;
	}
	
	
	
}
