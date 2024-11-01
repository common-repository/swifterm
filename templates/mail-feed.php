<?php

if('/wp-content/plugins/swifterm/templates/mail-feed.php' == $_SERVER['REQUEST_URI']);
{
	define('WP_USE_THEMES', false);
	require_once(__DIR__ . '/../../../../wp-blog-header.php');
	require_once plugin_dir_path(__FILE__).'../includes/class-swifterm-send-mail.php';
	$class_swifterm_send_mail = new Swifterm_Send_Mail();
	
}

