<?php namespace OneFile;

use InvalidArgumentException;

/**
 * Read Once / "Flash" Type Storage Class.
 * 
 * Used to persist input and/or state values for a single request cycle only.
 * 
 * @author neels - 02 May 2014
 * 
 * @updated 24 May 2014 : Added dot-notation key support
 * 
 * @updated 05 Aug 2014 : Changed method and property naming conventions + 
 * Added storeConnect(), forgetAll(), mergeNewItems() + _destruct()
 * Changed / simplified existing store methods.
 * 
 */
class Flash
{
	/**
	 * Wrapper array for all stored flash data values/ sets
	 * from the previous request cycle.
	 * 
	 * @var array
	 */
	protected $bag;

	/**
	 * Wrapper array for NEW flash data values/ sets
	 * for the next request cycle.
	 * 
	 * @var array
	 */
	protected $nextBag;

	/**
	 * Can be changed via constructor parameter.
	 * @var string
	 */
	protected $storeKey;

	/**
	 * Automatically retrieve the flashed content and keep it available 
	 * in "$this->bag" for the remainder of the request.
	 * 
	 * @param string $storeKey
	 */
	public function __construct($storeKey = '__FLASH__')
	{
		if ( ! $this->storeConnect() or ! $storeKey)
		{
			throw new InvalidArgumentException('Invalid Flash Store or Store Key');
		}

		$this->bag = $this->storeRead($storeKey, array());
		
		//Clear the flash stored data after retrieving it
		$this->storeForget($storeKey);

		$this->storeKey = $storeKey;

		$this->nextBag = array();
	}

	/**
	 * Store the flash items added for consumption in the next request just
	 * before we exit this script / request.
	 * 
	 */
	public function __destruct()
	{
		if ($this->nextBag)
		{
			$this->storeWrite($this->storeKey, $this->nextBag);
		}
	}
	
	/**
	 * OVERRIDE if you use a different store driver
	 * 
	 */
	protected function storeConnect()
	{
		if ( ! session_id())
		{
			return session_start();
		}
		
		return true;
	}

	/**
	 * OVERRIDE if you use a different store driver
	 * 
	 * @param string $key
	 */
	protected function storeForget($key)
	{
		unset($_SESSION[$key]);
	}

	/**
	 * OVERRIDE if you use a different store driver
	 * 
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function storeRead($key, $default = null)
	{
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
	}

	/**
	 * OVERRIDE you if use a different store driver
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	protected function storeWrite($key, $value)
	{
		$_SESSION[$key] = $value;
	}

	/**
	 * Sets a flash value with dot-notation allowed
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		if (strpos($key, '.') === false)
		{
			$this->nextBag[$key] = $value;
		}
		else
		{
			$current = & $this->nextBag;

			foreach (explode('.', $key) as $key)
			{
				$current = & $current[$key];
			}

			$current = $value;
		}	
	}

	/**
	 * Checks if a flash value exists with dot-notation allowed
	 * 
	 * @param string $key
	 * @return boolean
	 */
	public function has($key)
	{
		if (isset($this->bag[$key]))
		{
			return true;
		}

		$array = & $this->bag;

		foreach (explode('.', $key) as $segment)
		{
			if ( ! is_array($array) or ! array_key_exists($segment, $array))
			{
				return false;
			}

			$array = & $array[$segment];
		}

		return true;
	}

	/**
	 * Gets a flash value with dot-notation allowed
	 * Uses code from laravel array_get() helper
	 * 
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($key = null, $default = null)
	{
		if (is_null($key))
		{
			return $this->bag;
		}

		if (isset($this->bag[$key]))
		{
			return $this->bag[$key];
		}

		$array = & $this->bag;

		foreach (explode('.', $key) as $segment)
		{
			if ( ! is_array($array) or ! array_key_exists($segment, $array))
			{
				return $default;
			}

			$array = & $array[$segment];
		}

		return $array;
	}

	/**
	 * Convenience method. get() might not be intuitive!
	 * 
	 * @return array
	 */
	public function getAll()
	{
		return $this->get();
	}
	
	/**
	 * Remove a specific flash key stored in the previous request.
	 * 
	 * @param string $key
	 */
	public function forget($key)
	{
		unset($this->bag[$key]);
}

	/**
	 * Remove ALL the flash keys/data stored in the previous request.
	 * 
	 */
	public function forgetAll()
	{	
		$this->bag = array();
	}
	
	/**
	 * If we are not going to redirect, let's activate/consume any new flash messages
	 * by merging them into the active/current bag.
	 * 
	 */
	public function ageNewItems()
	{
		$this->bag = array_merge_recursive($this->bag, $this->nextBag);
		$this->nextBag = array();
	}
	
}