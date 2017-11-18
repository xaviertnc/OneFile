<?php

//Laravel4 Illuminate/Support/helpers.php
if ( ! function_exists('value'))
{
	/**
	 * Return the default value of the given value.
	 *
	 * @param  mixed  $value
	 * @return mixed
	 */
	function value($value)
	{
		return $value instanceof Closure ? $value() : $value;
	}
}

//Laravel4 Illuminate/Support/helpers.php
if(!function_exists('array_get'))
{
	/**
	 * Get an item from an array using "dot" notation.
	 * NOTE: If you don't change $array inside this function, $array will be passed by reference
	 * and NO COPY of array will be made!
	 * 
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $default Could be a closure!
	 * @return mixed
	 */
	function array_get($array, $key, $default = null)
	{
		$start_memory = memory_get_usage();
		
		if(is_null($key))
			return $array;

		if(isset($array[$key]))
			return $array[$key];

		foreach(explode('.', $key) as $segment)
		{
			if(!is_array($array) || !array_key_exists($segment, $array))
				return value($default);

			$array = $array[$segment];
		}

		echo 'array_get() memory: ' . (memory_get_usage() - $start_memory) . '<br>';
		
		return $array;
	}
}

//Adapted version of array_get
if(!function_exists('array_has'))
{
	/**
	 * Checks for an item from an array using "dot" notation.
	 * NOTE: If you don't change $array inside this function, $array will be passed by reference
	 * and NO COPY of array will be made!
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @return boolean
	 */
	function array_has($array, $key)
	{
		$start_memory = memory_get_usage();
		
		if(isset($array[$key]))
			return true;

		foreach(explode('.', $key) as $segment)
		{
			if(!is_array($array) || !array_key_exists($segment, $array))
				return false;

			$array = $array[$segment];
		}
		
		echo 'array_get() memory: ' . (memory_get_usage() - $start_memory) . '<br>';

		return true;
	}
}

if(!function_exists('array_set'))
{
	/**
	 * Set an array item using "dot" notation.
	 * NOTE: $array is passed by reference!
	 * 
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $value Could be a closure!
	 * @return mixed
	 */
	function array_set(&$array, $key, $value)
	{
		if(strpos($key, '.') === false)
		{
			$array[$key] = value($value);
		}
		else
		{
			$current = &$array;

			foreach(explode('.', $key) as $key)
			{
				$current = &$current[$key];
			}

			$current = value($value);
		}
	}
}

//Laravel4 Illuminate/Support/helpers.php
if ( ! function_exists('with'))
{
	/**
	 * Return the given object. Useful for chaining.
	 *
	 * @param  mixed  $object
	 * @return mixed
	 */
	function with($object)
	{
		return $object;
	}
}

if ( ! function_exists('redirect'))
{
	function redirect($url = '')
	{
		header('location:' . $url);
		exit(0);
	}
}