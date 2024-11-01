<?php
/**
 * Generates signature based on input array from the order/refund values and from the secret key from this plugins settings
 * @param input array consisting of data from the current order/refund
 * @param sec_key Secret key from this plugins options page.
 * 
 * @return signature The generated signature key string
 */
    namespace Takepayments_Mercury;
    function tp_mercury_generate_sigature($input,$sec_key)
    {
        
        $payload_string = implode(',', $input);
        $payload_string .= ','.$sec_key;
        $payload_string = strtoupper($payload_string);
        $signature = '';
        $hash = hash('sha256', $payload_string);
        $byte_arr = unpack('C*', strtoupper($hash));
        foreach ($byte_arr as $byte) {
            $signature .= chr($byte);
        }
        return $signature;
    }
    function tp_mercury_verifiy_signature($input, $sec_key, $signature)
    {
    $generated_signature = tp_mercury_generate_sigature($input, $sec_key);
    return $generated_signature === $signature;
    }
