<?php

/**
 * Class PEAE_AJAX_Controller
 * Handles All the request came from front end or the backend
 */
class PEAE_AJAX_Controller {

    public static function init() {
        self::handle_admin_ajax();
    }

    public static function handle_admin_ajax() {

        add_action('wp_ajax_peae_coupon_search', array(__CLASS__, 'coupon_search'));
    }

    /**
     * search woocommerce coupon generating options
     */
    public static function coupon_search() {
        if (empty($_POST['term'])) {
            wp_send_json_error(new \WP_Error('Bad Request'));
        }
        $results = [];
        $query_params = [
            'post_type' => 'shop_coupon',
            's' => $_POST['term'],
            'posts_per_page' => - 1,
        ];
        $query = new \WP_Query($query_params);
        if ($query->found_posts > 0) {
            foreach ($query->posts as $post) {
                $results[] = array(
                    'id' => $post->ID,
                    'coupon' => $post->post_title,
                );
            }
        }
        wp_send_json($results);
    }

}

PEAE_AJAX_Controller::init();
