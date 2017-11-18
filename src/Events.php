<?php namespace OneFile;

/*
 * By: C. Moller - 25 Jan 2015
 *
 * Based this class on an example on the PHP Documentation Site: http://php.net/manual/en/functions.anonymous.php
 *
 * function hello() { echo "Hello from function hello()\n"; }
 * class Foo { public function hello() { echo "Hello from foo->hello()\n"; } }
 * class Bar { public static function hello() { echo "Hello from Bar::hello()\n"; } }
 *
 * $foo = new Foo();
 *
 * //Bind a global function to the 'test' event
 * Events::appendHandler("test", "hello");
 *
 * //Bind an anonymous function
 * Events::appendHandler("test", function() { echo "Hello from anonymous function\n"; });
 *
 * //Bind an class function on an instance
 * Events::appendHandler("test", "hello", $foo);
 *
 * //Bind a static class function
 * Events::appendHandler("test", "Bar::hello");
 *
 * Events::trigger("test");
 *
 * Output:
 *  Hello from function hello()
 *  Hello from anonymous function
 *  Hello from foo->hello()
 *  Hello from Bar::hello()
*/

class Events
{

	public static $events = array();

	public static function prependHandler($eventName, $callback, $obj = null)
	{
		if (!isset(self::$events[$eventName])) { self::$events[$eventName] = array(); }
		$event = ($obj === null) ? $callback : array($obj, $callback);
		self::$events[$eventName] = array($event) + self::$events[$eventName];
	}

	public static function appendHandler($eventName, $callback, $obj = null)
	{
		if (!isset(self::$events[$eventName])) { self::$events[$eventName] = array(); }
		self::$events[$eventName][] = ($obj === null) ? $callback : array($obj, $callback);
	}

	public static function trigger($eventName)
	{
		foreach (isset(self::$events[$eventName]) ? self::$events[$eventName] : array() as $callback)
		{
			if (call_user_func($callback) === false) break;
		}
	}

}
