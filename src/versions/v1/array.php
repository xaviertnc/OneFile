<?php namespace OneFile;

/**
 * Arr(ay) Class
 * 
 * @author C. Moller <xavier.tnc@gmail.com> - 23 Aug 2014
 * 
 * This class is an almost direct adaptation of the array helper functions in
 * Tylor Ottwell's Laravel framework. They were changed into a more "OneFile" type
 * approach with a static class container and no external dependancies.
 * 
 * Method names were changed as well as method logic in a few places.
 * 
 * Licensed under the MIT license. Please see LICENSE for more information.
 */
class Arr
{
	/**
	 * Add an element to an array if it doesn't exist.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return array
	 */
	public static function setIfNotSet($array, $key, $value)
	{
		if ( ! isset($array[$key])) $array[$key] = $value;

		return $array;
	}

	/**
	 * Transform one array into another using a callback.
	 *
	 * @param  array  $array
	 * @param  \Closure  $callback
	 * @return array
	 */
	public static function transformKeysAndValues($array, Closure $callback)
	{
		$results = array();

		foreach ($array as $key => $value)
		{
			list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

			$results[$innerKey] = $innerValue;
		}

		return $results;
	}

	/**
	 * Split an array's value pairs into two arrays. One with keys and the other with values.
	 * Probably most usefull with associative arrays.
	 *
	 * @param  array  $array
	 * @return array
	 */
	public static function splitKeysAndValues($array)
	{
		return array(array_keys($array), array_values($array));
	}

	/**
	 * Flatten a multi-dimensional PLAIN array (i.e. not associative)
	 * into a single leveled PLAIN array.  The sequence ofthe values will
	 * depend on the source array's content sequence.
	 *
	 * @param  array  $array
	 * @return array
	 */
	public static function flattenToNoKeys($array)
	{
		$return = array();

		array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

		return $return;
	}	

	/**
	 * Flatten a multi-dimensional associative array with dots.
	 *
	 * @param  array   $array
	 * @param  string  $prepend
	 * @return array
	 */
	public static function flattenToDotKeys($array, $prepend = '')
	{
		$results = array();

		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$results = array_merge($results, static::flattenToDotKeys($value, $prepend.$key.'.'));
			}
			else
			{
				$results[$prepend.$key] = $value;
			}
		}

		return $results;
	}

	/**
	 * Get all of the given array except for a specified array of items.
	 *
	 * @param  array  $array
	 * @param  array  $keysToExclude
	 * @return array
	 */
	public static function getExcluding($array, $keysToExclude)
	{
		return array_diff_key($array, array_flip((array) $keysToExclude));
	}

	/**
	 * Get a subset of the items from the given array.
	 *
	 * @param  array  $array
	 * @param  array  $keysToGet
	 * @return array
	 */
	public static function getOnly($array, $keysToGet)
	{
		return array_intersect_key($array, array_flip((array) $keysToGet));
	}	

	
	/**
	 * Extract as an array of values, only a specific property/attribute
	 * of each of the "collection type" array items.
	 * 
	 * Just specify the item property name and optional item primary key.
	 * 
	 * DOT notation is NOT allowed.
	 * 
	 * Items may be objects.
	 *
	 * @param  array   $array
	 * @param  string  $property
	 * @param  string  $primaryKey
	 * @return array
	 */
	public static function getItemsProperty($array, $property, $primaryKey = null)
	{
		$results = array();

		foreach ($array as $item)
		{
			$itemValue = is_object($item) ? $item->{$property} : $item[$property];

			// If the key is "null", we will just append the value to the array and keep
			// looping. Otherwise we will key the array using the value of the key we
			// received from the developer. Then we'll return the final array form.
			if (is_null($primaryKey))
			{
				$results[] = $itemValue;
			}
			else
			{
				$itemKey = is_object($item) ? $item->{$primaryKey} : $item[$primaryKey];

				$results[$itemKey] = $itemValue;
			}
		}

		return $results;
	}	
	
	
	/**
	 * Extract as an array of values, only a specific property/attribute
	 * of each of the "collection type" array items.
	 * 
	 * You can use DOT notation to access a multi-dimentional item property.
	 * 
	 * All items MUST be arrays.
	 * 
	 * If you specify $primaryKey , the returned values will have keys equal to
	 * each item's primary key value. (Usually the item's ID)
	 *
	 * @param  array   $array
	 * @param  string  $property
	 * @param  string  $primaryKey
	 * @return array
	 */
	public static function getItemsDotProperty($array, $property, $primaryKey = null)
	{
		$level = 0;

		$ids = array();
		
		foreach (explode('.', $property) as $segment)
		{
			
			$results = array();			

			foreach ($array as $value)
			{

				if ($primaryKey && $level == 0)
				{
					$ids[] = $value[$primaryKey];
				}
				
				$value = (array) $value;

				$results[] = $value[$segment];
			}

			$array = array_values($results);

			$level++;
		}

		return $primaryKey ? array_values($results) : array_combine($ids, array_values($results));
	}
	
	/**
	 * Sort an items / collection type array using the values in a specified items property / column
	 *
	 * @param  array  $itemsArray
	 * @param  string  $property
	 * @return array
	 */
	public static function sortByItemsProperty($itemsArray, $property = null)
	{
		$sortIndex = array();
		
		foreach($itemsArray as $item)
		{
			if (is_object($item))
			{
				$sortIndex[] = $item->{($property?:'id')};
			}
			else
			{
				$sortIndex[] = $item[($property?:0)];
			}
		}

		array_multisort($sortIndex, $itemsArray);
	}	
	
	/**
	 * Set an array item to a given value
	 * 
	 * Pretty useless method. Adds no value except to allow semantic similarity in your code of your OCD
	 * demands it.  ;)
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return array
	 */
	public static function set(&$array, $key, $value)
	{
		$array[$key] = $value;
	}
	
	/**
	 * Set an array item to a given value using "dot" notation.
	 *
	 * If no key is given to the method, the entire array will be replaced.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return array
	 */
	public static function setDotKey(&$array, $key, $value)
	{
		if (is_null($key)) return $array = $value;

		$keys = explode('.', $key);

		while (count($keys) > 1)
		{
			$key = array_shift($keys);

			// If the key doesn't exist at this depth, we will just create an empty array
			// to hold the next value, allowing us to create the arrays to hold final
			// values at the correct depth. Then we'll keep digging into the array.
			if ( ! isset($array[$key]) || ! is_array($array[$key]))
			{
				$array[$key] = array();
			}

			$array =& $array[$key];
		}

		$array[array_shift($keys)] = $value;

		return $array;
	}
	
	/**
	 * Gets an item from an array by key with additional features like:
	 * 
	 *  - Optional DEFAULT return value if the array key doesn't exist.
	 *  - Returns the ENTIRE ARRAY if the key = NULL
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public static function get($array, $key, $default = null)
	{
		if (is_null($key)) return $array;

		return isset($array[$key]) ? $array[$key] : $default;
	}
	
	/**
	 * Swiss Army Knife for extracting array values!!
	 * Gets an item from an array by key with additional features like:
	 * 
	 *  - Multi-dimentional array value access using "dot" notation.
	 *  - Optional DEFAULT return value if the array key doesn't exist.
	 *  - Returns the ENTIRE ARRAY if the key = NULL (Not the same as 
	 *    specifying a key that does not exist!)
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public static function getDotKey($array, $key, $default = null)
	{
		if (is_null($key)) return $array;

		if (isset($array[$key])) return $array[$key];

		foreach (explode('.', $key) as $segment)
		{
			if ( ! is_array($array) || ! array_key_exists($segment, $array))
			{
				return $default;
			}

			$array = $array[$segment];
		}

		return $array;
	}
	
	/**
	 * Returns the first element in an array passing a given truth test.
	 *
	 * @param  array    $array
	 * @param  Closure  $callback
	 * @param  mixed    $default
	 * @return mixed
	 */
	public static function findFirst($array, $callback, $default = null)
	{
		foreach ($array as $key => $value)
		{
			if (call_user_func($callback, $key, $value)) return $value;
		}

		return value($default);
	}
	
	/**
	 * Return the last element in an array passing a given truth test.
	 *
	 * @param  array    $array
	 * @param  Closure  $callback
	 * @param  mixed    $default
	 * @return mixed
	 */
	public static function findLast($array, $callback, $default = null)
	{
		return static::findFirst(array_reverse($array), $callback, $default);
	}

	/**
	 * Remove an array item from a given array using "dot" notation.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @return void
	 */
	public static function forget(&$array, $key)
	{
		$keys = explode('.', $key);

		while (count($keys) > 1)
		{
			$key = array_shift($keys);

			if ( ! isset($array[$key]) || ! is_array($array[$key]))
			{
				return;
			}

			$array =& $array[$key];
		}

		unset($array[array_shift($keys)]);
	}

	/**
	 * Get a value from the array, and remove it.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public static function extract(&$array, $key, $default = null)
	{
		$value = static::get($array, $key, $default);

		static::forget($array, $key);

		return $value;
	}

	/**
	 * Filter the array using the given Closure.
	 *
	 * @param  array  $array
	 * @param  \Closure  $callback
	 * @return array
	 */
	function array_where($array, Closure $callback)
	{
		$filtered = array();

		foreach ($array as $key => $value)
		{
			if (call_user_func($callback, $key, $value)) $filtered[$key] = $value;
		}

		return $filtered;
	}
}