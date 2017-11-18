<?php namespace OneFile;

/** 
 * File Description
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 07 Jun 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */

class Format {
	
	/**
	 * 
	 * @param type $value
	 * @param type $nullTypes
	 * @param type $nullValue
	 * @return type
	 */
	public static function nulltype($value = null, $nullTypes = array('', 'NULL'), $nullValue = null)
	{
		return in_array($value, $nullTypes) ? $nullValue : $value;
	}

	/**
	 * 
	 * @param type $value
	 * @param type $default
	 * @param type $decimals
	 * @param type $seperator
	 * @return type
	 */
	public static function decimal($value, $default = null, $decimals = 0, $seperator = null)
	{
		if (is_null($value)) return $default;
		return is_numeric($value) ? number_format($value, $decimals, '.', $seperator) : $value;
	}

	/**
	 * 
	 * @param type $value
	 * @param type $default
	 * @param type $decimals
	 * @param type $symbol
	 * @param type $seperator
	 * @return type
	 */
	public static function currency($value, $default = null, $decimals = 0, $symbol = 'R', $seperator = null)
	{
		if (is_null($value)) return $default;
		return is_numeric($value) ? $symbol . number_format($value, $decimals, '.', $seperator) : $value;
	}

	/**
	 * 
	 * @param type $value
	 * @param type $default
	 * @param type $decimals
	 * @param type $seperator
	 * @return type
	 */
	public static function percent($value, $default = null, $decimals = 0, $seperator = null)
	{
		if (is_null($value)) return $default;
		return is_numeric($value) ? number_format($value, $decimals, '.', $seperator) . '%' : $value;
	}

	/**
	 * 
	 * @param unix|string $value
	 * @param string $format
	 * @return string
	 */
	public static function date($format = 'Y-m-d', $value = null, $default = null)
	{
		if (empty($value)) return $default;
		return is_numeric($value) ? date($format, $value) : date($format, strtotime($value));
	}

	/**
	 * 
	 * @param boolean $boolValue
	 * @param string $trueString
	 * @param string $falseString
	 * @return string
	 */
	public static function boolean($boolValue, $trueString = '1', $falseString = '0')
	{
		return $boolValue ? $trueString : $falseString;
	}

	/**
	 * 
	 * @param boolean $bool_value
	 * @return string
	 */
	public static function yesNo($bool_value)
	{
		return ($bool_value) ? 'Yes' : 'No';
	}

	/**
	 * 
	 * @param boolean $bool_value
	 * @return string
	 */
	public static function trueFalse($bool_value)
	{
		return ($bool_value) ? 'true' : 'false';
	}

	/**
	 * Limit the number of characters in a string.
	 *
	 * @param  string  $value
	 * @param  int     $limit
	 * @param  string  $end
	 * @return string
	 */
	public static function limit($value, $limit = 100, $end = '...')
	{
		if (mb_strlen($value) <= $limit) return $value;
		
		return rtrim(mb_substr($value, 0, $limit, 'UTF-8')).$end;
	}

	/**
	 * Limit the number of words in a string.
	 *
	 * @param  string  $value
	 * @param  int     $words
	 * @param  string  $end
	 * @return string
	 */
	public static function words($value, $words = 100, $end = '...')
	{
		$matches = array();
		
		preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $value, $matches);

		if ( ! isset($matches[0])) return $value;

		if (strlen($value) == strlen($matches[0])) return $value;

		return rtrim($matches[0]).$end;
	}
	
	/**
	 * Convert the given string to title case.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function title($value)
	{
		return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
	}

	/**
	 * Convert a string to snake case.
	 *
	 * @param  string  $value
	 * @param  string  $delimiter
	 * @return string
	 */
	public static function snake($value, $delimiter = '_')
	{
		$replace = '$1'.$delimiter.'$2';

		return ctype_lower($value) ? $value : strtolower(preg_replace('/(.)([A-Z])/', $replace, $value));
	}

	/**
	 * Convert a value to studly caps case.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function studly($value)
	{
		$value = ucwords(str_replace(array('-', '_'), ' ', $value));

		return str_replace(' ', '', $value);
	}
	
	/**
	 * Convert a value to camel case.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function camel($value)
	{
		return lcfirst(static::studly($value));
	}
	
	/**
	 * Generate a URL friendly "slug" from a given string.
	 *
	 * @param  string  $title
	 * @param  string  $separator
	 * @return string
	 */
	public static function slug($title, $separator = '-')
	{
		
		$flip = ($separator == '-') ? '_' : '-';

		$patterns = array(
			'/['.preg_quote($flip).']+/u',					// Convert all dashes/underscores into separator
			'/[^'.preg_quote($separator).'\pL\pN\s]+/u',	// Remove all characters that are not the separator, letters, numbers, or whitespace.
			'/['.preg_quote($separator).'\s]+/u'			// Replace all separator characters and whitespace by a single separator
		);
		
		$replacements = array($flip, '', $separator);
		
		foreach ($patterns as $i => $pattern)
		{
			$title = preg_replace($pattern, $replacements[$i], $title);
		}

		return trim($title, $separator);
	}

	/**
	 * JSON Encode Function for Pre-PHP 5.2
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function json($value)
	{
		if (is_array($value) || is_object($value))
		{
			$islist = is_array($value) && ( empty($value) || array_keys($value) === range(0, count($value) - 1) );

			if ($islist)
			{
				$json = '[' . implode(',', array_map(static::json, $value)) . ']';
			}
			else
			{
				$items = Array();
				foreach ($value as $key => $value)
				{
					$items[] = static::json("$key") . ':' . static::json($value);
				}
				$json = '{' . implode(',', $items) . '}';
			}
		}
		elseif (is_string($value))
		{
			# Escape non-printable or Non-ASCII characters.
			# I also put the \\ character first, as suggested in comments on the 'addclashes' page.
			$string = '"' . addcslashes($value, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"';
			$json = '';
			$len = strlen($string);
			# Convert UTF-8 to Hexadecimal Codepoints.
			for ($i = 0; $i < $len; $i ++)
			{

				$char = $string[$i];
				$c1 = ord($char);

				# Single byte;
				if ($c1 < 128)
				{
					$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
					continue;
				}

				# Double byte
				$c2 = ord($string[++ $i]);
				if (($c1 & 32) === 0)
				{
					$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
					continue;
				}

				# Triple
				$c3 = ord($string[++ $i]);
				if (($c1 & 16) === 0)
				{
					$json .= sprintf("\\u%04x", (($c1 - 224) << 12) + (($c2 - 128) << 6) + ($c3 - 128));
					continue;
				}

				# Quadruple
				$c4 = ord($string[++ $i]);
				if (($c1 & 8 ) === 0)
				{
					$u = (($c1 & 15) << 2) + (($c2 >> 4) & 3) - 1;

					$w1 = (54 << 10) + ($u << 6) + (($c2 & 15) << 2) + (($c3 >> 4) & 3);
					$w2 = (55 << 10) + (($c3 & 15) << 6) + ($c4 - 128);
					$json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
				}
			}
		}
		else
		{
			# int, floats, bools, null
			$json = strtolower(var_export($value, true));
		}
		return $json;
	}

}