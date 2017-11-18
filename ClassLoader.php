<?php namespace OneFile;

/**
 * Simple Class Loader
 * By: C. Moller - 26 Jan 2015
 *
 * Examples:
 * \OneFile\ClassLoader::addDirectory(__PUBLIC_HTML__ . '/php', 'KL');
 * \OneFile\ClassLoader::addDirectory(__PUBLIC_HTML__ . '/vendors');
 * \OneFile\ClassLoader::register();
 *
 */
class ClassLoader
{

	protected static $directories = array();

	public static function load($class)
	{
		foreach (static::$directories as $dirInfo)
		{
			$prefix = $dirInfo[0];
			$len = strlen($prefix);
			// If prefix defined, continue to next directory if classname does not start with prefix
			if ($len and strncmp($prefix, $class, $len) !== 0) continue;
			// If prefix defined, strip prefix from classname + backslash
			if ($len) { $class = substr($class, $len + 1); }

			$file = $dirInfo[1] . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
			if (file_exists($file))
			{
				require $file;
				return true;
			}
		}
		return false;
	}

	//Note: $prefix must exclude trailing backslash!
	public static function addDirectory($directory, $prefix = '') { static::$directories[] = array($prefix, $directory); }

	public static function register() { spl_autoload_register(array('\OneFile\ClassLoader', 'load')); }

}
