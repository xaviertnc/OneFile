<?php namespace OneFile;

class Config
{

	protected $config;

	public function __construct($config_path = 'config.php')
	{
		$this->config = include($config_path);
	}

	public function get($key = null, $default = null)
	{
		if (is_null($key))
		{
			return $this->config;
		}

		if ( ! is_array($key) and isset($this->config[$key]))
		{
			return $this->config[$key];
		}

		$array = & $this->config;

		if ( ! is_array($key))
		{
			$key = explode('.', $key);
		}

		foreach ($key as $segment)
		{
			if ( ! is_array($array) or ! array_key_exists($segment, $array))
				return $default;

			$array = & $array[$segment];
		}

		return $array;
	}

}