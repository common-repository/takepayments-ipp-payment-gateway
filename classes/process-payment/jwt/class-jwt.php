<?php

namespace Takepayments_Mercury;
// class for obtaining and verifing JWT tokens. 
use WP_Error;

class Takepayments_Mercury_JWT
{

  private $token_end_point = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

  private $token_validate_end_point = 'https://login.microsoftonline.com/common/discovery/keys';
  // private $access_token = null;


  function __construct($settings)
  {
    // Form the data required to obtain the token. 
    $this->POST_data = [
      'client_id' => $settings['oauth_id'],
      'client_secret' => $settings['oauth_key'],
      // 'scope' => 'https://takepaymentsintegrateddev.onmicrosoft.com/' . $settings['oauth_id'] . '/.default',
      'scope' => 'https://takepaymentsintegratedprod.onmicrosoft.com/' . $settings['oauth_id'] . '/.default',

      'grant_type' => 'client_credentials',
    ];
  }
  /**
   * Post the token request to the token endpoint
   * @return JSON array
   */
  public function fetch_JWT_token()
  {
    $response = wp_remote_post(
      $this->token_end_point,
      array(
        "method" => 'POST',
        'timeout' => 20,
        'redirection' => 5,
        'httpversion' => '1.0',
        // 'sslverify' => false,
        'sslverify' => true,
        'headers' => 'Accept: application/x-www-form-urlencoded',
        "body" => http_build_query($this->POST_data),
        "cookies" => array()
      )
    );
    return is_wp_error($response) ? false : wp_remote_retrieve_body($response);
    // return $response;
  }

  /**
   * Call the fetch_JWT_token function above, then decode the JSON response and finaly return either the token string or false on failue.
   * @return Mixed 
   */
  public function process_JWT()
  {
    // if (trim($settings['oauth_id']) == '' || trim($settings['oauth_key'])) {
    //   return false;
    // }
    $response = $this->fetch_JWT_token();
    if (!$response) {
      $response = $this->fetch_JWT_token_CURL_fallback();
    }
    if (isset(json_decode($response, true)['error_description'])) {
      return false;
    };

    $access_token = json_decode($response, true)['access_token'];
    // This is the access token. 
    $access_token_str = $access_token;
    $access_token = explode('.', $access_token);
    $access_token_header = json_decode(base64_decode($access_token[0]), true);
    $public_cert = $this->get_public_cert($access_token_header['kid']);
    $access_token_body = json_decode(base64_decode($access_token[1]), true);
    $access_token_signature = base64_decode(strtr($access_token[2], '-_', '+/'));
    // True if token is vailidated from the public certificate
    $token_valid = openssl_verify($access_token[0] . '.' . $access_token[1], $access_token_signature, $public_cert, OPENSSL_ALGO_SHA256);
    if ($this->check_header($access_token_header) && $this->check_expiration($access_token_body) && $token_valid) {
      // Passed all checks, return the access token;
      return $access_token_str;
    }
    return false;
  }
  function fetch_JWT_token_CURL_fallback()
  {
    $request_headers = [
      'Accept: application/x-www-form-urlencoded'
    ];

    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, $this->token_end_point);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $request_headers);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query($this->POST_data));
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($curl_handle);
    curl_close($curl_handle);

    return $response;
  }
  /**
   * Generates public key from the public certificate localated at the endpoint
   * @param string $token_kid
   * @return string $pub_key_str
   */
  function get_public_cert($token_kid)
  {
    if (is_null($token_kid)) {
      return false;
    };
    // $pub_cert_arr = file_get_contents($this->token_validate_end_point);
    $pub_cert_arr =  wp_remote_get($this->token_validate_end_point);
    if (is_wp_error($pub_cert_arr)) {
      return false;
    }
    $pub_cert_arr = wp_remote_retrieve_body($pub_cert_arr);
    $valid_pub_cert = '';
    // Mutiple keys exist, needs to loop though and match the relivent kid
    foreach (json_decode($pub_cert_arr, true)['keys'] as $key) {
      if ($key['kid'] == $token_kid) {
        $valid_pub_cert = $key['x5c'][0];
      }
    }
    $str_cert = '';
    $str_cert .= "-----BEGIN CERTIFICATE-----\r\n";
    $str_cert .= chunk_split($valid_pub_cert, 64);
    $str_cert .= "-----END CERTIFICATE-----\r\n";
    $cert_obj = openssl_x509_read($str_cert);
    $pub_key_obj = openssl_pkey_get_public($cert_obj);
    $pub_key_arr = openssl_pkey_get_details($pub_key_obj);
    $pub_key_str = $pub_key_arr['key'];
    return $pub_key_str;
  }
  /**
   *Ensure the expiration time is not less than the current time 
   *  @param Array $token_payload 
   * @return Boolean
   */
  function check_expiration($token_payload)
  {
    if ($token_payload['exp'] < time()) {
      // Expired
      return false;
    }
    return true;
  }

  /** Checks the header contains expected values, 
   *  @param Array $token_header 
   * @return Boolean 
   */
  public function check_header($token_header)
  {
    // Check if the header has the expected values 
    if ($token_header['typ'] === "JWT" && $token_header['alg'] === "RS256" && strpos('/', $token_header['kid']) === false && strpos('.', $token_header['kid']) === false) {
      // Header is fine
      return true;
    }
    return false;
  }
}
