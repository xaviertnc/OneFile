<?php namespace OneFile;

// NB: This is fully functional class but WAY too complicated to use! NM - 25 Nov 2016
// It tries to do too many things in one. Not good!

// Replaced by Collection.php which in-turn gets used to create services like Config and State

use Log; // NB! "Log" is NOT part of OneFile, but part of the APP using this class. Remove in public distro!
use BadMethodCallException;

/**
 * Message Bag Class
 *
 * @author neels - 06 Sep 2014
 *
 * method public add(mixed $message) Adds messages to the bag root. E.g. add('Hello World'), add('alert', 'Watch Out!'), add(array(...))
 * method public get(mixed $reference) Gets messages froms the bag root using $reference. E.g. get(5), get('first'), get('last'), get('all')
 * method public has(mixed $reference) Checks if the bag has any messages using $reference. E.g. has(4), has('any'), has('customkey'),
 * method public addError($message) or addError($key, $message).  Set or append $message to  "_Error" or "_Error.$key". If key exists, append. Else just set value.
 * method public setError($key, $message). This replaces the current key value with $message
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
 * @updated by neels - 19 Feb 2016
 * 		- Fix adding multiple messages to same key.. Should create an auto indexed array.
 *      - Added _set method when you DON'T want to add to a key or group, but instead overwrite it!
 * 		- Fix bagForget method. Seems it was never finished and/or tested
 *
 * 		- TODO: Considder adding a parameter to set and del to specify an index/offset in addition to the traget key, if
 *              the target value is an array.
 * 				e.g. setError('Exception', 5, 'Replacing existing message in "_Error.Exception" array, index = 5')
 * 				or delError('Exception', 5, 'Delete exception message in "_Error.Exception" array, index = 5')
 *
 * 		- TODO: Get singular/plural naming right or remove any references to such a feature.
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
		//Log::message("Messages::bagHas(key=$key, bag=" . print_r($this->bag, true));

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
		//Log::message("Messages::bagGet(key=$key, default=" . print_r($default, true) . '), bag=' . print_r($this->bag, true));

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
		if (is_null($key) or strtolower($key) == 'all')
		{
			$this->bag = array();
			return;
		}

		if (isset($this->bag[$key]))
		{
			unset($this->bag[$key]);
			return;
		}

		$array = & $this->bag;

		$keys = explode('.', $key);

		while (count($keys) > 1)
		{
			$topmostKeySegment = array_shift($keys);

			if ( ! isset($this->bag[$topmostKeySegment]) || ! is_array($this->bag[$topmostKeySegment]))
			{
				return;
			}

			$array =& $array[$topmostKeySegment];
		}

		$targetKey = array_shift($keys);

		//Log::message("Messages::bagForget(key=$key), targetKey=$targetKey, targetArray=" . print_r($array, true) . ')');

		unset($array[$targetKey]);
	}


	protected function _set($messageGroupName = null, $messageKey = null, $messageData = null)
	{
		do {

			if ($messageGroupName)
			{
				$messageGroupName = $this->groupNamesPrefix . $messageGroupName;

				//Log::message("Messages::_set(group=$messageGroupName, key=$messageKey, data=" . print_r($messageData, true) . ')');

				if ( ! is_null($messageKey))
				{
					$this->bagSet("$messageGroupName.$messageKey", $messageData);
					break;
				}

				$this->bagSet($messageGroupName, $messageData);
				break;
			}

			//Log::message("Messages::_set(group=*none*, key=$messageKey, data=" . print_r($messageData, true) . ')');

			if ( ! is_null($messageKey))
			{

				$this->bagSet("$messageKey", $messageData);
				break;
			}

			if ( ! is_null($messageData))
			{
				//Log::message('Messages::_add(), Adding NOT NULL message to BAG ROOT! Data Type = ' . gettype($messageData) . ', IS NULL = ' . is_null($messageData));
				$this->bag = $messageData;
				break;
			}

		} while (0);
	}


	protected function _add($messageGroupName = null, $messageKey = null, $messageData = null)
	{
		do {

			if ($messageGroupName)
			{
				$messageGroupName = $this->groupNamesPrefix . $messageGroupName;

				//Log::message("Messages::_add(group=$messageGroupName, key=$messageKey, data=" . print_r($messageData, true) . ')');

				if ( ! is_null($messageKey))
				{
					$value = $this->bagGet("$messageGroupName.$messageKey");
					//A keyed message can be an array or a scalar value
					if ($value) {
						if (!is_array($value)) { $value = array($value); }
						$value[] = $messageData;
					} else {
						$value = $messageData;
					}
					$this->bagSet("$messageGroupName.$messageKey", $value);
					break;
				}

				$group = $this->bagGet($messageGroupName, array());
				$group[] = $messageData;
				$this->bagSet($messageGroupName, $group);
				break;
			}

			//Log::message("Messages::_add(group=*none*, key=$messageKey, data=" . print_r($messageData, true) . ')');

			if ( ! is_null($messageKey))
			{

				$value = $this->bagGet("$messageKey");
				if ($value and ! is_array($value)) { $value = array($value); }
				$value[] = $messageData;
				$this->bagSet("$messageKey", $value);
				break;
			}

			if ( ! is_null($messageData))
			{
				//Log::message('Messages::_add(), Adding NOT NULL message to BAG ROOT! Data Type = ' . gettype($messageData) . ', IS NULL = ' . is_null($messageData));
				$this->bag[] = $messageData;
				break;
			}

		} while (0);
	}


	protected function _get($messageGroupName = null, $messageKey = null)
	{
		//Log::message("Messages::_get(group=$messageGroupName, key=$messageKey)");

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
		//Log::message("Messages::_has(group=$messageGroupName, key=$messageKey)");

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

			//Log::message("Messages::_del(group=$messageGroupName, key=$messageKey)");

			if ( ! is_null($messageKey))
			{
				// Remove a specific message in group
				return $this->bagForget("$messageGroupName.$messageKey");
			}

			// Remove an etire group
			return $this->bagForget($messageGroupName);
		}

		//Log::message("Messages::_del(group=*none*, key=$messageKey)");

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
		//Log::message("Messages::__call() Start, name=$name, args=" . print_r($arguments, true));

		do {

			if (strlen($name) < 3) break;

			$action = substr($name, 0, 3);

			if ( ! in_array($action, array('add', 'set', 'get', 'has', 'del'))) break;

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
					$isAddOrSet = ($action == 'add' or $action == 'set');
					$key  = $isAddOrSet ? null : $arguments[0];
					$data = $isAddOrSet ? $arguments[0] : null;
					return $this->{$methodName}($groupName, $key, $data);

				case 2:
					// e.g $messages->add('error','Error 200: Bad Mistake!');
					// e.g $messages->addErrors('200','Error 200: Bad Mistake!');
					return $this->{$methodName}($groupName, $arguments[0], $arguments[1]);
			}

		} while (0);

		//Log::error("Messages::__call(), Error - Bad Request: name=$name, args=" . print_r($arguments, true));

		throw new BadMethodCallException('Bad Message Bag Request: Method name needs to begin with: add|set|get|has|del!');
	}

}
