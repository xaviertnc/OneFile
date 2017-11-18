<?php namespace OneFile;

/**
 * Basic Mutator Class.
 * 
 * Mutators convert valid client-side input into correctly formatted server-side data and vice versa
 * 
 * Override Me! Add your own mutators
 * 
 * @author C. Moller <xavier.tnc@gmail.com> - 21 Jun 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Mutator
{
	/**
	 *
	 * @var mixed
	 */
	protected $mutatedValue;

	/**
	 * 
	 * @param mixed $value
	 * @param array $types
	 */
	public function __construct($value, $types = null)
	{
		$this->mutatedValue = $value;

		if ( ! $types)
		{
			$types = array();
		}

		foreach ($types as $type => $params)
		{
			$method_name = 'mutate_' . strtolower($type);

			if (method_exists($this, $method_name))
			{
				$this->mutatedValue = $this->$method_name($this->mutatedValue, $params);
			}
		}
	}
	
	public function getMutatedValue()
	{
		return $this->mutatedValue;
	}

	/**
	 * 
	 * @param string $value
	 * @param string $dateFormat
	 * @return string
	 */
	protected function mutateDate($value, $dateFormat = null)
	{
		if ( ! $dateFormat)
		{
			$dateFormat = 'Y-m-d';
		}
		
		return date($dateFormat, strtotime($value));
	}

	/**
	 * 
	 * @param string $value
	 * @param string $datetimeFormat
	 * @return string
	 */
	protected function mutateDatetime($value, $datetimeFormat = null)
	{
		if ( ! $datetimeFormat)
		{
			$datetimeFormat = 'Y-m-d H:i:s';
		}
		
		return date($datetimeFormat, strtotime($value));
	}

	/**
	 * 
	 * @param string $value
	 * @param array|string $trueValue
	 * @return string
	 */
	protected function mutateBoolean($value, $trueValue = null)
	{
		if ( ! $trueValue)
		{
			$trueValue = array(1, 'yes', 'true', 'on');
		}

		if (is_array($trueValue))
		{
			return in_array(strtolower($value), $trueValue);
		}
		else
		{
			return ($value === $trueValue) ? 1 : 0;
		}
	}
}