<?php namespace OneFile;

/**
 * Parse Class
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 27 Feb 2016
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 * Updated 23 Nov 2016 - C. Moller
 *   - Renamed and changed focus to opposite of Format:: class.
 *   -  i.e This class takes user INPUT and converts it into computer friendly formats.
 *
 * @updated: C. Moller - 20 Jan 2017
 *   -  Added Parse::text()
 *
 */
class Parse
{
	/**
	 * Parses an input value to REAL NULL if it has any of the values specified in nullTypes!
	 */
	public static function nulltype($value = null, $nullTypes = array('', 'NULL'), $nullValue = null)
	{
		return in_array($value, $nullTypes) ? $nullValue : $value;
	}


	public static function number($value)
	{
		return ($value != '') ? preg_replace('/[^0-9\-]/', '', $value) : null;
	}


	public static function decimal($value)
	{
		return ($value != '') ? preg_replace('/[^0-9\.\-]/', '', $value) : null;
	}


	public static function mysqlDate($value)
	{
		$parts = explode('-', str_replace('/', '-', preg_replace('/[^0-9\-]/', '', $value)));

		if(count($parts) == 3)
		{
			if(strlen($parts[0]) == 4)
			{
				$cen = $parts[0];
				$month = $parts[1];
				$day = $parts[2];
			}
			else
			{
				$cen = $parts[2];
				$month = $parts[1];
				$day = $parts[0];
			}

			if($cen >= 1800)
			{
				return $cen . '-' . $month . '-' . $day;
			}
			else
			{
				return;
			}
		}
		else
		{
			$time = strtotime($value);

			if($time === false)
			{
				return;
			}
			else
			{
				return date('Y-m-d', $time);
			}
		}
	}


	public static function mysqlTime($value, $show_seconds = true)
	{
		$parts = explode(':', preg_replace('/[^0-9\:]/', '', $value));

		switch(count($parts))
		{
			case 1:
				$hour = $parts[0];
				$min = '';
				$sec = '';
				break;

			case 2:
				$hour = $parts[0];
				$min = ':' . $parts[1];
				$sec = '';
				break;

			case 3:
				$hour = $parts[0];
				$min = ':' . $parts[1];
				$sec = ':' . $parts[2];
				break;

			default:
				$hour = '';
		}

		if($hour)
		{
			if($show_seconds)
			{
				return $hour . $min . $sec;
			}

			return $hour . $min;
		}
	}


	public static function alpha($value)
	{
		return ($value != '') ? preg_replace('/[^a-z\ ]/i', '', $value) : null;
	}


	public static function alphaNumeric($value)
	{
		return ($value != '') ? preg_replace('/[^a-z0-9]/i', '', $value) : null;
	}


	// Allow space, any unicode letter and digit, underscore and dash:
	public static function text($value)
	{
		return ($value != '') ? preg_replace('/[^\040\pL\pN_-]/u', '', $value) : null;
	}


	/**
	 * E.g.  1.5 (MB)  == ??????? (Bytes)
	 *
	 * @param integer $size
	 * @param string $units
	 * @return integer
	 */
	public static function fileSize($size = null, $units = null)
	{
		switch ($units)
		{
			case 'B' : return $size;
			case 'KB': return $size * 1024;
			case 'MB': return $size * 1048576;
			case 'GB': return $size * 1073741824;
		}

		return $size;
	}
}
