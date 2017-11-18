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
	protected $data;
	protected $layout;
	protected $name;
	protected $headers;
	protected $responseCode;
	protected $responseContentType;
	protected $renderEngine;
	
	
	public function __construct($name = 'home', $data = array(), $headers = array())
	{
		$this->name = $name;
		$this->data = $data;
		$this->headers = $headers;
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
	
	public function setData(array $data = array())
	{
		$this->data = $data;
	}
	
	public function setRenderEngine($renderEngineInstance)
	{
		$this->renderEngine = $renderEngineInstance;
	}
	
	public function addHeaderDefinition($definition)
	{
		$this->headers[] = $definition;
		return $this;
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
	
	public function setHeaders()
	{
		foreach ($this->headers?:array() as $header)
		{
			header($header);
		}
	}
	
	public function redirect($url, $code)
	{
		header("Location:'$url'", true, $code);
		exit(0);
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
		
		if ( ! is_null($data))
		{
			$this->setData($data);
		}
		
		return $this;
	}
	
	/**
	 * OVERRIDE ME!
	 * Apply your preferred template render engine here instead of just dumping the
	 * data values.
	 *  
	 * @param boolean $echo
	 * @return string
	 */
	public function render()
	{
		$this->setHeaders();
		return print_r($this->data, true);
	}
}
