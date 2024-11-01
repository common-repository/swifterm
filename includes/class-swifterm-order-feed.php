<?php

class Swifterm_Order_Feed {
	
	public function __construct() {
		
		$swifterm = new swifterm();
		$key = hex2bin(get_option(swifterm::OPTION_API_KEY));
		
		if (!is_bool($key) && !is_null($key)) {
			
			$domain = $_SERVER['HTTP_HOST'];
			$user = $swifterm->generate_user_id();
			if (version_compare(PHP_VERSION, '7.1.0', '<'))
			    $url = 'https:'.SwiftApi::SWIFTAPI_CRM_URL;
			else
			    $url = 'https:'.SwiftApi::SWIFTAPI_V4_CRM_URL;			
			
			$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? $_GET['limit'] : 50;
			$offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? $_GET['offset'] : 0;
			$date = isset($_GET['date']) && strtotime($_GET['date']) ? $_GET['date'] : date('Y-m-d');
			
			global $wpdb;
			$table_name = $wpdb->prefix . swifterm::SWIFTERM_ORDER_TABLE;
			// this adds the prefix which is set by the user upon instillation of wordpress
			$cache_data = $wpdb->get_col( "SELECT `swift_order_id` FROM `".$table_name."`" );
	
			$args = array(
				'post_type'		=> 'shop_order',
				'post_status' 		=> array_keys( wc_get_order_statuses() ),
				'posts_per_page'	=> $limit,
				'offset'		=> $offset * $limit,
				'orderby'		=> 'id',
				'order'			=> 'DESC',
				'date_query'		=> array(
					'after'			=> $date,
					'inclusive'		=> true
				)
			);
			
			$loop = new WP_Query( $args );
			
			if ($loop->have_posts()) {
				
				$i = 0;
				
				while ( $loop->have_posts() ) : $loop->the_post();
					$i++;
					
					if (isset($_GET['skip']) || false === array_search($loop->post->ID, $cache_data)) {
					
						$order_id = $loop->post->ID;
						$order = new WC_Order($order_id);
						$items = $order->get_items();
						$_product = array();
						
						$_pf = new WC_Product_Factory();
						
						foreach ($items as $item) {
							$product = $_pf->get_product($item['product_id']);
							$_product = array();
							if($product){
							$_product[] = new SwiftAPI_Product($item['product_id'], $item['qty'], wc_get_price_including_tax($product));
                            }
						}
						
						$request = new SwiftAPI_Request_PastOrder($domain, $user, $order->billing_email, $order->billing_first_name, $order->billing_last_name, $_product, $order_id, null, null, $order->order_date, get_option(swifterm::OPTION_API_KEY));

						if (swifterm::_is_curl()) {
							$curl = curl_init();
							curl_setopt($curl, CURLOPT_URL, $url);
							curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
							curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/x-www-form-urlencoded"));
							curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($curl, CURLOPT_POST, 1);
							if (version_compare(PHP_VERSION, '7.1.0', '<'))
							    curl_setopt($curl, CURLOPT_POSTFIELDS, SwiftAPI::Query($request, $key));
							else
							    curl_setopt($curl, CURLOPT_POSTFIELDS, SwiftAPI::Query_No_Encryption($request, $key));							
							    
							$result = curl_exec($curl);
						}
						else {
						        if (version_compare(PHP_VERSION, '7.1.0', '<'))
						        {    
							    $options = array (
								'https' => array(
									'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
									'method'  => 'POST',
									'content' => SwiftAPI::Query($request, $key)
								)
							    );
							}
							else
							{
							    $options = array (
								'https' => array(
									'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
									'method'  => 'POST',
									'content' => SwiftAPI::Query_No_Encryption($request, $key)
								)
							    );
							}

							$context  = stream_context_create($options);
							$result = file_get_contents($url, false, $context);
						}
						
						if (isset($_GET['report']) && false === array_search($loop->post->ID, $cache_data)) {
							$wpdb->insert(
								$table_name,
								array('swift_order_id' => $loop->post->ID),
								array('%d')
							);
						}
						
					}

				endwhile;
				
				if ($limit > $i) {
					$response = array();
					$response['status'] = 3;
					$response['message'] = 'No more orders to send at this time, but has not fetched a full '. $limit;
				}
				else {
					$response = array();
					$response['status'] =  1; 
					$response['message'] = 'Past orders successfully sent to swift';
				}
				
			}
			else {
				$response = array();
				$response['status'] = 2;
				$response['message'] = 'No more orders to send at this time';
			}
			
		}
		else {
			$response = array();
			$response['status'] = 0;
			$response['message'] = 'You cannot perform this operation as you have not registered your private key with swift';
		}
		
		echo json_encode($response);
	}
	
}
