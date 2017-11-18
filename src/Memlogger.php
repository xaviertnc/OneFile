<?php namespace OneFile;

/**
 * Memory Logger Class
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 29 Nov 2016
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Memlogger
{
	public $logs = [];
	public function __call($method, $args) { $this->log($method, $args ? $args[0] : '?'); }
	public function log($logType, $message) { $this->logs[] = "[$logType]: ".date('Y-m-d H:i:s')." - $message"; }
	public function dump($format = null) { $out = $this->export($format); return is_string($out) ? print($out) : print_r($out); }
	public function export($format = null) { $nl = PHP_EOL;	switch ($format) {
		case 'html': $html = ''; foreach($this->logs as $log) { $html .= "$log<br>"; } return "<pre>\n$html</pre>\n";
		case 'text': $text = ''; foreach($this->logs as $log) { $text .= $log . $nl; }	return $text;
		case 'json': return json_encode($this->logs);
		default: return $this->logs;
	}}
}
