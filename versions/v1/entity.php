<?php namespace OneFile;

/**
 * BasicEntity Class
 * 
 * @author C. Moller <xavier.tnc@gmail.com> - 02 May 2014
 * 
 * Re-write 04/05 May 2014
 * Simplify + Remove Validator + Mutator classes + Change code style - 21 Jun 2014
 * 
 * Licensed under the MIT license. Please see LICENSE for more information.
 * 
 */
class Entity
{
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
	 * Only allow setting attributes with names listed in the $allowed array.
	 * If $allowed is empty or null, allow setting ANY attribute!
	 * 
	 * @var array 
	 */
	protected $allowed;
	
	/**
	 * List of READONLY attributes.
	 * Read-Only attributes don't go into $initialValues since they will never change. This 
	 * is very useful if you have attributes that hold BIG data structures like huge text blocks
	 * or binary data.  Not duplicating to $initialValues SAVES resources and improves performance.
	 * 
	 * Read-Only status only affects the $initalValue and related functions. Other than that, it is
	 * only a FLAG for your application to use in however way needed.
	 * 
	 * Read-Only does NOT PREVENT SETTING an attribute's value!  This type of logic should be handled
	 * on a higher level if required. 
	 * 
	 * @var array
	 */
	protected $readOnly;
	
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
	 * Used in __get() to store an existing or newly created reference to the requested value
	 * using the Entity::value() method.
	 * 
	 * We need ALL values returned by __get() to be references to an existing variable.
	 * No static returned values allowed!
	 * 
	 * @var mixed
	 */
	private $__vref__ = null;
	
	
	/**
	 * Instantiate and initialze an entity
	 * 
	 * @param array $initialAttributeValues Array of current name => value pairs before updates.  Usually from an some persistence source.
	 * @param array $defaults Array of default name => value pairs if attributes aren't specified in $initailAttributeValues
	 * @param array $allowed Array of attribute names allowed. If not allowed, an attribute can't be added or set. Even in the constructor!
	 */
	public function __construct($initialAttributeValues = array(), $defaults = array(), $allowed = array(), $readOnly = array())
	{
		$this->allowed = $allowed;	
		$this->readOnly = $readOnly;
		
		if ( ! $initialAttributeValues)
		{
			//Convert $currentAttributeValues to an array if we get passed some kind of FALSY object instead of an array!
			$initialAttributeValues = array();
		}

		// Note: array_merge will only preserve array keys if the arrays have NAMED KEYS. i.e. associative. I.e. Always use Assoc. arrays
		$initialAttributeValues = array_merge($defaults, $initialAttributeValues);
		
		// Filter out attribute names starting with "_" and attributes NOT in $allowedAttributes and READONLY attributes for $initialVaues
		foreach (array_keys($initialAttributeValues) as $attributeName)
		{
			if ($attributeName[0] == '_' or ($allowed and empty($allowed[$attributeName])))
			{
				unset($initialAttributeValues[$attributeName]);
				continue;
			}
			
			if ( ! $readOnly or empty($readOnly[$attributeName]))
			{
				$this->initialValues[$attributeName] = $initialAttributeValues[$attributeName];
			}
		}

		$this->attributes = $initialAttributeValues;		
	}
	
	/**
	 * Magic function!
	 * To allow operations on virtual Entity array properties (aka. attributes) like:  $entity->arrayTypeAttribute[] = $newItem
	 * which fails with a "Indirect modification of overloaded property has no effect" type error,
	 * because the array is not returned by reference, so only a copy of the array gets updated.
	 * 
	 * @param type $value
	 * @return type
	 */
	private function &value(&$value)
	{
		return $this->__vref__= &$value;
	}
	
	/**
	 * Built-in helper function
	 * 
	 * @param array $array
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function &arrayGet(&$array, $key, $default = null)
	{
		return isset($array[$key]) ? $this->value($array[$key]) : $this->value($default);
	}

	/**
	 * Called on each isValid() request.
	 * 
	 * Override Me!
	 * Either extend/improve this implementation or call on a 
	 * seperate Validator class. See first commented line...
	 * 
	 * @param string $attributeName
	 * @param mixed $value
	 * @return stdClass
	 */
	protected function validate($attributeName, $value)
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
	 * Called each time we "set" an entity value.
	 * Convert valid input data from client-side to
	 * server-side format. (Typically for storage purposes)
	 * 
	 * Convert server-side data into userfriendly 
	 * client-side format. (Typically for display purposes)
	 * 
	 * Override Me!
	 * 
	 * Either extend/improve this implementation or call on a 
	 * seperate Mutator class. See first two commented lines...
	 * 
	 * @param mixed $value
	 * @param string $direction Possible values: 'toClient','fromClient'
	 * @param mixed $mutators Could apply more than one mutator per attribute.
	 * @return mixed
	 */
	protected function mutate($value = null, $direction = 'fromClient', $mutators = array())
	{
		// $mutator = new \OneFile\Mutator($value, $mutators);
		// return $mutator->mutatedValue;

		if ($direction == 'fromClient')
		{
			
			//Very basic mutator implementation
			if (in_array('date', $mutators))
			{
				return date('Y-m-d', strtotime($value));
			}

			if (in_array('timestamp', $mutators))
			{
				return date('Y-m-d H:i:s', strtotime($value));
			}

			if (in_array('boolean', $mutators))
			{
				return $value ? 1 : 0;
			}
			
		}
		
		return $value;
	}
	
	/**
	 * 
	 * @param string $attributeName
	 * @param mixed $value
	 */
	public function setAttribute($attributeName, $value = null)
	{
		if (empty($this->allowed) or in_array($attributeName, $this->allowed))
		{
			$metaData = $this->arrayGet($this->attributesMetaData, $attributeName);
			
			if ($metaData)
			{
				$value = $this->mutate($value, 'fromClient', $metaData);
			}
			
//			if (__DEBUG__) echo "Entity::set($attributeName) = " . htmlentities(Format::limit(print_r($value, true))) . '<br>';
			
			$this->attributes[$attributeName] = $value;
		}
	}

	/**
	 * Get any field value / property of the entity
	 * 
	 * @param string $attributeName
	 * @param mixed $default
	 * @return mixed
	 */
	public function &getAttribute($attributeName, $default = null)
	{		
//		if (__DEBUG__) echo "Entity::getAttribute($attributeName)<br>";
		
		$metaData = $this->arrayGet($this->attributesMetaData, $attributeName);
		
		if ($metaData) //Might have to be more specific on this test...
		{
			return $this->value($this->mutate($this->arrayGet($this->attributes, $attributeName, $default), 'toClient', $metaData));
		}
				
		return $this->arrayGet($this->attributes, $attributeName, $default);
	}

	/**
	 * 
	 * @param string $attributeName
	 * @param mixed $value
	 */
	public function __set($attributeName, $value)
	{
//		if (__DEBUG__) echo "Entity::__set($attributeName)<br>";
		
		$this->setAttribute($attributeName, $value);
	}

	/**
	 * Return the requested entity property / attribute by reference!
	 * If the return value is a scalar, assign it to __vref__ and send back the reference to __vref__
	 * 
	 * @param string $attribute_name
	 * @return mixed
	 */
	public function &__get($attribute_name)
	{
//		if (__DEBUG__) echo "Entity::__get($attribute_name)<br>";
		
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
		$this->allowed = array_diff($this->allowed, array($key));
		$this->readOnly = array_diff($this->readOnly, array($key));
	}
	
	/**
	 * 
	 * @param array $values
	 */
	public function update($values = array())
	{
		if ( ! $values)
		{
			//Convert this potentially FALSY parameter to an empty array when FALSY
			$values = array();
		}

		foreach ($this->allowed ? : array_keys($this->attributes) as $attributeName)
		{
			$this->setAttribute($attributeName, $this->arrayGet($values, $attributeName));
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
	 * Get all the modified attribute names as an array.
	 * 
	 * NOTE: Since READONLY values aren't added to $initialValues in the constructor, 
	 * we don't need to considder them here.
	 * 
	 * @return array
	 */
	public function getModifiedAttributes()
	{
		$this->modifiedAttributes = array();

		foreach ($this->initialValues as $attributeName => $value)
		{
			if ($this->attributes[$attributeName] <> $value)
			{
				$this->modifiedAttributes[] = $attributeName;
			}
		}

		return $this->modifiedAttributes;
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
	 * An array of attribute names that should be READONLY
	 * 
	 * @param array $readOnly
	 * @return \OneFile\Entity
	 */
	public function setReadOnlyAttributes(array $readOnly)
	{
		$this->readOnly = $readOnly;
		
		return $this;
	}	

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
			return ($this->arrayGet($this->readOnly, $attributeName) or 
				$this->attributes[$attributeName] <> $this->initialValues[$attributeName]);
		}
		else
		{
			return count($this->getModifiedAttributes());
		}
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
	public function isInvalid($attributeName = null)
	{
		return ! $this->isValid($attributeName);
	}

	/**
	 * 
	 * @return array
	 */
	public function toArray()
	{
		return $this->attributes;
	}

	/**
	 * 
	 * @return string
	 */
	public function toJson()
	{
		return json_encode($this->attributes);
	}
	
}
