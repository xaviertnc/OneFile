<?php namespace OneFile;

/**
 * Validators take raw inputs and check that they represent the correct type of information
 * with the correct client-side formating.
 * 
 * A Validator should return appropriate notification message(s) when the checked value is found to be invalid
 * 
 * Override Me! Add your own checks 
 * Or replace me with your own Super-Duper-Validator class by overriding BaseEntity->validate()
 */
class BasicValidator
{
	public $valid = true;
	
	public $invalid = false;
	
	public $messages = array();
	
	/**
	 * 
	 * @param mixed $value
	 * @param array $rules
	 */
	public function __construct($value, $rules = null)
	{
		if(!$rules)	$rules = array();
		
		foreach($rules as $rule => $params)
		{
			$message = null;
			
			$method_name = 'check_' . $rule;
			
			if(method_exists($this, $method_name))
				$message = $this->$method_name($value, $params);
				
			if($message)
			{
				$this->messages[] = $message;
				$this->valid = false;
			}
		}
		
		$this->invalid = !$this->valid;
	}
	
	/**
	 * 
	 * @param mixed $value
	 * @param string $params  //Alt Message
	 * @return string
	 */
	protected function check_required($value, $params)
	{
		if(empty($value))
			return is_string($params)?$params:'Required';
	}
	
	/**
	 * Add more check methods here! ...
	 */
}
		

/**
 * Mutators convert valid client-side input into correctly formatted server-side data. (Think database storage format as an example.)
 * 
 * A Mutator does not return any errors, it just converts already valid values
 * 
 * Override Me! Add your own mutators
 * 
 * Can also be used as sanitizors
 */
class BasicMutator
{
	/**
	 *
	 * @var mixed
	 */
	public $mutated_value;
	
	/**
	 * 
	 * @param mixed $value
	 * @param array $types
	 */
	public function __construct($value, $types = null)
	{
		$this->mutated_value = $value;
		
		if(!$types) $types = array();
		
		foreach($types as $type => $params)
		{
			$method_name = 'mutate_' . $type;
			
			if(method_exists($this, $method_name))
				$this->mutated_value = $this->$method_name($this->mutated_value, $params);
		}		
	}
	
	/**
	 * 
	 * @param string $value
	 * @param string $params
	 * @return string
	 */
	protected function mutate_date($value, $params = null)
	{
		if(!$params) $params = 'Y-m-d'; 
		return date($params, strtotime($value));
	}
	
	/**
	 * 
	 * @param string $value
	 * @param string $params
	 * @return string
	 */
	protected function mutate_datetime($value, $params = null)
	{
		if(!$params) $params = 'Y-m-d H:i:s'; 
		return date($params, strtotime($value));
	}
	
	/**
	 * 
	 * @param string $value
	 * @param array|string $params true value(s)
	 * @return string
	 */
	protected function mutate_boolean($value, $params = null)
	{
		if(!$params) $params = array(1, 'yes', 'true', 'on');
		
		if(is_array($params))
			return in_array(strtolower($value), $params);
		else
			return ($value === $params);
	}
}


/**
 * BaseEntity Class
 * @author C. Moller - 02 May 2014
 * Re-write 04/05 May 2014
 */
class Entity
{
	/**
	 *
	 * @var array 
	 */
	protected $attributes = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $initial_values = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $fillable = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $rules = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $attribute_types = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $modified_attributes = array();
	
	/**
	 *
	 * @var array 
	 */
	protected $validation_messages = array();
	
	/**
	 *
	 * @var boolean 
	 */
	protected $dynamic_attributes = true;

	/**
	 * 
	 * @param array $initial_values
	 * @param array $default_values
	 * @param array $fillable
	 */
	public function __construct($initial_values, $default_values = array(), $fillable = array())
	{
		if(!$initial_values) $initial_values = array();
		
		if($fillable)
		{
			$this->fillable = $fillable;
			$this->dynamic_attributes = false;
		}
		
		elseif($this->fillable)
			$this->dynamic_attributes = false;
		
		else
			$this->fillable = array_merge(array_keys($default_values), array_keys($initial_values));
		
		foreach($this->fillable as $attribute_name)
		{
			if(strpos($attribute_name, '_') === 0)
			{
				unset($this->fillable[$attribute_name]);
				continue; //Skip attribute names that start with an underscore
			}
			
			$value = $this->_array_get($initial_values, $attribute_name, $this->_array_get($default_values, $attribute_name));
			
			$this->_init($attribute_name, $value);
		}
	}
	
	/**
	 * 
	 * @param array $values
	 */
	public function update($values = array())
	{
		if(!$values) $values = array();
		
		foreach($this->fillable as $attribute_name)
		{
			$this->set($attribute_name, $this->_array_get($values, $attribute_name));
		}		
	}
	
	/**
	 * Built-in helper function
	 * 
	 * @param array $array
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function _array_get(&$array, $key, $default = null)
	{
		return isset($array[$key])?$array[$key]:$default;
	}
	
	/**
	 * 
	 * @param string $attribute_name
	 * @return mixed
	 */
	protected function __get($attribute_name)
	{
		return $this->get($attribute_name);
	}
	
	/**
	 * 
	 * @param string $attribute_name
	 * @param mixed $value
	 */
	protected function __set($attribute_name, $value)
	{
		$this->set($attribute_name, $value);
	}	
	
	/**
	 * OVERRIDE! with better suited implementation if necessary.
	 * Can also be seen as a value Sanitizer!
	 * 
	 * @param string $attribute_name
	 * @param mixed $value
	 * @return mixed
	 */
	protected function _mutate($attribute_name, $value = null)
	{
		$mutator = new BasicMutator($value, $this->_array_get($this->attribute_types, $attribute_name, array()));
		return $mutator->mutated_value;
	}

	/**
	 * OVERRIDE! with better suited implementation if necessary.
	 * 
	 * @param string $attribute_name
	 * @param mixed $value
	 * @return BasicValidator
	 */
	protected function _validate($attribute_name, $value = null)
	{
		return new BasicValidator($value, $this->_array_get($this->rules, $attribute_name, array()));		
	}
	
	/**
	 * 
	 * @param type $attribute_name
	 * @param type $value
	 */
	protected function _init($attribute_name, $value = null)
	{
		$this->attributes[$attribute_name] = $value;
		$this->initial_values[$attribute_name] = $value;
	}
	
	/**
	 * 
	 * @param type $attribute_name
	 * @param type $value
	 */
	public function set($attribute_name, $value = null)
	{
		if($this->dynamic_attributes or in_array($attribute_name, $this->fillable))
		{
			$this->attributes[$attribute_name] = $this->_mutate($attribute_name, $value);
		}
	}
	
	/**
	 * Get any field value / property of the entity
	 * 
	 * @param string $attribute_name
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($attribute_name, $default = null)
	{
		return $this->_array_get($this->attributes, $attribute_name, $default);
	}
	
	/**
	 * 
	 * @return array
	 */
	public function get_messages($stringify = false, $attribute_name = null, $items_wrapper = '<p>%s</p>')
	{
		$message_bag = $attribute_name?
			$this->_array_get($this->validation_messages, $attribute_name, array()):$this->validation_messages;
		
		if(!$stringify)	return $message_bag;
		
		$stringified = '';
		
		foreach($message_bag as $message)
		{
			if(!is_array($message))
			{	
				$stringified .= sprintf($items_wrapper, $message);
				continue;
			}
			
			foreach($message as $sub_message)
			{
				$stringified .= sprintf($items_wrapper, $sub_message);
			}
		}
		
		return $stringified;
	}
	
	/**
	 * 
	 * @param string $message
	 */
	public function add_message($message = null, $attribute_name = null)
	{
		if(is_string($message))
		{
			if($attribute_name)
				$this->validation_messages[$attribute_name][] = $message;
			else
				$this->validation_messages[] = $message;
		}
	}
		
	/**
	 * Get all the modified attribute names as an array.
	 * 
	 * @return array
	 */
	public function get_modified_attributes()
	{
		if(!$this->modified_attributes)
			$this->is_modified();
		
		return $this->modified_attributes;
	}
	
	/**
	 * 
	 * @param string $attribute_name
	 * @return boolean
	 */
	public function is_modified($attribute_name = null)
	{
		if($attribute_name)
		{
			return ($this->attributes[$attribute_name] <> $this->initial_values[$attribute_name]);
		}
		else
		{
			$modified = false;
			
			$this->modified_attributes = array();
			
			foreach($this->initial_values as $attribute_name => $value)
			{
				if($this->attributes[$attribute_name] <> $value)
				{
					$this->modified_attributes[] = $attribute_name;
					$modified = true;
				}
			}
			
			return $modified;
		}
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_valid()
	{
		$is_valid = true;
		
		foreach($this->attributes as $attribute_name => $value)
		{
			$result = $this->_validate($attribute_name, $value);
			
			if($result->invalid)
			{
				$this->validation_messages[$attribute_name] = $result->messages;
				$is_valid = false;
			}
		}
			
		return $is_valid;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_invalid()
	{
		return !$this->is_valid();
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_empty()
	{
		foreach($this->initial_values as $attribute_name => $value)
		{
			if(!is_null($this->initial_values[$attribute_name]))
				return false;
		}
		
		return true;
	}	
	
	/**
	 * Run this command after save to remove "has_changed" warnings
	 */
	public function commit_changes()
	{
		$this->initial_values = $this->attributes;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function to_array()
	{
		return $this->attributes;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function to_json()
	{
		return json_encode($this->attributes);
	}
}