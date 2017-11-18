<?php namespace OneFile;

/**
 * File Description
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 24 Dec 2016
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */

class Json {

	/**
	 * JSON Encode Function for Pre-PHP 5.2
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function encode($value)
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


	//From: tagmycode.com, Posted by: moalex
	public static function pretty($json, $asHtml = false)
	{
		$out = ''; $nl = "\n"; $cnt = 0; $tab = 4; $len = strlen($json); $space = ' ';

		if($asHtml)
		{
			$space = '&nbsp;';
			$nl = '<br/>';
		}

		$k = strlen($space)?strlen($space):1;
		for ($i=0; $i<=$len; $i++)
		{
			$char = substr($json, $i, 1);

			if($char == '}' || $char == ']')
			{
				$cnt --;
				$out .= $nl . str_pad('', ($tab * $cnt * $k), $space);
			}
			elseif($char == '{' || $char == '[')
			{
				$cnt ++;
			}

			$out .= $char;
			if($char == ',' || $char == '{' || $char == '[')
			{
				$out .= $nl . str_pad('', ($tab * $cnt * $k), $space);
			}

			if($char == ':') { $out .= ' '; }
		}

		return $out;
	}
}
