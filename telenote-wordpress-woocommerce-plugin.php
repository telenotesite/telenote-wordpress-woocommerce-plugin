<?php
/**
 * Plugin Name: Telenote Wordpress Plugin for WooCommerce
 * Plugin URI: https://www.telenote.site/#/plugins/telenote-wordpress-woocommerce-plugin/
 * Description: Telenote Wordpress Plugin for WooCommerce allows you to send notifications to site users via telegram
 * Version: 1.0.0
 * Requires at least: 5.3
 * Requires PHP: 7.0
 * Author: telenote
 * Author URI: https://www.telenote.site
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: telenote-plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    require_once WP_CONTENT_DIR . '/plugins/telenote-wordpress-woocommerce-plugin/telenote_api.php';
    require_once WP_CONTENT_DIR . '/plugins/telenote-wordpress-woocommerce-plugin/telenote_utils.php';
    require_once WP_CONTENT_DIR . '/plugins/telenote-wordpress-woocommerce-plugin/telenote_woocommerce.php';

    /**
     * Add the field to the checkout
     */
    add_action('woocommerce_review_order_before_submit', 'telenote_custom_checkout_field');
    add_action( 'woocommerce_checkout_update_order_meta', 'telenote_save_custom_checkout_field');

    function _view_telenote_field_link($link)
    {
        $checkout = WC_Checkout::instance();
        $key = 'telenote_woocommerce_checkout__use_telenote_field';
        $value = $checkout->get_value($key);

        $radio = woocommerce_form_field(
            $key,
            array(
                'type' => 'radio',
                'options' => array(
                    'yes' => __('Yes, send notifications in my Telegram. ', 'telenote-plugin'),
                    'no' => __('No, thanks.', 'telenote-plugin'),
                ),
                'return' => true,
            ),
            empty($value) ? 'yes' : $value
        );

        echo '<div class="view_telenote_field_link">';
            echo '<h3>' . __('Do you want to receive notifications in Telegram?', 'telenote-plugin') . '</h3>';
            echo $radio;
            echo '<div>';
                echo __('If yes, please click on this link before place order ', 'telenote-plugin')
                . '<a target="_blank" href="' . $link . '">' . $link . '</a>'
                . __(' to subscribe to our Telegram bot.', 'telenote-plugin');
            echo '</div>';
        echo '</div>';
        echo '<br/>';
    }

    function _view_telenote_field_error($errorMessage)
    {
        echo '<div class="view_telenote_field_error">';
            echo '<h3>' . __('Error when using Telenote plugin.', 'telenote-plugin') . '</h3>';
            echo '<div>';
                echo $errorMessage;
            echo '</div>';
        echo '</div>';
        echo '<br/>';
    }

    function telenote_custom_checkout_field()
    {
        try {
            $current_user = wp_get_current_user();

            # Предлагаем ссылку только тем пользователям которые вошли в систему
            if (empty($current_user->ID)) {
                return;
            }

            $link_id = telenote_utils_get_link_id($current_user->ID);

            # Если ссылка уже есть - провери дал ли юхер согласие на отпраку сообщений
            if (!empty($link_id)) {
                $user_meta_key = 'telenote_woocommerce_checkout__use_telenote_field';
                $user_meta = get_user_meta($current_user->ID, $user_meta_key);
                $user_meta_ok = count($user_meta) > 0 && $user_meta[0] == 'yes';
                if ($user_meta_ok) {
                    // не показываем ссылку если юзер уже связал аккаунт и дал согласие на получение сообщений
                    return;
                }
            }

            $api_token = get_option('telenote_api_token');

            if (empty($api_token)) {
                _view_telenote_field_error(__('Please set Telenote API Token in the settings.', 'telenote-plugin'));
                return;
            }

            $deep_link = telenote_utils_get_deep_link($api_token, $current_user->ID);

            if (!empty($deep_link)) {
                _view_telenote_field_link($deep_link);
            }

        } catch (Exception $e) {
            _view_telenote_field_error($e->getMessage());
        }
    }

    function telenote_save_custom_checkout_field()
    {
        $current_user = wp_get_current_user();
        if (empty($current_user->ID)) {
            return;
        }
        $key = 'telenote_woocommerce_checkout__use_telenote_field';
        if (array_key_exists($key, $_POST)){
            $value = $_POST[$key];
            update_user_meta($current_user->ID, $key, $value);
        }
    }
}
