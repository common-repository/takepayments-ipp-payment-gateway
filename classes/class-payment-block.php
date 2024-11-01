<?php

namespace Takepayments_Mercury;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;



final class Takepayments_mercury_Payment_block extends AbstractPaymentMethodType
{

	private $gateway;
	protected $name = 'tp_mercury';


	public function initialize()
	{
		require_once TAKEPAYMENTS_MERCURY_PATH . 'includes/payment-gateway/takepayments-mercury-gateway.php';

		$this->settings = get_option('woocommerce_tp_mercury_settings', []);
		if (!class_exists('WC_Takepayments_mercury_Gateway')) {
			$this->gateway  = new WC_Takepayments_mercury_Gateway;
		} else {
			$this->gateway  =   WC_Takepayments_MERCURY_Gateway;
		}
	}
	public function is_active()
	{
		return $this->gateway->is_available();
	}
	public function get_payment_method_script_handles()
	{
		$script_path       = TAKEPAYMENTS_MERCURY_PATH_URL . '/public/block/js/wc-payment-method-takepayments-mercury.js';
		$script_asset_path =  TAKEPAYMENTS_MERCURY_PATH . '/public/block/blocks.asset.php';
		$script_asset      = file_exists($script_asset_path)
			? require($script_asset_path)
			: array(
				'dependencies' => array(),
				'version'      => '1.0.0'
			);

		wp_register_script(
			'wc-takepayments-mercury-block',
			//$script_url,
			$script_path,
			$script_asset['dependencies'],

			$script_asset['version'],
			true
		);
		if ($this->settings['show_tp_styles'] === "yes") {
			wp_register_style('wc-takepayments-mercury-block-style', TAKEPAYMENTS_MERCURY_PATH_URL . '/public/stylesheet/mercury_stylesheet.css', []);
		}
        wp_register_style( 'tp_logo', false );
		wp_enqueue_style( 'tp_logo' );

		wp_add_inline_style( 'tp_logo', '.tp_logo {padding: 0 15px 0 0; }' );
		// if ( function_exists( 'wp_set_script_translations' ) ) {
		// 	wp_set_script_translations( 'wc-takepayments-mercury-block', 'woocommerce-gateway-dummy', WC_Dummy_Payments::plugin_abspath() . 'languages/' );
		// }

		return ['wc-takepayments-mercury-block'];
	}

	public function get_payment_method_data()
	{
	
		return [
			'title'                    => $this->settings['title'],
			'description'              => $this->settings['description'],
			'enableForVirtual'         => true,
			// 'enableForShippingMethods' => true,
			// 'supports'                 => ['products', 'refunds']
			'supports'    			  => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
			'amx_logo' 				  => $this->settings['amx_logo'] === "yes",
			'secured_by_tp_logo'	  => $this->settings['secured_by_tp_logo'] === "yes",
			'show_tp_styles'  		  => $this->settings['show_tp_styles'] === "yes",
			'plugin_path' 			  => TAKEPAYMENTS_MERCURY_PATH_URL,


		];
	}
}