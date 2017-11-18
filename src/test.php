<?php namespace OneFile;

/**
 * Custom Unit Testing Class.
 * 
 * Many existing options exist, so if this is a good idea ... mmmm... I don't know
 * Propably more for myself to get into testing and understand it better.
 * It's only ONE FILE though! ;-)
 * 
 * This class is far from feature complete, but it works for what I need right now.
 * 
 * @author C. Moller <xavier.tnc@gmail.com> - 21 Jun 2014
 * 
 * TODO: Scoring of results + Command Line version.
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
class Test
{
	/**
	 * 'echo', 'array', '[filePath]'
	 * 
	 * @var string
	 */
	protected $outputTarget;
	
	/**
	 * Aggregate all outputs from all tests into a single HTML
	 * formatted string to dump to screen.
	 * 
	 * @var boolean
	 */
	protected $outputAsHtml;
	
	/**
	 *
	 * @var array
	 */
	protected $testResults = array();

	/**
	 * 
	 * @param string $output 'echo', 'array', '[filePath]'
	 */
	public function __construct($outputTarget = 'array', $outputAsHtml = true)
	{
		$this->outputTarget = $outputTarget;
		$this->outputAsHtml = $outputAsHtml;
	}

	protected function fmtAction($action)
	{
		return sprintf('<td class="action">%s</td>', $action);
	}

	protected function fmtTest($test)
	{
		return sprintf('<td class="test">%s</td>', $test);
	}

	protected function fmtExpectedVal($value = '')
	{
		if ( ! $value and $this->outputAsHtml)
		{
			$value = '&nbsp;';
		}
		
		return sprintf('<td class="expected" title="%s">%s</td>', $value, substr($value, 0, 32));
	}

	protected function fmtActualVal($value = '')
	{
		if ( ! $value and $this->outputAsHtml)
		{
			$value = '&nbsp;';
		}
		
		return sprintf('<td class="actual" title="%s">%s</td>', $value, substr($value, 0, 32));
	}

	protected function fmtResult($test_result)
	{
		return sprintf('<td class="%s">%s</td>', strtolower($test_result), $test_result);
	}

	protected function fmtParams($params = '')
	{
		if ( ! $params and $this->outputAsHtml)
		{
			$params = '&nbsp;';
		}
		
		return sprintf('<td class="params" title="%s">%s</td>', $params, substr($params, 0, 12));
	}

	protected function fmtOutput($action, $output, $test, $expected, $test_result, $params = null)
	{
		if ($test_result)
		{
			$test_result = 'PASS';
		}
		else
		{
			$test_result = 'FAIL';
		}

		if ($this->outputAsHtml)
		{
			$message = '<tr class="line">' .
			$this->fmtAction($action) .
			$this->fmtTest($test) .
			$this->fmtExpectedVal($expected) .
			$this->fmtActualVal($output) .
			$this->fmtResult($test_result);


			if (is_array($params))
			{
				$message .= $this->fmtParams(print_r($params, true));
			}
			else
			{
				$message .= $this->fmtParams($params);
			}

			$message .= '</tr>';
		}
		else
		{
			$message = "$action: $test, " . trim($expected) . ' = ' . trim($output) . ", $test_result";

//			if (is_array($params))
//			{
//				$message .= ' :: params = ' . print_r($params, true);
//			}
//			elseif ($params)
//			{
//				$message .= ' :: params = ' . $params;
//			}
		}

		return $this->output($message);
	}

	protected function output($message, $isHeading = false)
	{
		do
		{
			if ($this->outputTarget == 'echo')
			{
				echo $message . "<br>\n";
				break;
			}

			if ($this->outputTarget == 'array')
			{
				$this->testResults[] = ($isHeading and $this->outputAsHtml) ? "<tr class=\"heading\"><th colspan='6'>$message</th></tr>" : $message;
				break;
			}

			if ($this->outputTarget and file_exists($this->outputTarget))
			{
				file_put_contents($this->outputTarget, $message, FILE_APPEND | LOCK_EX);
			}
		}
		while (0);

		return $message;
	}

	public function caseHeading($heading)
	{
		$this->output($heading, true);
	}

	public function isEqual($action, $actualResult, $expectedResult, $strict = false)
	{
		if ($strict)
		{
			$pass = ($actualResult === $expectedResult);
		}
		else
		{
			$pass = ($actualResult == $expectedResult);
		}

		return $this->fmtOutput($action, $actualResult, 'IS EQUAL TO', $expectedResult, $pass);
	}
	
	public function fileExists($action, $filename)
	{
		$pass = file_exists($filename);

		return $this->fmtOutput($action, ($pass ? 'True' : 'False'), 'FILE EXISTS', 'True', $pass, $filename);
	}

	public function fileNotFound($action, $filename)
	{
		$pass = ! file_exists($filename);

		return $this->fmtOutput($action, ($pass ? 'True' : 'False'), 'FILE NOT FOUND', 'True', $pass, $filename);
	}

	public function fileHasContent($action, $filename, $expectedContent)
	{
		if (file_exists($filename))
		{
			$actualContent = file_get_contents($filename);
			$pass = ($actualContent == $expectedContent);
		}
		else
		{
			$actualContent = null;
			$pass = false;
		}

		return $this->fmtOutput($action, $actualContent, 'FILE HAS CONTENT', $expectedContent, $pass, $filename);
	}

	public function __call($name, $arguments)
	{
		return $this->caseHeading('Unknown Test:' . $name . '(' . print_r($arguments, true) . ')');
	}

	public function __toString()
	{
		return implode(PHP_EOL, $this->testResults);
	}
}