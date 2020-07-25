<?php
/**
 * Main class
 *
 * @package Mofsy/WC_Unitpay
 */
defined('ABSPATH') || exit;

class WC_Unitpay
{
	/**
	 * The single instance of the class
	 *
	 * @var WC_Unitpay
	 */
	protected static $_instance = null;

	/**
	 * Logger
	 *
	 * @var WC_Unitpay_Logger
	 */
	public $logger = false;

	/**
	 * WooCommerce version
	 *
	 * @var
	 */
	protected $wc_version = '';

	/**
	 * WooCommerce currency
	 *
	 * @var string
	 */
	protected $wc_currency = 'RUB';

	/**
     * Result url
     *
	 * @var string
	 */
	private $result_url = '';

	/**
     * Fail url
     *
	 * @var string
	 */
	private $fail_url = '';

	/**
     * Success url
     *
	 * @var string
	 */
	private $success_url = '';

	/**
	 * WC_Unitpay constructor
	 */
	public function __construct()
	{
		// hook
		do_action('wc_unitpay_loading');

		wc_unitpay_plugin_text_domain();

		$this->init_includes();
		$this->init_hooks();

		// hook
		do_action('wc_unitpay_loaded');
	}

	/**
	 * Main WC_Unitpay instance
	 *
	 * @return WC_Unitpay
	 */
	public static function instance()
	{
		if(is_null(self::$_instance))
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}
	
	/**
	 * Init required files
	 */
	private function init_includes()
	{
		do_action('wc_unitpay_before_includes');

		require_once WC_UNITPAY_PLUGIN_DIR . 'includes/class-wc-unitpay-method.php';

		do_action('wc_unitpay_after_includes');
	}

	/**
     * Get current currency
     *
	 * @return string
	 */
	public function get_wc_currency()
    {
        return $this->wc_currency;
    }

	/**
     * Set current currency
     *
	 * @param $wc_currency
	 */
    public function set_wc_currency($wc_currency)
    {
        $this->wc_currency = $wc_currency;
    }

	/**
     * Get current WooCommerce version installed
     *
	 * @return mixed
	 */
	public function get_wc_version()
    {
		return $this->wc_version;
	}

	/**
     * Set current WooCommerce version installed
     *
	 * @param mixed $wc_version
	 */
	public function set_wc_version($wc_version)
    {
		$this->wc_version = $wc_version;
	}

	/**
	 * Hooks (actions & filters)
	 */
	private function init_hooks()
	{
		add_action('init', array($this, 'init'), 0);
		add_action('init', array($this, 'wc_unitpay_gateway_init'), 5);

		if(is_admin())
		{
			add_action('init', array($this, 'init_admin'), 0);
			add_action('admin_notices', array($this, 'wc_unitpay_admin_notices'), 10);

			add_filter('plugin_action_links_' . WC_UNITPAY_PLUGIN_NAME, array($this, 'links_left'), 10);
			add_filter('plugin_row_meta', array($this, 'links_right'), 10, 2);

			$this->page_explode();
		}
	}

	/**
	 * Init plugin gateway
	 *
	 * @return mixed|void
	 */
	public function wc_unitpay_gateway_init()
	{
		// hook
		do_action('wc_unitpay_gateway_init_before');

		if(class_exists('WC_Payment_Gateway') !== true)
		{
			wc_unitpay_logger()->emergency('WC_Payment_Gateway not found');
			return false;
		}

		add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_method'), 10);

		// hook
		do_action('wc_unitpay_gateway_init_after');
	}

	/**
	 * Initialization
	 */
	public function init()
	{
		if($this->load_logger() === false)
		{
			return false;
		}

		$this->load_wc_version();
		$this->load_currency();

		return true;
	}

	/**
	 * Admin initialization
	 */
	public function init_admin()
	{
		/**
		 * Load URLs for settings
		 */
		$this->load_urls();
	}

	/**
	 * Load WooCommerce current currency
	 *
	 * @return string
	 */
	public function load_currency()
    {
	    $wc_currency = wc_unitpay_get_wc_currency();

	    /**
	     * WooCommerce Currency Switcher
	     */
	    if(class_exists('WOOCS'))
	    {
		    global $WOOCS;

		    wc_unitpay_logger()->alert('load_currency WooCommerce Currency Switcher detect');

		    $wc_currency = strtoupper($WOOCS->storage->get_val('woocs_current_currency'));
	    }

	    wc_unitpay_logger()->debug('load_currency $wc_version', $wc_currency);

	    $this->set_wc_currency($wc_currency);

	    return $wc_currency;
    }

	/**
	 * Load current WC version
	 *
	 * @return string
	 */
	public function load_wc_version()
    {
    	$wc_version = wc_unitpay_get_wc_version();

	    wc_unitpay_logger()->info('load_wc_version: $wc_version' . $wc_version);

	    $this->set_wc_version($wc_version);
	    
	    return $wc_version;
    }

	/**
	 * Add the gateway to WooCommerce
	 *
	 * @param $methods - all WooCommerce initialized gateways
	 *
	 * @return array - new WooCommerce initialized gateways
	 */
	public function add_gateway_method($methods)
	{
	    $default_class_name = 'Wc_Unitpay_Method';

		$unitpay_method_class_name = apply_filters('wc_unitpay_method_class_name_add', $default_class_name);

		if(!class_exists($unitpay_method_class_name))
		{
			$unitpay_method_class_name = $default_class_name;
		}

		$methods[] = $unitpay_method_class_name;

		return $methods;
	}

	/**
	 * Load logger
	 *
	 * @return boolean
	 */
	protected function load_logger()
	{
		try
		{
			$logger = new WC_Unitpay_Logger();
		}
		catch(Exception $e)
		{
			return false;
		}

		if(function_exists('wp_upload_dir'))
		{
			$wp_dir = wp_upload_dir();

			$logger->set_path($wp_dir['basedir']);
			$logger->set_name('wc-unitpay.boot.log');

			$this->set_logger($logger);

			return true;
		}

		return false;
	}

	/**
	 * Filename for log
	 *
	 * @return mixed
	 */
	public function get_logger_filename()
	{
		$file_name = get_option('wc_unitpay_log_file_name');
		if($file_name === false)
		{
			$file_name = 'wc-unitpay.' . md5(mt_rand(1, 10) . 'MofsyMofsyMofsy' . mt_rand(1, 10)) . '.log';
			update_option('wc_unitpay_log_file_name', $file_name, 'no');
		}

		return $file_name;
	}

	/**
	 * Set logger
	 *
	 * @param $logger
	 *
	 * @return $this
	 */
	public function set_logger($logger)
	{
		$this->logger = $logger;

		return $this;
	}

	/**
	 * Get logger
	 *
	 * @return WC_Unitpay_Logger|null
	 */
	public function get_logger()
	{
		return $this->logger;
	}

	/**
	 * Setup left links
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function links_left($links)
	{
		return array_merge(array('settings' => '<a href="https://mofsy.ru/projects/wc-unitpay" target="_blank">' . __('Official site', 'wc-unitpay') . '</a>'), $links);
	}

	/**
	 * Setup right links
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public function links_right($links, $file)
	{
		if($file === WC_UNITPAY_PLUGIN_NAME)
		{
			$links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=unitpay') . '">' . __('Settings') . '</a>';
		}

		return $links;
	}

	/**
	 * Show admin notices
	 */
	public function wc_unitpay_admin_notices()
    {
    	$section = '';
    	if(isset($_GET['section']))
	    {
		    $section = $_GET['section'];
	    }

        $settings_version = get_option('wc_unitpay_last_settings_update_version');

        /**
         * Global notice: Require update settings
         */
        if(get_option('wc_unitpay_last_settings_update_version') !== false
           && $settings_version < WC_UNITPAY_VERSION
           && $section !== 'unitpay')
        {
	        ?>
	        <div class="notice notice-warning" style="padding-top: 10px; padding-bottom: 10px; line-height: 170%;">
		        <?php
		        echo __('The plugin for accepting payments through Unitpay for WooCommerce has been updated to a version that requires additional configuration.', 'wc-unitpay');
		        echo '<br />';
		        $link = '<a href="'. admin_url('admin.php?page=wc-settings&tab=checkout&section=unitpay') .'">'.__('here', 'wc-unitpay').'</a>';
		        echo sprintf( __( 'Press %s (go to payment gateway settings).', 'wc-unitpay' ), $link ) ?>
	        </div>
	        <?php
        }
    }

	/**
	 *  Add page explode actions
	 */
	public function page_explode()
	{
		add_action('wc_unitpay_admin_options_form_before_show', array($this, 'page_explode_table_before'));
		add_action('wc_unitpay_admin_options_form_after_show', array($this, 'page_explode_table_after'));
		add_action('wc_unitpay_admin_options_form_right_column_show', array($this, 'admin_right_widget_status'));
		add_action('wc_unitpay_admin_options_form_right_column_show', array($this, 'admin_right_widget_one'));
	}

	/**
	 * Page explode before table
	 */
	public function page_explode_table_before()
	{
		echo '<div class="row"><div class="col-24 col-md-17">';
	}

	/**
	 * Load urls:
     * - result
     * - fail
     * - success
	 */
	public function load_urls()
    {
	    $this->set_result_url(get_site_url(null, '/?wc-api=wc_unitpay&action=result'));
	    $this->set_fail_url(get_site_url(null, '/?wc-api=wc_unitpay&action=fail'));
	    $this->set_success_url(get_site_url(null, '/?wc-api=wc_unitpay&action=success'));
    }

	/**
	 * Get result url
	 *
	 * @return string
	 */
	public function get_result_url()
    {
		return $this->result_url;
	}

	/**
	 * Set result url
	 *
	 * @param string $result_url
	 */
	public function set_result_url($result_url)
    {
		$this->result_url = $result_url;
	}

	/**
	 * Get fail url
	 *
	 * @return string
	 */
	public function get_fail_url()
    {
		return $this->fail_url;
	}

	/**
	 * Set fail url
	 *
	 * @param string $fail_url
	 */
	public function set_fail_url($fail_url)
    {
		$this->fail_url = $fail_url;
	}

	/**
	 * Get success url
	 *
	 * @return string
	 */
	public function get_success_url()
    {
		return $this->success_url;
	}

	/**
	 * Set success url
	 *
	 * @param string $success_url
	 */
	public function set_success_url($success_url)
    {
		$this->success_url = $success_url;
	}

	/**
	 * Page explode after table
	 */
	public function page_explode_table_after()
	{
		echo '</div><div class="col-24 d-none d-md-block col-md-6">';

		do_action('wc_unitpay_admin_options_form_right_column_show');

		echo '</div></div>';
	}

	/**
	 * Widget one
	 */
	public function admin_right_widget_one()
	{
		echo '<div class="card border-light" style="margin-top: 0;padding: 0;">
  <div class="card-header" style="padding: 10px;">
    <h5 style="margin: 0;padding: 0;">' . __('Useful information', 'wc-unitpay') . '</h5>
  </div>
    <div class="card-body" style="padding: 0;">
      <ul class="list-group list-group-flush" style="margin: 0;">
    <li class="list-group-item"><a href="https://mofsy.ru/projects/wc-unitpay" target="_blank">' . __('Official plugin page', 'wc-unitpay') . '</a></li>
    <li class="list-group-item"><a href="https://mofsy.ru/blog/tag/unitpay" target="_blank">' . __('Related news: UNITPAY', 'wc-unitpay') . '</a></li>
    <li class="list-group-item"><a href="https://mofsy.ru/projects/tag/woocommerce" target="_blank">' . __('Plugins for WooCommerce', 'wc-unitpay') . '</a></li>
  </ul>
  </div>
</div>';
	}

	/**
	 * Widget status
	 */
	public function admin_right_widget_status()
	{
		$color = 'bg-success';
		$content = '';
		$footer = '';

		$color = apply_filters('wc_unitpay_widget_status_color', $color);
		$content = apply_filters('wc_unitpay_widget_status_content', $content);

		if($color === 'bg-success' || $color === 'text-white bg-success')
		{
			$footer = __('Errors not found. Payment acceptance is active.', 'wc-unitpay');
		}
		elseif($color === 'bg-warning' || $color === 'text-white bg-warning')
		{
			$footer = __('Warnings found. They are highlighted in yellow. You should attention to them.', 'wc-unitpay');
		}
		else
		{
			$footer = __('Critical errors were detected. They are highlighted in red. Payment acceptance is not active.', 'wc-unitpay');
		}

		echo '<div class="card mb-3 ' . $color . '" style="margin-top: 0;padding: 0;"><div class="card-header" style="padding: 10px;">
			<h5 style="margin: 0;padding: 0;">' . __('Status', 'wc-unitpay') . '</h5></div>
			<div class="card-body" style="padding: 0;">
      		<ul class="list-group list-group-flush" style="margin: 0;">';
		echo $content;
		echo '</ul></div>';
		echo '<div class="card-footer text-muted bg-light" style="padding: 10px;">';
		echo $footer;
		echo '</div></div>';
	}
}