<?php
/**
 * Plugin Name: Payment gateway - Unitpay for WooCommerce
 * Description: Integration Unitpay in WooCommerce as payment gateway.
 * Plugin URI: https://mofsy.ru/projects/wc-unitpay
 * Version: 0.1.0
 * WC requires at least: 3.0
 * WC tested up to: 4.3
 * Text Domain: wc-unitpay
 * Domain Path: /languages
 * Author: Mofsy
 * Author URI: https://mofsy.ru
 * Copyright: Mofsy © 2020
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Mofsy/WC_Unitpay
 */
defined('ABSPATH') || exit;

if(class_exists('WC_Unitpay') !== true)
{
	$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'));
	define('WC_UNITPAY_VERSION', $plugin_data['Version']);

	define('WC_UNITPAY_URL', plugin_dir_url(__FILE__));
	define('WC_UNITPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
	define('WC_UNITPAY_PLUGIN_NAME', plugin_basename(__FILE__));

	include_once __DIR__ . '/includes/functions-wc-unitpay.php';
	include_once __DIR__ . '/includes/class-wc-unitpay-logger.php';
	include_once __DIR__ . '/includes/class-wc-unitpay.php';

	add_action('plugins_loaded', 'WC_Unitpay', 5);
}