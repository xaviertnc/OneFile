<?php namespace OneFile;

/**
 * PHP already has fine session handling and you could surely just use it in its native form.
 * (Specially for smaller projects or quick concept testing)
 * 
 * Adding this thin layer over PHP's native session engine though, ensures we write code that 
 * is not so strongly tied to the PHP session engine.
 * 
 * If at any later stage we wanted to change our session implementation, 
 * we could do so without a LOT of code fixing!
 * 
 * Override the methods in this class with your improvements or just replace the entire file/class!
 *  
 * @author C. Moller <xavier.tnc@gmail.com> - 30 May 2014
 * 
 * Licensed under the MIT license. Please see LICENSE for more information.
 * 
 * @update C. Moller - 9 June 2014: Added session domain support
 * 
 */

class Session {

	protected $domain;
	
	/**
	 * Specify a domain if you want to keep your session values seperate
	 * from other potential session users.
	 * 
	 * Allows selecting a specific session to start by specifying its id.
	 * 
	 * @param string $id
	 */
	public function __construct($domain = null, $id = null)
	{
		$this->start($domain, $id);
	}

	public function id($id = null)
	{
		if ($id)
		{
			return session_id($id);
		}
		
		return session_id();
	}

	public function start($domain = null, $id = null)
	{
		if ($id)
		{
			$this->id($id);
		}
		
		$ok = session_start();
		
		if ($ok and $domain)
		{
			$this->domain = $domain;
			$_SESSION[$domain] = array();
		}
		
		return $ok;
	}

	public function has($key)
	{
		return $this->domain ? isset($_SESSION[$this->domain][$key]) : isset($_SESSION[$key]);
	}

	/**
	 * This method adds the convenience of not having to check if a key exists before retrieving
	 * and also returns a default value if not set!
	 * 
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($key = null, $default = null)
	{
		if (is_null($key))
		{
			return $this->domain ? $_SESSION[$this->domain] : $_SESSION;
		}

		if ($this->has($key))
		{
			return $this->domain ? $_SESSION[$this->domain][$key] : $_SESSION[$key];
		}
		else
		{
			return $default;
		}
	}

	public function all()
	{
		return $this->get();
	}

	public function put($key, $value)
	{
		if ($this->domain)
		{
			$_SESSION[$this->domain][$key] = $value;			
		}
		else
		{
			$_SESSION[$key] = $value;
		}
	}

	public function forget($key)
	{
		if ($this->domain)
		{
			unset($_SESSION[$this->domain][$key]);			
		}
		else
		{
			unset($_SESSION[$key]);
		}
	}

	public function destroy()
	{
		session_destroy();
	}

	public function clear($destory_current = false)
	{
		if ($destory_current)
		{
			$this->destroy();
			$this->start($this->domain);
		}
		else
		{
			if ($this->domain)
			{
				$_SESSION[$this->domain] = array();
			}
			else
			{
				$_SESSION = array();
			}
		}
	}

	public function change_id($delete_old_session = false)
	{
		session_regenerate_id($delete_old_session);
	}

	public function replace(array $new_session_array)
	{
		if ($this->domain)
		{
			$_SESSION[$this->domain] = $new_session_array;
		}
		else
		{
			$_SESSION = $new_session_array;
		}
	}

}
