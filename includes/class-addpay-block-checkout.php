<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_AddPay_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'addpay';

    public function initialize() {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
    }

    public function is_active() {
        return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-addpay-blocks-integration',
            plugin_dir_url( __DIR__ ) . 'build/index.js', 
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ],
            time(),
            true
        );
        
        return [ 'wc-addpay-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
        ];
    }

}