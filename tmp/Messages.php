<?php namespace OneFile;

use AppLog;
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
		//AppLog::message("Messages::bagHas(key=$key, bag=" . print_r($this->bag, true));

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
		//AppLog::message("Messages::bagGet(key=$key, default=" . print_r($default, true) . '), bag=' . print_r($this->bag, true));

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


	protected function _add($messageGroupName = null, $messageKey = null, $messageData = null)
	{
		//AppLog::message("Messages::_add(group=$messageGroupName, key=$messageKey, data=" . print_r($messageData, true) . ')');

		if ($messageGroupName)
		{
			$messageGroupName = $this->groupNamesPrefix . $messageGroupName;

			if ( ! is_null($messageKey))
			{
				return $this->bagSet("$messageGroupName.$messageKey", $messageData);
			}

			$group = $this->bagGet($messageGroupName, array());

			$group[] = $messageData;

			return;
		}

		if ( ! is_null($messageKey))
		{
			return $this->bagSet($messageKey, $messageData);
		}

		if ( ! is_null($messageData))
		{
			//AppLog::message('Messages::_add(), Adding NOT NULL message to BAG ROOT! Data Type = ' . gettype($messageData) . ', IS NULL = ' . is_null($messageData));
			$this->bag[] = $messageData;
		}
	}


	protected function _get($messageGroupName = null, $messageKey = null)
	{
		//AppLog::message("Messages::_get(group=$messageGroupName, key=$messageKey)");

		if ($messageGroupName)
		{
			$messageGroupName = $this->groupNamesPrefix . $messageGroupName;
			$messageGroup = $this->bagGet($messageGroupName, array());
		}
		else
		{
			$messageGroup = $this->bag;
		}

		if (is_null($messageKey))
		{
			// Get the entire group as an array
			return $messageGroup;
		}

		switch (strtolower($messageKey))
		{
			// NOTE: first() and last() return FALSE and NOT NULL when no value was found!  We NEED NULL!
			case 'first': $message = reset($messageGroup); return ($message === FALSE) ? null : $message;
			case 'last' : $message = end($messageGroup); return ($message === FALSE) ? null : $message;
			case 'all'  : return $messageGroup; // Get the entire group as an array
		}

		// Get a specific message in group by key
		return $this->bagGet(($messageGroupName ? "$messageGroupName.$messageKey" : $messageKey));

	}


	protected function _has($messageGroupName = null, $messageKey = null)
	{
		//AppLog::message("Messages::_has(group=$messageGroupName, key=$messageKey)");

		if ($messageGroupName)
		{
			$messageGroupName = $this->groupNamesPrefix . $messageGroupName;
			$messageGroup = $this->bagGet($messageGroupName, array());
		}
		else
		{
			$messageGroup = $this->bag;
		}

		if (is_null($messageKey))
		{
			// Has group and it's not empty?
			return ! empty($messageGroup);
		}

		switch (strtolower($messageKey))
		{
			case 'any': return ! empty($messageGroup); // Has group and it's not empty?
		}

		// Has a specific message in group by key?
		return $this->bagHas(($messageGroupName ? "$messageGroupName.$messageKey" : $messageKey));
	}


	protected function _del($messageGroupName = null, $messageKey = null)
	{
		if ($messageGroupName)
		{
			$messageGroupName = $this->groupNamesPrefix . $messageGroupName;

			if ( ! is_null($messageKey))
			{
				// Remove a specific message in group
				return $this->bagForget("$messageGroupName.$messageKey");
			}

			// Remove an etire group
			return $this->bagForget($messageGroupName);
		}

		if ( ! is_null($messageKey))
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
		//AppLog::message("Messages::__call() Start, name=$name, args=" . print_r($arguments, true));

		do {

			if (strlen($name) < 3) break;

			$action = substr($name, 0, 3);

			if ( ! in_array($action, array('add', 'get', 'has', 'del'))) break;

			$groupName = substr($name, 3);

			//if ($groupName and ($action != 'add') and  ! $this->bagHas($this->groupNamesPrefix . $groupName))
			//{
			//	$groupName .= 's'; // Very basic pluralize! :)
			//}

			$methodName = '_' . $action;

			switch(count($arguments))
			{
				case 0:
					// e.g. $messages->get();
					// e.g. $messages->getErrors();  get{Errors} => Errors == $groupName
					return $this->{$methodName}($groupName);

				case 1:
					// e.g. $messages->add('message');
					// e.g. $messages->addErrors('message');
					// e.g. $messages->get(0)
					$isAdd = ($action == 'add');
					$key  = $isAdd ? null : $arguments[0];
					$data = $isAdd ? $arguments[0] : null;
					return $this->{$methodName}($groupName, $key, $data);

				case 2:
					// e.g $messages->add('error','Error 200: Bad Mistake!');
					// e.g $messages->addErrors('200','Error 200: Bad Mistake!');
					return $this->{$methodName}($groupName, $arguments[0], $arguments[1]);
			}

		} while (0);

		AppLog::error("Messages::__call(), Error - Bad Request: name=$name, args=" . print_r($arguments, true));

		throw new BadMethodCallException('Bad Message Bag Request: Method name needs to begin with: add|set|get|has|del!');
	}

}
