<?php namespace OneFile;

class Dump
{

	public static function arr($array)
	{
		echo '<pre>', print($array), '</pre>';
	}

	//From: tagmycode.com, Posted by: moalex
	public static function json($json, $asHtml = false)
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