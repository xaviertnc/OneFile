<?php
/*
 * html.php
 *
 * Copyright 2016 Neels Moller <xavier.tnc@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 * Note: Good idea but over complicated!  NM - 25 Nov 2016
 *
 */

class Html {

	static $ENV = 'prod';
	static $BASE_PATH = '/var/www';


	static function read_file($filename)
	{
		ob_start();
		include $filename;
		return trim(ob_get_clean());
	}


	function indent($n = 1, $dent = "\t")
	{
		return str_repeat($dent, $n);
	}


	function indent_textblock($textblock, $n = 1)
	{
		$indent = self::indent($n);
		$replace = PHP_EOL . $indent . $indent;
		return $indent . str_replace(PHP_EOL, $replace, $textblock);
	}


	/**
	 * options:
	 *   indent = [0..n]
	 *   newline = [true,false]
	 */
	static function tag_open($tagname, $attributes = null, $options = null)
	{
		if ($options)
		{
			$nl = isset($options['newline']) ? PHP_EOL : '';
			$indent = isset($options['indent']) ? self::indent($options['indent']) : '';
			$first = ! empty($options['first']);
		}
		else
		{
			$nl = '';
			$indent = '';
			$first = false;
		}

		$html = ($first ? '' : $indent) . '<' . $tagname;

		if ($attributes) {
			$valuestrings = [];
			foreach ($attributes as $key => $value) {
				$valuestring = $key;
				if ($value) { $valuestring .= '="' . $value . '"'; }
				$valuestrings[] = $valuestring;
			}
			$html .= ' ' . implode(' ', $valuestrings);
		}

		$html .= '>' . $nl;

		return $html;
	}


	/**
	 * options:
	 *   indent = [0..n]
	 *   newline = [true,false]
	 */
	static function tag($tagname, $attributes = null, $innerHtml = null, $options = null)
	{
		if ($options)
		{
			$nl = isset($options['newline']) ? PHP_EOL : '';
			$indent_count = isset($options['indent']) ? $options['indent'] : 0;
			$indent = $indent_count ? self::indent($indent_count) : '';
			$first = ! empty($options['first']);
		}
		else
		{
			$nl = '';
			$indent = '';
			$indent_count = 0;
			$first = false;
		}

		$textblock = print_r($innerHtml, true);

		$html =  ($first ? '' : $indent) . self::tag_open($tagname, $attributes) . $nl;
		$html .= ($nl ? $indent . self::indent_textblock($textblock, 1) : $textblock) . $nl;
		$html .= ($nl ? $indent : '') . '</' . $tagname .'>' . $nl;

		return $html;
	}


	static function pre($innerHtml = null, $options = null)
	{
		return self::tag('pre', null, $innerHtml, $options);
	}


	/**
	 * options:
	 *   inline = [true, false]
	 *
 	 * if ["inline" AND $inline_code == null] then $inline_code = HTLM:read_file($href)
 	 * if ["inline"] then $newline is forced to be true
 	 *
	 */
	static function style($href, $attributes = null, $inline_code = null, $options = null)
	{
		$env = isset($options['env']) ? $options['env'] : null;
		if ($env && ! in_array($env, self::$ENV)) { return; }

		$inline = isset($options['inline']);
		if ($inline)
		{
			$options['newline'] = true;
			if ($inline_code)
			{
				// Ignore $href.  Use $inline_code provided!
				$html = self::tag('style', $attributes, $inline_code, $options);
			}
			else {
				$inline_code = self::read_file(self::$BASE_PATH . '/' . $href);
				$html = self::tag('style', $attributes, $inline_code, $options);
			}
		}
		else {
			$attributes['href'] = $href;
			$attributes['rel'] = 'stylesheet';
			$html = self::tag_open('link', $attributes, $options);
		}

		return $html;
	}


	/**
	 * options:
	 *   inline = [true, false]
	 *
 	 * if ["inline" AND $inline_code == null] then $inline_code = HTLM:read_file($src)
 	 * if ["inline"] then $newline is forced to be true
 	 *
	 */
	static function script($src, $attributes = null, $inline_code = null, $options = null)
	{
		$env = isset($options['env']) ? $options['env'] : null;
		if ($env && ! in_array($env, self::$ENV)) { return; }

		$inline = isset($options['inline']);
		if ($inline)
		{
			$options['newline'] = true;
			if ($inline_code)
			{
				$html = self::tag('script', $attributes, $inline_code, $options);
			}
			else {
				$inline_code = self::read_file(self::$BASE_PATH . '/' . $src);
				$html = self::tag('script', $attributes, $inline_code, $options);
			}
		}
		else {
			$attributes['src'] = $src;
			$html = self::tag('script', $attributes, null, $options);
		}

		return $html;
	}



}
