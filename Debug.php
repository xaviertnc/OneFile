<?php namespace OneFile;

/**
 * File Description
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 24 Dec 2016
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */

class Debug {


	public static function htmlEntities($text)
	{
		$text = htmlentities($text, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
		$text = str_replace('  ', '&nbsp;&nbsp;', $text);
		return $text;
	}


	//From: tagmycode.com, Posted by: moalex
	public static function prettyJson($json, $asHtml = false)
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


	//By: C. Moller - 2008
	public static function prettyArray($array, $asHtml = true)
	{
		if(is_array($array) and $asHtml)
		{
			$html_parts = [];
			// Check if array is Assoc
			if(count(array_filter(array_keys($array), 'is_string')) > 0)
			{
				foreach ($array as $k => $v)
				{
					$keyHtml = self::htmlEntities($k);
					if(is_array($v))
					{
						$html_parts[] = '{<span class="L1-key">' . $keyHtml . '</span>=array(' . self::prettyArray($v, true) . ')}';
					}
					else
					{
						if(is_string($v))
						{
							$html_parts[] = '{<span class="L2-key">' . $keyHtml . '</span>=' . self::htmlEntities($v).'}';
						}
						elseif(is_object($v))
						{
							$html_parts[] = '{<span class="L2-key">' . $keyHtml . '</span>=Object}';
						}
						else
						{
							$html_parts[] = '{<span class="L2-key">' . $keyHtml . '</span>=Unable to print!}';
						}
					}
				}
			}
			// No, it's Plain
			else
			{
				for ($i = 0; $i < count($array); $i++)
				{
					if(is_array($array[$i]))
					{
						$html_parts[] = 'array(' . self::prettyArray($array[$i], true) . ')';
					}
					else
					{
						$html_parts[] = self::htmlEntities($array[$i]);
					}
				}
			}

			return implode(',', $html_parts);
		}

		return print_r($array, true);
	}

}
