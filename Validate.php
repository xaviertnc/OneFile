<?php namespace OneFile;

use Log; // Debug only!


/**
 * Validate Class
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 20 Dec 2016
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Validate
{

	/**
	 * Checks if an input has a value
	 * @return Closure
	 */
	public function required()
	{
		/**
		 * @param mixed  $v : Field Value
		 * @param string $l : Field Label
		 * @param mixed  $d : AuxData to enable more advanced checks. E.g. Cross-field validation with $d == The FormObj.
		 * @param string $t : Validation message template in sprintf() format.
		 *
		 * @return string
		 */
		$fn = function($v = null, $l = null, $d = null, $t = null)
		{
			$o = null; $l = rtrim($l, ':');
			if (empty($v) and $v !== 0 and $v !== '0') { $o = is_null($t) ? "$l is required." : sprintf($t, $l); }
			return $o;
		};

		return $fn;
	}


	public function equals($other_value = null, $other_value_label = null)
	{
		if (is_null($other_value_label)) { $other_value_label = json_encode($other_value); }
		$fn = function($v = null, $l = null, $d = null, $t = null) use ($other_value, $other_value_label)
		{
			$o = null; $l = rtrim($l, ':');
			if ($v != $other_value) { $o = is_null($t) ? "$l should equal $other_value_label" : sprintf($t, $l, $other_value_label); }
			return $o;
		};

		return $fn;
	}

  
	public function equals_case_insensitive($other_value = null, $other_value_label = null)
	{
		if (is_null($other_value_label)) { $other_value_label = json_encode($other_value); }
		$fn = function($v = null, $l = null, $d = null, $t = null) use ($other_value, $other_value_label)
		{
			$o = null; $l = rtrim($l, ':');
			if (strtolower($v) != strtolower($other_value)) { $o = is_null($t) ? "$l should equal $other_value_label" : sprintf($t, $l, $other_value_label); }
			return $o;
		};

		return $fn;
	}

  
  /**
   * @param mixed $d Set to 'allow-empty' to only check if set.
   */   
	public function email()
	{
		$fn = function($v = null, $l = null, $d = null, $t = null)
		{
      if ( ! $v and $d == 'allow-empty') { return; }
			$o = null; $l = rtrim($l, ':');
			if ( ! filter_var($v, FILTER_VALIDATE_EMAIL)) { $o = is_null($t) ? "$l is invalid." : sprintf($t, $l); }
			return $o;
		};

		return $fn;
	}
}

//Log::debug('OneFile::Validate::required(), d=' . var_export($d, true));
//Log::debug(sprintf('OneFile::Validate::required(), v=%s, l=`%s`, t=`%s` o=`%s`' , var_export($v, true), $l, $t, is_null($o) ? 'IS VALID!' : $o));
