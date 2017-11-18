<?php namespace OneFile;

class Test
{
	protected $outputTarget;
	
	protected $outputHtml;
	
	protected $results = array();
	
	/**
	 * 
	 * @param string $output 'echo', 'array', '[filePath]'
	 */
	public function __construct($outputTarget = 'array', $outputHtml = true)
	{
		$this->outputTarget = $outputTarget;
		$this->outputHtml = true;
	}
	
	protected function fmtAction($action)
	{
		return sprintf('<td class="action">%s</td>', $action);
	}
	
	protected function fmtVal($value = '')
	{
		if(!$value and $this->outputHtml)
		{
			$value = '&nbsp;';
		}
		return sprintf('<td class="value" title="%s">%s</td>', $value, substr($value, 0, 20));
	}
	
	protected function fmtTest($test)
	{
		return sprintf('<td class="test">%s</td>', $test);
	}
	
	protected function fmtResult($test_result)
	{		
		return sprintf('<td class="%s">%s</td>', strtolower($test_result), $test_result);
	}
	
	protected function fmtParams($params = '')
	{
		if(!$params and $this->outputHtml)
		{
			$params = '&nbsp;';
		}
		return sprintf('<td class="params" title="%s">%s</td>', $params, substr($params, 0, 35));
	}
	
	protected function fmtOutput($action, $output, $test, $expected, $test_result, $params = null)
	{
		if($test_result)
		{
			$test_result = 'PASS';
		}
		else
		{
			$test_result = 'FAIL';
		}
		
		if($this->outputHtml)
		{
			$message =  '<tr class="line">' .
						$this->fmtAction($action) .
						$this->fmtVal($output) . 
						$this->fmtTest($test) .
						$this->fmtVal($expected) .
						$this->fmtResult($test_result);
						
			
			if(is_array($params))
			{
				$message .= $this->fmtParams(print_r($params, true));
			}
			else
			{
				$message .= $this->fmtParams($params);
			}
			
			$message .=	'</tr>';
		}
		else
		{
			$message = "$action = $output $test expected = $expected :: $test_result\n";
			
			if(is_array($params))
			{
				$message .= ' :: ' . print_r($params, true);
			}
			elseif($params)
			{
				$message .= ' :: ' . $params;
			}
		}
		
		return $this->output($message, true);		
	}
	
	public function output($message, $preWrapped = false)
	{
		do {
			
			if ($this->outputTarget == 'echo')
			{
				echo $message . "<br>\n";
				break;
			}

			if ($this->outputTarget == 'array') 
			{
				$this->results[] = $preWrapped ?  $message : "<tr><th colspan='6'>$message</th></tr>";
				break;
			}

			if ($this->outputTarget and file_exists($this->outputTarget))
			{
				file_put_contents($this->outputTarget, $message, FILE_APPEND | LOCK_EX);
			}
			
		} while(0);
		
		return $message;
	}
	
	public function isEqual($param, $value, $expected, $strict = false)
	{
		if ($strict)
		{
			$result = ($value === $expected);
		}
		else
		{
			$result = ($value == $expected);
		}
		
		return $this->fmtOutput($param, $value, 'IS EQUAL TO', $expected, $result);
	}
	
	public function fileExists($action, $filename)
	{
		$test_result = file_exists($filename);
		
		return $this->fmtOutput($action, null, 'FILE EXISTS', null, $test_result, $filename);
	}
	
	public function fileNotFound($action, $filename)
	{
		$test_result = !file_exists($filename);
		
		return $this->fmtOutput($action, null, 'FILE NOT FOUND', null, $test_result, $filename);
	}
	
	public function fileHasContent($action, $filename, $expected_content)
	{
		if (file_exists($filename))
		{
			$actual_content = file_get_contents($filename);
			$test_result = ($actual_content == $expected_content);
		}
		else
		{
			$actual_content = null;
			$test_result = false;
		}
		
		return $this->fmtOutput($action, $actual_content, 'FILE HAS CONTENT', $expected_content, $test_result, $filename);
	}
	
	public function __call($name, $arguments)
	{
		return $this->output('Unknown Test:' . $name . '(' . print_r($arguments, true) . ')');
	}	
	
	public function __toString()
	{
		return implode(PHP_EOL, $this->results);
	}
	
}