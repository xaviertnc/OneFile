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
 * //Bind a global function to the 'test' command
 * Command::appendHandler("test", "hello");
 *
 * //Bind an anonymous function
 * Command::appendHandler("test", function() { echo "Hello from anonymous function\n"; });
 *
 * //Bind an class function on an instance
 * Command::appendHandler("test", "hello", $foo);
 *
 * //Bind a static class function
 * Command::appendHandler("test", "Bar::hello");
 *
 * Command::execute("test");
 *
 * Output:
 *  Hello from function hello()
 *  Hello from anonymous function
 *  Hello from foo->hello()
 *  Hello from Bar::hello()
*/

class Commands
{

	public static $commands = array();

	public static function prependHandler($commandName, $callback, $obj = null)
	{
		if (!isset(self::$commands[$commandName])) { self::$commands[$commandName] = array(); }
		$command = ($obj === null) ? $callback : array($obj, $callback);
		self::$commands[$commandName] = array($command) + self::$commands[$commandName];
	}

	public static function appendHandler($commandName, $callback, $obj = null)
	{
		if (!isset(self::$commands[$commandName])) { self::$commands[$commandName] = array(); }
		self::$commands[$commandName][] = ($obj === null) ? $callback : array($obj, $callback);
	}

	public static function execute($commandName)
	{
		foreach (isset(self::$commands[$commandName])?:array() as $callback)
		{
			if (call_user_func($callback) === false) break;
		}
	}

}
