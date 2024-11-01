<?php
/*
 * Plugin Name:       Takepayments Mercury Payment Gateway
 * Plugin URI:        https://www.takepayments.com/
 * Description:       Add Takepayments Mercury Gateway To Your WooCommerce Checkout.
 * Version:           1.0.1
 * Author:            Takepayments
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       takepayments-payments-mercury
 */


namespace Takepayments_Mercury;


if (!defined('WPINC')) {
  die;
}
define('TAKEPAYMENTS_MERCURY_PATH', plugin_dir_path(__FILE__));
define('TAKEPAYMENTS_MERCURY_PATH_URL', plugin_dir_URL(__FILE__));
define('TAKEPAYMENTS_MERCURY_VERSION',"1.0.1");

require_once TAKEPAYMENTS_MERCURY_PATH . 'includes/tp-mercury-woocommerce-active-check.php';
if (tp_mercury_woocommerce_active()) {
  // WooCommerce is active on the site. Start the plugin processes 
  register_activation_hook(__FILE__, 'Takepayments_Mercury\tp_mercury_plugin_activated');
  register_deactivation_hook(__FILE__, 'Takepayments_Mercury\tp_mercury_plugin_deactivated');
  require_once TAKEPAYMENTS_MERCURY_PATH . 'classes/class-takepayments-mercury-main.php';
  new Takepayments_Mercury_Main;
} else {
  // WooCommerce is not active on the site. Don't start the plugin processes, show an admin notice instead. 
  add_action('admin_notices', function () {
    $class = 'notice notice-error';
    $message = __('Takepayments Mercury requires WooCommerce to be active on this site', 'sample-text-domain');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
  });
}
// WooCommerce currently handles adding this plugin settings to the DB.
function tp_mercury_plugin_activated()
{
}
// When deactivated remove settings from the DB.
function tp_mercury_plugin_deactivated()
{
  $settings_name = 'woocommerce_tp_mercury_settings';
  if (get_option($settings_name)) {
    delete_option($settings_name);
  };
}
