<?php namespace OneFile;

/**
 * Description of Flash To Session
 * @author neels - 02 May 2014
 * @updated 24 May 2014 : Added dot-notation key support
 */

class Flash {
	
	/**
	 * Wrapper array for all flashed data values / sets
	 * @var array
	 */
	protected $flash_bag;

	/**
	 * Can be changed via constructor parameter.
	 * @var string
	 */
	protected $flash_bag_session_key;

	/**
	 * Automatically retrieve the flashed content and keep it available 
	 * in "$this->flash_bag" for the remainder of the request.
	 * 
	 * @param string $flash_bag_session_key
	 */
	public function __construct($flash_bag_session_key = '__FLASH__')
	{
		if ($flash_bag_session_key)
		{
			$this->flash_bag_session_key = $flash_bag_session_key;
		}

		$this->flash_bag = $this->_session_read($this->flash_bag_session_key, array());

		//Clear the flash after retrieving it
		$this->clear();
	}

	/**
	 * OVERRIDE if you use a different session driver
	 * 
	 * @param string $key
	 */
	protected function _session_forget($key)
	{
		if (session_id())
		{
			unset($_SESSION[$key]);
		}
	}

	/**
	 * OVERRIDE if you use a different session driver
	 * 
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function _session_read($key, $default = null)
	{
		if ( ! session_id())
		{
			session_start();
		}
		
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
	}

	/**
	 * OVERRIDE you if use a different session driver
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	protected function _session_write($key, $value)
	{
		if ( ! session_id())
		{
			session_start();
		}
		
		$_SESSION[$key] = $value;
	}

	/**
	 * Removes any trace of flash data from the session.
	 * 
	 */
	public function clear()
	{
		$this->_session_forget($this->flash_bag_session_key);
	}

	/**
	 * 
	 * @param string $key
	 */
	public function forget($key)
	{
		unset($this->flash_bag[$key]);
		
		$this->_session_write($this->flash_bag_session_key, $this->flash_bag);
	}

	/**
	 * Sets a flash value with one level deep dot-notation allowed
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		if (strpos($key, '.') === false)
		{
			$this->flash_bag[$key] = $value;
		}
		else
		{
			$current = &$this->flash_bag;

			foreach (explode('.', $key) as $key)
			{
				$current = &$current[$key];
			}

			$current = $value;
		}

		$this->_session_write($this->flash_bag_session_key, $this->flash_bag);
	}

	/**
	 * Checks if a flash value exists with one level deep dot-notation allowed
	 * 
	 * @param string $key
	 * @return boolean
	 */
	public function has($key)
	{
		if (isset($this->flash_bag[$key]))
		{
			return true;
		}

		$array = & $this->flash_bag;

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
	 * Gets a flash value with one level deep dot-notation allowed
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
			return $this->flash_bag;
		}

		if (isset($this->flash_bag[$key]))
		{
			return $this->flash_bag[$key];
		}

		$array = & $this->flash_bag;

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
	 * @return array
	 */
	public function get_bag()
	{
		return $this->get();
	}

}