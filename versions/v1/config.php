<?php namespace OneFile;

class Config
{

	protected $config;

	public function __construct($config_path = 'config.php')
	{
		$this->config = include($config_path);
	}

	/**
	 * Sets a config value with dot-notation allowed
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		if (strpos($key, '.') === false)
		{
			$this->config[$key] = $value;
		}
		else
		{
			$current = & $this->config;

			foreach (explode('.', $key) as $key)
			{
				$current = & $current[$key];
			}

			$current = $value;
		}	
	}

	/**
	 * Checks if a config value exists with dot-notation allowed
	 * 
	 * @param string $key
	 * @return boolean
	 */
	public function has($key)
	{
		if (isset($this->config[$key]))
		{
			return true;
		}

		$array = & $this->config;

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
	 * Gets a config value with dot-notation allowed
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
			return $this->config;
		}

		if (isset($this->config[$key]))
		{
			return $this->config[$key];
		}

		$array = & $this->config;

		foreach (explode('.', $key) as $segment)
		{
			if ( ! is_array($array) or ! array_key_exists($segment, $array))
				return $default;

			$array = & $array[$segment];
		}

		return $array;		
	}
	
	/**
	 * Merges an external array into the internal $config array
	 * Use to layer config files by environment or just add seperate module configs.
	 * 
	 * @param array $alt_config
	 * @return \OneFile\Config
	 */
	public function merge(array $alt_config)
	{
		$this->config = array_merge($this->config, $alt_config);
		
		return $this;
	}
}
