<?php namespace OneFile;

use AppLog;
use DateTime;
use Exception;

class MissingValueException extends Exception {}
class MissingMethodException extends Exception {}
class ProtectedAttributeException extends Exception {}

/**
 * BasicEntity Class for PHP 5.3 and older.
 * Do not use "PASS BY REFERENCE" on getters as it is not properly supported before PHP 5.4!
 * 
 * @author C. Moller <xavier.tnc@gmail.com> - 02 May 2014
 * 
 * Re-write 04/05 May 2014
 * Simplify + Remove Validator + Mutator classes + Change code style - 21 Jun 2014
 * 
 * Licensed under the MIT license. Please see LICENSE for more information.
 * 
 */
class Entity53
{
	const MODE_NONE = 0;
	const MODE_CREATE = 1;
	const MODE_UPDATE = 2;
	const MODE_EXPORT = 3;
	const MODE_SAVE = 4;
	const MODE_VIEW = 5;
	
	/**
	 * Used in getMode()
	 * 
	 * @var array
	 */
	protected $modeIdentifiers = array(
		1 => 'createmode',
		2 => 'updatemode',
		3 => 'exportmode',
		4 => 'savemode',
		5 => 'viewmode',
	);
	
	/**
	 * Where all current Entity property / field / attribute (all same thing really) values are kept.
	 * 
	 * NOTE: There is NO need to namespace PROTECTED properties with a leading 
	 * underscore or something.  Since __get and __set can only access PUBLIC
	 * properties, entity attributes with the same name as protected properties
	 * will NOT conflict!
	 * 
	 * @var array 
	 */
	protected $attributes = array();

	/**
	 * Specifies the data type and other meta properties for each entity attribute.
	 * 
	 * Use TYPE values (string|numeric|date|etc...) to display 
	 * attributes correclty in views and reversely apply client
	 * input mutators.
	 * 
	 * @var array 
	 */
	protected $attributesMetaData = array();

	/**
	 * Keeps a copy of the initial attribute values to detect changes in the data
	 * after an update. This allows evaluation of changes before saving and reverting
	 * back to initial values.
	 *   
	 * @var array 
	 */
	protected $initialValues = array();

	/**
	 * A FIXED list of allowed attributes names in ANY mode.
	 * An EMPTY or NULL list disables this filter.
	 * 
	 * @var array 
	 */
	protected $allowed = array();
	
	/**
	 * The reverse of $allowed
	 *  
	 * @var array 
	 */
	protected $notAllowed = array();
	
	/**
	 * A FIXED list of VIEWBLE attribute names.
	 * An EMPTY or NULL list disables this filter.
	 * 
	 * Used in __get(), getAttribute(), toArray() and toJson() when ATTR_MODE = VIEW
	 * 
	 * @var array
	 */
	protected $viewable = array();
	
	/**
	 * The reverse of $viewable
	 * 
	 * @var array 
	 */
	protected $notViewable = array();
	
	/**
	 * A FIXED list of UPDATEABLE / SAVEABLE attribute names.
	 * An EMPTY or NULL list disables this filter.
	 * 
	 * @var type 
	 */
	protected $updatable = array();	
	
	/**
	 * Essentially the reverse of $updatable with a few extra implications.
	 * 
	 * Read-Only attributes DON'T have an INITIAL VALUE COPY, making them lighter on resources.
	 * Checking for changes obviously won't work since we assume the value will never change and
	 * we don't have an initial value to compare against.
	 * 
	 * Read-Only attributes will be rejected by the update() method and can ONLY be set
	 * by the load() method or if the entity MODE == ATTR_MODE_INITIALIZE.
	 * 
	 * Read-Only attributes CAN NOT be saved.
	 * 
	 * An EMPTY or NULL list disables this filter.
	 * 
	 * @var array
	 */
	protected $notUpdatable = array();
	
	/**
	 * A FIXED list of EXPORTABLE attribute names.
	 * An EMPTY or NULL list disables this filter.
	 * 
	 * Used in __get(), getAttribute(), toArray() and toJson() when ATTR_MODE = EXPORT
	 * 
	 * @var array
	 */
	protected $exportable = array();
	
	/**
	 * The reverse of $exportable
	 * 
	 * @var array 
	 */
	protected $notExportable = array();
	
	/**
	 * A FIXED list of SAVEABLE attribute names.
	 * An EMPTY or NULL list disables this filter.
	 * 
	 * Used in __get(), getAttribute(), toArray() and toJson() when ATTR_MODE = SAVE
	 * 
	 * @var array
	 */
	protected $saveable = array();
	
	/**
	 * The reverse of $saveable
	 * 
	 * @var array 
	 */
	protected $notSaveable = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $validationRules = array();

	/**
	 *
	 * @var array 
	 */
	protected $validationMessages = array();

	/**
	 *
	 * @var string Possible values: load, view, update, save, export
	 */
	protected $entityMode;
	
	
	/**
	 * Instantiates and initializes an entity
	 * 
	 * @param array $initialAttributeValues Array of current name => value pairs before updates.  Usually from an some persistence source.
	 * @param array $defaults Array of default name => value pairs if attributes aren't specified in $initailAttributeValues taken from some data source.
	 * @param array $allowed
	 * @param array $notAllowed
	 */
	public function __construct($initialAttributeValues = array(), $defaults = array(), $allowed = null, $notAllowed = null)
	{
		$this->load($initialAttributeValues, $defaults, $allowed, $notAllowed);
	}
	
	
	// -------------------------------------------------
	// -------------------- UTILITY --------------------
	// -------------------------------------------------	
	
	/**
	 * Built-in helper function
	 * 
	 * @param array $array
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function arrayGet($array, $key, $default = null)
	{
		return isset($array[$key]) ? $array[$key] : $default;
	}
	
	
	// -------------------------------------------------
	// ------------------- LOAD DATA -------------------
	// -------------------------------------------------	
	
	/**
	 * 
	 * @param array $attributeInitialValues
	 * @param array $defaults
	 * @param array $allowed
	 * @param array $notAllowed
	 */
	public function load($attributeInitialValues = array(), $defaults = array(), $allowed = array(), $notAllowed = array())
	{		
		if ($allowed) $this->allowed = $allowed;
		if ($notAllowed) $this->notAllowed = $notAllowed;				
		
		// Convert $initialAttributeValues to an array if we get passed some kind of FALSY object instead of an array!
		if ( ! $attributeInitialValues) $attributeInitialValues = array();

		// Create Mode will ensure that setAttribute() also sets initial values if the attributes are Updatable!
		$this->setMode(static::MODE_CREATE);	
				
		foreach ($attributeInitialValues as $attribute => $value)
		{						
			if ($attribute[0] == '_') continue;
			$this->setAttribute($attribute, $value);
		}
		
		// If after initialization / creation we have any attributes defined, it means we provided 
		// starting / initial values and we need to go into UPDATE MODE form here!
		// If we have no attributes, it means we instantiated an empty entity with the intention of 
		// initializing / creating it. For this condition we remain in CREATE MODE.
		if ($this->attributes)
		{
			return $this->setMode(static::MODE_UPDATE);
		}

		foreach ($defaults?:array() as $default => $value)
		{
			if ( ! isset($this->attributes[$default])) $this->setAttribute($default, $value);
		}
	}
	
	
	// ---------------------------------------------
	// ------------- UPDATE DATA  ------------------
	// ---------------------------------------------	
	
	/**
	 * Updates all or most of the entity properties from user input / feedback.
	 * Only attributes that CAN / IS ALLOWED TO change will be updated!
	 * 
	 * Note: You can only update attributes that have already been defined or is defined in your $allowed list.
	 * 
	 * @param array $updatedAttributeValues  Array of attribute name=>value pairs.
	 * @param array $defaults
	 */
	public function update($updatedAttributeValues = array(), $defaults = array())
	{
		// Setting the mode ensures that setAttribute() handles / formats the input data correctly.
		$this->setMode(static::MODE_UPDATE);
		
		// Convert a FALSY array into an empty array
		if ( ! $updatedAttributeValues) { $updatedAttributeValues = array(); }
		
		// Note: array_merge will only preserve array keys if the arrays have NAMED KEYS. i.e. associative. I.e. Always use Assoc. arrays
		if ($defaults) { $updatedAttributeValues = array_merge($defaults, $updatedAttributeValues); }
		
		foreach (array_keys($updatedAttributeValues) as $attributeName)
		{						
			if ($attributeName[0] == '_') continue;
			$this->setAttribute($attributeName, $updatedAttributeValues[$attributeName]);
		}
	}
	
	
	// -------------------------------------------------
	// ----------- EXPORT AND / OR SAVE DATA -----------
	// -------------------------------------------------
	
	/**
	 * Override Me!
	 * 
	 * @param integer $mode
	 * @param boolean $mutate
	 * @param array $keep
	 * @param array $ignore
	 * @return array
	 */
	public function toArray($mode = null, $mutate = true, $keep = array(), $ignore = array())
	{
		return $this->getAccessibleAttributes($this->attributes, $keep, $ignore, $mode?:$this->entityMode, $mutate);
	}

	/**
	 * Override Me!
	 *
	 * @param integer $mode
	 * @param boolean $mutate
	 * @param array $keep
	 * @param array $ignore
	 * @return array
	 */
	public function toJson($mode = null, $mutate = true, $keep = array(), $ignore = array())
	{
		return json_encode($this->toArray($mode, $mutate, $keep, $ignore));
	}
	
	
	// ==========================================================
	// -------------------- INTERNAL SERVICES -------------------
	// ==========================================================
	
	// ----------------------------------------------------------
	// ---------------- ATTRIBUTE STATE SERVICE -----------------
	// ----------------------------------------------------------	
	
	/**
	 * Tests if any attributes or a single attribute was modified.
	 * If $attributeName == null, ALL attributes are checked.
	 * 
	 * @param string $attributeName
	 * @return boolean Returns the number modified attributes if no attribute name is specified.
	 */
	public function isModified($attributeName = null)
	{		
		if ($attributeName)
		{
			return (in_array($attributeName, $this->initialValues) and 
				$this->attributes[$attributeName] <> $this->initialValues[$attributeName]);
		}
		else
		{			
			if ($this->updatable)
			{
				$checkableAttributes = & $this->updatable;
			}
			else
			{
				$checkableAttributes = array_diff(array_keys($this->attributes), $this->notUpdatable);
			}

			if (count($checkableAttributes) <> count($this->initialValues)) return true;

			foreach ($checkableAttributes as $attributeName)
			{
				if ($this->attributes[$attributeName] <> $this->initialValues[$attributeName]) return true;
			}
		}
	}
	
	/**
	 * Get all the modified attribute names as an array.
	 * 
	 * NOTE: Since READONLY values aren't added to $initialValues in the constructor, 
	 * we don't need to considder them here.
	 * 
	 * @return array
	 */
	public function getModifiedAttributes()
	{
		$modifiedAttributes = array();
		
		if ($this->updatable)
		{
			$checkableAttributes = & $this->updatable;
		}
		else
		{
			$checkableAttributes = array_diff(array_keys($this->attributes), $this->notUpdatable);
		}

		foreach ($checkableAttributes as $attributeName)
		{
			if ($this->attributes[$attributeName] <> $this->initialValues[$attributeName])
			{
				$modifiedAttributes[] = $attributeName;
			}
		}

		return $modifiedAttributes;
	}
	
	
	// ----------------------------------------------------------
	// ----------- EXTENDABLE ACCESS CONTROL SERVICE ------------
	// ----------------------------------------------------------
	
	/**
	 * Override Me!
	 * 
	 * @param string $attributeName
	 * @param array $keep
	 * @param array $ignore
	 * @param integer $mode
	 * @return boolean
	 */
	public function isProtected($attributeName, $keep = array(), $ignore = array(), $mode = null)
	{
		if ($keep or $ignore)
		{
			$mode = null;
		}
		else
		{
			if ( ! $mode) $mode = $this->entityMode;
		}
		
		switch ($mode)
		{
			case static::MODE_CREATE:				
				$keep = $this->allowed;
				$ignore = $this->notAllowed;
				break;
			
			case static::MODE_UPDATE:
				$keep = $this->updatable;
				$ignore = $this->notUpdatable;
				break;
			
			case static::MODE_EXPORT:
				$keep = $this->exportable;
				$ignore = $this->notExportable;
				break;
			
			case static::MODE_SAVE:
				$keep = $this->saveable;
				$ignore = $this->notSaveable;
				break;
			
			case static::MODE_VIEW:
			default:
				// $keep = $keep;
				// $ignore = $ignore;
		}
		
		if ($ignore and in_array($attributeName, $ignore)) return true;
		if ($keep and ! in_array($attributeName, $keep)) return true;
		
		return false;
	}
	
	/**
	 * 
	 * @param string $attributeName
	 * @param array $keep
	 * @param array $ignore
	 * @param integer $mode
	 * @return boolean
	 */
	public function isAccessable($attributeName, $keep = array(), $ignore = array(), $mode = null)
	{
		return ! $this->isProtected($attributeName, $keep, $ignore, $mode);
	}
	
	/**
	 * Reduces an array of attributes to only those accessible in the current mode.
	 * 
	 * @param array $attributes
	 * @param array $keep
	 * @param array $ignore
	 * @param integer $mode Minimum possible values: 1, 2, 3, ... See Mode Constants.
	 * @return array
	 */
	public function getAccessibleAttributes(array $attributes = array(), $keep = array(), $ignore = array(), $mode = null, $mutate = false)
	{
		$results = array();
		
		if ($mutate)
		{
			//Mutate all the attributes to their correct format for the specified mode.
			$attributes = $this->mutateAll($mode, $attributes);
		}
		
		foreach ($attributes as $attributeName => $attributeValue)
		{
			if ($this->isAccessable($attributeName, $keep, $ignore, $mode)) { $results[$attributeName] = $attributeValue; }
		}
		
		return $results;
	}
	
	
	// ----------------------------------------------------------
	// --------- EXTENDABLE INTEGRATED VALIDATION SERVICE -------
	// ----------------------------------------------------------
	
	/**
	 * Called on each isValid() request.
	 * 
	 * Override Me!
	 * Either improve this implementation or just delegate the
	 * request to an external Validator class. See first commented line...
	 * 
	 * @param string $attributeName
	 * @param mixed $value
	 * @return stdClass
	 */
	public function validate($attributeName, $value)
	{
		// return new \OneFile\Validator($value, $this->arrayGet($this->rules, $attribute_name, array()));
		
		// Very basic validator implementation ... Only handles "required" rules
		$validator = new \stdClass();
		
		$attributeValidationRules = $this->arrayGet($this->validationRules, $attributeName, array());
		
		if (in_array('required', array_keys($attributeValidationRules)))
		{
			$validator->invalid = empty($value);
			
			if ($validator->invalid)
			{
				$validator->messages = $this->arrayGet($attributeValidationRules, 'required', 'Required');
			}
		}
		else
		{
			$validator->invalid = false;
		}

		$validator->valid = ! $validator->invalid;
		
		return $validator;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function isValid($attributeName = null)
	{
		$is_valid = true;
		
		if ($attributeName)
		{
			$attributes = array($attributeName => $this->getArray($this->attributes, $attributeName));
		}
		else
		{
			$attributes = $this->attributes;
		}
		
		foreach ($attributes as $attributeName => $value)
		{
			$result = $this->validate($attributeName, $value);

			if ($result->invalid)
			{
				$this->validationMessages[$attributeName] = $result->messages;
				$is_valid = false;
			}
		}
		
		return $is_valid;
	}

	/**
	 * 
	 * @return boolean
	 */
	public function notValid($attributeName = null)
	{
		return ! $this->isValid($attributeName);
	}
	
	
	// -------------------------------------------------------------
	// --------- EXTENDABLE INTEGRATED VALUE MUTATOR SERVICE -------
	// -------------------------------------------------------------

	/**
	 * When loading and posting, we want to update $this->attributes. For saving we don't want to change the
	 * local attributes, but only save a muted copy of them. To display an attribute, we can mutate it individually!
	 * 
	 * @param string $attributeName  If specified, use this array instead of $this->attributes
	 * @param mixed $value  The value to mutate in single value mode.
	 * @param mixed $default  The default returned value in single value mode.
	 * @param string $format
	 * @return mixed
	 * @throws MissingValueException
	 * @throws MissingMethodException
	 */
	public function mutate($attributeName, $value = null, $default = null, $format = null)
	{	
		
		//if ($attributeName == 'date' or $this->modeIdentifiers == static::MODE_SAVE) 
		//	AppLog::mutate("Entity53::mutate($attributeName), Mode=" . $this->getModeIdentifier() . ", Format=$format, Value = " . 
		//		(($attributeName == 'text') ? '...' : print_r($value, true)));

		if (is_null($value)) { $value = $this->arrayGet($this->attributes, $attributeName); }
		
		// Exit with $default, if the value is still NULL.
		if (is_null($value))
		{
			if ($attributeName == 'date' or $this->modeIdentifiers == static::MODE_SAVE)
			{
				AppLog::mutate("Entity53::mutate($attributeName), Value = $value, Setting value = dafault = $default");
			}

			return $default;
		}
		
		if ($value === '_NULL_')
		{
			if ($attributeName == 'date' or $this->modeIdentifiers == static::MODE_SAVE)
			{
				AppLog::mutate("Entity53::mutate($attributeName), Value = $value. Setting value == NULL");
			}
			
			$value = null;
		}
		
		$metaData = $this->arrayGet($this->attributesMetaData, $attributeName);

		if ( ! $metaData) return $value;

		$mutators = $this->arrayGet($metaData, 'mutators');

		if ( ! $mutators) return $value;

		$mutator = $this->arrayGet($mutators, ($format?:$this->getModeIdentifier()));

		//Check if hhe specified mutator variant is a reference to another variant!
		if (is_string($mutator)) $mutator = $this->arrayGet($mutators, $mutator);

		if ( ! $mutator) return $value;

		$type = ucfirst($this->arrayGet($mutator, 'type'));

		if ( ! $type)
		{ 
			throw new MissingValueException(
				'Entity53::mutate(), Missing "type" index in $mutator! $mutator = ' . print_r($mutator, true)
			);
		}

		$methodName = "mutate$type";

		//if ($attributeName == 'date' or $this->modeIdentifiers == static::MODE_SAVE) AppLog::mutate("Entity53::mutate(value=$value), method=$methodName, mutator=" . print_r($mutator, true));

		if (method_exists($this, $methodName))
		{
			// We could loop through multiple mutators, but for this simple implementation we assume
			// ONE mutator per request direction per attribute.
			return $this->{$methodName}($value, $mutator);
		}
		else
		{
			throw new MissingMethodException('Entity53::mutate(), Missing Mutator Method: "' . $methodName . '"!');				
		}		
	}
	
	/**
	 * Mutate the $attributes parameter or $this->attributes and return the resulting array.
	 * When loading or updating, we want to directly update $this->attributes.
	 * When exporting or saving we don't want to change $this->attributes and only want a mutated version of the supplied $attributes array.
	 * 
	 * @param integer $entityMode
	 * @param array $attributes  If specified, use this array instead of $this->attributes and return a mutated version.
	 * @return array|null If $attributes == NULL, $this->attributes will be updated directly and NULL returned. Else return mutated attributes array.
	 * @throws MissingValueException
	 * @throws MissingMethodException
	 */
	public function mutateAll($entityMode = null, $attributes = null)
	{	
		$directMode = empty($attributes);
		
		if ($directMode) $attributes = &$this->attributes; // link directly to local attributes
		
		$entityModeIdentifier = $entityMode ? $this->getModeIdentifier($entityMode) : $this->getModeIdentifier();
		
		foreach ($attributes?:array() as $attributeName => $value)
		{
			$attributes[$attributeName] = $this->mutate($attributeName, $value, null, $entityModeIdentifier);
		}
		
		if ( ! $directMode) return $attributes;
	}
	
	/**
	 * Date mutator method that can be used independantly from the getters / setters.
	 * Therefore also PUBLIC.
	 * 
	 * @param mixed $value
	 * @param array $options Mutator options like: format, timezone, etc...
	 * @return mixed
	 */
	protected function mutateDateTime($value, $options = array())
	{
		if (is_null($value)) return null;
		
		$format = trim($this->arrayGet($options, 'format'));
		$lcformat = strtolower($format);
		
		switch ($lcformat)
		{
			case 'unix':
				if (is_object($value)) { $result = $value->getTimestamp(); break; }
				
				if (is_string($value)) { $result = strtotime($value); break; }
				
				$result = $value;
				break;
				
			case 'datetime':
				// NB: options['timezone'] == DateTimeZone object e.g. new DateTimeZone('Africa/Johannesburg'))
				$result = new DateTime($value, $this->arrayGet($options, 'timezone'));
				break;
			
			default:
				if ( ! $format) { $result = $value; break; } //e.g. $format = 'Y-m-d'

				if (is_object($value)) { $result = $value->format($format); break; }

				$result = date($format, (is_string($value) ? strtotime($value) : $value));
		}
		
		//The above $value assignments should be "returns"!  Only assignined $value for debugging purposes.
		if ($this->modeIdentifiers == static::MODE_SAVE) AppLog::mutate("Entity53::mutateDateTime(), fmt='$format', mutated-value='" . print_r($result, true) . "'");
		
		return $result;
	}
	
	/**
	 * Convenience clone of mutateDateTime()
	 *  
	 * @param mixed $value
	 * @param array $options
	 * @return mixed
	 */
	protected function mutateDate($value, $options = array())
	{
		return $this->mutateDateTime($value, $options);
	}
	
	/**
	 * Convenience clone of mutateDateTime()
	 * 
	 * @param mixed $value
	 * @param array $options
	 * @return mixed
	 */
	protected function mutateTime($value, $options = array())
	{
		if ($this->modeIdentifiers == static::MODE_SAVE) AppLog::mutate("Entity53::mutateTime(), value = $value, isString = " . is_string($value)); 
		if (is_string($value)) { $value = preg_replace('/[h,H](?=\d+)/', ':', $value); }
		return $this->mutateDateTime($value, $options);
	}
	
	/**
	 * Get custom TRUE / FALSE values
	 * 
	 * @param mixed $value
	 * @param array $options Mutator options like: format, true-text, false-text, etc...
	 * @return mixed
	 */
	protected function mutateBoolean($value, $options = array())
	{
		$format		= strtolower(trim($this->arrayGet($options, 'format')));
		$trueText	= $this->arrayGet($options, 'true-text',  'true');
		$falseText	= $this->arrayGet($options, 'false-text', 'false');
		
		if ( ! $value)
		{
			$boolState = false;
		}
		else
		{
			if (is_string($value))
			{
				$boolState = (strtolower(trim($value)) !== strtolower(trim($falseText)));
			}
			else
			{
				$boolState = true;
			}
		}
		
		if ($format == 'boolean')
		{
			$result = $boolState;
		}
		elseif ($format == 'string')
		{
			$result = $boolState ? $trueText : $falseText;
		}			
			
		return $result;
	}

	
	// ------------------------------------------------------------
	// ---------- EXTENDABLE INTERNAL MESSAGING SERVICE -----------
	// ------------------------------------------------------------	

	/**
	 * 
	 * @param mixed $message
	 * @param string $attributeName
	 */
	public function addMessage($message, $attributeName = null)
	{
		if ($attributeName)
		{
			$this->validationMessages[$attributeName][] = $message;
		}
		else
		{
			$this->validationMessages[] = $message;
		}
	}
	
	/**
	 * Get attribute messages as an array or formatted string.

	 * Get messages for a specific attribute or "all" attributes.
	 * An $attributeName of "null" also fetches messages for all attributes.
	 *  
	 * @param string $attributeName
	 * @param boolean $asString Collapse messages array into a formatted string.
	 * @param string $decorator Decorate each message with a sprintf() style format string.
	 * @return mixed Array or String
	 */
	public function getMessages($attributeName = 'all', $asString = true, $decorator = '<p>%s</p>')
	{
		if (empty($attributeName) or strtolower($attributeName) == 'all')
		{
			$messages = $this->validationMessages;
		}
		else
		{
			$messages = $this->arrayGet($this->validationMessages, $attributeName, array());
		}

		if ( ! $asString)
		{
			return $messages;
		}

		$stringified = '';

		foreach ($messages as $message)
		{
			if ( ! is_array($message))
			{
				$stringified .= sprintf($decorator, $message);
				continue;
			}

			foreach ($message as $sub_message)
			{
				$stringified .= sprintf($decorator, $sub_message);
			}
		}

		return $stringified;
	}

	
	
	// ==========================================================
	// ------------------ GETTERS AND SETTERS -------------------
	// ==========================================================
		
	// ----------------------------------------------------------
	// ------------------------ GETTERS -------------------------
	// ----------------------------------------------------------		
	
	/**
	 * 
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	
	/**
	 * 
	 * @param string $attributeName
	 * @return array
	 */
	public function getAttributeMeta($attributeName)
	{
		return $this->arrayGet($this->attributesMetaData, $attributeName, array());
	}
	
	/**
	 * Get any field value / property of the entity
	 * 
	 * @param string $attributeName
	 * @param mixed $default
	 * @param string $format
	 * @return mixed
	 */
	public function getAttribute($attributeName, $default = null, $format = null)
	{						
		if ($format)
		{
			return $this->mutate($attributeName, null, $default, $format);
		}
		
		return $this->arrayGet($this->attributes, $attributeName, $default);
	}
	
	/**
	 * Get the attribute TYPE from the attribute's META data.
	 * 
	 * @param string $attributeName
	 * @return string
	 */
	public function getAttributeType($attributeName)
	{
		$meta = $this->getAttributeMeta($attributeName);
		return $this->arrayGet($meta, 'type');
	}
	
	/**
	 * @return string
	 */
	public function getModeIdentifier($mode = null)
	{
		return $this->arrayGet($this->modeIdentifiers, ($mode?:$this->entityMode));
	}
	
	
	// ----------------------------------------------------------
	// ------------------------ SETTERS -------------------------
	// ----------------------------------------------------------		
	
	/**
	 * Set any field value / property of the entity.
	 * 
	 * NB: Only initAttribute() and update() is affected by the $allowed and $readOnly filters.
	 * 
	 * @param string $attributeName
	 * @param mixed $value
	 * @param string $format Optional mutator name, Must be defined in MetaData of the attribute.
	 * @return Entity
	 */
	public function setAttribute($attributeName, $value = null, $format = null)
	{		
		//AppLog::debug("Entity53::setAttribute($attributeName), Start: CurrentMode={$this->entityMode}, Format=$format,	Value=" .
		//	Format::limit(htmlentities(print_r($value, true))));
		
		if ($this->isProtected($attributeName))
		{
			throw new ProtectedAttributeException("Setting Attr: '$attributeName' Not Allowed! Mode=" . $this->getModeIdentifier());
		}

		$this->attributes[$attributeName] = $this->mutate($attributeName, (is_null($value) ? '_NULL_' : $value), null, $format);

		if($this->entityMode == static::MODE_CREATE and $this->isAccessable($attributeName, $this->updatable, $this->notUpdatable))
		{
			// We don't set initial values for attributes that can not change.
			$this->initialValues[$attributeName] = $this->attributes[$attributeName];
		}
		
		// AppLog::debug("Entity53::setAttribute($attributeName), Done: Result=" . 
		//	Format::limit(htmlentities(print_r($this->attributes[$attributeName], true))));
		
		return $this;
	}

	/**
	 * 
	 * Note: NO Checks or Formating!
	 * 
	 * @param array $attributes
	 * @param mixed $value
	 */
	public function setAttributes(array $attributes)
	{		
		$this->attributes = $attributes;
	}

	/**
	 * Set Validation Rules.
	 * 
	 * Format: array(attributeName => array(rule1 => params1, rule2 => params2, ...), ...)
	 * 
	 * @param array $rules
	 * @return Entity
	 */
	public function setValidationRules(array $rules)
	{
		$this->validationRules = $rules;
		
		return $this;
	}
	
	/**
	 * Set Attribute Additional / Meta Data
	 * 
	 * @param array $attributeMetaData  array('attrb1' => array('type' => 'integer', 'max' => 1000, ...), 'attrb2' => array('type' => 'date', ...)
	 * @return Entity
	 */
	public function setAttributesMetaData(array $attributeMetaData)
	{
		$this->attributesMetaData = $attributeMetaData;
		
		return $this;
	}
	
	/**
	 * A filter that only allows setting attributes with names that are in this list.
	 * 
	 * IF allowed == NULL or allowed == EMPTY THEN allowed filter == Disabled.
	 * 
	 * @param array $attributeNames
	 * @return \OneFile\Entity
	 */
	public function setAllowedAttributes(array $attributeNames, $asNotAllowed = false)
	{
		if ($asNotAllowed)
		{
			$this->notAllowed = $attributeNames;
		}
		else
		{
			$this->allowed = $attributeNames;
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param array $attributeNames
	 * @param boolean $asNotUpdatable
	 * @return \OneFile\Entity53
	 */
	public function setUpdatableAttributes(array $attributeNames, $asNotUpdatable = false)
	{
		if ($asNotUpdatable)
		{
			$this->notUpdatable = $attributeNames;
		}
		else
		{
			$this->updatable = $attributeNames;
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param array $attributeNames
	 * @param boolean $asNotViewable
	 * @return \OneFile\Entity53
	 */
	public function setViewableAttributes(array $attributeNames, $asNotViewable = false)
	{
		if ($asNotViewable)
		{
			$this->notViewable = $attributeNames;
		}
		else
		{
			$this->viewable = $attributeNames;
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param array $attributeNames
	 * @param boolean $asNotExportable
	 * @return \OneFile\Entity53
	 */
	public function setExportableAttributes(array $attributeNames, $asNotExportable = false)
	{
		if ($asNotExportable)
		{
			$this->notExportable = $attributeNames;
		}
		else
		{
			$this->exportable = $attributeNames;
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param array $attributeNames
	 * @param boolean $asNotSaveable
	 * @return \OneFile\Entity53
	 */
	public function setSaveableAttributes(array $attributeNames, $asNotSaveable = false)
	{
		if ($asNotSaveable)
		{
			$this->notSaveable = $attributeNames;
		}
		else
		{
			$this->saveable = $attributeNames;
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param integer $mode Possible values: 1 .. 5 (See Entity Class MODE Constants)
	 * @return \OneFile\Entity53
	 */
	public function setMode($mode)
	{
		$this->entityMode = $mode;
		
		return $this;
	}	
	
	
	// -----------------------------------------------------------------
	// ------------- ARRAY ACCESS MAGIC METHODS / SERVICE --------------
	// -----------------------------------------------------------------
	
	/**
	 * Set the requested entity attribute
	 * 
	 * @param string $attributeName
	 * @param mixed $value
	 */
	public function __set($attributeName, $value)
	{
		$this->setAttribute($attributeName, $value);
	}

	/**
	 * Get the requested entity attribute
	 * 
	 * @param string $attribute_name
	 * @return mixed
	 */
	public function __get($attribute_name)
	{
		return $this->getAttribute($attribute_name);
	}

	/**
	 * Dynamically check if an attribute is set.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function __isset($key)
	{
		return isset($this->attributes[$key]);
	}

	/**
	 * Dynamically unset an attribute.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function __unset($key)
	{
		unset($this->attributes[$key]);
		unset($this->initialValues[$key]);
		unset($this->attributesMetaData[$key]);
		unset($this->validationMessages[$key]);
		unset($this->validationRules[$key]);
		if ($this->allowed) $this->allowed = array_diff($this->allowed, array($key));
		if ($this->notAllowed) $this->notAllowed = array_diff($this->notAllowed, array($key));
		if ($this->updatable) $this->updatable = array_diff($this->updatable, array($key));
		if ($this->notUpdatable) $this->notUpdatable = array_diff($this->notUpdatable, array($key));
		if ($this->exportable) $this->exportable = array_diff($this->exportable, array($key));
		if ($this->notExportable) $this->notExportable = array_diff($this->notExportable, array($key));
		if ($this->saveable) $this->saveable = array_diff($this->saveable, array($key));
		if ($this->notSaveable) $this->notSaveable = array_diff($this->notSaveable, array($key));
		if ($this->viewable) $this->viewable = array_diff($this->viewable, array($key));
		if ($this->notViewable) $this->notViewable = array_diff($this->notViewable, array($key));
	}	
}