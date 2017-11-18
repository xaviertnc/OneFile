<?php

namespace NM\Support;

/**
 * By. C Moller - 2011 - 15 Mar 2013
 */
class Arr
{
	/**
	* Gets the element of an array with given index, while checking if the index exists.
	* If not, return the default value
	* @param  <object> $source  &Array
	* @param  <mixed>  $index   Array index
	* @param  <mixed>  $default Default value
	* @return <mixed>  Array[index] or Default Value
	*/
	public static function getVal(&$array,$key,$default=null)
	{
		return (isset($array[$key]))?$array[$key]:$default;
	}

	public static function isAssoc(&$array)
	{
		return (isset($array) && is_array($array) && (0 !== count(array_diff_key($array, array_keys(array_keys($array)))) || count($array)==0));
	}

	//More thorrough than just using isset()
	public static function hasItem(&$array,$key,$allow_null_value=false)
	{
		if($allow_null_value)
			return array_key_exists($key, $array); //If the item exists and the value of the item = NULL, it will report TRUE.  Also slower than ISSET
		else
			return (isset($array[$key])); //If the item exists and the value of the item = NULL, it will report FALSE
	}

	public static function toString(&$array,$remove_linebreaks=true)
	{
		if($remove_linebreaks)
			return str_replace("\n", '', var_export($array,true));
		else
			return var_export($array,true);  //var_export() is alternative to print_r()
	}

	public static function toHtml(&$array)
	{
		if(is_array($array))
		{
			$html = '';
			if(Arr::isAssoc($array))
			{
				foreach ($array as $k => $v)
				{
					if(is_array($v))
						$html .= ',{<span class="L1-key">'.Html::prep($k).'</span>=array('.Arr::toHtml($v).')}';
					else
					{
						if(is_string($v))
							$html .= ',{<span class="L2-key">'.Html::prep($k).'</span>='.Html::prep($v).'}';
						elseif(is_object($v))
							$html .= ',{<span class="L2-key">'.Html::prep($k).'</span>=Object}';
						else
							$html .= ',{<span class="L2-key">'.Html::prep($k).'</span>=Unable to print!}';
					}
				}
			}
			else
			{
				for ($i = 0; $i < count($array); $i++)
				{
					if(is_array($array[$i]))
						$html .= ',array('.Arr::toHtml($array[$i]).')';
					else
						$html .= ','.Html::prep($array[$i]);
				}
			}
			$html = substr($html, 1);
			if(!$html)
				$html = 'Array Empty';
		}
		else
			$html = 'Parameter NOT Array: Value = "'.nl2br(Html::prep(Arr::toString($array,false))).'"';
		return $html;
	}

	public static function toObject(&$array,$obj)
	{
		foreach($array as $k => $v)	$obj->{$k} = $v; //Note: Non-Recursive!
	}
}