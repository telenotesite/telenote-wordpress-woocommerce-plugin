<?php
namespace telenote;

/**
 * Plugin Name: Telenote
 * Plugin URI: https://www.telenote.site/#/plugins/telenote-wordpress-woocommerce-plugin/
 * Description: Telenote plugin allows you to send notifications to site users via telegram
 * Version: 1.0.0
 * Requires at least: 5.3
 * Requires PHP: 5.6
 * Author: telenote
 * Author URI: https://www.telenote.site
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: telenote-plugin
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once 'telenote_utils.php';
require_once 'telenote_api_client.php';
require_once 'telenote_api_utils.php';

final class Telenote
{
    static public function entrypoint()
    {
        $telenote = new \telenote\Telenote();
    }

    const OPTION_API_TOKEN = 'telenote_api_token';
    const OPTION_PROJECT_ALIAS = 'telenote_project_alias';
    const OPTION_SEND_NEW_ADM = 'telenote_send_new_adm';
    const OPTION_SEND_NEW_CL = 'telenote_send_new_cl';
    const OPTION_NEW_ADM = 'telenote_new_adm';
    const OPTION_NEW_CL = 'telenote_new_cl';
    const OPTION_SEND_CHANGE_ADM = 'telenote_send_change_adm';
    const OPTION_SEND_CHANGE_CL = 'telenote_send_change_cl';
    const OPTION_CHANGE_ADM = 'telenote_change_adm';
    const OPTION_CHANGE_CL = 'telenote_change_cl';

    const YES = 'yes';
    const NO = 'no';
    const ADD = 'add';
    const POST = 'post';
    const ACTION = 'action';
    const ADMIN_PAGE_SLUG = 'telenote-settings';
    const USER_AGREEMENT_FIELD = 'telenote_woocommerce_checkout__use_telenote_field';

    /**
     * @var \telenote\Telenote
     */
    private static $instance;

    /**
     * @var \telenote\Utils
     */
    private $_utils;

    /**
     * @var \telenote\ApiClient
     */
    private $_apiClient;

    /**
     * @var \telenote\ApiUtils
     */
    private $_apiUtils;

    function __construct()
    {
        // Add menu item
        add_action('admin_menu', array($this, 'admin_menu'));

        // Handling an order status change event
        add_action('woocommerce_order_status_changed', array($this, 'telenote_new_order_and_change_status'), 10, 3);

        // Handling the order creation event
        add_action('woocommerce_checkout_order_processed', array($this, 'telenote_new_order_and_change_status'));

        // Checking account settings and displaying a message in the admin console
        add_action('admin_notices', array($this, 'check_telenote_account_settings'));

        /**
         * Add the field to the checkout
         */
        add_action('woocommerce_review_order_before_submit', array($this, 'telenote_custom_checkout_field'));

        /**
         * Save field value to user meta
         */
        add_action('woocommerce_checkout_update_order_meta', array($this, 'telenote_save_custom_checkout_field'));

        // Cleanup on plugin deactivation
        register_deactivation_hook(__FILE__, array($this, 'deactivation'));

        $this->load_plugin_text_domain();

        $this->_utils = new \telenote\Utils();
        $this->_apiClient = new \telenote\ApiClient();
        $this->_apiUtils = new \telenote\ApiUtils();

        self::$instance = $this;
    }

    /**
     * Get plugin instance
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Load plugin text domain
     */
    function load_plugin_text_domain()
    {
        $plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages'; /* Relative to WP_PLUGIN_DIR */
        load_plugin_textdomain( 'telenote-plugin', false, $plugin_rel_path );
    }

    /**
     * Checks the entered account settings and displays a banner warning that the plugin is not working
     * The message is displayed in the admin console
     *
     * DEV NOTE: when editing a function, you also need to check the function check_telenote_account_settings_plugin_page
     */
    function check_telenote_account_settings()
    {
        global $pagenow;

        if ($pagenow !== 'index.php') {
            return;
        }

        $response = $this->_apiUtils->call_api_account_info();

        if (empty($response)) {
            return;
        }

        if ($this->_utils->is_response_not_ok($response)) {
            $class = 'notice notice-error';
            $message = __('Telenote plugin warning! An error has occurred. Please check settings.', 'telenote-plugin');

            printf(
                '<div class="%1$s">
                    <p>%2$s</p>
                    <p><pre>%3$s</pre></p>
                    <p><a href="/wp-admin/admin.php?page=%4$s">Fix plugin settings</a></p>
                </div>',
                esc_attr($class),
                esc_html($message),
                esc_html($response['body']),
                self::ADMIN_PAGE_SLUG,
            );
        }
    }

    /**
     * Checks the entered account settings and displays a banner warning that the plugin is not working
     * The message is displayed on the plugin settings page
     *
     * DEV NOTE: when editing a function, you also need to check the function check_telenote_account_settings
     */
    function check_telenote_account_settings_plugin_page()
    {
        $response = $this->_apiUtils->call_api_account_info();

        if (empty($response)) {
            return;
        }

        if ($this->_utils->is_response_not_ok($response)) {
            $class = 'notice notice-error';
            $message = __('Telenote plugin warning! An error has occurred. Please check settings.', 'telenote-plugin');

            printf(
                '<div class="%1$s">
                    <p>%2$s</p>
                    <p><pre>%3$s</pre></p>
                </div>',
                esc_attr($class),
                esc_html($message),
                esc_html($response['body']),
            );
        }
    }

    /**
     * Plugin deactivation hook
     */
    function deactivation()
    {
        delete_option(self::OPTION_API_TOKEN);
        delete_option(self::OPTION_PROJECT_ALIAS);
        delete_option(self::OPTION_SEND_NEW_ADM);
        delete_option(self::OPTION_SEND_NEW_CL);
        delete_option(self::OPTION_NEW_ADM);
        delete_option(self::OPTION_NEW_CL);
        delete_option(self::OPTION_SEND_CHANGE_ADM);
        delete_option(self::OPTION_SEND_CHANGE_CL);
        delete_option(self::OPTION_CHANGE_ADM);
        delete_option(self::OPTION_CHANGE_CL);
    }

    /**
     * Admin menu hook
     */
    function admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Telenote plugin settings', 'telenote-plugin'),
            __('Telenote plugin settings', 'telenote-plugin'),
            'manage_woocommerce',
            self::ADMIN_PAGE_SLUG,
            array($this, 'options_page')
        );
    }

    /**
     * GET/POST handler for the plugin settings page
     */
    function options_page()
    {
        if (isset($_GET[self::ACTION]) && $_GET[self::ACTION] == self::ADD) {
            update_option(self::OPTION_API_TOKEN, sanitize_text_field($_POST['api_token']));
            update_option(self::OPTION_PROJECT_ALIAS, sanitize_text_field($_POST['project_alias']));
            update_option(self::OPTION_SEND_NEW_ADM, array_key_exists('send_new_adm', $_POST) && $_POST['send_new_adm'] == 'on' ? 1 : 0);
            update_option(self::OPTION_SEND_NEW_CL, array_key_exists('send_new_cl', $_POST) && $_POST['send_new_cl'] == 'on' ? 1 : 0);
            update_option(self::OPTION_NEW_ADM, sanitize_textarea_field($_POST['new_adm']));
            update_option(self::OPTION_NEW_CL, sanitize_textarea_field($_POST['new_cl']));
            update_option(self::OPTION_SEND_CHANGE_ADM, array_key_exists('send_change_adm', $_POST) && $_POST['send_change_adm'] == 'on' ? 1 : 0);
            update_option(self::OPTION_SEND_CHANGE_CL, array_key_exists('send_change_cl', $_POST) && $_POST['send_change_cl'] == 'on' ? 1 : 0);
            update_option(self::OPTION_CHANGE_ADM, sanitize_textarea_field($_POST['change_adm']));
            update_option(self::OPTION_CHANGE_CL, sanitize_textarea_field($_POST['change_cl']));

            $result = __('Telenote Plugin Settings updated.', 'telenote-plugin');
        }

        $api_token = get_option(self::OPTION_API_TOKEN);
        $project_alias = get_option(self::OPTION_PROJECT_ALIAS);

        $balance_escaped = $api_token ? $this->_apiUtils->get_balance_escaped() : __('Please set API token.', 'telenote-plugin');

        $current_user = wp_get_current_user();

        $link_id = $this->_apiUtils->get_link_id($current_user->ID);

        if (empty($link_id)) {
            $deep_link_escaped = $this->_apiUtils->get_deep_link_escaped($api_token, $current_user->ID);
        }
?>
        <div class='wrap woocommerce'>
            <form method='post' id='mainform' action="<?php echo admin_url('admin.php?page=' . self::ADMIN_PAGE_SLUG . '&action=' . self::ADD);?>">
                <div class='icon32 icon32-woocommerce-settings' id='icon-woocommerce'></div>
                <?php if (isset($result)) { echo '<h3>' . $result . '</h3>'; } ?>

                <div>
                    <?php $this->check_telenote_account_settings_plugin_page(); ?>
                </div>

                <table class='widefat' style='width:auto; float:left; display:inline; clear:none; margin-bottom:30px;'>
                    <tr>
                        <th colspan='2' style='text-align:center'>
                            <h2><?php _e('Telenote Account settings', 'telenote-plugin'); ?></h2>

                    <tr>
                        <td>
                            <label for='api_token'><?php _e('Telenote API Token', 'telenote-plugin'); ?></label>
                        <td>
                            <input type='text' name='api_token' id='api_token' value='<?php echo esc_attr($api_token);?>'>

                    <tr>
                        <td>
                            <label for='project_alias'><?php _e('Telenote Project Alias', 'telenote-plugin'); ?></label>
                        <td>
                            <input type='text' name='project_alias' id='project_alias' value='<?php echo esc_attr($project_alias);?>'>

                    <?php if ($balance_escaped) { echo '<tr><td>'.__('Your balance: ', 'telenote-plugin').'<td><b>', $balance_escaped, '</b>'; } ?>
                </table>

                <?php if(empty($link_id) && !empty($deep_link_escaped)): ?>
                    <table class='widefat' style='width:auto; margin-bottom:30px;'>
                        <tr>
                            <th style='text-align:center'>
                                <h2><?php _e('Link your account', 'telenote-plugin'); ?></h2>

                        <tr>
                            <td>
                                <?php _e('Click on this link to receive admin notifications', 'telenote-plugin'); ?>:
                                <a target="_blank" href="<?php echo $deep_link_escaped; ?>"><?php echo $deep_link_escaped; ?></a>

                    </table>
                <?php endif; ?>

                <table class='widefat' style='width:auto;'>
                    <tr>
                        <th colspan='3' style='text-align:center'>
                            <h2><?php _e('Message Templates', 'telenote-plugin'); ?></h2>

                    <tr>
                        <th colspan='2' style='text-align:center;'>
                            <b><?php _e('Template for "New Order" message', 'telenote-plugin'); ?></b>

                    <tr>
                        <td style='padding:0'>
                        <td style='padding:0'>
                        <td rowspan='4'><br><br><br><br>
                            <b><?php _e('Placeholdres:', 'telenote-plugin'); ?></b><br>
                            {NUM} - <?php _e('Order Number', 'telenote-plugin'); ?><br>
                            {SUM} - <?php _e('Order Total Sum', 'telenote-plugin'); ?><br>
                            {EMAIL} - <?php _e('Client Email Address', 'telenote-plugin'); ?><br>
                            {PHONE} - <?php _e('Client Phone Number', 'telenote-plugin'); ?><br>
                            {FIRSTNAME} - <?php _e('Client First Name', 'telenote-plugin'); ?><br>
                            {LASTNAME} - <?php _e('Client Last Name', 'telenote-plugin'); ?><br>
                            {CITY} - <?php _e('Client City', 'telenote-plugin'); ?><br>
                            {ADDRESS} - <?php _e('Client Address', 'telenote-plugin'); ?><br>
                            {BLOGNAME} - <?php _e('Shop Name', 'telenote-plugin'); ?><br>
                            {OLD_STATUS} - <?php _e('Prevous Order Status', 'telenote-plugin'); ?><br>
                            {NEW_STATUS} - <?php _e('New Order Status', 'telenote-plugin'); ?><br>
                            {ITEMS} - <?php _e('Items List In "Name Count Price" format', 'telenote-plugin'); ?><br>
                            {PAYMENT_METHOD} - <?php _e('Payment Method', 'telenote-plugin'); ?><br>
                            {SHIPPING_METHOD} - <?php _e('Delivery Method', 'telenote-plugin'); ?><br>
                            {CREATE_STATUS} - <?php _e('Order Creation Status', 'telenote-plugin'); ?>
                    <tr>
                        <td>
                            <label for='new_adm'><?php _e('Message To Admin', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='new_adm' id='new_adm'><?php echo stripslashes(get_option(self::OPTION_NEW_ADM));?></textarea><br>

                            <label for='send_new_adm'><?php _e('Send Message To Admin?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_new_adm' id='send_new_adm' <?php echo get_option(self::OPTION_SEND_NEW_ADM) ? 'checked' : '';?>>

                        <td>
                            <label for='new_cl'><?php _e('Message To Client', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='new_cl' id='new_cl'><?php echo stripslashes(get_option(self::OPTION_NEW_CL));?></textarea><br>

                            <label for='send_new_cl'><?php _e('Send Message To Client?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_new_cl' id='send_new_cl' <?php echo get_option(self::OPTION_SEND_NEW_CL) ? 'checked' : '';?>>

                    <tr>
                        <th colspan='2' style='text-align:center;'><br>
                        <b><?php _e('Template for "Order Changed" message', 'telenote-plugin'); ?></b>

                    <tr>
                        <td>
                            <label for='change_adm'><?php _e('Message To Admin', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='change_adm' id='change_adm'><?php echo stripslashes(get_option(self::OPTION_CHANGE_ADM));?></textarea><br>

                            <label for='send_change_adm'><?php _e('Send Message To Admin?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_change_adm' id='send_change_adm' <?php echo get_option(self::OPTION_SEND_CHANGE_ADM) ? 'checked' : '';?>>

                        <td>
                            <label for='change_cl'><?php _e('Message To Client', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='change_cl' id='change_cl'><?php echo stripslashes(get_option(self::OPTION_CHANGE_CL));?></textarea><br>

                            <label for='send_change_cl'><?php _e('Send Message To Client?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_change_cl' id='send_change_cl' <?php echo get_option(self::OPTION_SEND_CHANGE_CL) ? 'checked' : '';?>>
                </table><br>

                <input type='submit' class='button-primary' value='<?php _e('Save Changes', 'telenote-plugin'); ?>'>
            </form>
        </div>
<?php
    }

    /**
     * Function for processing the event of order creation and order status change
     */
    function telenote_new_order_and_change_status($order_id, $old_status = -1, $new_status = -1)
    {
        try{
            if (($api_token = get_option(self::OPTION_API_TOKEN)) && ($project_alias = get_option(self::OPTION_PROJECT_ALIAS))) {
                $send_new_adm = get_option(self::OPTION_SEND_NEW_ADM);
                $new_adm = get_option(self::OPTION_NEW_ADM);
                $send_new_cl = get_option(self::OPTION_SEND_NEW_CL);
                $new_cl = get_option(self::OPTION_NEW_CL);
                $send_change_adm = get_option(self::OPTION_SEND_CHANGE_ADM);
                $change_adm = get_option(self::OPTION_CHANGE_ADM);
                $send_change_cl = get_option(self::OPTION_SEND_CHANGE_CL);
                $change_cl = get_option(self::OPTION_CHANGE_CL);

                if (($send_new_adm && $new_adm) || ($send_new_cl && $new_cl) || ($send_change_adm && $change_adm) || ($send_change_cl && $change_cl)) {
                    $order = new \WC_Order($order_id);

                    $meta_phone = '';

                    if (function_exists('get_post_meta') && array_key_exists("post_ID", $_POST)) {
                        $meta_phone = get_post_meta(intval($_POST['post_ID']), '_billing__tel', true);
                    }

                    $billing_phone = $order->get_billing_phone() ?: $meta_phone;

                    global $wpdb;
                    $shipping = $wpdb->get_var(
                        "
                        SELECT order_item_name
                        FROM {$wpdb->prefix}woocommerce_order_items
                        WHERE order_id = $order_id AND order_item_type = 'shipping'
                        "
                    );

                    $items = '';

                    if (preg_match_all('~<td.+?>(.+?)</td>~s', wc_get_email_order_items($order), $m))
                        foreach ($m[1] as $k => $v)
                            $items .= mb_convert_encoding(($k % 3 ? " " : "\n").trim(strip_tags($v)), 'UTF-8', 'HTML-ENTITIES');

                    $search = array(
                        '{NUM}',
                        '{SUM}',
                        '{EMAIL}',
                        '{PHONE}',
                        '{FIRSTNAME}',
                        '{LASTNAME}',
                        '{CITY}',
                        '{ADDRESS}',
                        '{BLOGNAME}',
                        '{OLD_STATUS}',
                        '{NEW_STATUS}',
                        '{ITEMS}',
                        '{PAYMENT_METHOD}',
                        '{SHIPPING_METHOD}',
                        '{CREATE_STATUS}'
                    );
                    $replace = array(
                        '#' . $order_id,
                        html_entity_decode(strip_tags($order->get_formatted_order_total()), ENT_COMPAT | ENT_HTML401, 'UTF-8'),
                        $order->get_billing_email(),
                        $billing_phone,
                        $order->get_billing_first_name(),
                        $order->get_billing_last_name(),
                        $order->get_shipping_city(),
                        $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
                        get_option('blogname'),
                        __($old_status, 'woocommerce'),
                        $new_status == -1 ? $order->get_status() : __($new_status, 'woocommerce'),
                        trim($items),
                        $order->get_payment_method_title(),
                        $shipping,
                        $order->get_status()
                    );

                    $order_user_id = $order->get_user_id();
                    $admins_logins = get_super_admins();
                    $admin_user = null;

                    if (!empty($admins_logins)) {
                        $first_login = $admins_logins[0];
                        $admin_user = get_user_by('login', $first_login);
                    }

                    $user_meta_key = self::USER_AGREEMENT_FIELD;

                    // order created
                    if ($order->get_date_created() == $order->get_date_modified()) {
                        unset($search[9], $replace[9]);
                        if ($send_new_adm && $new_adm && $admin_user && $admin_user->ID > 0) {
                            $this->_apiClient->post_message(
                                $api_token,
                                $project_alias,
                                strval($admin_user->ID),
                                str_replace($search, $replace, $new_adm)
                            );
                        }

                        $user_meta = get_user_meta($order_user_id, $user_meta_key);
                        $user_meta_ok = count($user_meta) > 0 && $user_meta[0] == self::YES;

                        if ($send_new_cl && $new_cl && !empty($order_user_id) && $user_meta_ok) {
                            $this->_apiClient->post_message(
                                $api_token,
                                $project_alias,
                                strval($order_user_id),
                                str_replace($search, $replace, $new_cl)
                            );
                        }

                    } else { // order status changed
                        if ($send_change_adm && $change_adm && $admin_user && $admin_user->ID > 0) {
                            $this->_apiClient->post_message(
                                $api_token,
                                $project_alias,
                                strval($admin_user->ID),
                                str_replace($search, $replace, $change_adm)
                            );
                        }

                        $user_meta = get_user_meta($order_user_id, $user_meta_key);
                        $user_meta_ok = count($user_meta) > 0 && $user_meta[0] == self::YES;

                        if ($send_change_cl && $change_cl && !empty($order_user_id) && $user_meta_ok) {
                            $this->_apiClient->post_message(
                                $api_token,
                                $project_alias,
                                strval($order_user_id),
                                str_replace($search, $replace, $change_cl)
                            );
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            $logger = wc_get_logger();
            $logger->error("Telenote error: " . $ex->getMessage() . ' ' . 'Trace: ' . $ex->getTraceAsString());
        }
    }

    /**
     * Add telenote field to woocommerce checkout
     */
    function telenote_custom_checkout_field()
    {
        try {
            $current_user = wp_get_current_user();

            # We offer the link only to those users who are logged in
            if (empty($current_user->ID)) {
                return;
            }

            $link_id = $this->_apiUtils->get_link_id($current_user->ID);

            # If the link already exists, check if the user has given consent to send messages
            if (!empty($link_id)) {
                $user_meta_key = self::USER_AGREEMENT_FIELD;
                $user_meta = get_user_meta($current_user->ID, $user_meta_key);
                $user_meta_ok = count($user_meta) > 0 && $user_meta[0] == self::YES;
                if ($user_meta_ok) {
                    // do not show the link if the user has already linked the account and agreed to receive messages
                    return;
                }
            }

            $api_token = get_option(self::OPTION_API_TOKEN);

            if (empty($api_token)) {
                $this->_view_telenote_field_error(__('Please set Telenote API Token in the settings.', 'telenote-plugin'));
                return;
            }

            $deep_link_escaped = $this->_apiUtils->get_deep_link_escaped($api_token, $current_user->ID);

            if (!empty($deep_link_escaped)) {
                $this->_view_telenote_field_link($deep_link_escaped);
            }

        } catch (Exception $e) {
            $this->_view_telenote_field_error($e->getMessage());
        }
    }

    /**
     * Save user aggrement in DB as user meta
     */
    function telenote_save_custom_checkout_field()
    {
        $current_user = wp_get_current_user();
        if (empty($current_user->ID)) {
            return;
        }
        $key = self::USER_AGREEMENT_FIELD;
        if (array_key_exists($key, $_POST)){
            $value = sanitize_text_field($_POST[$key]) == self::YES ? self::YES : self::NO;
            update_user_meta($current_user->ID, $key, $value);
        }
    }

    /**
     * View field in checkout
     */
    function _view_telenote_field_link($link)
    {
        $checkout = \WC_Checkout::instance();
        $key = self::USER_AGREEMENT_FIELD;
        $value = $checkout->get_value($key);
        $link_escaped = esc_url($link);

        $radio = woocommerce_form_field(
            $key,
            array(
                'type' => 'radio',
                'options' => array(
                    self::YES => __('Yes, send notifications in my Telegram. ', 'telenote-plugin'),
                    self::NO => __('No, thanks.', 'telenote-plugin'),
                ),
                'return' => true,
            ),
            empty($value) ? self::YES : $value
        );

        echo '<div class="view_telenote_field_link">';
            echo '<h3>' . __('Do you want to receive notifications in Telegram?', 'telenote-plugin') . '</h3>';
            echo $radio;
            echo '<div>';
                echo __('If yes, please click on this link before place order ', 'telenote-plugin')
                . '<a target="_blank" href="' . $link_escaped . '">' . $link_escaped . '</a>'
                . __(' to subscribe to our Telegram bot.', 'telenote-plugin');
            echo '</div>';
        echo '</div>';
        echo '<br/>';
    }

    /**
     * View error in checkout
     */
    function _view_telenote_field_error($errorMessage)
    {
        echo '<div class="view_telenote_field_error">';
            echo '<h3>' . __('Error when using Telenote plugin.', 'telenote-plugin') . '</h3>';
            echo '<div>';
                echo esc_html($errorMessage);
            echo '</div>';
        echo '</div>';
        echo '<br/>';
    }
}

if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) )
{
    add_action('plugins_loaded', __NAMESPACE__ .'\Telenote::entrypoint');
}
