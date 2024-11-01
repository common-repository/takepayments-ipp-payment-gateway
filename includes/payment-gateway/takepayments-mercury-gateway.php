<?php

/**
 *  Set the plugin settings pages option and controls classes for JWT tokens, payments and refunds.
 */


namespace Takepayments_Mercury;

use WC_Payment_Gateway;
use WC_Order;
use WP_Error;


function takepayments_mercury_init()
{
    class WC_Takepayments_Mercury_Gateway extends WC_Payment_Gateway
    {
        function __construct()
        {
            $this->current_order = null;
            // Load the settings saved in DB.
            $this->id = 'tp_mercury'; // payment gateway  ID
            // $this->id = 'takepayments-mercury'; // payment gateway  ID

            $this->icon = TAKEPAYMENTS_MERCURY_PATH_URL . 'public/images/TP_Card_Logos_[Horiz]_no_AMEX_Full_Colour_cropped.png';
            $this->method_title = 'Takepayments Mercury Payment Gateway';
            $this->method_description = 'Use Takepayments Mercury Gateway To Process Payments';
            $this->supports = array('products', 'refunds');
            // Settings options/values fields
            $this->init_form_fields();
            $this->init_settings();
            // Sanitize the user inputted settings

            $this->settings = wc_clean($this->settings);

            $this->title = (trim($this->settings['title']) === '' ? 'Pay With Takepayments Mercury Payment Gateway' : sanitize_text_field($this->settings['title']));

            $this->description = 'Use Takepayments Mercury Payment Gateway To Process Payments';
            $this->icon = ($this->settings['amx_logo']) === 'yes' ? TAKEPAYMENTS_MERCURY_PATH_URL . 'public/images/TP_Card_Logos_[Horiz]_AMEX_Full_Colour_cropped.png' : TAKEPAYMENTS_MERCURY_PATH_URL . 'public/images/TP_Card_Logos_[Horiz]_no_AMEX_Full_Colour_cropped.png';
            // Hook fires when user saves the Takepayment Mercury settings.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            // Stylesheet may be needed for the checkout page. 
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts_styles'));
            // Hook fires after the user clicks paynow button and is redirected to the receipt page. 

            add_action('woocommerce_receipt_' . $this->id, function ($order_id) {

                if (!class_exists("Takepayments_Mercury/Takepayments_Mercury_Process_Payment")) {
                    require_once TAKEPAYMENTS_MERCURY_PATH . 'classes/process-payment/class-process-payment.php';
                }
                new Takepayments_Mercury_Process_Payment($order_id, $this->settings);

                // Takepayments_mercury_Process_Payment will echo an autosubmiting form on the recipts page. The User will be taken to the payment 
                // gateway to enter their details. 

            });
            // Hook fires after the user returns from the payment gateway. 
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'process_response_callback'));
        }
        public function admin_options(){
            // Checks weather the user credentails are vaild.
            if(isset($_POST['woocommerce_tp_mercury_enabled']) && $_POST['woocommerce_tp_mercury_enabled'] == 1){
                if(!class_exists('Takepayments_Mercury_JWT')){
                    include_once TAKEPAYMENTS_MERCURY_PATH.'classes/process-payment/jwt/class-jwt.php';
                    $credentals_checker = new Takepayments_Mercury_JWT(["oauth_id"=>$_POST['woocommerce_tp_mercury_oauth_id'], "oauth_key"=>$_POST['woocommerce_tp_mercury_oauth_key']]);
                    if($credentals_checker->process_JWT()){
                        // Creds are valid
                        $class = 'notice notice-success';
                        $message = __('Your User ID and Password are vaild.', 'sample-text-domain');
                    }else{
                        // Invaild creds
                        $class = 'notice notice-error';
                        $message = __('Your User ID and/or Password are incorrect. Takepayment Mercury will not be able to process payments with invaild credentails', 'sample-text-domain');
                    };
                }
                printf('<div class="%1$s"><p><b>%2$s</b></p></div>', esc_attr($class), esc_html($message));

            }
            
            parent::admin_options();
        }

        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable'),
                    'label'       => __('Enable ' . ucwords($this->method_title)),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'intergration_type' => array(
                    'title' => 'Type of Integration ',
                    // 'description' => 'Please Select Your Intergration Type:<br><br> Hosted: Customers Will Be Redirected Away From Your Site To Complete The Payment. <br><br>Iframe: Customers Will Complete Payment On Your Site. <br><b>Important: Your Site Must Have An SSL Cerificate To Use Iframe.',
                    'type' => 'select',
                    'default' => 'Hosted',
                   
                    'options' => array(
                         'Hosted' => 'Hosted',
                        // Iframe option removed due to the x-frame-options changing on the gateway side. 
                         //  "iframe" => 'Iframe'
                    ) 
                    ),
                'oauth_id' => array(
                    'title' => 'Your User ID*',
                    'type' => 'text',
                    'description' => 'Your Oauth ID, this is a available from your merchant portal.',
                    'default' => 'Enter Your Oauth ID Here',
                    // 'desc_tip' => true,
                    'custom_attributes' => ['required' => true],
                ),
                'oauth_key' => array(
                    'title' => 'Your Password*',
                    'type' => 'text',
                    'description' => "Your Secret Oauth Password, this is a available from your merchant portal.",
                    'default' => 'Enter Your Oauth Password Here',
                    // 'desc_tip' => true,
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ),
                'title' => array(
                    'title' => 'Takepayments Mercury Gateway Title',
                    'type' => 'text',
                    'default' => 'Takepayments Mercury',
                    'description' => 'Change the payment gateway title on the checkout page.
                ',
                ),
                'description' => array(
                    'title' => 'Takepayments Mercury Gateway Description',
                    'type' => 'textarea',
                    'css' => 'width:500px;',
                    'description' => 'Add a description to the Takepayments Mercury payment gateway,
                 leave blank for no description.'
                ),
                'amx_logo' => array(
                    'title' => 'Add American Express Logo.',
                    'label' => 'Add American Express Logo to the checkout.',
                    'type' => 'checkbox',
                    'description' => 'Adds American Express logo on the checkout.',
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'secured_by_tp_logo' => array(
                    'title' => 'Add Secured By Takepayments Image.',
                    'label' => 'Add Secured By Takepayments Image to the checkout',
                    'type' => 'checkbox',
                    'description' => 'Add Secured By Takepayments Image to the checkout.',
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'show_tp_styles' => array(
                    'title' => 'Add Takepayments styles to the payment gateway.',
                    'label' => 'Add Takepayments styles to the payment gateway on the checkout page',
                    'type' => 'checkbox',
                    'description' => 'Add Takepayments styles to the payment gateway on the checkout page.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
     
            );
        }
        /*
         * Currently no custom fields to validate, WooCommerce validates the name/address ect fields
         */
        public function validate_fields()
        {
            return true;
        }
        /*
         * Checkout payment gateway is here
         */
        public function payment_fields()
        {
            do_action('woocommerce_credit_card_form_start', $this->id);
            // echo any additonal elements here
            if ($this->settings['secured_by_tp_logo'] === "yes") {
                echo wp_kses('<img style="height:100%;width:50%" src="' . esc_url(TAKEPAYMENTS_MERCURY_PATH_URL . 'public/images/TP_Secure_Badge_Colour_.png') . '" alt="Secured By Takepayments">', array('img' => array('src' => true, 'style' => true)));
            }
            if (trim($this->settings['description']) !== "") {
                echo wp_kses("<div class='tp_mercury_desc'>" . esc_html(wp_strip_all_tags(sanitize_textarea_field($this->settings['description']))) . "</div>", array('div' => array('class' => true)));
            }
            do_action('woocommerce_credit_card_form_end', $this->id);
        }
        /*
         * Custom CSS and JS
         */
        public function payment_scripts_styles()
        {
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) || $this->settings['show_tp_styles'] === "no") {
                return;
            }
            wp_enqueue_style('mercury_stylesheet', TAKEPAYMENTS_MERCURY_PATH_URL . 'public/stylesheet/mercury_stylesheet.css', array(), null);
        }

        /**
         * Pass a success message to WordPress, this will move the user to the recipts page and the payment process will start.
         * @param order_id the id of the current order. 
         * @return Array 
         */
        public function process_payment($order_id)
        {
            // $this->set_target_order($order_id);
            $order = new WC_Order($order_id);

            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
        }

        /**
         * Valitates the return signiture, determines the outcome of the transaction and completes the WooCommerce order. 
         * 
         * @return mixed
         */
        public function process_response_callback()
        {

            require_once TAKEPAYMENTS_MERCURY_PATH . 'includes/tp-mercury-signature-generator.php';

            $temp_array = array();
            $ajax_response = [];
            $next_page_Url = "";
            $error_msg = "";
            $order = null;
            $gateway_response = wc_clean($_POST);
            if (
                array_key_exists('Status', $gateway_response) == false ||
                array_key_exists('MerchantTransactionId', $gateway_response) == false ||
                array_key_exists('TransactionId', $gateway_response) == false ||
                array_key_exists('Signature', $gateway_response) == false
            ) {

                $error_msg = 'No response from the gateway, please try again';
            } else {
                $temp_array = array(
                    'MerchantTransactionId' => strtoupper('MerchantTransactionId=' . $gateway_response['MerchantTransactionId']),
                    'Status'                => strtoupper('Status=' . $gateway_response['Status']),
                    'TransactionId'         => strtoupper('TransactionId=' . $gateway_response['TransactionId'])
                );
                ksort($temp_array);
                if (tp_mercury_verifiy_signature($temp_array, $this->settings['oauth_key'], $gateway_response['Signature']) === false) {
                    $error_msg = 'Received response from gateway, but signature could not be verified. This order has not been completed.';
                } else {
                    // look at ofuscating the order ID
                    $order = new WC_Order($gateway_response['MerchantTransactionId']);
                }
            }
            if ($error_msg == "" && !is_null($order) && $order->get_id() != $gateway_response['MerchantTransactionId']) {
                $error_msg = 'Unexpected Transaction ID';
            }
            if ($error_msg !== "") {
                // echo '<script>window.top.location.href = "' . wc_get_checkout_url() . '";</script>';
                wp_print_inline_script_tag("window.top.location.href = '" . wc_get_checkout_url() . "'");
                if (is_ajax()) {
                    return ['result' => 'error', 'redirect' => wc_get_checkout_url()];
                };
                wc_add_notice(__(esc_html($error_msg)), 'error');
                die();
            }

            $order_notes = '';
            $order_outcome_msg = '';

            $next_page_Url = $this->get_return_url($order);

            if (strtoupper($gateway_response['Status']) === "APPROVED") {
                // Payment Successfull
                $order_outcome_msg = " Payment Successfull";
                $order_notes  = "\r\nTransaction status: Approved\r\n";
                $order_notes .= "\r\nTransaction Id: " . $gateway_response['TransactionId'] . "\r\n";
                $order_notes .= "\r\nWooCommerce Order ID: " . $order->get_id() . "\r\n";
                $order_notes .= "\r\nAmount: " . $order->get_total() . "\r\n";
                $order_notes .= "\r\nThanks for using Takepayments";

                $order->set_transaction_id($gateway_response['TransactionId']);
                $order->payment_complete();

                $ajax_response = [
                    'result' => 'success',
                    'redirect' => $next_page_Url,
                ];
            } else {
                $order_outcome_msg = 'Failed to complete payment';
                $_SESSION['payment_gateway_error'] = $order_outcome_msg;
                wc_add_notice(__($order_outcome_msg), 'error');
                $order_notes  = "\r\nTransaction status: Failed\r\n";
                $order_notes .= "\r\nTransaction Id: " . $gateway_response['TransactionId'] . "\r\n";
                $order_notes .= "\r\nWooCommerce order Key: " . $order->get_order_key() . "\r\n";
                $order_notes .= "\r\nTransaction Amount: " . $order->get_total() . "\r\n";
                $order_notes .= "\r\nThanks for using Takepayments";
                $order->update_status('failed');
            }
            $order->add_order_note(esc_html(ucwords($this->method_title) . $order_outcome_msg . $order_notes));


            $order->save();
            if (is_ajax()) {
                return $ajax_response;
            };
            // echo '<script>window.top.location.href = "' . $next_page_Url . '";</script>';
            // Sanitizing the $next_page_Url will break the payment process and return the user to the checkout page. 
            wp_print_inline_script_tag("window.top.location.href = '" . $next_page_Url . "'");
            die();
        }
        /**
         * Starts the refund process.
         * @param order_id the id of the current order
         * @param amount Requested refund amount. Note the payment gateway does not currently support partal refunds. 
         * @param reason User inputed reason for the refund. 
         * @return Mixed
         * 
         * 
         */
        function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = new WC_Order($order_id);
            if (!$this->can_refund_order($order)) {
                return new WP_Error('error', __('Refund failed.', 'woocommerce'));
            }
            // Bail early if user attemps partal refund here
            if ($order->get_total() != $amount) {
                return new WP_Error('tp_mercury', __('Takepayments Mercury cannot preform partial refunds. Please use the MMS system', "error"));
            }

            if (!class_exists("Takepayments_Mercury_Process_Payment")) {
                require_once TAKEPAYMENTS_MERCURY_PATH . 'classes/process-payment/class-process-payment.php';
                $refund_class = new Takepayments_Mercury_Process_Payment(
                    $order_id,
                    $this->settings,
                    array(
                        "transactionId" => $order->get_transaction_id(),
                        "returnUrl" => get_home_url()
                    )
                );
            }

            // Errors occoured while attemping the refund. Show a WooCommerce error message display the error from the gateway and add the message to the order notes.
            if ($refund_class->get_refund_errors() != false) {
                $errors = wc_clean($refund_class->get_refund_errors());
                $msg = implode("\r\n", $errors);
                $order->add_order_note(ucwords($this->method_title) . " Refund Error: \r\n" . $msg);
                $order->save();
                return new WP_Error('tp_mercury', __('Error Count: ' . count($errors) . "\r\n" . $msg . "\r\n\r\n Refund failed. Please refresh this page and try again.\r\n\r\n If this error persists, please report to this site administrator", "error"));
            };
            // Refund successfull. Add the order details to the order notes.

            $order->add_order_note(esc_html(ucwords($this->method_title) . " Refund Complete: \r\n" . $refund_class->get_refund_success_msg() . "\r\n\r\n Thanks For Using Takepayments"));
            $order->save();
            if (is_ajax()) {
                return array(
                    'result' => 'success',
                );
            }
            return true;
        }
    }
}
