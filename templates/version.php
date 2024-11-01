<?php
if('/wp-content/plugins/swifterm/templates/version.php' == $_SERVER['REQUEST_URI']);
{
	define('WP_USE_THEMES', false);
	require_once(__DIR__ . '/../../../../wp-blog-header.php');
	require_once plugin_dir_path(__FILE__).'../includes/class-swifterm-version-information.php';
	new Swifterm_Version_Information();
}

