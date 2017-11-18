<?php namespace OneFile;

use stdClass;

/**
 * Facilitates Application Service Class Facades in Global Namespace!
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 05 Sep 2014
 *
 * @updated: C. Moller - 14 Jan 2017
 *   - Added Facade::getFacadeHost()
 *
 * @updated: C. Moller - 02 Fed 2017
 *   - Added Facade::hasFacadeHost()
 *
 *   ## Mainly to ease debugging ##
 *   - Added returning "new stdClass()" instead of null from a failed getFacadeHost() call to
 *     allow call_user_func_array() to at least tell us which method could not be called.
 *
 *	NOTE: We purposly don't check if a facade host exists before trying to fetch it!
 *  This is to make this process as fast as possible, specially if a facade is used
 *  in an intensive loop for example.
 *
 *  If you know there's a risk of a facade host not existing, use the hasFacadeHost() method to check.
 *
 */
class Facade
{
	public static $hostClassInstances = array();


	public static function setFacadeHost($hostClassInstance, $name = null)
	{
		if ( ! $name) { $name = get_called_class(); }
		Facade::$hostClassInstances[$name] = $hostClassInstance;
	}


	/**
	 * @return stdClass - See comments above.
	 */
	public static function getFacadeHost($name = null)
	{
		if ( ! $name) { $name = get_called_class(); }
		return Facade::$hostClassInstances[$name] ?: new stdClass();
	}


	public static function hasFacadeHost($name = null)
	{
		if ( ! $name) { $name = get_called_class(); }
		return isset(Facade::$hostClassInstances[$name]);
	}


	public static function __callStatic($method_name, $arguments)
	{
		return call_user_func_array(array(self::getFacadeHost(), $method_name), $arguments);
	}
}
