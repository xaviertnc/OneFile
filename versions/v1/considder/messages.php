<?php namespace OneFile;

use BadMethodCallException;

/**
 * Message Bag Class
 *  
 * @author neels - 06 Sep 2014
 * 
 * method public add(mixed $message) Adds messages to the bag root. E.g. add('Hello World'), add('alert', 'Watch Out!'), add(array(...))
 * method public get(mixed $reference) Gets messages froms the bag root using $reference. E.g. get(5), get('first'), get('last'), get('all')
 * method public has(mixed $reference) Checks if the bag has any messages using $reference. E.g. has(4), has('any'), has('customkey'),
 * method public addError($message) or addError($type, $message).  This first appends the error message to the end of the bag root with a numeric key.
 * method public getError(mixed $reference) $reference: first, last, key: typename, code, etc...
 * method public hasError($reference) $reference: key: typename, code, etc...
 * method public getErrors(mixed $reference) $reference: all, first, last, key: typename, code, etc...
 * method public hasErrors()
 * method public addAlert($type, $message) Adds messages to the bag root. E.g. add('Hello World'), add('alert', 'Watch Out!'), add(array(...))
 * method public getAlert(mixed $reference) $reference: first, last, key: typename, code, etc...
 * method public hasAlert($reference) $reference: key: 'notice', 'danger', 'warning', etc...
 * method public getAlerts(mixed $reference) $reference: all, first, last, key: typename, code, etc...
 * method public hasAlerts()
 * method public del(mixed $reference) Removes a message or group from the message bag.
 * 
 */
class Messages
{
	/**
	 * A groupname prefix to namespace/differentiate group keys from message keys in the message bag.
	 * 
	 * @var string
	 */
	protected $groupNamesPrefix;


	/**
	 * Wrapper array for all messages
	 * 
	 * @var array
	 */
	protected $bag = array();
	
	
	/**
	 * 
	 * @param string $groupKeysPrefix
	 */
	public function __construct($groupKeysPrefix = '_')
	{
		$this->groupNamesPrefix = $groupKeysPrefix;
	}


	/**
	 * Sets an array value with dot-notation allowed
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	protected function bagSet($key, $value)
	{
		if (strpos($key, '.') === false)
		{
			$this->bag[$key] = $value;
		}
		else
		{
			$current = & $this->bag;

			foreach (explode('.', $key) as $key)
			{
				$current = & $current[$key];
			}

			$current = $value;
		}	
	}

	
	/**
	 * Checks if an array key exists with dot-notation allowed
	 * 
	 * @param string $key
	 * @return boolean
	 */
	protected function bagHas($key)
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
	 * Gets a messagebag item with dot-notation allowed
	 * Uses code from laravel array_get() helper
	 * 
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function bagGet($key = null, $default = null)
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
	 * Removes a messagebag item using "dot" notation.
	 *
	 * @param  string  $key
	 * @return void
	 */
	protected function bagForget($key)
	{
		$keys = explode('.', $key);
		
		while (count($keys) > 1)
		{
			$key = array_shift($keys);

			if ( ! isset($this->bag[$key]) || ! is_array($this->bag[$key]))
			{
				return;
			}

			$array =& $array[$key];
		}

		unset($array[array_shift($keys)]);
	}
	
	
	protected function _add($messageData, $messageGroup = null, $messageKey = null)
	{
		if ($messageGroup)
		{
			$messageGroup = $this->groupNamesPrefix . strtolower($messageGroup);
			
			if ($messageKey)
			{
				return $this->bagSet("$messageGroup.$messageKey", $messageData); 
			}
			
			$group = $this->bagGet($messageGroup, array());
			
			$group[] = $messageData;
			
			return;
		}

		if ($messageKey)
		{
			return $this->bagSet($messageKey, $messageData); 
		}		
		
		$this->bag[] = $messageData;

		return;
	}
	
	
	protected function _get($messageGroup = null, $messageKey = null)
	{
		if ($messageGroup)
		{
			$messageGroup = $this->groupNamesPrefix . strtolower($messageGroup);
			
			if ( ! $messageKey)
			{
				// Get the entire group as an array
				return $this->bagGet($messageGroup, array());
			}
			
			$messageKey = strtolower($messageKey);
			
			switch ($messageKey)
			{
				case 'last':
					// Get LAST message in group
					return end($this->bagGet("$messageGroup"));
					
				case 'first':
					// Get FIRST message in group
					return reset($this->bagGet("$messageGroup"));
					
				case 'all':
					// Get the entire group as an array
					return $this->bagGet("$messageGroup", array());

				default:
					// Get a specific message in group by key
					return $this->bagGet("$messageGroup.$messageKey");
			}
		}

		if ($messageKey)
		{
			// Get a specific message in root
			return $this->bagGet($messageKey);
		}

		// Get the entire BAG as an array
		return $this->bag[];
	}	
	
	
	protected function _has($messageGroup = null, $messageKey = null)
	{
		if ($messageGroup)
		{
			$messageGroup = $this->groupNamesPrefix . strtolower($messageGroup);
			
			if ( ! $messageKey)
			{
				// Has group and it's not empty?
				return ! empty($this->bagGet("$messageGroup"));
			}
			
			$messageKey = strtolower($messageKey);
			
			switch ($messageKey)
			{					
				case 'any':
					// Has group and it's not empty?
					return ! empty($this->bagGet("$messageGroup"));

				default:
					// Has a specific message in group by key?
					return $this->bagHas("$messageGroup.$messageKey");
			}						
		}

		if ($messageKey)
		{
			// Has specific message in root?
			return $this->bagHas($messageKey);
		}

		// Has BAG and it's not empty?
		return ! empty($this->bag[]);
	}
	
	
	protected function _del($messageGroup = null, $messageKey = null)
	{
		if ($messageGroup)
		{
			$messageGroup = $this->groupNamesPrefix . strtolower($messageGroup);
			
			if ($messageKey)
			{
				// Remove a specific message in group
				return $this->bagForget("$messageGroup.$messageKey");
			}
			
			// Remove an etire group
			return $this->bagForget($messageGroup);
		}

		if ($messageKey)
		{
			// Remove specific message in root
			return $this->bagForget($messageKey);
		}

		// Clear the entire BAG
		return $this->bag[] = array();
	}	
	
	
	/**
	 * 
	 * @param type $name
	 * @param type $arguments
	 */
	public function __call($name, $arguments)
	{
		do {
			
			if (strlen($name) < 3) break;

			$action = substr($name, 0, 3);

			if ( ! in_array($action, array('add', 'get', 'has', 'del'))) break;

			$groupName = strtolower(substr($name, 3));
						
			if ($groupName and ($action != 'add') and  ! $this->bagHas($this->groupNamesPrefix . $groupName))
			{
				$groupName .= 's'; // Very basic pluralize! :)
			}
			
			$methodName = '_' . $action;

			switch(count($arguments))
			{
				case 0:
					// e.g. $messages->get();
					// e.g. $messages->getErrors();  get{Errors} => Errors == $groupName
					return $this->{$methodName(null, null, $groupName)};
					
				case 1:
					// e.g. $messages->add('message');
					// e.g. $messages->addErrors('message');
					return $this->{$methodName($arguments[0], null, $groupName)};

				case 2:
					// e.g $messages->add('error','Error 200: Bad Mistake!');
					// e.g $messages->addErrors('200','Error 200: Bad Mistake!');
					return $this->{$methodName($arguments[1], $groupName, $arguments[0])};
			}
			
		} while (0);
		
		throw new BadMethodCallException('Bad Message Bag Request: Method name needs to begin with: add|set|get|has|del!');
	}	
	
}