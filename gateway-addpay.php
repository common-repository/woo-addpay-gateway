<?php
/**
 * Plugin Name: WooCommerce AddPay Gateway
 * Plugin URI: http://github.com/addpay/woo_addpay_gateway
 * Description: Receive payments using the AddPay payments provider.
 * Author: AddPay Pty Ltd
 * Author URI: https://www.addpay.co.za/
 * Developer: Richard Slabbert/Stephen Lake/Zonica Pietersen
 * Developer URI: https://www.addpay.co.za/
 * Version: 2.6.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Test to see if WooCommerce is active (including network activated).
$plugin_path = trailingslashit(WP_PLUGIN_DIR) . 'woocommerce/woocommerce.php';

add_action('woocommerce_loaded', 'wcapgw_woocommerce_loaded');

function wcapgw_woocommerce_loaded()
{
    add_action('plugins_loaded', 'wcapgw_init', 0);
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcapgw_plugin_links');
}

// Initialize Payment Gateway class
function wcapgw_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    load_plugin_textdomain('wcagw-payment-gateway', false, trailingslashit(dirname(plugin_basename(__FILE__))));
    require_once (plugin_basename('includes/class-wc-gateway-addpay.php'));
    add_filter('woocommerce_payment_gateways', 'wcapgw_add_gateway');
}
add_action('plugins_loaded', 'wcapgw_init', 0);

function wcapgw_plugin_links($links)
{
    $settings_url = add_query_arg(
        array(
            'page' => 'wc-settings',
            'tab' => 'checkout',
            'section' => 'addpay',
        ),
        admin_url('admin.php')
    );

    $plugin_links = array(
        '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'wcagw-payment-gateway') . '</a>',
        '<a href="https://addpay.co.za/contact-us/">' . __('Support', 'wcagw-payment-gateway') . '</a>',
        '<a href="https://github.com/AddPay/woo_addpay_gateway">' . __('Docs', 'wcagw-payment-gateway') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcapgw_plugin_links');

function wcapgw_add_gateway($methods)
{
    $methods[] = 'WCAPGW_Gateway';
    return $methods;
}


/* Blocks */
function addpay_block_enqueue()
{
    wp_enqueue_script(
        'gateway-addpay-block',
        plugins_url('build/index.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor'),
        filemtime(plugin_dir_path(__FILE__) . 'build/index.js')
    );
}
add_action('enqueue_block_editor_assets', 'addpay_block_enqueue');


add_action('woocommerce_blocks_loaded', 'wcapgw_register_order_approval_payment_method_type');
function wcapgw_register_order_approval_payment_method_type()
{
    require_once __DIR__ . '/includes/class-addpay-block-checkout.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_AddPay_Blocks);
        }
    );
}