<?php namespace OneFile;

/**
 * Description of View Class
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 23 Jun 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 * 
 */
class View
{
	/**
	 *
	 * @var array
	 */
	protected $data;
	
	protected $layout;
	protected $name;
	protected $headers;
	protected $responseCode;
	protected $responseContentType;
	protected $renderEngine;
	
	
	public function __construct($name = 'home', $data = array(), $responseCode = null, $headers = array())
	{
		$this->name = $name;
		$this->data = $data;		
		$this->headers = $headers;

		if ($responseCode) $this->setResponseCode($responseCode);
	}
	
	/**
	 * Use this function to setup your view before rendering
	 * chained with "->with()" clauses if you don't 
	 * want to use the $data parameter.
	 * 
	 * @param string $name  This name is used to identify the view and determine the view template filename
	 * @param array $data All the data we will require inside our view template
	 * @return \OneFile\View
	 */
	public function make($name = null, $data = null)
	{
		if ($name)
		{
			$this->setName($name);
		}
		
		if (is_array($data))
		{
			$this->setData($data);
		}
		
		return $this;
	}

	public function makeAjaxResponse($data = null, $jsonEncode = true, $responseCode = null)
	{
		$response = $jsonEncode ? json_encode($data) : $data;

		// We first create $response to ensure that an exception will not result in an ajax response with unwanted HTML for the client to view!
		if ($response)
		{
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Content-type: application/json');

			if ($responseCode) http_response_code($responseCode);

			die($response);
		}
	}

	public function makeCsvDownloadResponse($filename)
	{
		if ($filename and file_exists($filename))
		{
			header('Content-Type: application/csv');
			header('Content-Disposition: attachment; filename=' . basename($filename));
			header('Pragma: no-cache');
			readfile($filename);
			die;
		}
	}

	public function with($key, $value)
	{
		$this->data[$key] = $value;
		return $this;
	}
	
	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	/**
	 * OVERRIDE ME!
	 * Convert your view name to a view template filename.
	 * 
	 * @return string
	 */
	public function getTemplateFilename()
	{
		return $this->name;
	}
	
	public function getData()
	{
		return $this->data;
	}
	
	public function setData(array $data)
	{
		$this->data = $data;
	}
	
	public function setRenderEngine($renderEngineInstance)
	{
		$this->renderEngine = $renderEngineInstance;
	}
	
	public function setResponseCode($code)
	{
		$this->responseCode = $code;
		return $this;
	}
	
	public function setResponseContentType($contentType)
	{
		$this->responseContentType = $contentType;
		return $this;
	}
	
	public function addHttpHeader($httpHeader)
	{
		$this->headers[] = $httpHeader;
		return $this;
	}
	
	public function renderHttpHeaders()
	{
		foreach ($this->headers?:array() as $header)
		{
			header($header);
		}
	}

	/**
	 * OVERRIDE ME!
	 * Apply your preferred template render engine here instead of just dumping the
	 * data values.
	 *  
	 * @param string $response
	 * @param integer $responseCode
	 * @param boolean $print
	 * @return string
	 */
	public function render($response = null, $responseCode = null, $print = true)
	{
		if ($responseCode) $this->responseCode = $responseCode;

		if ($this->responseCode)
		{
			http_response_code($this->responseCode);
		}
		
		$this->renderHttpHeaders();

		if ($response)
		{
			if ($print)
			{
				print($response);
			}
			else
			{
				return $response;
			}
		}
	}
	
	/**
	 * 
	 * @param string $url
	 * @param integer $code
	 */
	public function redirect($url, $code)
	{
		header("Location:'$url'", true, $code);
		exit(0);
	}	
}
