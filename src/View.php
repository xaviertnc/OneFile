<?php namespace OneFile;

//use Log;

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
	protected $title;
	protected $headers;
	protected $responseCode;
	protected $responseContentType;
	protected $renderEngine;
	protected $allowedCrossOrigin;
	protected $templateFilename;

	public function __construct($name = null, $data = array(), $responseCode = null, $headers = array())
	{
		$this->name = $name;
		$this->data = $data;
		$this->headers = $headers;

		if ($responseCode) {
		    $this->responseCode = $responseCode;
		    $this->setResponseCode($responseCode);
		}
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

	public function addCrossOriginHeaders()
	{
		if ( ! $this->allowedCrossOrigin) { return; }
		header('Access-Control-Allow-Origin: ' . $this->allowedCrossOrigin);
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
		return $this;
	}

	public function makeOptionsResponse()
	{
		if (ob_get_level()) ob_end_clean();
		if ( ! $this->addCrossOriginHeaders()) { $this->setResponseCode(400); }
		die();
	}

	public function makeRedirectResponse($redirectUrl = null)
	{
		if (ob_get_level()) ob_end_clean();
		$this->setResponseCode(202);
		$response = ["redirect" => $redirectUrl];
		die(json_encode($response));
	}

	public function makeAjaxResponse($data = null, $jsonEncode = true, $responseCode = null)
	{
		$response = $jsonEncode ? json_encode($data) : $data;

		// We first create $response to ensure that an exception will not result in an ajax response with unwanted HTML for the client to view!
		if ($response)
		{
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			$this->addCrossOriginHeaders();
			header('Content-type: application/json');
			if ($responseCode) { $this->setResponseCode($responseCode); }
			//Log::view('Ajax Response = ' . print_r($response, true));
			die($response);
		}
	}

	public function makeDownloadResponse($filename, $file_ext, $file_mimetype, $binary = false)
	{
		if ($filename and file_exists($filename))
		{
		    $basename = basename($filename);
		    $ext_offset = strrpos($basename, '.') + 1;
		    if ($ext_offset === false) { die('Error: File not found.'); }
		    $ext = strtolower(substr($basename, $ext_offset));
		    if ($ext !== $file_ext) { die('Error: File not found.'); }
            header("Cache-Control: public"); // needed for internet explorer
			header('Content-Type: ' . $file_mimetype);
			if ($binary)
			{
                header("Content-Transfer-Encoding: Binary");
                header("Content-Length:" . filesize($filename));
			}
			header('Content-Disposition: attachment; filename=' . $basename);
			header('Pragma: no-cache');
			readfile($filename);
			die;
        } else {
            die("Error: File not found.");
        } 
    }
    
    // TODO: Also add (Array) $csvdata STREAM to php://out version (i.e. No save to disk)
    public function makeCsvDownloadResponse($filename)
	{
	    $this->makeDownloadResponse($filename, 'csv', 'application/csv');
    }

	public function makeCssDownloadResponse($filename)
	{
	    $this->makeDownloadResponse($filename, 'css', 'text/css');
	}

	public function makeJsDownloadResponse($filename)
	{
	    $this->makeDownloadResponse($filename, 'js', 'application/javascript');
	}
	
	public function makeZipDownloadResponse($filename)
	{
	    $this->makeDownloadResponse($filename, 'zip', 'application/zip', 'binary');
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

	public function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}

	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * OVERRIDE ME!
	 * Parse $filename before setting $this->templateFilename here...
	 *
	 * @param string $filename
	 * @return View
	 */
	public function setTemplateFilename($filename)
	{
		$this->templateFilename = $filename;
		return $this;
	}

	/**
	 * OVERRIDE ME!
	 * Format $this->templateFilename before consumption here...
	 *
	 * @return string
	 */
	public function getTemplateFilename()
	{
		return $this->templateFilename;
	}

	public function hasTemplate()
	{
		return (isset($this->renderEngine) and isset($this->templateFilename));
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

	public function setAllowedCrossOrigin($allowedCrossOrigin)
	{
		$this->allowedCrossOrigin = $allowedCrossOrigin;
		return $this;
	}

	public function setResponseCode($responseCode)
	{
		http_response_code($responseCode);
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
		if ($responseCode) { $this->setResponseCode($responseCode);	}

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
