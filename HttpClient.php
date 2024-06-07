<?php namespace F1;

/**
 * F1 - HTTP Client Class - 18 Jun 2023
 *
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 1.2 - DEV - 07 Jun 2024
 *   - Add CURLOPT_FOLLOWLOCATION to get() method
 *
 */

class HttpClient {

  public function get( $url, $headers = [] ) {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->prepareHeaders( $headers ) );
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    $response = curl_exec( $ch );
    curl_close( $ch );
    return $response;
  }


  public function post( $url, $params = [], $headers = [] ) {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->prepareHeaders( $headers ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    $response = curl_exec( $ch );
    curl_close( $ch );
    return $response;
  }


  private function prepareHeaders( $headers ) {
    $preparedHeaders = [];
    foreach ( $headers as $key => $value ) $preparedHeaders[] = "$key: $value";
    return $preparedHeaders;
  }

}
