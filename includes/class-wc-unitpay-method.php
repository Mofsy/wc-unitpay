<?php
/**
 * Main method class
 *
 * @package Mofsy/WC_Unitpay
 */
defined('ABSPATH') || exit;

class Wc_Unitpay_Method extends WC_Payment_Gateway
{
	/**
	 * Unique gateway id
	 *
	 * @var string
	 */
	public $id = 'unitpay';

	/**
	 * All support WooCommerce currency
	 *
	 * @var array
	 */
	public $currency_all =
	[
		'RUB', 'USD', 'EUR'
	];

	/**
	 * User language
	 *
	 * @var string
	 */
	public $user_interface_language = 'ru';

	/**
	 * @var string
	 */
	public $public_key = '';

	/**
	 * @var string
	 */
	public $secret_key = '';

	/**
	 * Flag for test mode
	 *
	 * @var mixed
	 */
	public $test = 'no';

	/**
	 * @var string
	 */
	public $test_secret_key = '';

	/**
	 * Receipt status
	 *
	 * @var bool
	 */
	public $ofd_status = false;

	/**
	 * Tax system
	 *
	 * @var string
	 */
	public $ofd_sno = 'usn';

	/**
	 * @var string
	 */
	public $ofd_nds = 'none';

	/**
	 * @var string
	 */
	public $ofd_payment_method = '';

	/**
	 * @var string
	 */
	public $ofd_payment_object = '';

	/**
	 * Page skipping
	 *
	 * @var string
	 */
	public $page_skipping = 'no';

	/**
	 * Max receipt items
	 *
	 * @var int
	 */
	protected $receipt_items_limit = 100;

	/**
	 * Available only for shipping
	 *
	 * @var array|false
	 */
	protected $available_shipping = false;

	/**
	 * Wc_Unitpay_Method constructor
	 */
	public function __construct()
	{
		/**
		 * The gateway shows fields on the checkout OFF
		 */
		$this->has_fields = false;

		/**
		 * Admin title
		 */
		$this->method_title = __('Unitpay', 'wc-unitpay');

		/**
		 * Admin method description
		 */
		$this->method_description = __('Pay via Unitpay.', 'wc-unitpay');

		/**
		 * Init
		 */
		$this->init_logger();
		$this->init_filters();
		$this->init_form_fields();
		$this->init_settings();
		$this->init_options();
		$this->init_actions();

		/**
		 * Save options
		 */
		if(current_user_can('manage_options') && is_admin())
		{
			$this->process_options();
		}

		/**
		 * Gateway allowed?
		 */
		if($this->is_available_front() === false)
		{
			$this->enabled = false;
		}

		if(false === is_admin())
		{
			/**
			 * Receipt page
			 */
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'), 10);

			/**
			 * Auto redirect
			 */
			add_action('wc_unitpay_input_payment_notifications', array($this, 'input_payment_notifications_redirect_by_form'), 20);

			/**
			 * Payment listener/API hook
			 */
			add_action('woocommerce_api_wc_' . $this->id, array($this, 'input_payment_notifications'), 10);
		}
	}

	/**
	 * Admin options
	 */
	public function process_options()
	{
		/**
		 * Options save
		 */
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'), 10);

		/**
		 * Update last version
		 */
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'wc_unitpay_last_settings_update_version'), 10);
	}

	/**
	 * Logger
	 */
	public function init_logger()
	{
		if($this->get_option('logger', '') !== '')
		{
			$level = $this->get_option('logger');

			wc_unitpay_logger()->set_level($level);

			$file_name = WC_Unitpay()->get_logger_filename();

			wc_unitpay_logger()->set_name($file_name);
		}
	}

	/**
	 * Initialize filters
	 */
	public function init_filters()
	{
		add_filter('wc_unitpay_init_form_fields', array($this, 'init_form_fields_main'), 10);
		add_filter('wc_unitpay_init_form_fields', array($this, 'init_form_fields_test_payments'), 20);
		add_filter('wc_unitpay_init_form_fields', array($this, 'init_form_fields_interface'), 30);
		add_filter('wc_unitpay_init_form_fields', array($this, 'init_form_fields_ofd'), 40);
		add_filter('wc_unitpay_init_form_fields', array($this, 'init_form_fields_order_notes'), 45);
		add_filter('wc_unitpay_init_form_fields', array($this, 'init_form_fields_technical'), 50);
	}

	/**
	 * Init actions
	 */
	public function init_actions()
	{
		/**
		 * Payment fields description show
		 */
		add_action('wc_unitpay_payment_fields_show', array($this, 'payment_fields_description_show'), 10);

		/**
		 * Payment fields test mode show
		 */
		if($this->get_test() === 'yes' && $this->get_option('test_mode_checkout_notice', 'no') === 'yes')
		{
			add_action('wc_unitpay_payment_fields_after_show', array($this, 'payment_fields_test_mode_show'), 10);
		}

		/**
		 * Receipt form show
		 */
		add_action('wc_unitpay_receipt_page_show', array($this, 'wc_unitpay_receipt_page_show_form'), 10);
	}

	/**
	 * Update plugin version at settings update
	 */
	public function wc_unitpay_last_settings_update_version()
	{
		$result = update_option('wc_unitpay_last_settings_update_version', WC_UNITPAY_VERSION);

		if($result)
		{
			wc_unitpay_logger()->info('wc_unitpay_last_settings_update_version: success');
		}
		else
		{
			wc_unitpay_logger()->warning('wc_unitpay_last_settings_update_version: not updated');
		}
	}

	/**
	 * Init gateway options
	 */
	public function init_options()
	{
		/**
		 * Gateway not enabled?
		 */
		if($this->get_option('enabled', 'no') !== 'yes')
		{
			$this->enabled = false;
		}

		/**
		 * Page skipping enabled?
		 */
		if($this->get_option('page_skipping', 'no') === 'yes')
		{
			$this->set_page_skipping('yes');
		}

		/**
		 * Title for user interface
		 */
		$this->title = $this->get_option('title', '');

		/**
		 * Set description
		 */
		$this->description = $this->get_option('description', '');

		/**
		 * Testing?
		 */
		$this->set_test($this->get_option('test', 'yes'));

		/**
		 * Default language for Unitpay interface
		 */
		$this->set_user_interface_language($this->get_option('language'));

		/**
		 * Automatic language
		 */
		if($this->get_option('language_auto', 'no') === 'yes')
		{
			$lang = get_locale();
			switch($lang)
			{
				case 'en_EN':
					$this->set_user_interface_language('en');
					break;
				default:
					$this->set_user_interface_language('ru');
					break;
			}
		}

		/**
		 * Set order button text
		 */
		$this->order_button_text = $this->get_option('order_button_text');

		/**
		 * Ofd
		 */
		if($this->get_option('ofd_status', 'no') === 'yes')
		{
			$this->set_ofd_status(true);
		}

		/**
		 * Ofd sno
		 */
		$ofd_sno_code = $this->get_option('ofd_sno', '');
		if($ofd_sno_code !== '')
		{
			$ofd_sno = 'osn';

			if($ofd_sno_code == '1')
			{
				$ofd_sno = 'usn_income';
			}

			if($ofd_sno_code == '2')
			{
				$ofd_sno = 'usn_income_outcome';
			}

			if($ofd_sno_code == '3')
			{
				$ofd_sno = 'envd';
			}

			if($ofd_sno_code == '4')
			{
				$ofd_sno = 'esn';
			}

			if($ofd_sno_code == '5')
			{
				$ofd_sno = 'patent';
			}

			$this->set_ofd_sno($ofd_sno);
		}

		/**
		 * Ofd nds
		 */
		$ofd_nds_code = $this->get_option('ofd_nds', '');
		if($ofd_nds_code !== '')
		{
			$ofd_nds = 'none';

			if($ofd_nds_code == '1')
			{
				$ofd_nds = 'vat0';
			}

			if($ofd_nds_code == '2')
			{
				$ofd_nds = 'vat10';
			}

			if($ofd_nds_code == '3')
			{
				$ofd_nds = 'vat20';
			}

			if($ofd_nds_code == '4')
			{
				$ofd_nds = 'vat110';
			}

			if($ofd_nds_code == '5')
			{
				$ofd_nds = 'vat120';
			}

			$this->set_ofd_nds($ofd_nds);
		}

		/**
		 * Set ofd_payment_method
		 */
		if($this->get_option('ofd_payment_method', '') !== '')
		{
			$this->set_ofd_payment_method($this->get_option('ofd_payment_method'));
		}

		/**
		 * Set ofd_payment_object
		 */
		if($this->get_option('ofd_payment_object', '') !== '')
		{
			$this->set_ofd_payment_object($this->get_option('ofd_payment_object'));
		}

		/**
		 * Set public key
		 */
		if($this->get_option('public_key', '') !== '')
		{
			$this->set_public_key($this->get_option('public_key'));
		}

		/**
		 * Set secret key
		 */
		if($this->get_option('secret_key', '') !== '')
		{
			$this->set_secret_key($this->get_option('secret_key'));
		}

		/**
		 * Set test secret key
		 */
		if($this->get_option('test_secret_key', '') !== '')
		{
			$this->set_test_secret_key($this->get_option('test_secret_key'));
		}

		/**
		 * Set icon
		 */
		if($this->get_option('enable_icon', 'no') === 'yes')
		{
			$this->icon = apply_filters('woocommerce_icon_unitpay', WC_UNITPAY_URL . 'assets/img/unitpay.png', $this->id);
		}

		$available_shipping = $this->get_option('available_shipping', '');
		if(is_array($available_shipping))
		{
			$this->set_available_shipping($available_shipping);
		}
	}

	/**
	 * Get user interface language
	 *
	 * @return string
	 */
	public function get_user_interface_language()
	{
		return $this->user_interface_language;
	}

	/**
	 * Set user interface language
	 *
	 * @param string $user_interface_language
	 */
	public function set_user_interface_language($user_interface_language)
	{
		$this->user_interface_language = $user_interface_language;
	}

	/**
	 * Get flag for test mode
	 *
	 * @return mixed
	 */
	public function get_test()
	{
		return $this->test;
	}

	/**
	 * Set flag for test mode
	 *
	 * @param mixed $test
	 */
	public function set_test($test)
	{
		$this->test = $test;
	}

	/**
	 * Get page skipping flag
	 *
	 * @return string
	 */
	public function get_page_skipping()
	{
		return $this->page_skipping;
	}

	/**
	 * Set page skipping flag
	 *
	 * @param string $page_skipping
	 */
	public function set_page_skipping($page_skipping)
	{
		$this->page_skipping = $page_skipping;
	}

	/**
	 * @return bool
	 */
	public function is_ofd_status()
	{
		return $this->ofd_status;
	}

	/**
	 * @param bool $ofd_status
	 */
	public function set_ofd_status($ofd_status)
	{
		$this->ofd_status = $ofd_status;
	}

	/**
	 * @return string
	 */
	public function get_ofd_sno()
	{
		return $this->ofd_sno;
	}

	/**
	 * @param string $ofd_sno
	 */
	public function set_ofd_sno($ofd_sno)
	{
		$this->ofd_sno = $ofd_sno;
	}

	/**
	 * @return string
	 */
	public function get_ofd_nds()
	{
		return $this->ofd_nds;
	}

	/**
	 * @param string $ofd_nds
	 */
	public function set_ofd_nds($ofd_nds)
	{
		$this->ofd_nds = $ofd_nds;
	}

	/**
	 * @return string
	 */
	public function get_ofd_payment_method()
	{
		return $this->ofd_payment_method;
	}

	/**
	 * @param string $ofd_payment_method
	 */
	public function set_ofd_payment_method($ofd_payment_method)
	{
		$this->ofd_payment_method = $ofd_payment_method;
	}

	/**
	 * @return string
	 */
	public function get_ofd_payment_object()
	{
		return $this->ofd_payment_object;
	}

	/**
	 * @param string $ofd_payment_object
	 */
	public function set_ofd_payment_object($ofd_payment_object)
	{
		$this->ofd_payment_object = $ofd_payment_object;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @return void
	 */
	public function init_form_fields()
	{
		$this->form_fields = apply_filters('wc_unitpay_init_form_fields', []);
	}

	/**
	 * Add fields for main settings
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_main($fields)
	{
		$fields['main'] = array
		(
			'title'       => __('Main settings', 'wc-unitpay'),
			'type'        => 'title',
			'description' => __('Without these settings, the payment gateway will not work. Be sure to make settings in this block.', 'wc-unitpay'),
		);

		$fields['enabled'] = array
		(
			'title'       => __('Online / Offline', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Tick the checkbox if you need to activate the payment gateway.', 'wc-unitpay'),
			'description' => __('On disconnection, the payment gateway will not be available for selection on the site. It is useful for payments through subsidiaries, or just in case of temporary disconnection.', 'wc-unitpay'),
			'default'     => 'off'
		);

		$fields['public_key'] = array
		(
			'title'       => __('Public Key', 'wc-unitpay'),
			'type'        => 'text',
			'description' => __('Copy Public Key from your account page in unitpay system.', 'wc-unitpay'),
			'default'     => ''
		);

		$fields['secret_key'] = array
		(
			'title'       => __('Secret Key', 'wc-unitpay'),
			'type'        => 'text',
			'description' => __('Copy Secret Key from your account page in unitpay system.', 'wc-unitpay'),
			'default'     => ''
		);

		$result_url_description = '<p class="input-text regular-input unitpay_urls">' . WC_Unitpay()->get_result_url() . '</p>' . __('Address to notify the site of the results of operations in the background. Copy the address and enter it in your personal account UNITPAY in the technical settings. Notification method: POST.', 'wc-unitpay');

		$fields['result_url'] = array
		(
			'title'       => __('Result Url', 'wc-unitpay'),
			'type'        => 'text',
			'disabled'    => true,
			'description' => $result_url_description,
			'default'     => ''
		);

		$success_url_description = '<p class="input-text regular-input unitpay_urls">' . WC_Unitpay()->get_success_url() . '</p>' . __('The address for the user to go to the site after successful payment. Copy the address and enter it in your personal account UNITPAY in the technical settings. Notification method: POST. You can specify other addresses of your choice.', 'wc-unitpay');

		$fields['success_url'] = array
		(
			'title'       => __('Success Url', 'wc-unitpay'),
			'type'        => 'text',
			'disabled'    => true,
			'description' => $success_url_description,
			'default'     => ''
		);

		$fail_url_description = '<p class="input-text regular-input unitpay_urls">' . WC_Unitpay()->get_fail_url() . '</p>' . __('The address for the user to go to the site, after payment with an error. Copy the address and enter it in your personal account UNITPAY in the technical settings. Notification method: POST. You can specify other addresses of your choice.', 'wc-unitpay');

		$fields['fail_url'] = array
		(
			'title'       => __('Fail Url', 'wc-unitpay'),
			'type'        => 'text',
			'disabled'    => true,
			'description' => $fail_url_description,
			'default'     => ''
		);

		return $fields;
	}

	/**
	 * Add settings for test payments
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_test_payments($fields)
	{
		$fields['test_payments'] = array
		(
			'title'       => __('Parameters for test payments', 'wc-unitpay'),
			'type'        => 'title',
			'description' => __('Passwords and hashing algorithms for test payments differ from those specified for real payments.', 'wc-unitpay'),
		);

		$fields['test'] = array
		(
			'title'       => __('Test mode', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'   => __('Select the checkbox to enable this feature. Default is enabled.', 'wc-unitpay'),
			'description' => __('When you activate the test mode, no funds will be debited. In this case, the payment gateway will only be displayed when you log in with an administrator account. This is done in order to protect you from false orders.', 'wc-unitpay'),
			'default'     => 'yes'
		);

		$fields['test_secret_key'] = array
		(
			'title'       => __('Secret Key', 'wc-unitpay'),
			'type'        => 'text',
			'description' => __('Copy Secret Key from your account page in unitpay system.', 'wc-unitpay'),
			'default'     => ''
		);

		$fields['test_mode_checkout_notice'] = array
		(
			'title'   => __('Test notification display on the test mode', 'wc-unitpay'),
			'type'    => 'checkbox',
			'label'   => __('Select the checkbox to enable this feature. Default is enabled.', 'wc-unitpay'),
			'description' => __('A notification about the activated test mode will be displayed when the payment.', 'wc-unitpay'),
			'default' => 'yes'
		);

		return $fields;
	}

	/**
	 * Add settings for sub methods
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_sub_methods($fields)
	{
		$fields['title_sub_methods'] = array
		(
			'title' => __('Sub methods', 'wc-unitpay'),
			'type' => 'title',
			'description' => __('General settings for the sub methods of payment.', 'wc-unitpay'),
		);

		$fields['sub_methods'] = array
		(
			'title' => __('Enable sub methods', 'wc-unitpay'),
			'type' => 'checkbox',
			'label' => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Use of all mechanisms add a child of payment methods.', 'wc-unitpay'),
			'default' => 'no'
		);

		$fields['sub_methods_check_available'] = array
		(
			'title' => __('Check available via the API', 'wc-unitpay'),
			'type' => 'checkbox',
			'label' => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Check whether child methods are currently available for payment.', 'wc-unitpay'),
			'default' => 'no'
		);

		$fields['rates_merchant'] = array
		(
			'title' => __('Show the total amount including the fee', 'wc-unitpay'),
			'type' => 'checkbox',
			'label' => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('If you enable this option, the exact amount payable, including fees, will be added to the payment method headers.', 'wc-unitpay'),
			'default' => 'no'
		);

		return $fields;
	}

	/**
	 * Add settings for interface
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_interface($fields)
	{
		$fields['interface'] = array
		(
			'title'       => __('Interface', 'wc-unitpay'),
			'type'        => 'title',
			'description' => __('Customize the appearance. Can leave it at that.', 'wc-unitpay'),
		);

		$fields['enable_icon'] = array
		(
			'title'   => __('Show icon?', 'wc-unitpay'),
			'type'    => 'checkbox',
			'label'   => __('Select the checkbox to enable this feature. Default is enabled.', 'wc-unitpay'),
			'default' => 'yes',
			'description' => __('Next to the name of the payment method will display the logo Unitpay.', 'wc-unitpay'),
		);

		$fields['language'] = array
		(
			'title'       => __('Language interface', 'wc-unitpay'),
			'type'        => 'select',
			'options'     => array
			(
				'ru' => __('Russian', 'wc-unitpay'),
				'en' => __('English', 'wc-unitpay')
			),
			'description' => __('What language interface displayed for the customer on Unitpay?', 'wc-unitpay'),
			'default'     => 'ru'
		);

		$fields['language_auto'] = array
		(
			'title'       => __('Language based on the locale?', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'   => __('Enable user language automatic detection?', 'wc-unitpay'),
			'description' => __('Automatic detection of the users language from the WordPress environment.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['page_skipping'] = array
		(
			'title'       => __('Skip the received order page?', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'   => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('This setting is used to reduce actions when users switch to payment.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['title'] = array
		(
			'title'       => __('Title', 'wc-unitpay'),
			'type'        => 'text',
			'description' => __('This is the name that the user sees during the payment.', 'wc-unitpay'),
			'default'     => __('Unitpay', 'wc-unitpay')
		);

		$fields['order_button_text'] = array
		(
			'title'       => __('Order button text', 'wc-unitpay'),
			'type'        => 'text',
			'description' => __('This is the button text that the user sees during the payment.', 'wc-unitpay'),
			'default'     => __('Goto pay', 'wc-unitpay')
		);

		$fields['description'] = array
		(
			'title'       => __('Description', 'wc-unitpay'),
			'type'        => 'textarea',
			'description' => __('Description of the method of payment that the customer will see on our website.', 'wc-unitpay'),
			'default'     => __('Payment via Unitpay.', 'wc-unitpay')
		);

		return $fields;
	}

	/**
	 * Add settings for OFD
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_ofd($fields)
	{
		$fields['ofd'] = array
		(
			'title'       => __('Cart content sending (54fz)', 'wc-unitpay'),
			'type'        => 'title',
			'description' => __('These settings are required only for legal entities in the absence of its cash machine.', 'wc-unitpay'),
		);

		$fields['ofd_status'] = array
		(
			'title'       => __('The transfer of goods', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('When you select the option, a check will be generated and sent to the tax and customer. When used, you must set up the VAT of the items sold. VAT is calculated according to the legislation of the Russian Federation. There may be differences in the amount of VAT with the amount calculated by the store.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['ofd_sno'] = array
		(
			'title'   => __('Taxation system', 'wc-unitpay'),
			'type'    => 'select',
			'default' => '0',
			'options' => array
			(
				'0' => __('General', 'wc-unitpay'),
				'1' => __('Simplified, income', 'wc-unitpay'),
				'2' => __('Simplified, income minus consumption', 'wc-unitpay'),
				'3' => __('Single tax on imputed income', 'wc-unitpay'),
				'4' => __('Single agricultural tax', 'wc-unitpay'),
				'5' => __('Patent system of taxation', 'wc-unitpay'),
			),
		);

		$fields['ofd_nds'] = array
		(
			'title'   => __('Default VAT rate', 'wc-unitpay'),
			'type'    => 'select',
			'default' => '0',
			'options' => array
			(
				'0' => __('Without the vat', 'wc-unitpay'),
				'1' => __('VAT 0%', 'wc-unitpay'),
				'2' => __('VAT 10%', 'wc-unitpay'),
				'3' => __('VAT 20%', 'wc-unitpay'),
				'4' => __('VAT receipt settlement rate 10/110', 'wc-unitpay'),
				'5' => __('VAT receipt settlement rate 20/120', 'wc-unitpay'),
			),
		);

		$fields['ofd_payment_method'] = array
		(
			'title'       => __('Indication of the calculation method', 'wc-unitpay'),
			'description' => __('The parameter is optional. If this parameter is not configured, the check will indicate the default value of the parameter from the Personal account.', 'wc-unitpay'),
			'type'        => 'select',
			'default'     => '',
			'options'     => array
			(
				''                => __('Default in Unitpay', 'wc-unitpay'),
				'full_prepayment' => __('Prepayment 100%', 'wc-unitpay'),
				'prepayment'      => __('Partial prepayment', 'wc-unitpay'),
				'advance'         => __('Advance', 'wc-unitpay'),
				'full_payment'    => __('Full settlement', 'wc-unitpay'),
				'partial_payment' => __('Partial settlement and credit', 'wc-unitpay'),
				'credit'          => __('Transfer on credit', 'wc-unitpay'),
				'credit_payment'  => __('Credit payment', 'wc-unitpay')
			),
		);

		$fields['ofd_payment_object'] = array
		(
			'title'       => __('Sign of the subject of calculation', 'wc-unitpay'),
			'description' => __('The parameter is optional. If this parameter is not configured, the check will indicate the default value of the parameter from the Personal account.', 'wc-unitpay'),
			'type'        => 'select',
			'default'     => '',
			'options'     => array
			(
				''                      => __('Default in Unitpay', 'wc-unitpay'),
				'commodity'             => __('Product', 'wc-unitpay'),
				'excise'                => __('Excisable goods', 'wc-unitpay'),
				'job'                   => __('Work', 'wc-unitpay'),
				'service'               => __('Service', 'wc-unitpay'),
				'gambling_bet'          => __('Gambling rate', 'wc-unitpay'),
				'gambling_prize'        => __('Gambling win', 'wc-unitpay'),
				'lottery'               => __('Lottery ticket', 'wc-unitpay'),
				'lottery_prize'         => __('Winning the lottery', 'wc-unitpay'),
				'intellectual_activity' => __('Results of intellectual activity', 'wc-unitpay'),
				'payment'               => __('Payment', 'wc-unitpay'),
				'agent_commission'      => __('Agency fee', 'wc-unitpay'),
				'composite'             => __('Compound subject of calculation', 'wc-unitpay'),
				'another'               => __('Another object of the calculation', 'wc-unitpay'),
				'property_right'        => __('Property right', 'wc-unitpay'),
				'non-operating_gain'    => __('Extraordinary income', 'wc-unitpay'),
				'insurance_premium'     => __('Insurance premium', 'wc-unitpay'),
				'sales_tax'             => __('Sales tax', 'wc-unitpay'),
				'resort_fee'            => __('Resort fee', 'wc-unitpay')
			),
		);

		return $fields;
	}

	/**
	 * Add settings for order notes
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_order_notes($fields)
	{
		$fields['orders_notes'] = array
		(
			'title'       => __('Orders notes', 'wc-unitpay'),
			'type'        => 'title',
			'description' => __('Settings for adding notes to orders. All are off by default.', 'wc-unitpay'),
		);

		$fields['orders_notes_unitpay_request_validate_error'] = array
		(
			'title'       => __('Errors when verifying the signature of requests', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Recording a errors when verifying the signature of requests from Unitpay.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['orders_notes_process_payment'] = array
		(
			'title'       => __('Process payments', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Recording information about the beginning of the payment process by the user.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['orders_notes_unitpay_paid_success'] = array
		(
			'title'       => __('Successful payments', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Recording information about received requests with successful payment.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['orders_notes_unitpay_request_result'] = array
		(
			'title'       => __('Background requests', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Recording information about the background queries about transactions from Unitpay.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['orders_notes_unitpay_request_fail'] = array
		(
			'title'       => __('Failed requests', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Recording information about the clients return to the canceled payment page.', 'wc-unitpay'),
			'default'     => 'no'
		);

		$fields['orders_notes_unitpay_request_success'] = array
		(
			'title'       => __('Success requests', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Recording information about the clients return to the success payment page.', 'wc-unitpay'),
			'default'     => 'no'
		);

		return $fields;
	}

	/**
	 * Add settings for technical
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function init_form_fields_technical($fields)
	{
		$fields['technical'] = array
		(
			'title'       => __('Technical details', 'wc-unitpay'),
			'type'        => 'title',
			'description' => __('Setting technical parameters. Used by technical specialists. Can leave it at that.', 'wc-unitpay'),
		);

		$logger_path = wc_unitpay_logger()->get_path() . '/' . wc_unitpay_logger()->get_name();

		$fields['logger'] = array
		(
			'title'       => __('Logging', 'wc-unitpay'),
			'type'        => 'select',
			'description' => __('You can enable gateway logging, specify the level of error that you want to benefit from logging. All sensitive data in the report are deleted. By default, the error rate should not be less than ERROR.', 'wc-unitpay') . '<br/>' . __('Current file: ', 'wc-unitpay') . '<b>' . $logger_path . '</b>',
			'default'     => '400',
			'options'     => array
			(
				''    => __('Off', 'wc-unitpay'),
				'100' => 'DEBUG',
				'200' => 'INFO',
				'250' => 'NOTICE',
				'300' => 'WARNING',
				'400' => 'ERROR',
				'500' => 'CRITICAL',
				'550' => 'ALERT',
				'600' => 'EMERGENCY'
			)
		);

		$fields['cart_clearing'] = array
		(
			'title'       => __('Cart clearing', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Clean the customers cart if payment is successful? If so, the shopping cart will be cleaned. If not, the goods already purchased will most likely remain in the shopping cart.', 'wc-unitpay'),
			'default'     => 'no',
		);

		$fields['fail_set_order_status_failed'] = array
		(
			'title'       => __('Mark order as cancelled?', 'wc-unitpay'),
			'type'        => 'checkbox',
			'label'       => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('Change the status of the order to canceled when the user cancels the payment. The status changes when the user returns to the cancelled payment page.', 'wc-unitpay'),
			'default'     => 'no',
		);

		if(version_compare(wc_unitpay_get_wc_version(), '3.2.0', '>='))
		{
			$options = array();

			try
			{
				$data_store = WC_Data_Store::load('shipping-zone');
			}
			catch(Exception $e)
			{
				return $fields;
			}

			$raw_zones = $data_store->get_zones();

			foreach($raw_zones as $raw_zone)
			{
				$zones[] = new WC_Shipping_Zone($raw_zone);
			}

			$zones[] = new WC_Shipping_Zone(0);

			foreach(WC()->shipping()->load_shipping_methods() as $method)
			{
				$options[$method->get_method_title()] = array();

				// Translators: %1$s shipping method name.
				$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'woocommerce'), $method->get_method_title());

				foreach($zones as $zone)
				{
					$shipping_method_instances = $zone->get_shipping_methods();

					foreach($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance)
					{
						if($shipping_method_instance->id !== $method->id)
						{
							continue;
						}

						$option_id = $shipping_method_instance->get_rate_id();

						// Translators: %1$s shipping method title, %2$s shipping method id.
						$option_instance_title = sprintf(__('%1$s (#%2$s)', 'woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

						// Translators: %1$s zone name, %2$s shipping method instance name.
						$option_title = sprintf(__('%1$s &ndash; %2$s', 'woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'woocommerce'), $option_instance_title);

						$options[$method->get_method_title()][$option_id] = $option_title;
					}
				}
			}

			$fields['available_shipping'] =  array
			(
				'title' => __('Enable for shipping methods', 'wc-unitpay'),
				'type' => 'multiselect',
				'class' => 'wc-enhanced-select',
				'css' => 'width: 400px;',
				'default' => '',
				'description' => __('If only available for certain methods, set it up here. Leave blank to enable for all methods.', 'wc-unitpay'),
				'options' => $options,
				'custom_attributes' => array
				(
					'data-placeholder' => __('Select shipping methods', 'wc-unitpay'),
				),
			);
		}

		$fields['commission_merchant'] = array
		(
			'title' => __('Payment of the commission for the buyer', 'wc-unitpay'),
			'type' => 'checkbox',
			'label' => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('When you enable this feature, the store will pay all customer Commission costs. Works only when you select a payment method on the site and for stores individuals.', 'wc-unitpay'),
			'default' => 'no'
		);

		$fields['commission_merchant_by_cbr'] = array
		(
			'title' => __('Preliminary conversion of order currency into roubles for commission calculation', 'wc-unitpay'),
			'type' => 'checkbox',
			'label' => __('Select the checkbox to enable this feature. Default is disabled.', 'wc-unitpay'),
			'description' => __('If the calculation of the customer commission is included and the order is not in roubles, the order will be converted to roubles based on data from the Central Bank of Russia.
			This is required due to poor Unitpay API.', 'wc-unitpay'),
			'default' => 'no'
		);

		return $fields;
	}

	/**
	 * @return array
	 */
	public function get_currency_all()
	{
		return $this->currency_all;
	}

	/**
	 * @param array $currency_all
	 */
	public function set_currency_all($currency_all)
	{
		$this->currency_all = $currency_all;
	}

	/**
	 * Check currency support
	 *
	 * @param string $currency
	 *
	 * @return bool
	 */
	public function is_support_currency($currency = '')
	{
		if($currency === '')
		{
			$currency = WC_Unitpay()->get_wc_currency();
		}

		if(!in_array($currency, $this->get_currency_all(), false))
		{
			wc_unitpay_logger()->alert('is_support_currency: currency not support');
			return false;
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function get_available_shipping()
	{
		return $this->available_shipping;
	}

	/**
	 * @param array $available_shipping
	 */
	public function set_available_shipping($available_shipping)
	{
		$this->available_shipping = $available_shipping;
	}

	/**
	 * Check available in front
	 */
	public function is_available_front()
	{
		wc_unitpay_logger()->info('is_available_front: start');

		/**
		 * Check allow currency
		 */
		if($this->is_support_currency() === false)
		{
			wc_unitpay_logger()->alert('is_available_front: is_support_currency');
			return false;
		}

		/**
		 * Check test mode and admin rights
		 *
		 * @todo сделать возможность тестирования не только админами
		 */
		if($this->get_test() === 'yes' && false === current_user_can('manage_options'))
		{
			wc_unitpay_logger()->alert('is_available_front: test mode only admin');
			return false;
		}

		wc_unitpay_logger()->info('is_available_front: success');

		return true;
	}

	/**
	 * Output settings screen
	 */
	public function admin_options()
	{
		wp_enqueue_style('unitpay-admin-styles', WC_UNITPAY_URL . 'assets/css/main.css');

		add_filter('wc_unitpay_widget_status_color', array($this, 'admin_right_widget_status_content_color'), 20);
		add_action('wc_unitpay_widget_status_content', array($this, 'admin_right_widget_status_content_logger'), 10);
		add_action('wc_unitpay_widget_status_content', array($this, 'admin_right_widget_status_content_currency'), 20);
		add_action('wc_unitpay_widget_status_content', array($this, 'admin_right_widget_status_content_test'), 20);

		// hook
		do_action('wc_unitpay_admin_options_before_show');

		echo '<h2>' . esc_html($this->get_method_title());
		wc_back_link(__('Return to payment gateways', 'wc-unitpay'), admin_url('admin.php?page=wc-settings&tab=checkout'));
		echo '</h2>';

		// hook
		do_action('wc_unitpay_admin_options_method_description_before_show');

		echo wp_kses_post(wpautop($this->get_method_description()));

		// hook
		do_action('wc_unitpay_admin_options_method_description_after_show');

		// hook
		do_action('wc_unitpay_admin_options_form_before_show');

		echo '<table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>';

		// hook
		do_action('wc_unitpay_admin_options_form_after_show');

		// hook
		do_action('wc_unitpay_admin_options_after_show');
	}

	/**
	 * There are no payment fields for sprypay, but we want to show the description if set
	 */
	public function payment_fields()
	{
		// hook
		do_action('wc_' . $this->id . '_payment_fields_before_show');

		// hook
		do_action('wc_' . $this->id . '_payment_fields_show');

		// hook
		do_action('wc_' . $this->id . '_payment_fields_after_show');
	}

	/**
	 * Show description on site
	 */
	public function payment_fields_description_show()
	{
		if($this->description)
		{
			echo wpautop(wptexturize($this->description));
		}
	}

	/**
	 * Show test mode on site
	 *
	 * @return void
	 */
	public function payment_fields_test_mode_show()
	{
		echo '<div style="padding:5px; border-radius:20px; background-color: #ff8982;text-align: center;">';
		echo __('TEST mode is active. Payment will not be charged. After checking, disable this mode.', 'wc-unitpay');
		echo '</div>';
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment($order_id)
	{
		wc_unitpay_logger()->info('process_payment: start');

		$order = wc_get_order($order_id);

		if($order === false)
		{
			wc_unitpay_logger()->error('process_payment: $order === false');

			if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_process_payment') === 'yes')
			{
				$order->add_order_note(__('The customer clicked the payment button, but an error occurred while getting the order object.', 'wc-unitpay'));
			}

			return array
			(
				'result' => 'failure',
				'redirect' => ''
			);
		}

		// hook
		do_action('wc_unitpay_before_process_payment', $order_id, $order);

		wc_unitpay_logger()->debug('process_payment: order', $order);

		if($this->get_page_skipping() === 'yes')
		{
			wc_unitpay_logger()->info('process_payment: page skipping, success');

			if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_process_payment') === 'yes')
			{
				$order->add_order_note(__('The customer clicked the payment button and was sent to the side of the Unitpay.', 'wc-unitpay'));
			}

			return array
			(
				'result' => 'success',
				'redirect' => $this->get_url_auto_redirect($order_id)
			);
		}

		wc_unitpay_logger()->info('process_payment: success');

		if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_process_payment') === 'yes')
		{
			$order->add_order_note(__('The customer clicked the payment button and was sent to the page of the received order.', 'wc-unitpay'));
		}

		return array
		(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url(true)
		);
	}

	/**
	 * Receipt page
	 *
	 * @param $order
	 *
	 * @return void
	 */
	public function receipt_page($order)
	{
		// hook
		do_action('wc_' . $this->id . '_receipt_page_before_show', $order);

		// hook
		do_action('wc_' . $this->id . '_receipt_page_show', $order);

		// hook
		do_action('wc_' . $this->id . '_receipt_page_after_show', $order);
	}

	/**
	 * @param $order
	 *
	 * @return void
	 */
	public function wc_unitpay_receipt_page_show_form($order)
	{
		echo $this->generate_form($order);
	}

	/**
	 * Generate payments form
	 *
	 * @param $order_id
	 *
	 * @return string - payment form
	 **/
	public function generate_form($order_id)
	{
		wc_unitpay_logger()->info('generate_form: start');

		$order = wc_get_order($order_id);
		if(!is_object($order))
		{
			wc_unitpay_logger()->error('generate_form: $order', $order);
			die('Generate form error. Order not found.');
		}

		wc_unitpay_logger()->debug('generate_form: $order', $order);

		$args = [];

		$out_sum = number_format($order->get_total(), 2, '.', '');
		$args['sum'] = $out_sum;

		$args['account'] = $order_id;
		$args['desc'] = __('Order number: ' . $order_id, 'wc-unitpay');

		/**
		 * Rewrite currency from order
		 */
		if(WC_Unitpay()->get_wc_currency() !== $order->get_currency('view'))
		{
			wc_unitpay_logger()->info('generate_form: rewrite currency' . $order->get_currency());
			WC_Unitpay()->set_wc_currency($order->get_currency());
		}

		/**
		 * Set currency to Unitpay
		 */
		$args['currency'] = 'RUB';
		switch(WC_Unitpay()->get_wc_currency())
		{
			case 'USD':
				$args['currency'] = 'USD';
				break;
			case 'EUR':
				$args['currency'] = 'EUR';
				break;
			case 'KZT':
				$args['currency'] = 'KZT';
				break;
		}

		if($this->get_test() === 'yes')
		{
			wc_unitpay_logger()->info('generate_form: test mode active');
			$args['test'] = 1;
		}

		/**
		 * Receipt
		 */
		$receipt_json = '';
		if($this->is_ofd_status() === true)
		{
			wc_unitpay_logger()->info('generate_form: fiscal active');

			$receipt['sno'] = $this->get_ofd_sno();
			$receipt['items'] = $this->generate_receipt_items($order);

			$receipt_json = urlencode(json_encode($receipt, 256));

			wc_unitpay_logger()->debug('generate_form: $receipt_result', $receipt_json);
		}

		/**
		 * Signature
		 */
		if($receipt_json !== '')
		{
			$args['Receipt'] = $receipt_json;
		}

		$args['signature'] = hash('sha256', join('{up}', array
		(
			$args['account'],
			$args['currency'],
			$args['desc'],
			$args['sum'],
			$this->get_secret_key()
		)));

		/**
		 * Language (culture)
		 */
		$args['locale'] = $this->get_user_interface_language();

		/**
		 * Execute filter wc_unitpay_form_args
		 */
		$args = apply_filters('wc_unitpay_payment_form_args', $args);

		wc_unitpay_logger()->debug('generate_form: final $args', $args);

		/**
		 * Form inputs generic
		 */
		$args_array = array();
		foreach ($args as $key => $value)
		{
			$args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
		}

		wc_unitpay_logger()->info('generate_form: success');

		return '<form action="https://unitpay.ru/pay/' . $this->get_public_key() . '" method="POST" id="wc_unitpay_payment_form" accept-charset="utf-8">' . "\n" .
		       implode("\n", $args_array) .
		       '<input type="submit" class="button alt" id="submit_wc_unitpay_payment_form" value="' . __('Pay', 'wc-unitpay') .
		       '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel & return to cart', 'wc-unitpay') . '</a>' . "\n" .
		       '</form>';
	}

	/**
	 * Generate receipt
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function generate_receipt_items($order)
	{
		$receipt_items = array();

		wc_unitpay_logger()->info('generate_receipt_items: start');

		/**
		 * Order items
		 */
		foreach($order->get_items() as $receipt_items_key => $receipt_items_value)
		{
			/**
			 * Quantity
			 */
			$item_quantity = $receipt_items_value->get_quantity();

			/**
			 * Total item sum
			 */
			$item_total = $receipt_items_value->get_total();

			/**
			 * Build positions
			 */
			$receipt_items[] = array
			(
				/**
				 * Название товара
				 *
				 * максимальная длина 128 символов
				 */
				'name' => $receipt_items_value['name'],

				/**
				 * Стоимость предмета расчета с учетом скидок и наценок
				 *
				 * Цена в рублях:
				 *  целая часть не более 8 знаков;
				 *  дробная часть не более 2 знаков.
				 */
				'sum' => intval($item_total),

				/**
				 * Количество/вес
				 *
				 * максимальная длина 128 символов
				 */
				'quantity' => intval($item_quantity),

				/**
				 * Tax
				 */
				'tax' => $this->get_ofd_nds(),

				/**
				 * Payment method
				 */
				'payment_method' => $this->get_ofd_payment_method(),

				/**
				 * Payment object
				 */
				'payment_object' => $this->get_ofd_payment_object(),
			);
		}

		/**
		 * Delivery
		 */
		if($order->get_shipping_total() > 0)
		{
			/**
			 * Build positions
			 */
			$receipt_items[] = array
			(
				/**
				 * Название товара
				 *
				 * максимальная длина 128 символов
				 */
				'name' => __('Delivery', 'wc-unitpay'),

				/**
				 * Стоимость предмета расчета с учетом скидок и наценок
				 *
				 * Цена в рублях:
				 *  целая часть не более 8 знаков;
				 *  дробная часть не более 2 знаков.
				 */
				'sum' => intval($order->get_shipping_total()),

				/**
				 * Количество/вес
				 *
				 * максимальная длина 128 символов
				 */
				'quantity' => 1,

				/**
				 * Tax
				 */
				'tax' => $this->get_ofd_nds(),

				/**
				 * Payment method
				 */
				'payment_method' => $this->get_ofd_payment_method(),

				/**
				 * Payment object
				 */
				'payment_object' => $this->get_ofd_payment_object(),
			);
		}

		wc_unitpay_logger()->info('generate_receipt_items: success');

		return $receipt_items;
	}

	/**
	 * Получение ссылки на автоматический редирект в робокассу
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function get_url_auto_redirect($order_id)
	{
		return get_site_url( null, '/?wc-api=wc_' . $this->id . '&action=redirect&order_id=' . $order_id);
	}

	/**
	 * Автоматический редирект на робокассу методом автоматической отправки формы
	 */
	public function input_payment_notifications_redirect_by_form()
	{
		if(false == isset($_GET['action']))
		{
			return;
		}

		if(false == isset($_GET['order_id']))
		{
			return;
		}

		if($_GET['action'] !== 'redirect')
		{
			return;
		}

		if($_GET['order_id'] === '')
		{
			return;
		}

		$order_id = $_GET['order_id'];

		/**
		 * Form data
		 */
		$form_data = $this->generate_form($order_id);

		/**
		 * Page data
		 */
		$page_data = '<html lang="ru"><body style="display: none;" onload="document.forms.wc_unitpay_payment_form.submit()">' . $form_data .'</body></html>';

		/**
		 * Echo form an die :(
		 */
		die($page_data);
	}

	/**
	 * @param $params
	 * @param $method
	 * @param $secret
	 *
	 * @return bool
	 */
	public function verify_signature($params, $method, $secret)
	{
		return $params['signature'] == $this->get_signature($method, $params, $secret);
	}

	/**
	 * @param $method
	 * @param array $params
	 * @param $secretKey
	 *
	 * @return string
	 */
	public function get_signature($method, array $params, $secretKey)
	{
		ksort($params);

		unset($params['sign'], $params['signature']);

		array_push($params, $secretKey);
		array_unshift($params, $method);

		return hash('sha256', join('{up}', $params));
	}

	/**
	 * @param $order
	 * @param $params
	 *
	 * @return array[]
	 */
	public function input_payment_notifications_result_check($order, $params)
	{
		$sum = number_format($order->order_total, 2, '.', '');
		$currency = $order->get_order_currency();

		$result = array
		(
			'result' => array('message' => __('Request successfully', 'wc-unitpay'))
		);

		if((float) $sum != (float) $params['orderSum'])
		{
			$result = array
			(
				'error' => array('message' => __('Wrong order sum', 'wc-unitpay'))
			);
		}
		elseif($currency != $params['orderCurrency'])
		{
			$result = array
			(
				'error' => array('message' => __('Wrong order currency', 'wc-unitpay'))
			);
		}

		return $result;
	}

	/**
	 * @param $order
	 * @param $params
	 *
	 * @return array
	 */
	public function input_payment_notifications_result_payment($order, $params)
	{
		$sum = number_format($order->order_total, 2, '.', '');
		$currency = $order->get_order_currency();

		if((float) $sum != (float) $params['orderSum'])
		{
			$result = array
			(
				'error' => array('message' => __('Wrong order sum', 'wc-unitpay'))
			);
		}
		elseif($currency != $params['orderCurrency'])
		{
			$result = array
			(
				'error' => array('message' => __('Wrong order currency', 'wc-unitpay'))
			);
		}
		else
		{
			$order->payment_complete();

			$result = array
			(
				'result' => array('message' => __('Request successfully', 'wc-unitpay'))
			);
		}

		return $result;
	}

	/**
	 * @param $order
	 * @param $params
	 *
	 * @return array
	 */
	public function input_payment_notifications_result_error($order, $params)
	{
		$order->update_status('failed', __('Payment error', 'wc-unitpay'));

		$result = array
		(
			'result' => array('message' => __('Request successfully', 'wc-unitpay'))
		);

		return $result;
	}

	/**
	 * @param $order
	 */
	public function input_payment_notifications_result($order)
	{
		$method = '';
		$params = array();
		$status_sign = false;

		if((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature'])))
		{
			$params = $_GET['params'];
			$method = $_GET['method'];

			$secret_key = $this->get_secret_key();
			$status_sign = $this->verify_signature($params, $method, $secret_key);
		}

		if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_unitpay_request_result') === 'yes')
		{
			//$order->add_order_note(sprintf(__('Unitpay request. Sum: %1$s. Signature: %2$s. Remote signature: %3$s', 'wc-unitpay'), $sum, $local_signature, $signature));
		}

		$result = array('error' => array('message' => __('Wrong signature', 'wc-unitpay')));

		if($status_sign)
		{
			switch($method)
			{
				case 'check':
					$result = $this->input_payment_notifications_result_check($order, $params);
					break;
				case 'pay':
					$result = $this->input_payment_notifications_result_payment($order, $params);
					break;
				case 'error':
					$result = $this->input_payment_notifications_result_error($order, $params);
					break;
				default:
					$result = array('error' => array('message' => __('Wrong method', 'wc-unitpay')));
					break;
			}
		}
		else
		{
			wc_unitpay_logger()->notice('input_payment_notifications: $signature !== $local_signature');

			if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_unitpay_request_validate_error') === 'yes')
			{
				//$order->add_order_note(sprintf(__('Validate hash error. Local: %1$s Remote: %2$s', 'wc-unitpay'), $local_signature, $signature));
			}
		}

		header('Content-type:application/json;  charset=utf-8');
		echo json_encode($result);
		die();
	}

	/**
	 * @param $order
	 */
	public function input_payment_notifications_fail($order)
	{
		wc_unitpay_logger()->info('input_payment_notifications: The order has not been paid');

		if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_unitpay_request_fail') === 'yes')
		{
			$order->add_order_note(__('Order cancellation. The client returned to the payment cancellation page.', 'wc-unitpay'));
		}

		if($this->get_option('fail_set_order_status_failed') === 'yes')
		{
			wc_unitpay_logger()->info('input_payment_notifications: fail_set_order_status_failed');

			$order->update_status('failed');
		}

		wc_unitpay_logger()->info('input_payment_notifications: redirect to order cancel page');
		wp_redirect(str_replace('&amp;', '&', $order->get_cancel_order_url()));
		die();
	}

	/**
	 * @param $order
	 */
	public function input_payment_notifications_success($order)
	{
		wc_unitpay_logger()->info('input_payment_notifications: Client return to success page');

		if(method_exists($order, 'add_order_note') && $this->get_option('orders_notes_unitpay_request_success') === 'yes')
		{
			$order->add_order_note(__('The client returned to the payment success page.', 'wc-unitpay'));
		}

		if($this->get_option('cart_clearing') === 'yes')
		{
			wc_unitpay_logger()->info('input_payment_notifications: clear cart');
			WC()->cart->empty_cart();
		}

		wc_unitpay_logger()->info('input_payment_notifications: redirect to success page');
		wp_redirect($this->get_return_url($order));
		die();
	}

	/**
	 * Check instant payment notification
	 *
	 * @return void
	 */
	public function input_payment_notifications()
	{
		wc_unitpay_logger()->info('input_payment_notifications: start');

		// hook
		do_action('wc_unitpay_input_payment_notifications');

		$action = '';
		if(isset($_REQUEST['action']))
		{
			$action = $_REQUEST['action'];
		}

		$order_id = 0;
		if(array_key_exists('account', $_REQUEST))
		{
			$order_id = $_REQUEST['account'];
		}

		$order = wc_get_order($order_id);

		if($order === false)
		{
			wc_unitpay_logger()->error('input_payment_notifications: order not found');

			$result = array
			(
				'error' => array('message' => __('Order not found', 'wc-unitpay'))
			);

			header('Content-type:application/json;  charset=utf-8');
			echo json_encode($result);
			die();
		}

		switch($action)
		{
			case 'fail':
				$this->input_payment_notifications_fail($order);
				break;
			case 'success':
				$this->input_payment_notifications_success($order);
				break;
			default:
				$this->input_payment_notifications_result($order);
				break;
		}

		wc_unitpay_logger()->info('input_payment_notifications: error, action not found');
		wp_die(__('Api request error. Action not found.', 'wc-unitpay'), 'Payment error', array('response' => '503'));
	}

	/**
	 * Check if the gateway is available for use
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$is_available = $this->enabled;

		if(WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total())
		{
			$is_available = false;
		}

		wc_unitpay_logger()->debug('is_available: parent $is_available', $is_available);

		if(is_array($this->get_available_shipping()) && !empty($this->get_available_shipping()) && version_compare(wc_unitpay_get_wc_version(), '3.2.0', '>='))
		{
			$order = null;
			$needs_shipping = false;

			// Test if shipping is needed first
			if(WC()->cart && WC()->cart->needs_shipping())
			{
				$needs_shipping = true;
			}
			elseif(is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay'))
			{
				$order_id = absint(get_query_var('order-pay'));
				$order = wc_get_order($order_id);

				// Test if order needs shipping
				if(0 < count($order->get_items()))
				{
					foreach($order->get_items() as $item)
					{
						$_product = $item->get_product();
						if($_product && $_product->needs_shipping())
						{
							$needs_shipping = true;
							break;
						}
					}
				}
			}

			$needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

			// Only apply if all packages are being shipped via chosen method
			if($needs_shipping && !empty($this->get_available_shipping()))
			{
				$order_shipping_items = is_object($order) ? $order->get_shipping_methods() : false;
				$chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

				if($order_shipping_items)
				{
					$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
				}
				else
				{
					$canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
				}

				if(!count($this->get_matching_rates($canonical_rate_ids)))
				{
					$is_available = false;
				}
			}
		}

		/**
		 * Change status from external code
		 */
		$is_available = apply_filters('wc_unitpay_method_get_available', $is_available);

		wc_unitpay_logger()->debug('is_available: $is_available', $is_available);

		return $is_available;
	}

	/**
	 * Widget status: test mode
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function admin_right_widget_status_content_test($content)
	{
		$message = __('active', 'wc-unitpay');
		$color = 'bg-warning';

		if('yes' !== $this->get_test())
		{
			$color = 'text-white bg-success';
			$message = __('inactive', 'wc-unitpay');
		}

		$content .= '<li class="list-group-item mb-0 ' . $color . '">'
		            . __('Test mode: ', 'wc-unitpay') . $message .
		            '</li>';

		return $content;
	}

	/**
	 * Widget status: currency
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function admin_right_widget_status_content_currency($content)
	{
		$color = 'bg-danger';

		if(true === $this->is_support_currency())
		{
			$color = 'bg-success';
		}

		$content .= '<li class="list-group-item mb-0 text-white ' . $color . '">'
		            . __('Currency: ', 'wc-unitpay') . WC_Unitpay()->get_wc_currency() .
		            '</li>';

		return $content;
	}

	/**
	 * Widget status: logger
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function admin_right_widget_status_content_logger($content)
	{
		if(wc_unitpay_logger()->get_level() < 200)
		{
			$content .= '<li class="list-group-item mb-0 text-white bg-warning">'
			            . __('The logging level is too low. Need to increase the level after debugging.', 'wc-unitpay') .
			            '</li>';
		}

		return $content;
	}

	/**
	 * Widget status: color
	 *
	 * @param $color
	 *
	 * @return string
	 */
	public function admin_right_widget_status_content_color($color)
	{
		if('yes' === $this->get_test())
		{
			$color = 'bg-warning';
		}
		elseif('' === $this->get_public_key() || '' === $this->get_secret_key())
		{
			$color = 'bg-warning';
		}
		elseif(wc_unitpay_logger()->get_level() < 200)
		{
			$color = 'bg-warning';
		}

		if(false === $this->is_support_currency())
		{
			$color = 'bg-danger';
		}

		return $color;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format
	 *
	 * @param array $order_shipping_items Array of WC_Order_Item_Shipping objects
	 *
	 * @return array $canonical_rate_ids Rate IDs in a canonical format
	 */
	protected function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
	{
		$canonical_rate_ids = array();

		foreach($order_shipping_items as $order_shipping_item)
		{
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * @return string
	 */
	public function get_public_key()
	{
		return $this->public_key;
	}

	/**
	 * @param string $public_key
	 */
	public function set_public_key($public_key)
	{
		$this->public_key = $public_key;
	}

	/**
	 * @return string
	 */
	public function get_secret_key()
	{
		return $this->secret_key;
	}

	/**
	 * @param string $secret_key
	 */
	public function set_secret_key($secret_key)
	{
		$this->secret_key = $secret_key;
	}

	/**
	 * @return string
	 */
	public function get_test_secret_key()
	{
		return $this->test_secret_key;
	}

	/**
	 * @param string $test_secret_key
	 */
	public function set_test_secret_key($test_secret_key)
	{
		$this->test_secret_key = $test_secret_key;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format
	 *
	 * @param array $chosen_package_rate_ids Rate IDs as generated by shipping methods.
	 * Can be anything if a shipping method doesn't honor WC conventions.
	 *
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	protected function get_canonical_package_rate_ids($chosen_package_rate_ids)
	{
		$shipping_packages = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if(!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids))
		{
			foreach($chosen_package_rate_ids as $package_key => $chosen_package_rate_id)
			{
				if(!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id]))
				{
					$chosen_rate = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @param array $rate_ids Rate ids to check
	 *
	 * @return mixed
	 */
	protected function get_matching_rates($rate_ids)
	{
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique(array_merge
        (
            array_intersect($this->get_available_shipping(), $rate_ids),
            array_intersect($this->get_available_shipping(), array_unique(array_map('wc_get_string_before_colon', $rate_ids)))
        ));
	}
}