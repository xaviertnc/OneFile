<?php namespace OneFile;


class MultiState
{

	protected $statesStore = [];

	protected $storeKey = '__MULTI_STATE__';


	/**
	 *
	 * Override me!
	 *
	 * Use your own implementation or session store if it's not $_SESSION
	 *
	 * NOTE: PHP automatically serializes objects in $_SESSION
	 *
	 */
	protected function __session_put($key, $value)
	{
		$_SESSION[$key] = $value;
	}


	/**
	 *
	 * Override me!
	 *
	 * Use your own implementation or session store if it's not $_SESSION
	 *
	 * NOTE: PHP automatically unserializes objects in $_SESSION
	 *
	 */
	protected function __session_get($key, $default)
	{
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
	}


	public function setStoreKey($storeKey = null)
	{
		$this->storeKey = $storeKey;
		return $this;
	}


	public function getStoreKey($stateName = null)
	{
		return $this->storeKey . ($stateName ? ".$stateName" : '');
	}


	public function setState($stateName, array $state)
	{
		if (is_null($stateName))
		{
			$this->statesStore = $state;
		}
		else
		{
			$this->statesStore[$stateName] = $state;
		}
		return $this;
	}


	public function dropState($stateName)
	{
		unset($this->statesStore[$stateName]);
		return $this;
	}


	public function getState($stateName = null, $defaultState = null)
	{
		if (is_null($stateName)) { return $this->statesStore; }
		return isset($this->statesStore[$stateName]) ? $this->statesStore[$stateName] : $defaultState;
	}


	public function load($customStoreKey = null, $default = null)
	{
		$storeKey = $customStoreKey ?: $this->getStoreKey();
		$this->statesStore = $this->__session_get($storeKey, $default);
		return $this;
	}


	public function save($customStoreKey = null)
	{
		$storeKey = $customStoreKey ?: $this->getStoreKey();
		$this->__session_put($storeKey, $this->statesStore);
		return $this;
	}


	public function clear()
	{
		return $this->setState(null, []);
	}


	/**
	 * Override!
	 *
	 */
	public function flash($customStoreKey = null)
	{
		$storeKey = $customStoreKey ?: $this->getStoreKey();
		return $this->save("flash.$storeKey");
	}
}
