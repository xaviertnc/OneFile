<?php namespace OneFile;

/**
 * Basic Router Class.
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 06 May 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Router
{
	protected static $instance;

	protected $default_controller;


	public static function create($default_controller = null)
	{
		if(!static::$instance)
			return new static($default_controller);
		else
			return static::$instance;
	}

	public function __construct($default_controller = 'home')
	{
		$this->default_controller = $default_controller;
	}

	/**
	 * Purposely avoid checking for callable functions to NOT
	 * scan all possible functions just to route!
	 *
	 * You can use a closure for custom logic or you can call a custom function
	 * from within the closure.
	 *
	 * @param type $controllerFilenameMapper
	 * @param type $controller
	 * @return type
	 */
	public function getControllerFilename($controllerFilenameMapper, $controller)
	{
		// Closure
		if(is_object($controllerFilenameMapper))
			$filename = $controllerFilenameMapper($controller);
		else
			$filename = sprintf($controllerFilenameMapper, $controller);

		if(!file_exists($filename))
			$filename = sprintf($controllerFilenameMapper, $this->default_controller);

		return $filename;
	}

	/**
	 * Controller filename mapper should be a sprintf() mask or closure
	 *
	 * @param string $controller_classname
	 * @param string $action
	 * @param array $parameters
	 * @param mixed $controllerFilenameMapper
	 */
	public function run($controller_classname, $action, $parameters = null, $controllerFilenameMapper = 'controllers/%s.php')
	{
		$controllerFile = $this->getControllerFilename($controllerFilenameMapper, $controller_classname);

		include($controllerFile);

		$controller = new $controller_classname();

		return call_user_func_array($controller->$action, $parameters);
	}

	public function runUsingUrl($url, $controllerFilenameMapper = 'controllers/%s.php')
	{
		$action = '';
		$parameters = array();
		$controller_classname = $url;
		return $this->run($controller_classname, $action, $parameters, $controllerFilenameMapper);
	}
}
