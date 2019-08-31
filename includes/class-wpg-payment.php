<?php

if (!defined('ABSPATH')) {
    exit;
}
/**
 * e-Khalti	 Payment Gateway
 *
 * Provides an e-Khalti Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		wc_ekhalti_gateway
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 */
add_action('plugins_loaded', 'wc_ekhalti_gateway_init', 11);

function wc_ekhalti_gateway_init() {
    if (!class_exists('WC_Ekhalti')) :

        define('WC_EKHALTI_MAIN_FILE', __FILE__);
        define('WC_EKHALTI_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
        define('WC_EKHALTI_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

        class WC_Ekhalti extends WC_Payment_Gateway {

            public $msg = [];

            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance() {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }

            /**
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            private function __clone() {

            }

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            private function __wakeup() {

            }

            /**
             * Constructor for the gateway.
             */
            public function __construct() {

                global $woocommerce;
                $plugin_dir = plugin_dir_url(__FILE__);

                $this->id = 'ekhaltigateway';
                $this->icon = " ";
                $this->has_fields = true;
                $this->method_title = __('e-khalti', 'ekhalti_gateway');
                $this->method_description = __('e-khalti Payment Gateway', 'ekhalti_gateway');
                //$this->payment_url = 'http://pay.ekhalti.local/api/payrequest';
                $this->payment_url = 'https://merchant.e-khalti.com/api/payrequest';


                // Method with all the options fields
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();
                // Get settings.
                $this->merchant_id = $this->get_option('merchant_id');

                $this->api_key = $this->get_option('api_key');

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->enabled = $this->get_option('enabled');

                // but in this tutorial we begin with simple payments
                $this->supports = array(
                    'products',
                    'refunds',
                    'tokenization',
                    'add_payment_method',
                    'subscriptions',
                    'subscription_cancellation',
                    'subscription_suspension',
                    'subscription_reactivation',
                    'subscription_amount_changes',
                    'subscription_date_changes',
                    'subscription_payment_method_change',
                    'subscription_payment_method_change_customer',
                    'subscription_payment_method_change_admin',
                    'multiple_subscriptions',
                    'pre-orders',
                );
                /**
                 *  Actions
                 */
                add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
                add_action('init', array(&$this, 'wc_ekhalti_gateway_response'), 11);
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'handle_ekhalti_gateway_response')); //update for woocommerce >2.0
                //            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
                //            add_action('woocommerce_receipt_ekhalti_gateway', array(&$this, 'receipt_page'));
                // If the user is an administrator, add the settings tab
                // Save settings
                if (is_admin()) {
                    // Versions over 2.0
                    // Save our administration options. Since we are not going to be doing anything special
                    // we have not defined 'process_admin_options' in this class so the method in the parent
                    // class will be used instead
                    if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); //update for woocommerce >2.0
                    } else {
                        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options')); // WC-1.6.6
                    }
                }
            }

            function receipt_page($order) {
                echo '<p><strong>' . __('Thank you for your order.', 'wpg') . '</strong><br/>' . __('The payment page will open soon.', 'wpg') . '</p>';
                echo $this->generate_ekhalti_form($order);
            }

            //END-receipt_page

            /**
             * Add the gateways to WooCommerce.
             *
             * @since 1.0.0
             * @version 4.0.0
             */
            public function add_gateways($gateways) {
                $gateways[] = 'wc_ekhalti';

                return $gateways;
            }

            /**
             * Setup general properties for the gateway.
             */
            protected function setup_properties() {
                $this->id = 'cod';
                $this->icon = apply_filters('woocommerce_cod_icon', '');
                $this->method_title = __('Cash on delivery', 'woocommerce');
                $this->method_description = __('Have your customers pay with cash (or by other means) upon delivery.', 'woocommerce');
                $this->has_fields = false;
            }

            /**
             * Generate button link
             * */
            function get_order_item_names($order) {
                $item_names = $$item_id = $result = array();

                foreach ($order->get_items() as $item) {
                    $item_name = $item->get_name();
                    $item_id [] = $item->get_ID();

                    $item_meta = strip_tags(
                            wc_display_item_meta(
                                    $item, array(
                        'before' => '',
                        'separator' => ', ',
                        'after' => '',
                        'echo' => false,
                        'autop' => false,
                                    )
                            )
                    );

                    if ($item_meta) {
                        $item_name .= ' (' . $item_meta . ')';
                    }

                    $item_names[] = $item_name;
                }
                $result['itemname'] = implode(', ', $item_names);
                $result['itemid'] = implode(', ', $item_id);
                return apply_filters('ekhalti_order_get_order_item_names', $result, $order);
            }

            function initialize_payment($order_id) {
                global $woocommerce;
                $redirect_url = get_permalink($this->redirect_page);
                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
                }

                $order = new WC_Order($order_id);
                $item_info = $this->get_order_item_names($order);
                $all_items_name = $item_info['itemname'];
                $all_items_number = $item_info['itemid'];
                $success_url = $order->get_checkout_order_received_url();
                $paramet = array(
                    'order' => $order_id,
                    'order_id' => $order_id,
                    'merchant' => $this->merchant_id,
                    'item_name' => $all_items_name,
                    'item_number' => $all_items_number,
                    'custom' => $all_items_number,
                    'amount' => $order->order_total,
                    //'currency' => get_woocommerce_currency(),
                    'currency' => "debit_base",
//                'custom' => $order->customer_note,
                    'first_name' => $order->billing_first_name,
                    'last_name' => $order->billing_last_name,
                    'email' => $order->billing_email,
                    'phone' => $order->billing_phone,
                    'address' => $order->billing_address_1,
                    'city' => $order->billing_city,
                    'state' => $order->billing_state,
                    'country' => $order->billing_country,
                    'postalcode' => $order->billing_postcode,
                    'notify_url' => $redirect_url,
                    'success_url' => $success_url,
                    'fail_link' => $order->get_cancel_order_url()
                );
                $url = $this->payment_url;
                $response = wp_remote_post($url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(
                        'x-api-key' => $this->api_key,
                        'content-type' => "application/x-www-form-urlencoded"
                    ),
                    'body' => $paramet,
                    'cookies' => array()
                        )
                );
                $body = wp_remote_retrieve_body($response);
                $status = wp_remote_retrieve_response_code($response);

//            if (is_wp_error($response)) {
//                $error_message = $response->get_error_message();
//                echo "Something went wrong: $error_message";
//            } else {
//                echo 'Response:<pre>';
//                print_r($response);
//                echo '</pre>';
//            }

                if ($status == 200) {
                    //return json_decode($body, true);
                }
                return json_decode($body, true);
            }

            function generate_ekhalti_form($order_id) {
                global $woocommerce;
                $redirect_url = get_permalink($this->redirect_page);
                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
                }

                $order = new WC_Order($order_id);
                $item_info = $this->get_order_item_names($order);
                $all_items_name = $item_info['itemname'];
                $all_items_number = $item_info['itemid'];
                $success_url = $order->get_checkout_order_received_url();
                $paramet = array(
                    'order' => $order_id,
                    'merchant' => $this->merchant_id,
                    'item_name' => $all_items_name,
                    'item_number' => $all_items_number,
                    'amount' => $order->order_total,
                    'currency' => get_woocommerce_currency(),
                    'custom' => $order->customer_note,
                    'first_name' => $order->billing_first_name,
                    'last_name' => $order->billing_last_name,
                    'email' => $order->billing_email,
                    'phone' => $order->billing_phone,
                    'address' => $order->billing_address_1,
                    'city' => $order->billing_city,
                    'state' => $order->billing_state,
                    'country' => $order->billing_country,
                    'postalcode' => $order->billing_postcode,
                    'notify_url' => $redirect_url,
                    'success_url' => $success_url,
                    'fail_link' => $order->get_cancel_order_url()
                );
                $url = $this->payment_url;

                $ekhalti_args_array = array();
                foreach ($paramet as $key => $value) {
                    $ekhalti_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }

                return '	<form action="' . $url . '" method="post" id="ekhalti_payment_form">
  				' . implode('', $ekhalti_args_array) . '
				<input type="submit" class="button-alt" id="submit_ekhalti_payment_form" value="' . __('Pay via e-Khalti.com', 'wpg') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'wpg') . '</a>
					<script type="text/javascript">
					jQuery(function(){
					jQuery("#submit_ekhalti_payment_form").click();
					});
					</script>
				</form>';
            }

            /**
             * Initialize Gateway Settings Form Fields
             */
            public function init_form_fields() {
                $this->form_fields = require( dirname(__FILE__) . '/admin/ekhalti-settings.php' );
            }

            /**
             * Add content to the WC emails.
             *
             * @access public
             * @param WC_Order $order
             * @param bool $sent_to_admin
             * @param bool $plain_text
             */
            public function email_instructions($order, $sent_to_admin, $plain_text = false) {

                if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
                    echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
                }
            }

            /**
             * Process the payment and return the result
             *
             * @param int $order_id
             * @return array
             */
            public function process_payment($order_id) {
                if (!WC()->session || !WC()->session->has_session()) {
                    wc_add_notice(__('Payment error:', 'Please login', 'error'));
                    return;
                }

                global $woocommerce;
                $order = new WC_Order($order_id);

                //            $checkout_payment_url = $order->get_checkout_payment_url(true);
                $payment = $this->initialize_payment($order_id);
                //            print_r($payment);
                //            die;
                //            return array(
                //                'result' => 'success',
                //                'redirect' => add_query_arg(
                //                        'order', $order->id, add_query_arg(
                //                                'key', $order->order_key, $checkout_payment_url
                //                        )
                //                )
                //            );
//                print_r($payment);
//                die;
                if ($payment !== null && $payment['status'] == "200") {
                    return array(
                        'result' => 'success',
                        'redirect' => $payment['link']
                    );
                } else if (isset($payment['msg'])) {
                    wc_add_notice(__('Payment error:', 'ekhalti_gateway') . "{$payment['msg']}", 'error');
                    return;
                } else {
                    wc_add_notice(__('Payment error:', 'ekhalti_gateway') . "Some Techincal Issue try after some time", 'error');
                    return;
                }
            }

            public function handle_ekhalti_gateway_response() {
                global $woocommerce;
                /* Change IPN URL */
                if (isset($_REQUEST['hash']) && isset($_REQUEST['order'])) {
                    $order_id = $_REQUEST['order'];
                    if ($order_id != '') {
                        try {
                            $order = new WC_Order($order_id);
                            $hash = $_REQUEST['hash'];
                            $status = $_REQUEST['status'];
                            $trans_authorised = false;

                            if (!$order->has_status('completed')) {
                                $status = strtolower($status);
                                if ('confirmed' == $status) {
                                    $trans_authorised = true;
                                    $this->msg['message'] = "Thank you for the order. Your account has been charged and your transaction is successful.";
                                    $this->msg['class'] = 'success';
//                                    $order->add_order_note('e-Khalti payment successful main.<br/>e-Khalti Transaction ID: ' . $_REQUEST['id_transfer']);
                                    $order->payment_complete();
                                    $woocommerce->cart->empty_cart();
                                    $order->update_status('completed');
                                } else {
                                    $this->msg['class'] = 'error';
                                    $this->msg['message'] = "Thank you for the order. However, the transaction has been declined now.";
                                    $order->add_order_note('Transaction Fail');
                                }
                            }
                        } catch (Exception $e) {
                            $msg = "Error";
                        }
                    }

                    if (function_exists('wc_add_notice')) {
                        wc_add_notice($msg['message'], $msg['class']);
                    } else {
                        if ('success' == $msg['class']) {
                            $woocommerce->add_message($msg['message']);
                        } else {
                            $woocommerce->add_error($msg['message']);
                        }
                        $woocommerce->set_messages();
                    }

                    // @see: https://wordpress.org/support/topic/enabling-default-woocommerce-redirects/#post-9728440
                    if ('success' == $this->msg['class']) {
                        if ('' == $this->redirect_page || 0 == $this->redirect_page) {
                            $redirect_url = $this->get_return_url($order);
                        } else {
                            $redirect_url = get_permalink($this->redirect_page);
                        }
                    } else {
                        $redirect_url = wc_get_checkout_url();
                    }

                    wc_print_notices();

                    wp_redirect($redirect_url);
                    exit;
                }
            }

            public function wc_ekhalti_gateway_response() {
                global $woocommerce;
                /* Change IPN URL */

                if (isset($_REQUEST['ekref'])) {

                    $ekref = $this->e_decrypt(trim($_REQUEST['ekref']), $this->api_key);
                    $de_data = explode('&', $ekref);

                    foreach ($de_data as $param => $value) {
                        $tmp_value = explode("=", $value);
                        $tmp[$tmp_value[0]] = $tmp_value[1];
                    }

                    $order_id = $tmp['order'];
                    if ($order_id != '') {
                        try {
                            $order = new WC_Order($order_id);
//                            $hash = $_REQUEST['hash'];
                            $status = $tmp['status'];
                            $trans_authorised = false;

                            if (!$order->has_status('completed')) {
                                $status = strtolower($status);
                                if ('confirmed' == $status) {
                                    $trans_authorised = true;
                                    $this->msg['message'] = "Thank you for the order. Your account has been charged and your transaction is successful.";
                                    $this->msg['class'] = 'success';
                                    $order->add_order_note('e-Khalti payment successful main.<br/>e-Khalti Transaction ID: ' . $tmp['transaction']);
                                    $order->payment_complete();
                                    $woocommerce->cart->empty_cart();
                                    $order->update_status('completed');
                                } else {
                                    $this->msg['class'] = 'error';
                                    $this->msg['message'] = "Thank you for the order. However, the transaction has been declined now.";
                                    $order->add_order_note('Transaction Fail');
                                }
                            }
                        } catch (Exception $e) {
                            $this->msg['class'] = 'error';
                            $this->msg['message'] = "Thank you for the order. However, the transaction has been declined now.";
                        }
                    }
                    if ($order->has_status('completed')) {
                        $this->msg['message'] = "Thank you for the order. Your account has been charged and your transaction is successful.";
                        $this->msg['class'] = 'success';
                    } else {
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = "Thank you for the order. However, the transaction has been declined now.";
                    }
//                    echo "<pre>";
//                    print_r($tmp);
//                    print_r($this->msg);
//                    print_r($_REQUEST);
//                    die;

                    if (function_exists('wc_add_notice')) {
                        wc_add_notice($this->msg['message'], $this->msg['class']);
                    } else {
                        if ('success' == $this->msg['class']) {
                            $woocommerce->add_message($this->msg['message']);
                        } else {
                            $woocommerce->add_error($this->msg['message']);
                        }
                        $woocommerce->set_messages();
                    }

                    // @see: https://wordpress.org/support/topic/enabling-default-woocommerce-redirects/#post-9728440
                    if ('success' == $this->msg['class']) {
                        $redirect_url = $order->get_checkout_order_received_url();
                    } else {
                        $redirect_url = wc_get_checkout_url();
                    }

                    wc_print_notices();

                    wp_redirect($redirect_url);
                    exit;
                }
            }

            public function ___process_admin_options() {
                $post_data = $this->get_post_data();
                $mode = 'live';
//            if ($post_data['woocommerce_' . $this->id . '_sandbox'] == '1') {
//                $mode = 'test';
//            }
                $this->merchant_id = $post_data['woocommerce_' . $this->id . '_merchant_id'];
                $this->api_key = $post_data['woocommerce_' . $this->id . '_api_key'];
//            $this->publishable_key = $post_data['woocommerce_' . $this->id . '_' . $mode . '_publishable_key'];
                $env = $mode == 'live' ? 'Producton' : 'Sandbox';
                if ($this->merchant_id == '' || $this->api_key == '') {
                    $settings = new WC_Admin_Settings();
                    $settings->add_error('You need to enter "' . $env . '" credentials if you want to use this plugin in this mode.');
                } else {
//                $this->createWebhook();
                }
                return parent::process_admin_options();
            }

            public function payment_fields() {
                return;
                // ok, let's display some description before the payment form
                if ($this->description) {
                    // you can instructions for test mode, I mean test card numbers etc.
                    if ($this->testmode) {
                        $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
                        $this->description = trim($this->description);
                    }
                    // display the description with <p> tags etc.
                    echo wpautop(wp_kses_post($this->description));
                }

                // I will echo() the form, but you can close PHP tags and print it directly in HTML
                echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

                // Add this action hook if you want your custom payment gateway to support it
                do_action('woocommerce_credit_card_form_start', $this->id);

                // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
                echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
		<input id="misha_ccNo" type="text" autocomplete="off">
		</div>
		<div class="form-row form-row-first">
			<label>Expiry Date <span class="required">*</span></label>
			<input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
		</div>
		<div class="form-row form-row-last">
			<label>Card Code (CVC) <span class="required">*</span></label>
			<input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
		</div>
		<div class="clear"></div>';

                do_action('woocommerce_credit_card_form_end', $this->id);

                echo '<div class="clear"></div></fieldset>';
            }

            /**
             * Encrypts with a bit more complexity
             *
             * @since 1.1.2
             */
            private function e_encrypt($plainText, $key) {
                $encryptionMethod = "AES-128-CBC";
                $secretKey = $this->e_hextobin(md5($key));
                $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
                $encryptedText = openssl_encrypt($plainText, $encryptionMethod, $secretKey, OPENSSL_RAW_DATA, $initVector);
                return bin2hex($encryptedText);
            }

            private function e_decrypt($encryptedText, $key) {
                $encryptionMethod = "AES-128-CBC";
                $secretKey = $this->e_hextobin(md5($key));
                $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
                $encryptedText = $this->e_hextobin($encryptedText);
                $decryptedText = openssl_decrypt($encryptedText, $encryptionMethod, $secretKey, OPENSSL_RAW_DATA, $initVector);
                return $decryptedText;
            }

            private function e_pkcs5_pad($plainText, $blockSize) {
                $pad = $blockSize - (strlen($plainText) % $blockSize);
                return $plainText . str_repeat(chr($pad), $pad);
            }

            private function e_hextobin($hexString) {
                $length = strlen($hexString);
                $binString = "";
                $count = 0;
                while ($count < $length) {
                    $subString = substr($hexString, $count, 2);
                    $packedString = pack("H*", $subString);
                    if ($count == 0) {
                        $binString = $packedString;
                    } else {
                        $binString .= $packedString;
                    }

                    $count += 2;
                }
                return $binString;
            }

        }

        WC_Ekhalti::get_instance();
    endif;
    // end
}
