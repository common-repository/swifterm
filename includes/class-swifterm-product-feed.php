<?php

/**
 * Description of class-swift-product-feed
 *
 * @author daniel
 */
class Swifterm_Product_Feed_Generator {
	
	const SWIFT_XML_PRODUCT_VERSION = 2;

	protected $offset;
	
	protected $limit;
	
	public function __construct() {
		$this->offset = 0;
		$this->limit = 100;
	}
	
	public function set_offset($offset = 0) {
		if (is_numeric($offset)) {
			$this->offset = $offset;
		}
	}
	
	public function set_limit($limit = 100) {
		if (is_numeric($limit)) {
			$this->limit = $limit;
		}
	}
	
	/**
	 * Retrieves product details and generates the appropriate response to the request
	 *
	 */
	public function generate_xml() {
		
		$xml = new xml();
		$xmlRow = array();
		$today = time();
		$args = array( 'post_type' => 'product', 'posts_per_page' => $this->limit, 'offset' => $this->offset);
		$loop = new WP_Query( $args );
		
		while ( $loop->have_posts() ) : $loop->the_post();
		
			global $product;
			
			$post_meta = get_post_meta($product->id);
			
			if ($post_meta['_stock_status'][0] == 'instock') {
				
				$tempXml = array();
				$method = 'g:id';
				$tempXml[] = $xml -> $method(base64_encode($product->id));
				$tempXml[] = $xml -> title(base64_encode(htmlspecialchars($product->post->post_title, ENT_QUOTES)));
				$tempXml[] = $xml -> description(base64_encode(htmlspecialchars($product->post->post_content, ENT_QUOTES)));
				$tempXml[] = $xml -> short_description(base64_encode(htmlspecialchars($product->post->post_excerpt, ENT_QUOTES)));
				$tempXml[] = $xml -> link(base64_encode(get_permalink()));
				// featured image
				$method = 'g:image_link';
				$tempXml[] = $xml -> $method(base64_encode(wp_get_attachment_image_src( $post_meta['_thumbnail_id'][0], 'single-post-thumbnail' )[0]));
				$attachment_ids = $product->get_gallery_attachment_ids();
				$method = 'g:small_image_link';
				$tempXml[] = $xml -> $method(base64_encode(isset($attachment_ids[0]) ? wp_get_attachment_url( $attachment_ids[0] ) : ''));
				$method = 'g:additional_image_link';
				$tempXml[] = $xml -> $method(base64_encode(isset($attachment_ids[1]) ? wp_get_attachment_url( $attachment_ids[1] ) : ''));
				$method = 'g:price';
				$tempXml[] = $xml -> $method(base64_encode(wc_get_price_including_tax($product)));
				$method = 'g:sale_price';
				
				$special_price = '';
				if ($product->is_on_sale() && $product->get_regular_price()) {
					$special_price = wc_get_price_including_tax($product);
				}
				
				$tempXml[] = $xml -> $method(base64_encode($special_price));
				$product_cats = wp_get_post_terms( $product->id, 'product_cat' );
				$tempXml[] = $xml -> subcategory(base64_encode(isset($product_cats[count($product_cats) - 1]) ? htmlspecialchars($product_cats[count($product_cats) - 1]->name, ENT_QUOTES) : ''));
				$tempXml[] = $xml -> parentcategory(base64_encode(isset($product_cats[count($product_cats) - 2]) ? htmlspecialchars($product_cats[count($product_cats) - 2]->name, ENT_QUOTES) : ''));
				$tempXml[] = $xml -> sku(base64_encode(is_null($post_meta['_sku'][0]) ? null : htmlspecialchars($post_meta['_sku'][0], ENT_QUOTES)));
				$xmlRow[] = $xml -> product(implode($tempXml));
				
			}
			
		endwhile;
		
		wp_reset_query();
		header("HTTP/1.0 200 OK");
		header('Content-Type: application/xml; charset=utf-8');
		echo '<?xml version="1.0" encoding="UTF-8"?>'. "\n" . $xml -> urlset($xml->version(self::SWIFT_XML_PRODUCT_VERSION).$xml -> products(implode($xmlRow)), array('xmlns:g' => "http://base.google.com/ns/1.0"));
		die();
	}
	
}
