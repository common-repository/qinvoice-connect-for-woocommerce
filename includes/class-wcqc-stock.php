<?php
/**
 * XML Export class
 */
if (!class_exists('WooCommerce_Qinvoice_Connect_Stock')) {
    class Woocommerce_Qinvoice_Connect_Stock
    {
        public $error;
        public $json;

        /**
         * Constructor
         */
        public function __construct()
        {
            global $woocommerce;
            //$this->order = new WC_Order();

            //add_action( 'wp_ajax_generate_wcqc', array($this, 'process_request_ajax' ));
        }

        public function update($params, $secret)
        {
            global $wpdb;

            if ($params['sku'] == '') {
                $this->error = __('SKU is missing');
                return false;
            }

            $string = 'sku=' . $params['sku'] . '|qty=' . $params['qty'];
            if (md5($string . $secret) != $params['check']) {
                $this->error =
                    __('Incorrect checksum. Check your secret key.');
                return false;
            }

            $id = wc_get_product_id_by_sku($params['sku']);

            if ($id == 0) {
                $this->error = __('Product not found: ' . $params['sku']);
                return false;
            }

            $sql = "UPDATE $wpdb->postmeta SET meta_value = '" .
                $params['qty'] . "' WHERE post_id = '" . $id . "' and meta_key = '_stock'";

            if ($wpdb->query($sql) === false) {
                $this->error = __('Error executing query');
                return false;
            } else {
                return true;
            }
        }

        public function export($params, $secret)
        {
            $i                 = 0;
            $full_product_list = array();
            $loop              = new WP_Query(array('post_type' => array('product', 'product_variation'),
                'posts_per_page'                                    => -1,
            ));

            $string = 'store_id=' . $params['store_id'];
            if (md5($string . $secret) != $params['check']) {
                $this->error =
                    __('Incorrect checksum. Check your secret key.');
                return false;
            }

            while ($loop->have_posts()): $loop->the_post();

                $theid = get_the_ID();
                try {
                    $product = new WC_Product($theid);
                } catch (Exception $e) {
                    continue;
                }
                $attr = array();

                // its a variable product
                if (get_post_type() == 'product_variation') {
                    $parent_id  = wp_get_post_parent_id($theid);
                    $sku        = get_post_meta($theid, '_sku', true);
                    $stock      = get_post_meta($theid, '_stock', true);
                    $price      = get_post_meta($theid, '_price', true);
                    $thetitle   = get_the_title($parent_id);
                    $attr_array = array();
                    foreach (get_post_meta($theid, '', false) as $name =>
                    $data) {
                        if (substr($name, 0, 10) == 'attribute_') {
                            $thetitle = $data[0];
                        }
                        // stop after first value
                        if (count($attr_array) > 0) {
                            break;
                        }
                    }

                    $product_array[$i]['price_with'] = wc_get_price_including_tax($product); //($product->get_price_including_tax());

                    // ****************** end error checking *****************

                    // its a simple product
                } else {
                    $sku      = get_post_meta($theid, '_sku', true);
                    $thetitle = get_the_title();
                    $stock    = get_post_meta($theid, '_stock', true);
                    $price    = get_post_meta($theid, '_price', true);
                    $attr_array = array();

                }
                // add product to array but don't add the parent of product variations
                if (!empty($sku)) {
                    $product_array[$i]['sku']        = $sku;
                    $product_array[$i]['stock']      = $stock;
                    $product_array[$i]['price']      = $price;
                    $product_array[$i]['price_with'] = wc_get_price_including_tax($product); //$product->get_price_including_tax();
                    $product_array[$i]['vat'] = $product->get_tax_class();
                    $product_array[$i]['title'] = $thetitle;
                    $product_array[$i]['id']    = $theid;
                    $product_array[$i]['attr']  = $attr_array;

                    if (function_exists('the_post_thumbnail_url') &&
                    function_exists('wp_get_attachment_image_src')) {
                        $img =
                            wp_get_attachment_image_src(get_post_thumbnail_id($theid), 'full');
                        $thumb = $img[0];
                    } else {
                        $thumb = false;
                    }

                    $product_array[$i]['thumbnail'] = $thumb;

                }

                $i++;

            endwhile;

            wp_reset_query();
            // sort into alphabetical order, by title
            //sort($full_product_list);
            $this->json = json_encode($product_array);
            //print_r( $product_array );
            wp_reset_postdata();
            return true;
        }

    }
}
