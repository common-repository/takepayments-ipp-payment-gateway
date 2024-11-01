<?php
// Registers the Takepayments Mercury gateway with WooCommerce and registers the init function for the payment gatewy. Also adds the 
//settings link to the plugin list page. In the futue this will preform plugin updates.
namespace Takepayments_Mercury;

use WC_Takepayments_Mercury_Gateway;

class Takepayments_Mercury_Main
{

    function __construct()
    {
        $this->tp_mercury_load_dependencies();
        $this->tp_mercury_add_actions_and_filters();
    }
    protected function tp_mercury_load_dependencies()
    {
        require_once TAKEPAYMENTS_MERCURY_PATH . 'includes/payment-gateway/takepayments-mercury-gateway.php';
    }
    protected function tp_mercury_add_actions_and_filters()
    {
        // Hook fires when woocommerce is gathering information about the available payment gateways. 
        add_filter('woocommerce_payment_gateways', function ($gateways) {
            $gateways[] = 'Takepayments_Mercury\WC_Takepayments_mercury_Gateway';

            return $gateways;
        });
        // Init function for the payment gateway
        add_action('plugins_loaded', 'Takepayments_Mercury\takepayments_mercury_init');
        // Add the settings link to the plugin list page. 
        add_filter('plugin_action_links', function ($plugin_actions, $plugin_file) {
            $new_actions = [];
            if ('takepayments-ipp-payment-gateway/takepayments-mercury-init.php' === $plugin_file) {
                $new_actions['tp_mercury_settings'] = sprintf(__('<a href="%s">Settings</a>', 'tp_mercury'), esc_url(admin_url("admin.php?page=wc-settings&tab=checkout&section=tp_mercury")));
            }
            return array_merge($new_actions, $plugin_actions);
        }, 10, 5);
        add_action('woocommerce_blocks_loaded', function () {
            if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                require_once TAKEPAYMENTS_MERCURY_PATH . 'classes/class-payment-block.php';
                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                        $payment_method_registry->register(new Takepayments_Mercury_Payment_block);
                    }
                );
            }
        });
    }

    protected function check_for_update()
    {
    }
    protected function preform_update()
    {
    }
}
