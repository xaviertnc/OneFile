<?php namespace OneFile;

class Curl
{
	public static function create()
	{
		return new static;
	}
	
	public function httpPost($url, $params)
	{
		$postData = '';
		
		//create name value pairs seperated by &
		foreach($params as $k => $v) 
		{ 
		   $postData .= $k . '='.$v.'&'; 
		}
		
		rtrim($postData, '&');

		$ch = curl_init();  

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false); 
		curl_setopt($ch, CURLOPT_POST, count($postData));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120); //timeout in seconds
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout in seconds

		$output=curl_exec($ch);

		curl_close($ch);
		
		return $output;
	}
}