<?php namespace OneFile;

class Curl
{
    
	public function httpGet($url, $timeout = 60)
	{
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $timeout, // seconds
            CURLOPT_CONNECTTIMEOUT => $timeout, // seconds
            CURLOPT_RETURNTRANSFER => true
        ));
        
        $resp = curl_exec($curl);

		curl_close($curl);

		return $resp;
	}


	public function httpPost($url, $data, $timeout = 60)
	{
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => $timeout, // seconds
            CURLOPT_HEADER => false,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_CONNECTTIMEOUT => $timeout, // seconds
            CURLOPT_RETURNTRANSFER => true,
        ));

        $resp = curl_exec($curl);

		curl_close($curl);

		return $resp;
	}
	
}
