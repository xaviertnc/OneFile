<?php namespace OneFile;

/** 
 * Facilitates Application Service Class Facades in Global Namespace!
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 05 Sep 2014
 * 
 */
class Facade
{
	public static $hostClassInstances = array();	
	
	
	public static function setFacadeHost($hostClassInstance)
	{
		self::$hostClassInstances[get_called_class()] = $hostClassInstance;
	}
	
	
	public static function __callStatic($method_name, $arguments)
	{
		return call_user_method_array($method_name, self::$hostClassInstances[get_called_class()], $arguments);
	}
}