<?php
// Class for handling payments and refunds. 
namespace Takepayments_Mercury;

use WC_Order;


class Takepayments_Mercury_Process_Payment
{
    protected $payload = [];
    function __construct($order, $settings, $refund_payload = [])
    {
        global $woocommerce;
        $this->tp_mercury_load_dependencies();
        static $count = 0;
        $this->errors = array();
        $this->success_msg  = '';
        $this->is_refund = count($refund_payload) > 0;
        if ($count > 0 && !$this->is_refund) {
            return;
        };
        $this->current_order = new WC_Order($order);
        $this->settings = wc_clean($settings);
        // Get the JWT token. This token is required for both refunds and payments.
        $JWT_class = new Takepayments_Mercury_JWT($this->settings);
        $this->access_token = $JWT_class->process_JWT();
        if ($this->access_token === false) {
            // Could not get access token, possably incorrect Oauth settings.
            $this->set_refund_error('Payment Gateway error, could not obtain JWT token. Please contact the site administrator if this error persists');
            if (is_ajax()) {
                return ['result' => 'error', 'redirect' => wc_get_checkout_url()];
            } else {
                wp_print_inline_script_tag("window.top.location.href = '" . esc_html(wc_get_checkout_url()) . "'");
            }
            wc_add_notice(__("Failed to obtain an authentication token. Please contact the site administrator."), 'error');
            die();
        }
        // $this->post_target_url = 'https://tp-integrated-test-paypages.azurewebsites.net/';
        $this->post_target_url = 'https://gateway1.takepayments.com/';

        $this->capture_order_detaills($this->current_order, $refund_payload);
        $count++;
        $this->is_refund ? $this->post_refund() : $this->post_payment();
    }
    /**
     * Obtain information about the current order. If is refund use the refund array. Allways adds the signature to the array
     * @param current_order order object
     * @param refund_payload array containing either information about the refund or null if not a rufnd request. 
     * @return Array
     */
    function capture_order_detaills($current_order, $refund_payload)
    {
        if (!$this->is_refund) {
            $current_order = wc_clean($current_order);

            $this->payload = [
                "countryCode" => AmountHelper::AVAILABLE_CURRENCIES[($current_order->get_currency())]['iso_num'],
                "customerAddress" => $current_order->get_billing_address_1() . ' ' . $current_order->get_billing_address_2(),
                "customerEmail" => $current_order->get_billing_email(),
                "customerName" => $current_order->get_billing_first_name() . ' ' . $current_order->get_billing_last_name(),
                "customerPhone" => trim($current_order->get_billing_phone() != '' ? $current_order->get_billing_phone() : ''),
                "customerPostcode" => $current_order->get_billing_postcode(),
                "merchantCategoryCode" => "5411",
                "MerchantTransactionReference" => $current_order->get_id(),
                'countryCode2' => AmountHelper::AVAILABLE_CURRENCIES[$current_order->get_currency()]['iso_num'],
                "amount" => AmountHelper::calculateAmountByCurrency($current_order->get_total(), $current_order->get_currency())/100,

                "returnUrl" => add_query_arg('wc-api', 'wc_tp_mercury', home_url('/')),
            ];
        } else {
            $this->payload = wc_clean($refund_payload);
        }
        $this->payload['signature'] = tp_mercury_generate_sigature($this->payload, $this->settings['oauth_key']);
        error_log(print_r($this->payload, true));
        return $this->payload;
    }

    /**
     * Echo the self submitting payment form, with the fileld from the payload array.
     *
     */
    function post_payment()
    {
        $is_iframe = $this->settings['intergration_type'] === "iframe";

        $countdown_txt = ($is_iframe) ? 'Payment Form Will Show In' : 'You Will Be Redirected To The Secure Payment Form In';
        $post_form = '<div class="tp_mercury_countdown ver"><div class="tp_mercury_countdown_text">' . $countdown_txt . ' <div class="tp_mercury_timer" style="display:inline;padding:5px;font-weight:bold;">3</div> seconds</div>';

        // Image reflects the settings
        if ($this->settings['secured_by_tp_logo'] === "yes") {
            $post_form .= '<img src="' . esc_url(TAKEPAYMENTS_MERCURY_PATH_URL . 'public/images/TP_Secure_Badge_Colour_.png') . '" alt="Secured By Takepayments" class="tp_mercury_countdown_image">
            </div>';
        }
        wp_enqueue_script('receipts_page_script', TAKEPAYMENTS_MERCURY_PATH_URL . 'public/block/js/receipts_page_script.js');

        if ($is_iframe) {
            wp_enqueue_style('mercury_iframe_stylesheet', TAKEPAYMENTS_MERCURY_PATH_URL . 'public/stylesheet/mercury_iframe_stylesheet.css', array(), null);
            $post_form .= '<div class="payment_iframe_overlay iframe_inactive"><div class="payment_iframe_container iframe_inactive"><div class="payment_iframe_header"><div class="payment_iframe_header_text"><img src="' . esc_url(TAKEPAYMENTS_MERCURY_PATH_URL . '/public/images/tp_logo.svg') . '" alt="Takepayments Logo" id="payment_iframe_header_logo" class="payment_iframe_header_logo">Takepayments Mercury</div><div class="payment_iframe_hide_btn">&#x21d3;Hide</div></div><iframe name="payment_iframe" id="payment_iframe"></iframe></div></div>';
            $post_form .= '<form id="silentPost" action="' . esc_url($this->post_target_url . '?token=' . $this->access_token) . '" method="POST" target="payment_iframe">';
        } else {
            $post_form .= '<form id="silentPost" action="' . esc_url($this->post_target_url . '?token=' . $this->access_token) . '" method="POST">';
        }


        foreach ($this->payload as $field_name => $field_value) {
            $post_form .= '<input type="hidden" name="' . $field_name . '" value="' . $field_value . '"/> ';
        }
        // Incase the user isn't using Javascript
        $post_form .= '<noscript><input type="submit" value="Continue To Secure Payment Page"></noscript></form><div>';

        // Style property Display on the div above needs to be whitelisted
        add_filter('safe_style_css', function ($styles) {
            $styles[] = 'display';
            return $styles;
        });
        echo wp_kses(
            $post_form,
            array(
                'div' => array('class' => true, 'id' => true, 'style' => array('padding' => true)),
                'img' => array('class' => true, 'alt' => true, 'src' => true, 'id' => true),
                'form' => array('action' => true, 'id' => true, 'method' => true, 'target' => true),
                'input' => array('type' => true, 'name' => true, 'value' => true),
                'noscript' => array(),
                'iframe' => array('name' => true, 'id' => true, 'style' => array('width' => true, 'height' => true)),

            )
        );
        // wp_print_inline_script_tag($post_form_script);
        // var_dump($this->settings);
    }
    /**
     * Send a CURL post request to the payment gateway with the refund information. HTML is reterned and later parsed. 
     */
    protected function post_refund()
    {
        esc_url($this->post_target_url .= '/Refund/?token=' . $this->access_token);
        // $this->payload['Signature'] = tp_mercury_generate_sigature($this->payload, $this->settings['oauth_key']);

        $response = wp_remote_post(
            $this->post_target_url,
            array(
                'method' => 'POST',
                'user-agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])),
                'header' => array('Content-Type: application/x-www-form-urlencoded'),
                'body' => http_build_query($this->payload),
                'cookies' => array()
            )
        );
        if (is_wp_error($response)) {
            $this->set_refund_error('Failed to communicate with payment gateway.');
        };
        // error_log(print_r($response, true));
        $response['response']['message'] === "OK" && $response['response']['code'] === 200 ?
            // Gateway accepted the CURL request. Start parsing the HTML response.
            $this->parse_form_values(wp_remote_retrieve_body($response)) :
            // Gateway returned an error from the CURL request. Add the error message to the refund errors array. 
            $this->set_refund_error('Gateway reported the following error message: ' . $response['response']['code'] . ' ' . $response['response']['message']);
    }


    /**
     *   Parse the form values from the HTML response. When performing refunds WooCommerce expects true/false/WP_Error, connot ehco a HTML form nor Iframe. CURL POST request and parsing the gateway form values is 
     *   the only option available.
     * @param reponse Array containing HTML from the payment gateway
     */
    protected function parse_form_values($response)
    {
        // error_log(wp_kses($response, array('input'=>array('name'=>true, 'value'=>true, 'id'=>true, 'type'=>true),'form'=>array('class'=>true,'method'=>true, 'action'=>true))));
        // Below should work. Needs testing
        $response = wp_kses($response, array('input' => array('name' => true, 'value' => true, 'id' => true, 'type' => true), 'form' => array('class' => true, 'method' => true, 'action' => true)));

        $response = strstr($response, '<input');
        $response = substr($response, 0, strpos($response, '</form>'));
        $response_signature = '';
        $response = str_replace(['<input type="text"', "value=", 'id=', 'class=', 'name=', "value=", '\n'], '', $response);
        $response = array_unique(explode('/>', $response), SORT_STRING);
        $signature_array = array();
        $formated_response = array();
        foreach ($response as $value) {
            $value = explode('" "', $value);
            if (array_key_exists(0, $value) && array_key_exists(1, $value)) {
                // Note: this signature has diffrent string structure to the inital request signature. This signature must contain the array key = value in uppercase. Example:
                // STATUS=APPROVED,TRANSACTIONID=XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
                $signature_array[$value[1]] = strtoupper($value[1]) . '=' . strtoupper(trim(str_replace(['"', "\n", "\r"], "", $value[0])));
                $formated_response[$value[1]] = trim(str_replace(['"', "\n", "\r"], "", $value[0]));
                // Get the response sigature in a new varable. 
                if ($value[1] === 'Signature') {
                    $response_signature = trim(str_replace(['"', "\n", "\r"], "", $value[0]));
                };
            }
        }
        // Remove the signature from the response array
        unset($signature_array['Signature']);
        // Sort response array alphabetically, based on keys.  
        ksort($signature_array);
        // Check if the array values + user key from the settings page produces the same signature grovided in the gateway response. This ensures the response used the same user key to generate the provided signature.
        tp_mercury_verifiy_signature($signature_array, $this->settings['oauth_key'], $response_signature) ?
            // Signature is fine, continue with the refund
            $this->process_refund($signature_array) :
            // Signature is not valid, add an error to the error array. 
            $this->set_refund_error('Cannot verifiy the gateway response. This may be due to incorrect oauth id / key');
    }
    /**
     * Everything is fine with the gateway response. Detmin the outcome of the refund request. 
     * @param signature_array
     * 
     */
    function process_refund($signature_array)
    {
        $formated_response_str = str_replace("=", ": ", implode("\r\n", $signature_array));
        $formated_response_str = str_replace("MERCHANTTRANSACTIONID", "MERCHANT TRANSACTION ID", $formated_response_str);
        $formated_response_str = str_replace('TRANSACTIONID', 'TRANSACTION ID', $formated_response_str);


        switch ($signature_array['Status']) {
                // case "STATUS=APPROVED":
            case "STATUS=CANCELLED":
                $this->set_refund_success_msg($formated_response_str);
                break;
                // case "STATUS=CANCELLED":
                //     $this->set_refund_error($formated_response_str . "\r\n\r\n" . " This transaction has previously been refunded." );
                //     break;
            case "STATUS=GENERALFAILURE":
                $this->set_refund_error($formated_response_str . "\r\n\r\n" . " Transactions can take 24 hours before they become refundable");
                break;
            case "STATUS=SINGATUREINVAILD":
                $this->set_refund_error($formated_response_str . "\r\n\r\n" . " Payment gateway reported invaild credientals.");
                break;
            default:
                $this->set_refund_error($formated_response_str . "\r\n\r\n" . " Unknown error");
        }
    }
    /**
     * This getter function is called by takepayments-mercury-gateway class to determine if errors occoured during the refund process.
     *  If an error has occoured then it returns the error array, if not then return false
     * @return Mixed
     */
    function get_refund_errors()
    {
        return count($this->errors) > 0 ? $this->errors : false;
    }
    /**
     * If errors occour in the apove refund process,  then add error messages to the errors array. All the errors will be shown to the user and the refund will fail. 
     * @param msg string containing the error message. 
     */
    function set_refund_error($msg)
    {
        $this->errors['Error_' . count($this->errors)] = ucwords(strtolower($msg));
    }
    /**
     * Set the success response message formed from the gateway success reponce
     * @return success_msg String
     */
    function get_refund_success_msg()
    {
        return $this->success_msg;
    }
    /**
     * If the refund was successfull then set the refund information to be added to the order refunds notes
     * @param success_msg string formed from the gateway success response 
     */
    function set_refund_success_msg($success_msg)
    {
        $this->success_msg = ucwords(strtolower($success_msg));
    }
    protected function tp_mercury_load_dependencies()
    {
        require_once TAKEPAYMENTS_MERCURY_PATH . 'classes/process-payment/jwt/class-jwt.php';
        require_once TAKEPAYMENTS_MERCURY_PATH . 'classes/process-payment/class-amount-helper.php';
        require_once TAKEPAYMENTS_MERCURY_PATH . 'includes/tp-mercury-signature-generator.php';
    }
}
