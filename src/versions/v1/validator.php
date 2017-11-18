<?php namespace OneFile;

/**
 * Basic Validator Class.
 * 
 * Validators take raw inputs and check that they represent the correct type of information.
 * A Validator should return appropriate notification message(s) when the checked value is found to be invalid.
 * 
 * Override Me! Add your own checks 
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 21 Jun 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Validator
{
	/**
	 * Set to true if the supplied value passes all rule tests.
	 * 
	 * @var boolean
	 */
	protected $valid;
	
	/**
	 * String, Array or MessageBag Object.
	 * Inject your own message bag implementation via the constructor.
	 * Default message bag format = Array
	 * 
	 * @var array|mixed
	 */
	protected $messages;
	
	/**
	 * Local string e.g 'EN' etc.
	 * 
	 * Maybe your message bag implementation already contains locale info?
	 * If so, just ignore this property.
	 * 
	 * @var string
	 */
	protected $locale;

	/**
	 * 
	 * @param mixed $value
	 * @param array $rules
	 */
	public function __construct($value, $rules = null, $messages = null, $locale = null)
	{
		if ( ! $rules)
		{
			$rules = array();
		}
		
		if (is_null($messages))
		{
			$messages = array();
		}

		$this->messages = $messages;
		
		$this->locale = $locale;
		
		$this->valid = true;
		
		// $ruleparams can be a string, array or value object.
		// $ruleparams should contain values required to perform the test
		// as well as custom response message(s) if applicable.
		foreach ($rules as $rulename => $ruleparams)
		{
			$feedback = null;

			$method_name = 'check' . ucfirst($rulename);

			// Dynamically call the method: $this->check{RuleName}
			if (method_exists($this, $method_name))
			{
				$feedback = $this->$method_name($value, $ruleparams);
			}

			// Add the test feedback to the message bag.
			// $feedback can be any type: null|boolean|string|array|object
			// $feedback should be truthy if the test failed.
			if ($feedback)
			{
				$this->addMessage($rulename, $feedback);
				$this->valid = false;
			}
		}
	}
	
	public function isValid()
	{
		return $this->valid;
	}
	
	public function isInvalid()
	{
		return ! $this->valid;
	}
	
	public function getMessages()
	{
		return $this->messages;
	}
	
	/**
	 * Override me if your message bag is not an array.
	 * 
	 * @param string $rulename Use it or loose it.
	 * @param mixed $message Can be: null|boolean|string|array|object.
	 */
	public function addMessage($rulename, $message)
	{
		$this->messages[$rulename] = $message;
	}

	/**
	 * 
	 * @param mixed $value
	 * @param string $customMessage
	 * @return string
	 */
	protected function checkRequired($value, $customMessage)
	{
		if (empty($value))
		{
			return is_string($customMessage) ? $customMessage : 'Required';
		}
	}

	/**
	 * Add more check methods here! ...
	 */
}
