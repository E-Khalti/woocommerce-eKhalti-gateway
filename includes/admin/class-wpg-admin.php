<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Admin Class
 *
 * Handles adding scripts functionality to the admin pages
 * as well as the front pages.
 *
 * @package WooCommerce Bring Gateway
 * @since 1.0.0
 */
class Wpg_Admin {

    /**
     * Add the gateway to WC Available Gateways
     *
     * @since 1.0.0
     * @param array $gateways all available WC gateways
     * @return array $gateways all WC gateways + Bring gateway
     */
    function wpg_add_to_gateways($gateways) {
        $gateways[] = 'wc_ekhalti_gateway';
        return $gateways;
    }

    function wpg_pallapay_gateway_icon($icon, $id) {
        if ($id === 'ekhalti_gateway') {
            return '<img src="' . WPG_URL . 'includes/images/logo.png" alt="e-Khalti" width="80px"/>';
        } else {
            return $icon;
        }
    }

    public function wpg_check_pallapay_response() {
        global $woocommerce;
        /* Change IPN URL */
        if (isset($_REQUEST['hash']) && isset($_REQUEST['order']) && isset($_GET['wc-api']) && $_GET['wc-api'] == 'wc_pallapay') {
            $order_id = $_REQUEST['order'];
            if ($order_id != '') {
                try {
                    $order = new WC_Order($order_id);
                    $hash = $_REQUEST['hash'];
                    $status = $_REQUEST['status'];
                    $trans_authorised = false;

                    if ('completed' !== $order->status) {
                        $status = strtolower($status);
                        if ('confirmed' == $status) {
                            $trans_authorised = true;
                            $message = "Thank you for the order. Your account has been charged and your transaction is successful.";
                            $class = 'success';
                            $order->add_order_note('e-Khalti payment successful.<br/>e-Khalti Transaction ID: ' . $_REQUEST['id_transfer']);
                            $order->payment_complete();
                            $woocommerce->cart->empty_cart();
                            $order->update_status('completed');
                        } else {
                            $class = 'error';
                            $message = "Thank you for the order. However, the transaction has been declined.";
                            $order->add_order_note('Transaction Fail');
                        }
                    }
                } catch (Exception $e) {
                    $msg = "Error";
                }
            }
        }
    }

    function add_hooks() {

        add_filter('woocommerce_payment_gateways', array($this, 'wpg_add_to_gateways'));

        add_filter('woocommerce_gateway_icon', array($this, 'wpg_pallapay_gateway_icon'), 10, 2);

        add_action('init', array($this, 'wpg_check_pallapay_response'));
    }

}

?>
