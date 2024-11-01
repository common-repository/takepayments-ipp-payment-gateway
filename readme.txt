=== Takepayments Mercury Payment Gateway ===
 


Contributors: Takepayments
Tags: woocommerce, payment, payments, commerce, Takepayments, Mercury, Takepayments Mercury
Requires at least: 5.9
Tested up to: 6.3
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
  
Process WooCommerce payment via Takapayments Mercury

== Description ==
  
This plugin will add Takepayments Mercury payment method to WooCommerce store. Your customers will be able to pay for their goods/services with a debit/credit card through the Takepayments payment gateway. 

== Installation ==

FTP Installation  
1. Upload the plugin folder to your /wp-content/plugins/ folder.
2. Go to the **Plugins** page and activate the plugin.

Installation via WordPress
(hover-over) Plugins -> (click) Add New -> (click) Upload plugin ->(click) Browse -> (upload) takepayments-mercury.zip 
-> (click) install -> (click) Activate Plugin -> (find) Takepayments Mercury -> (click) Activate 

  
== Frequently Asked Questions ==
= How to uninstall the plugin? =
  
Simply deactivate and delete the plugin, or remove it from the plugin folder via FTP. 

= Do I have to be a Takepayments customer to use this plugin? =
Yes, you do. You can find more information at: https://www.takepayments.com/
  
= WooCoommerce is not showing Takepayments Mercury as a payment method =
Takepayments Mercury may not be an active payment method. To enable the payment gateway:
(hover-over) WooCommerce -> (click) Settings -> (click) Payments -> Takepayments Mercury -> (toggle) Enabled -> (click) Save Changes

= What are my Oauth ID and Oauth password =
These details should have been provided to you during your onboarding process. 

= I get "Takepayments Mercury requires WooCommerce to be active on this site" Error message.
This message is nothing to worry about, just a reminder that you cannot Takepayments until WooCommerce is activated. 

= My customers report seeing "Failed to obtain an authentication token. Please contact the site administrator." error message on the checkout page =
Oauth ID and or Oauth password are incorrect. 

= Some of my payments are failing = 
If you are using a caching plugin to cache your WordPress WooCommerce website/shop, please check if your caching plugin can exclude pages/plugins 
once you have successfully installed this Takepayments Mercury plugin. The plugin may not be able to process payments properly if it is cached. To find out
if your caching plugin can exclude pages/plugins or URLs, please ask the provider of your caching plugin. If your caching plugin does not have the 
option to exclude other plugins/pages / URLs from caching, you may have to switch to a caching plugin that provides this option/possibility.

  

  
== Screenshots ==
1. Appearance of Takepayments Mercury gateway. 
/assets/tp_logo.png

2. Takepayments Logo
/assets/screenshot-2.jpg
== Changelog ==
= 1.0.1 =
* Initial plugin release

== Upgrade Notice == 
= 1.0 =  
*First version
