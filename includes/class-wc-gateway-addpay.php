<?php

define('WCAPGW_VERSION', '2.6.2');

class WCAPGW_Gateway extends WC_Payment_Gateway
{

    /**
     * Version
     */
    public $version;

    /**
     * HTTP Payload
     */
    protected $payload = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version            = WCAPGW_VERSION;
        $this->id                 = 'addpay';
        $this->method_title       = __('AddPay', 'wcagw-payment-gateway');
        $this->method_description = sprintf(__('AddPay works by sending the user to %1$sAddPay%2$s to enter their payment information.', 'wcagw-payment-gateway'), '<a href="http://addpay.co.za/">', '</a>');
        $this->icon               = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__))) . '/logo.png';
        $this->debug_email        = get_option('admin_email');

        $this->supports = array(
            'products',
        );
        $this->init_form_fields();
        $this->init_settings();
      
        $this->client_id            = $this->get_option('client_id');
        $this->client_secret        = $this->get_option('client_secret');
        $this->environment          = $this->get_option('environment');
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->payment_url          = $this->get_option('payment_url', '');
        $this->enabled              = $this->get_option( 'enabled' );
       

        if ($this->environment == 'yes') {
            $this->url              = 'https://secure.addpay.co.za/v2/transactions';
        } else {
            $this->url              = 'https://secure-test.addpay.co.za/v2/transactions';
        }

        add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // $this->wcapgw_check_result();

        add_action('woocommerce_api_wcapgw_gateway', [$this, 'wcapgw_check_result']);
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'wcagw-payment-gateway'),
                'label'       => __('Enable AddPay', 'wcagw-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('This controls whether or not this gateway is enabled within WooCommerce.', 'wcagw-payment-gateway'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'title' => array(
                'title'       => __('Title', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wcagw-payment-gateway'),
                'default'     => __('AddPay', 'wcagw-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'wcagw-payment-gateway'),
                'default'     => __('Proceed via AddPay suite of payment methods.', 'wcagw-payment-gateway'),
                'desc_tip'    => true,
            ),
            'client_id' => array(
                'title'       => __('Client ID', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This is the Client ID generated on the AddPay merchant console.', 'wcagw-payment-gateway'),
                'default'     => 'CHANGE ME',
            ),
            'client_secret' => array(
                'title'       => __('Client Secret', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('This is the Client Secret generated on the AddPay merchant console.', 'wcagw-payment-gateway'),
                'default'     => 'CHANGE ME',
            ),
            'environment' => array(
                'title'       => __('Environment', 'wcagw-payment-gateway'),
                'label'       => __('Live AddPay API Credentials', 'wcagw-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('This controls whether or not this gateway is using sandbox or live credentials.', 'wcagw-payment-gateway'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'payment_url' => array(
                'title'       => __('Payment URL', 'wcagw-payment-gateway'),
                'type'        => 'text',
                'description' => __('The URL of your custom payment page. AddPay Plus Customers only.  ', 'wcagw-payment-gateway'),
                'default'     => __('', 'wcagw-payment-gateway'),
            )

        );
    }

    /**
     * Process the payment and return the result.
     *
     * @since 1.0.0
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // $notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WCAPGW_Gateway', home_url('/')));
        $return_url = str_replace('https:', 'http:', home_url('/') . 'wc-api/wcapgw_gateway/');
        $timestamp = intval(microtime(true));
        $reference_long = "wcap-" . $order->get_order_number() . "-" . $timestamp;
        $reference = substr($reference_long, 0, 24); // ensure that it is less than 24 characters as is required by Addpay API

        $this->payload = json_encode(array(
            'reference'   => $reference,
            'description' => get_bloginfo('name'),
            'customer' => array(
                'firstname' => self::wcapgw_get_order_prop($order, 'billing_first_name'),
                'lastname'  => self::wcapgw_get_order_prop($order, 'billing_last_name'),
                'email'     => self::wcapgw_get_order_prop($order, 'billing_email'),
                'mobile'    => self::wcapgw_get_order_prop($order, 'billing_phone'),
            ),
            'amount'  => array(
              'value'         => $order->get_total(),
              'currency_code' => get_woocommerce_currency(),
            ),
            'service' => array(
              'key'     => 'DIRECTPAY',
              'intent'  => 'SALE'
            ),
            'return_url'    => $return_url, //$this->get_return_url($order),
            /**
             * Notify URL should NOT be the same as the return URL, this could (and likely does) cause
             * duplicate orders since the notify could come before or after the checkout, rendering 
             * an additional result check. Since the result is checked when the return URL is 
             * hit, we don't even need the notification. The only instance where this may
             * be needed is in the event of a refund or chargeback.
             *
             * 'notify_url'    => $return_url,
             */
            'cancel_url'    => $return_url
        ));

        $this->result = wp_remote_post($this->url, array(
              'method'       => 'POST',
              'timeout'      => 45,
              'redirection'  => 5,
              'httpversion'  => '1.0',
              'blocking'     => true,
              'headers'      => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'Authorization' => 'Token ' . base64_encode("{$this->client_id}:{$this->client_secret}"),
              ],
              'body'         => $this->payload,
              'cookies'      => array()
        ));

        if ($this->result['response']['code'] == 201 || $this->result['response']['code'] == 200) {
            $result = json_decode($this->result['body'])->data;

            if (strlen($this->payment_url) == 0){
                return array(
                    'result'     => 'success',
                    'redirect'   => $result->direct,
                    );
                }
            else {
                $redirect = strpos($this->payment_url, "?") !== false ? $this->payment_url .'&transaction_id=' . $result->id : $this->payment_url .'?transaction_id=' . $result->id;
                return array(
                    'result'     => 'success',
                    'redirect'   => $redirect
                );
            }

        } else {
            try {
                $result = json_decode($this->result['body']);

                wc_add_notice(__('<strong>Payment Error</strong><br/>', 'woothemes') . $result->meta->message . '', 'error');
                return;
            } catch (\Exception $e) {
                wc_add_notice(__('<strong>Payment Error:</strong><br/>', 'woothemes') . 'System error', 'error');
                return;
            }
        }
    }

    /**
     * Check payment result.
     *
     * Check the result of the transaction and mark the order appropriately
     *
     * @since 1.0.0
     */
    public function wcapgw_check_result()
    {
        global $woocommerce;
        $transaction_id = isset($_GET['transaction_id']) ? sanitize_text_field($_GET['transaction_id']) : false;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : false;
        $checkout_url = $woocommerce->cart->get_checkout_url();
        $cart_url = wc_get_cart_url();

        if ($transaction_id) {
            try {
                $this->result = wp_remote_get("{$this->url}/{$transaction_id}", array(
                    'method'       => 'GET',
                    'timeout'      => 45,
                    'redirection'  => 5,
                    'httpversion'  => '1.0',
                    'blocking'     => true,
                    'headers'      => [
                        'Content-Type'  => 'application/json',
                        'accept'        => 'application/json',
                        'Authorization' => 'Token ' . base64_encode("{$this->client_id}:{$this->client_secret}"),
                    ],
                    'cookies'      => array()
                ));

                $transaction = json_decode($this->result['body'])->data;
                $order_id = explode("-", $transaction->reference)[1];
                $order = new WC_Order($order_id);
                $redirect = $this->get_return_url($order);
                $status = !$status ? $transaction->status : $status;

                if ($status == 'COMPLETE') {
                    $order->update_status('processing');
                    $order->payment_complete();
                    $woocommerce->cart->empty_cart();
                    wp_redirect($redirect);
                } elseif ($status == 'FAILED') {
                    if (!isset($_POST['transaction_id'])) {
                        $state = ucFirst(strtolower($status));
    
                        wc_add_notice(__("<strong>Payment {$state}</strong><br/>{$transaction->status_reason}", 'woothemes'), 'error');
                    }
    
                    $order->update_status('failed');
                    wp_redirect($cart_url);
                } else if ($status == 'CANCELLED') {
                    wp_redirect($checkout_url);
                } else {
                    if (!isset($_POST['transaction_id'])) {
                        $state = ucFirst(strtolower($status));
                        wc_add_notice(__("<strong>Invalid transaction status: {$state}</strong><br/>{$transaction->status_reason}", 'woothemes'), 'error');
                    }
                    wp_redirect($cart_url);
                }
            } catch (\Throwable $th) {
                wc_add_notice(__('<strong>An error occurred while processing the transaction response. Please submit a ticket to <a href="https://helpdesk.addpay.co.za/">AddPay support</a> with this error message.</strong> ', 'woothemes'), 'error');
            }
        } else {
            if (!isset($_POST['transaction_id'])) {
                wc_add_notice(__('<strong>AddPay response does not include the transaction details. Please submit a ticket to <a href="https://helpdesk.addpay.co.za/">AddPay support</a> with this error message.</strong> ', 'woothemes'), 'error');
            }
            wp_redirect($cart_url);
        }
        exit;
    }

    /**
     * Get order property with compatibility check on order getter introduced
     * in WC 3.0.
     *
     * @since 1.0.0
     *
     * @param WC_Order $order Order object.
     * @param string   $prop  Property name.
     *
     * @return mixed Property value
     */
    public static function wcapgw_get_order_prop($order, $prop)
    {
        switch ($prop) {
            case 'order_total':
                $getter = array( $order, 'get_total' );
                break;
            default:
                $getter = array( $order, 'get_' . $prop );
                break;
        }

        return is_callable($getter) ? call_user_func($getter) : $order->{ $prop };
    }
}
