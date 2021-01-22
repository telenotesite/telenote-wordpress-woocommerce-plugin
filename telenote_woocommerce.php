<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('telenote_woocommerce'))
    exit;

if (!is_callable('is_plugin_active'))
    include_once(ABSPATH .'wp-admin/includes/plugin.php');

if (is_plugin_active('woocommerce/woocommerce.php'))
    add_action('plugins_loaded', 'telenote_woocommerce_entrypoint');

function telenote_woocommerce_entrypoint()
{
    $telenote_woocommerce = new telenote_woocommerce();
    $telenote_woocommerce->load_plugin_text_domain();
}

final class telenote_woocommerce
{
    private static $instance;

    function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'));

        add_action('woocommerce_order_status_changed', array($this, 'telenote_new_order_and_change_status'), 10, 3);
        add_action('woocommerce_checkout_order_processed', array($this, 'telenote_new_order_and_change_status'));

        add_action('admin_notices', array($this, 'check_telenote_settings'));

        register_deactivation_hook(__FILE__, array($this, 'telenote_woocommerce_deactivation'));

        telenote_woocommerce::$instance = $this;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    function load_plugin_text_domain()
    {
        $plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages'; /* Relative to WP_PLUGIN_DIR */
        load_plugin_textdomain( 'telenote-plugin', false, $plugin_rel_path );
    }

    /**
     * Проверяет введенные настройки аккаунта и отображает баннер с предупреждением о том что плагин не работает
     */
    function check_telenote_settings() {
        global $pagenow;

        if ($pagenow !== 'index.php') {
            return;
        }

        $response = telenote_utils_call_api_account_info();

        if (empty($response)) {
            return;
        }

        $status_code = $response['response']['code'];
        $body = $response['body'];

        $class = 'notice notice-error';
        $message = __('Telenote plugin warning! An error has occurred. Please check settings.', 'telenote-plugin');

        if ($status_code !== 200) {
            printf(
                '<div class="%1$s">
                    <p>%2$s</p>
                    <p><pre>%3$s</pre></p>
                    <p><a href="/wp-admin/admin.php?page=telenote_settings">Fix plugin settings</a></p>
                </div>',
                esc_attr($class),
                esc_html($message),
                esc_html($body),
            );
        }
    }

    function check_telenote_settings_plugin_page()
    {
        $response = telenote_utils_call_api_account_info();

        if (empty($response)) {
            return;
        }

        $status_code = $response['response']['code'];
        $body = $response['body'];

        $class = 'notice notice-error';
        $message = __('Telenote plugin warning! An error has occurred. Please check settings.', 'telenote-plugin');

        if ($status_code !== 200) {
            printf(
                '<div class="%1$s">
                    <p>%2$s</p>
                    <p><pre>%3$s</pre></p>
                </div>',
                esc_attr($class),
                esc_html($message),
                esc_html($body),
            );
        }
    }

    function telenote_woocommerce_deactivation()
    {
        delete_option('telenote_api_token');
        delete_option('telenote_project_alias');
        delete_option('telenote_send_new_adm');
        delete_option('telenote_send_new_cl');
        delete_option('telenote_new_adm');
        delete_option('telenote_new_cl');
        delete_option('telenote_send_change_adm');
        delete_option('telenote_send_change_cl');
        delete_option('telenote_change_adm');
        delete_option('telenote_change_cl');
    }
    
    function admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Telenote plugin settings', 'telenote-plugin'),
            __('Telenote plugin settings', 'telenote-plugin'),
            'manage_woocommerce',
            'telenote_settings',
            array($this, 'options_page')
        );
    }
    
    function options_page()
    {
        if (isset($_GET['action']) && $_GET['action'] == 'add') {
            update_option('telenote_api_token', $_POST['api_token']);
            update_option('telenote_project_alias', $_POST['project_alias']);
            update_option('telenote_send_new_adm', array_key_exists('send_new_adm', $_POST) && $_POST['send_new_adm'] == 'on' ? 1 : 0);
            update_option('telenote_send_new_cl', array_key_exists('send_new_cl', $_POST) && $_POST['send_new_cl'] == 'on' ? 1 : 0);
            update_option('telenote_new_adm', stripslashes($_POST['new_adm']));
            update_option('telenote_new_cl', stripslashes($_POST['new_cl']));
            update_option('telenote_send_change_adm', array_key_exists('send_change_adm', $_POST) && $_POST['send_change_adm'] == 'on' ? 1 : 0);
            update_option('telenote_send_change_cl', array_key_exists('send_change_cl', $_POST) && $_POST['send_change_cl'] == 'on' ? 1 : 0);
            update_option('telenote_change_adm', stripslashes($_POST['change_adm']));
            update_option('telenote_change_cl', stripslashes($_POST['change_cl']));

            $result = __('Telenote Plugin Settings updated.', 'telenote-plugin');
        }

        $api_token = get_option('telenote_api_token');
        $project_alias = get_option('telenote_project_alias');

        $balance = $api_token ? telenote_utils_get_balance() : __('Please set API token.', 'telenote-plugin');
        $current_user = wp_get_current_user();
        $link_id = telenote_utils_get_link_id($current_user->ID);

        if (empty($link_id)) {
            $deep_link = telenote_utils_get_deep_link($api_token, $current_user->ID);
        }
?>
        <div class='wrap woocommerce'>
            <form method='post' id='mainform' action="<?php echo admin_url('admin.php?page=telenote_settings&action=add');?>">
                <div class='icon32 icon32-woocommerce-settings' id='icon-woocommerce'></div>
                <?php if (isset($result)) { echo '<h3>'.$result.'</h3>'; } ?>
                
                <div>
                    <?php $this->check_telenote_settings_plugin_page(); ?>
                </div>

                <table class='widefat' style='width:auto; float:left; display:inline; clear:none; margin-bottom:30px;'>
                    <tr>
                        <th colspan='2' style='text-align:center'>
                            <h2><?php _e('Telenote Account settings', 'telenote-plugin'); ?></h2>
                    
                    <tr>
                        <td>
                            <label for='api_token'><?php _e('Telenote API Token', 'telenote-plugin'); ?></label>
                        <td>
                            <input required type='text' name='api_token' id='api_token' value='<?php echo $api_token;?>'>
                    
                    <tr>
                        <td>
                            <label for='project_alias'><?php _e('Telenote Project Alias', 'telenote-plugin'); ?></label>
                        <td>
                            <input required type='text' name='project_alias' id='project_alias' value='<?php echo $project_alias;?>'>
                    
                    <?php if ($balance) { echo '<tr><td>'.__('Your balance: ', 'telenote-plugin').'<td><b>', $balance, '</b>'; } ?>
                </table>

                <?php if(empty($link_id) && !empty($deep_link)): ?>
                    <table class='widefat' style='width:auto; margin-bottom:30px;'>
                        <tr>
                            <th style='text-align:center'>
                                <h2><?php _e('Link your account', 'telenote-plugin'); ?></h2>

                        <tr>
                            <td>
                                <?php _e('Click on this link to receive admin notifications', 'telenote-plugin'); ?>:
                                <a target="_blank" href="<?php echo $deep_link; ?>"><?php echo $deep_link; ?></a>
                                
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
                            <textarea cols='20' rows='5' name='new_adm' id='new_adm'><?php echo get_option('telenote_new_adm');?></textarea><br>

                            <label for='send_new_adm'><?php _e('Send Message To Admin?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_new_adm' id='send_new_adm' <?php echo get_option('telenote_send_new_adm') ? 'checked' : '';?>>

                        <td>
                            <label for='new_cl'><?php _e('Message To Client', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='new_cl' id='new_cl'><?php echo get_option('telenote_new_cl');?></textarea><br>
                                
                            <label for='send_new_cl'><?php _e('Send Message To Client?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_new_cl' id='send_new_cl' <?php echo get_option('telenote_send_new_cl') ? 'checked' : '';?>>
                    
                    <tr>
                        <th colspan='2' style='text-align:center;'><br>
                        <b><?php _e('Template for "Order Changed" message', 'telenote-plugin'); ?></b>
                    
                    <tr>
                        <td>
                            <label for='change_adm'><?php _e('Message To Admin', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='change_adm' id='change_adm'><?php echo get_option('telenote_change_adm');?></textarea><br>
                            
                            <label for='send_change_adm'><?php _e('Send Message To Admin?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_change_adm' id='send_change_adm' <?php echo get_option('telenote_send_change_adm') ? 'checked' : '';?>>

                        <td>
                            <label for='change_cl'><?php _e('Message To Client', 'telenote-plugin'); ?></label><br><br>
                            <textarea cols='20' rows='5' name='change_cl' id='change_cl'><?php echo get_option('telenote_change_cl');?></textarea><br>
                            
                            <label for='send_change_cl'><?php _e('Send Message To Client?', 'telenote-plugin'); ?></label>
                            <input type='checkbox' name='send_change_cl' id='send_change_cl' <?php echo get_option('telenote_send_change_cl') ? 'checked' : '';?>>
                </table><br>

                <input type='submit' class='button-primary' value='Сохранить'>
            </form>
        </div>
<?php
    }

    function telenote_new_order_and_change_status($order_id, $old_status = -1, $new_status = -1)
    {
        if (($api_token = get_option('telenote_api_token')) && ($project_alias = get_option('telenote_project_alias'))) {
            $send_new_adm = get_option('telenote_send_new_adm');
            $new_adm = get_option('telenote_new_adm');
            $send_new_cl = get_option('telenote_send_new_cl');
            $new_cl = get_option('telenote_new_cl');
            $send_change_adm = get_option('telenote_send_change_adm');
            $change_adm = get_option('telenote_change_adm');
            $send_change_cl = get_option('telenote_send_change_cl');
            $change_cl = get_option('telenote_change_cl');

            if (($send_new_adm && $new_adm) || ($send_new_cl && $new_cl) || ($send_change_adm && $change_adm) || ($send_change_cl && $change_cl)) {
                $order = new WC_Order($order_id);

                $meta_phone = '';
                if (function_exists('get_post_meta') && array_key_exists("post_ID", $_POST))
                    $meta_phone = get_post_meta($_POST['post_ID'], '_billing__tel', true);

                $billing_phone = $order->get_billing_phone() ?: $meta_phone;

                global $wpdb;
                $shipping = $wpdb->get_var("SELECT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = $order_id AND order_item_type = 'shipping'");

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

                $user_meta_key = 'telenote_woocommerce_checkout__use_telenote_field';

                // создание заказа
                if ($order->get_date_created() == $order->get_date_modified()) {
                    unset($search[9], $replace[9]);
                    if ($send_new_adm && $new_adm && $admin_user && $admin_user->ID > 0)
                        telenote_api_post_message($api_token, $project_alias, strval($admin_user->ID), str_replace($search, $replace, $new_adm));

                    $user_meta = get_user_meta($order_user_id, $user_meta_key);
                    $user_meta_ok = count($user_meta) > 0 && $user_meta[0] == 'yes';

                    if ($send_new_cl && $new_cl && !empty($order_user_id) && $user_meta_ok)
                        telenote_api_post_message($api_token, $project_alias, strval($order_user_id), str_replace($search, $replace, $new_cl));
                } else { // изменение статуса заказа
                    if ($send_change_adm && $change_adm && $admin_user && $admin_user->ID > 0)
                        telenote_api_post_message($api_token, $project_alias, strval($admin_user->ID), str_replace($search, $replace, $change_adm));

                    $user_meta = get_user_meta($order_user_id, $user_meta_key);
                    $user_meta_ok = count($user_meta) > 0 && $user_meta[0] == 'yes';

                    if ($send_change_cl && $change_cl && !empty($order_user_id) && $user_meta_ok)
                        telenote_api_post_message($api_token, $project_alias, strval($order_user_id), str_replace($search, $replace, $change_cl));
                }
            }
        }
    }
}
