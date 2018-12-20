<?php

/**
 * Class PEAE_Common
 * Handles Common Functions For Admin as well as front end interface
 */
class PEAE_Common {

    public static $ins = null;
    public static $http = null;

    public static function init() {
        
    }

    public static function get_instance() {
        if (null == self::$ins) {
            self::$ins = new self;
        }

        return self::$ins;
    }

    public static function http() {
        if (self::$http == null) {
            self::$http = new WP_Http();
        }

        return self::$http;
    }

    /**
     * Send remote call
     *
     * @param $api_url
     * @param $data
     * @param string $method_type
     *
     * @return array|mixed|null|object|string
     */
    public static function send_remote_call($api_url, $data, $method_type = 'post') {
        if ('get' == $method_type) {
            $httpPostRequest = self::http()->get($api_url, array(
                'body' => $data,
                'sslverify' => false,
                'timeout' => 30,
            ));
        } else {
            $httpPostRequest = self::http()->post($api_url, array(
                'body' => $data,
                'sslverify' => false,
                'timeout' => 30,
            ));
        }

        if (isset($httpPostRequest->errors)) {
            $response = null;
        } elseif (isset($httpPostRequest['body']) && '' != $httpPostRequest['body']) {
            $body = $httpPostRequest['body'];
            $response = json_decode($body, true);
        } else {
            $response = 'No result';
        }

        return $response;
    }

    public static function array_flatten($array) {
        if (!is_array($array)) {
            return false;
        }
        $result = iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($array)), false);

        return $result;
    }

    public static function pr($arr) {
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }

    public static function get_product_id_hash($coupon_id, $offer_id, $product_id) {
        if ($coupon_id == 0 || $offer_id == 0 || $product_id == 0) {
            return md5(time());
        }

        $unique_multi_plier = $coupon_id * $offer_id;
        $unique_key = ( $unique_multi_plier * $product_id ) . time();
        $hash = md5($unique_key);

        return $hash;
    }

    public static function get_variation_attribute($variation) {
        if (is_a($variation, 'WC_Product_Variation')) {
            $variation_attributes = $variation_attributes_basic = $variation->get_attributes();
            //          $variation_attributes       = array();
            //          foreach ( $variation_attributes_basic as $key => $value ) {
            //              $variation_attributes[ wc_attribute_label( $key, $variation ) ] = $value;
            //          }
        } else {
            $variation_attributes = array();
            if (is_array($variation)) {
                foreach ($variation as $key => $value) {
                    $variation_attributes[str_replace('attribute_', '', $key)] = $value;
                }
            }
        }

        return ( $variation_attributes );
    }

    public static function get_formatted_product_name($product) {
        $formatted_variation_list = self::get_variation_attribute($product);

        $arguments = array();
        if (!empty($formatted_variation_list) && count($formatted_variation_list) > 0) {
            foreach ($formatted_variation_list as $att => $att_val) {
                if ($att_val == '') {
                    $att_val = __('any');
                }
                $att = strtolower($att);
                $att_val = strtolower($att_val);
                $arguments[] = "$att: $att_val";
            }
        }

        return sprintf('%s (#%d) %s', $product->get_title(), $product->get_id(), ( count($arguments) > 0 ) ? '(' . implode(',', $arguments) . ')' : '');
    }

    public static function search_products($term, $include_variations = false) {
        global $wpdb;
        $like_term = '%' . $wpdb->esc_like($term) . '%';
        $post_types = $include_variations ? array('product', 'product_variation') : array('product');
        $post_statuses = current_user_can('edit_private_products') ? array(
            'private',
            'publish'
                ) : array('publish');
        $type_join = '';
        $type_where = '';

        $product_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT DISTINCT posts.ID FROM {$wpdb->posts} posts
				LEFT JOIN {$wpdb->postmeta} postmeta ON posts.ID = postmeta.post_id
				$type_join
				WHERE (
					posts.post_title LIKE %s
					OR (
						postmeta.meta_key = '_sku' AND postmeta.meta_value LIKE %s
					)
				)
				AND posts.post_type IN ('" . implode("','", $post_types) . "')
				AND posts.post_status IN ('" . implode("','", $post_statuses) . "')
				$type_where
				ORDER BY posts.post_parent ASC, posts.post_title ASC", $like_term, $like_term));

        if (is_numeric($term)) {
            $post_id = absint($term);
            $post_type = get_post_type($post_id);

            if ('product_variation' === $post_type && $include_variations) {
                $product_ids[] = $post_id;
            } elseif ('product' === $post_type) {
                $product_ids[] = $post_id;
            }

            $product_ids[] = wp_get_post_parent_id($post_id);
        }

        return wp_parse_id_list($product_ids);
    }

    public static function slugify_classname($class_name) {
        $classname = sanitize_title($class_name);
        $classname = str_replace('_', '-', $classname);

        return $classname;
    }

    /**
     * Recursive Un-serialization based on   WP's is_serialized();
     *
     * @param $val
     *
     * @return mixed|string
     * @see is_serialized()
     */
    public static function unserialize_recursive($val) {
        //$pattern = "/.*\{(.*)\}/";
        if (is_serialized($val)) {
            $val = trim($val);
            $ret = unserialize($val);
            if (is_array($ret)) {
                foreach ($ret as &$r) {
                    $r = self::unserialize_recursive($r);
                }
            }

            return $ret;
        } elseif (is_array($val)) {
            foreach ($val as &$r) {
                $r = self::unserialize_recursive($r);
            }

            return $val;
        } else {
            return $val;
        }
    }

    public static function maybe_filter_boolean_strings($options) {
        $cloned_option = $options;
        foreach ($options as $key => $value) {

            if (is_object($options)) {

                if ($value === 'true' || $value === true) {

                    $cloned_option->$key = true;
                }

                if ($value === 'false' || $value === false) {
                    $cloned_option->$key = false;
                }
            } elseif (is_array($options)) {

                if ($value === 'true' || $value === true) {

                    $cloned_option[$key] = true;
                }
                if ($value === 'false' || $value === false) {
                    $cloned_option[$key] = false;
                }
            }
        }

        return $cloned_option;
    }

    public static function get_date_format() {
        return get_option('date_format', '') . ' ' . get_option('time_format', '');
    }

    public static function clean_ascii_characters($content) {

        if ('' == $content) {
            return $content;
        }

        $content = str_replace('%', '_', $content);
        $content = str_replace('!', '_', $content);
        $content = str_replace('\"', '_', $content);
        $content = str_replace('#', '_', $content);
        $content = str_replace('$', '_', $content);
        $content = str_replace('&', '_', $content);
        $content = str_replace('(', '_', $content);
        $content = str_replace(')', '_', $content);
        $content = str_replace('(', '_', $content);
        $content = str_replace('*', '_', $content);
        $content = str_replace(',', '_', $content);
        $content = str_replace('', '_', $content);
        $content = str_replace('.', '_', $content);
        $content = str_replace('/', '_', $content);

        return $content;
    }

    public static function generate_log($data) {
        $log = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL . "Webhook: " . print_r($data, true) . PHP_EOL . "-------------------------" . PHP_EOL;
        file_put_contents(ABSPATH . '/log_.txt', $log, FILE_APPEND);
    }

}
