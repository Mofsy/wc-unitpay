<?php
/**
 * Main instance of WC_Unitpay
 *
 * @return WC_Unitpay|false
 */
function WC_Unitpay()
{
	if(is_callable('WC_Unitpay::instance'))
	{
		return WC_Unitpay::instance();
	}

	return false;
}

/**
 * Get current version WooCommerce
 */
function wc_unitpay_get_wc_version()
{
	if(function_exists('is_woocommerce_active') && is_woocommerce_active())
	{
		global $woocommerce;

		if(isset($woocommerce->version))
		{
			return $woocommerce->version;
		}
	}

	if(!function_exists('get_plugins'))
	{
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
	}

	$plugin_folder = get_plugins('/woocommerce');
	$plugin_file = 'woocommerce.php';

	if(isset($plugin_folder[$plugin_file]['Version']))
	{
		return $plugin_folder[$plugin_file]['Version'];
	}

	return null;
}

/**
 * Get WooCommerce currency code
 *
 * @return string
 */
function wc_unitpay_get_wc_currency()
{
	return get_woocommerce_currency();
}

/**
 * Logger
 *
 * @return WC_Unitpay_Logger
 */
function wc_unitpay_logger()
{
	return WC_Unitpay()->get_logger();
}

/**
 * Load localisation files
 */
function wc_unitpay_plugin_text_domain()
{
	/**
	 * WP 5.x or later
	 */
	if(function_exists('determine_locale'))
	{
		$locale = determine_locale();
	}
	else
	{
		$locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
	}

	/**
	 * Change locale from external code
	 */
	$locale = apply_filters('plugin_locale', $locale, 'wc-unitpay');

	/**
	 * Unload & load
	 */
	unload_textdomain('wc-unitpay');
	load_textdomain('wc-unitpay', WP_LANG_DIR . '/wc-unitpay/wc-unitpay-' . $locale . '.mo');
	load_textdomain('wc-unitpay', WC_UNITPAY_PLUGIN_DIR . 'languages/wc-unitpay-' . $locale . '.mo');
}