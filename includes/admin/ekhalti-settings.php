<?php

if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
        'wc_ekhalti_gateway_settings', array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'ekhalti_gateway'),
        'type' => 'checkbox',
        'label' => __('Enable e-Khalti Payment', 'ekhalti_gateway'),
        'default' => 'yes'
    ),
    'title' => array(
        'title' => __('Title', 'ekhalti_gateway'),
        'type' => 'text',
        'description' => __('This controls the title for the payment method the customer sees during checkout.', 'ekhalti_gateway'),
        'default' => __('Credit Or Debit Card', 'ekhalti_gateway'),
        'desc_tip' => true,
    ),
    'description' => array(
        'title' => __('Description', 'ekhalti_gateway'),
        'type' => 'textarea',
        'description' => __('Payment method description that the customer will see on your checkout.', 'ekhalti_gateway'),
        'default' => __('Pay Securely With e-Khalti.', 'ekhalti_gateway'),
        'desc_tip' => true,
    ),
    'merchant_id' => array(
        'title' => __('Merchant ID', 'ekhalti_gateway'),
        'type' => 'text',
        'description' => __('Used for payment.', 'ekhalti_gateway'),
        'default' => '',
        'desc_tip' => true,
    ),
    'api_key' => array(
        'title' => __('API Key', 'woocommerce-integration-demo'),
        'type' => 'text',
        'description' => __('Enter with your API Key. You can find this in "User Profile" drop-down (top right corner) > API Keys.', 'woocommerce-integration-demo'),
        'desc_tip' => true,
        'default' => ''
    ),
        )
);
