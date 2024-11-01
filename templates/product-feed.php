<?php
if('/wp-content/plugins/swifterm/templates/product-feed.php' == $_SERVER['REQUEST_URI']);
{
	define('WP_USE_THEMES', false);
	require_once(__DIR__ . '/../../../../wp-blog-header.php');
	require_once plugin_dir_path(__FILE__).'../includes/class-swifterm-product-feed.php';
	$class_swifterm_product_feed = new Swifterm_Product_Feed_Generator();
	if (isset($_GET['offset'])) {
		$class_swifterm_product_feed->set_offset($_GET['offset']);
	}
	if (isset($_GET['limit'])) {
		$class_swifterm_product_feed->set_limit($_GET['limit']);
	}
	$class_swifterm_product_feed->generate_xml();
}