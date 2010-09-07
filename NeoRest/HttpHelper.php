<?php
/**
 * Very messy HTTP utility library
 *
 * @package NeoRest 
 */

/**
 * Very messy HTTP utility library
 *
 * @package NeoRest 
 */
class HttpHelper
{
	const GET = 'GET';
	const POST = 'POST';
	const PUT = 'PUT';
	const DELETE = 'DELETE';
	
	/**
	 * A general purpose HTTP request method
	 *
	 * @param string $url 
	 * @param string $method HTTP verb
	 * @param string $post_data 
	 * @param string $content_type 
	 * @param string $accept_type 
	 *
	 * @return void
	 */
	public function request($url, $method='GET', $post_data='', $content_type='', $accept_type='')
	{
		// Uncomment for debugging
		//echo 'HTTP: ', $method, " : " ,$url , " : ", $post_data, "\n";
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);

		//if ($method==self::POST){
		//	curl_setopt($ch, CURLOPT_POST, true); 
		//} else {
		//	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		//}
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	
		if ($post_data)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			
			$headers = array(
						'Content-Length: ' . strlen($post_data),
						'Content-Type: '.$content_type,
						'Accept: '.$accept_type
						);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		}
			
		$response = curl_exec($ch);

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return array($response, $http_code);
	}
	
	/**
	 * A HTTP request that returns json and optionally sends a json payload (post only)
	 *
	 * @param string $url
	 * @param string $method HTTP verb
	 * @param mixed $data 
	 *
	 * @return mixed
	 */
	public function jsonRequest($url, $method, $data=NULL)
	{
		$json = json_encode($data);
		$ret = self::request($url, $method, $json, 'application/json', 'application/json');
		$ret[0] = json_decode($ret[0], TRUE);
		return $ret;
	}
	
	/**
	 * Convenience wrapper for making a PUT jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	public function jsonPutRequest($url, $data)
	{
		return self::jsonRequest($url, self::PUT, $data);
	}
	
	/**
	 * Convenience wrapper for making a POST jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	public function jsonPostRequest($url, $data)
	{
		return self::jsonRequest($url, self::POST, $data);
	}
	
	/**
	 * Convenience wrapper for making a GET jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	public function jsonGetRequest($url)
	{
		return self::jsonRequest($url, self::GET);
	}
	
	/**
	 * Convenience wrapper for making a DELETE jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	public function deleteRequest($url)
	{
		return self::request($url, self::DELETE);
	}
}