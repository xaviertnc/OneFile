<?php namespace OneFile;

/*
 * Html.php
 *
 * HTML Helper Class
 *
 * @author: Neels Moller <xavier.tnc@gmail.com>
 * @date: 24 December 2016
 *
 * @updated: 7 Feb 2017
 *  - Put back into OneFile stack
 *  - Remove script + style + pre methods
 *  - Change some naming conventions
 *
 */

class Html {

	static $fileCache = [];

	static function e($str)
	{
		return is_string($str) ? htmlspecialchars($str, ENT_QUOTES | ENT_IGNORE, "UTF-8", false) : null;
	}


	static function indent($n, $dent = null)
	{
		return $n ? str_repeat($dent?:"\t", $n) : '';
	}


	static function indentBlock($block, $offset, $indent, $nl)
	{
		return $offset . $indent . implode($nl.$offset.$indent, explode($nl, $block)) . $nl;
	}


	static function getFile($filename, $context = array(), $cacheKey = null, $renderEngine = null)
	{
		if ($cacheKey and isset(self::$fileCache[$cachekey])) { return self::$fileCache[$cacheKey]; }
		ob_start();
		extract($context);
		include $filename;
		$out = trim(ob_get_clean());
		if ($cacheKey) { self::$fileCache[$cacheKey] = $out; }
		return $out;
	}


	static function attr($name, $value = null)
	{
		return isset($value) ? " $name=\"$value\"" : " $name";
	}


	static function attrs(array $attributes)
	{
		$attrsStr = '';
		$rawAttrsStr = '';

		if (isset($attributes['raw']))
		{
			$rawAttrsStr = ' ' . $attributes['raw'];
			unset($attributes['raw']);
		}

		foreach ($attributes as $name => $value)
		{
			if ($name == 'class' and is_array($value)) { $value = implode(' ', $value); }
			$attrsStr .= self::attr($name, $value);
		}

		return $attrsStr . $rawAttrsStr;
	}


	static function tag($tagname, $attributes = null)
	{
		if ( ! $attributes) { $attributes = []; }
		elseif (is_string($attributes)) $attributes = ['class' => $attributes];
		return '<' . $tagname . self::attrs($attributes) . '>';
	}
}
