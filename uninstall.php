<?php
/**
 * Uninstall
 *
 * @package Mofsy/WC_Unitpay
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Delete plugin options
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'woocommerce_unitpay%';");
