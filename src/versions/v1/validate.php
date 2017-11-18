<?php namespace OneFile;

/**
 * Validate Class
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 27 Feb 2016
 *
 * Based on NM Framework Validate Class - 2011/2012
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Validate
{

	public static function validate($value, $rules=array())
	{
		$error_message = '';
		foreach ($rules as $check => $param)
		{
			switch ($check)
			{
				case 'required':
					if ($value == '' || $value == null) { $error_message = $param; break 2; } //Changed condition from (!$value) to ($value == "" || null) - NM 12 Nov 2012
					break;
				case 'min':
					$n=strlen($value); if ($n && ($n < $param)) { $error_message = '*min-length: '.$param; break 2; }
					break;
				case 'max':
					if (strlen($value) > $param) { $error_message = '*max-length: '.$param; break 2; }
					break;
				case 'low':
					if ($value!=null && $value < $param) { $error_message = '*min-value: '.$param; break 2; }
					break;
				case 'hi':
					if ($value > $param) { $error_message = '*max-value: '.$param; break 2; }
					break;
				case 'email':
					if (strlen($value) && !preg_match ( "/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $value ) )
						{ $error_message = $param; break 2; }
					break;
				case 'number':
					if (strlen($value) && !ereg("^[0-9\.]+$", $value)) { $error_message = $param; break 2; }
					break;
				case 'alpha':
					if (strlen($value) && !preg_match("/[a-z\ ]/i", $value)) { $error_message = $param; break 2; }
					break;
				case 'alphaNum':
					if (strlen($value) && !preg_match("/^([a-z0-9])+$/i", $value)) { $error_message = $param; break 2; }
					break;
				case 'equals':	break;
				case 'pattern': break;
			}
		}
		return $error_message;
	}
}
