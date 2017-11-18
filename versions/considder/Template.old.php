<?php namespace OneFile;

use AppLog;
use Closure;
use Exception;

/**
 * Template is a PHP Templating class based largely on code from Laravel4's Blade Compiler
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 * What makes it different?
 *
 * 1. All framework and external dependancies are removed. I.e. Only one file!
 *
 * 2. The templating process is different.  You still get inheritance and partials, but without any runtime
 *    including of files!  The entire template is built and cached as one file with all partials and layouts included.
 *    This takes care of a number of variable scope issues when dynamically including files at runtime.
 *    It could also improves performance?
 *
 * 3. Template cares about code structure and attempts to preserve indentation where possible.
 *    You might want to use it to generate code that looks decent and not just cached files for runtime.
 *
 * 4. Template rendering is included
 *
 * 5. Options to cache/save compiled output and specify output filename
 *
 * 6. Render() echo's the output unless you specifiy to return the result as a string
 *
 * 7. Re-compiles if any dependant templates change.
 *
 * TODO:
 *  Option to NOT check dependancies (i.e. Production mode)
 *  Option to ignore indenting
 *  Option to minify (Removing all redundant white space and comments)
 *  Yield Defaults
 *  Add @use('file.tpl', data_array) to the compiler.
 *    Like @include, but only evaluated at runtime.
 *	 @use Statements should not compile to html, but rather compile to a PHP render function, like child templates in the old Blade system.
 *
 * By: C. Moller - 11 May 2014
 *
 * Added Tabs compiler + Improved @section / @stop regex: C. Moller - 31 May 2014
 */
class Template
{
	/**
	 * All of the registered extensions.
	 *
	 * @var array
	 */
	protected $extensions = array();

	/**
	 * All of the available compiler functions.
	 *
	 * @var array
	 */
	protected $compilers = array(
		'Extensions',
		'Comments',
		'Statements',
		'Echos',
		'Openings',
		'Closings',
		'Else',
		'Unless',
		'EndUnless',
		'Includes',
		'SectionShow',
		'SectionStop',
		'Yield',
		'Extends',
		'Tabs',
	);

	/**
	 * Array of opening and closing tags for echos.
	 *
	 * @var array
	 */
	protected $contentTags = array('{{', '}}');

	/**
	 * Array of opening and closing tags for raw statements.
	 *
	 * @var array
	 */
	protected $statementTags = array('{{~', '~}}');

	/**
	 * Array of opening and closing tags for escaped echos.
	 *
	 * @var array
	 */
	protected $escapedTags = array('{{{', '}}}');

	/**
	 * Array of opening and closing tags for template comments.
	 * Defaults: {*   and    *}
	 *
	 * Defaults defined in the constructor to allow escaping the '*' characters
	 *
	 * @var array
	 */
	protected $commentTags;

	/**
	 * Get the templates path to use for compiling views.
	 *
	 * @var string
	 */
	protected $templatesPath;

	/**
	 *
	 * @var string
	 */
	protected $templateFilePath;

	/**
	 *
	 * @var string
	 */
	protected $compiledFilename;

	/**
	 *
	 * @var string
	 */
	protected $compiledFilePath;

	/**
	 * Get the cache path for the compiled views.
	 *
	 * @var string
	 */
	protected $cachePath;

	/**
	 * A child template from which to import sections referenced in this template.
	 *
	 * @var Template
	 */
	protected $child;

	/**
	 * Array of sections in this template to potentially yield in a parent template.
	 *
	 * @var array
	 */
	protected $sections = array();

	/**
	 * Array of template files that are required to successfully compile this template
	 * The list includes this file
	 *
	 * @var array
	 */
	protected $dependancies = array();

	/**
	 * Create a new template instance.
	 *
	 * @param string $templatesPath
	 * @param string $cachePath
	 * @param Template $childTemplate A child template from which to import sections referenced in this template.
	 */
	public function __construct($templatesPath = null, $cachePath = null, $childTemplate = null)
	{
		$this->setTemplatesPath($templatesPath);

		$this->setCachePath($cachePath);

		$this->child = $childTemplate;

		//Initialize here to allow using preg_quote()
		$this->commentTags = array(preg_quote('{*'), preg_quote('*}'));
	}

	/**
	 * Override current or lazy assign (After class instantiation) the uncompiled templates path depending on your use case
	 *
	 * @param string $templatesPath
	 * @return \OneFile\Template Return this class to allow method chaining
	 */
	public function setTemplatesPath($templatesPath = null)
	{
		$this->templatesPath = $templatesPath ? realpath($templatesPath) : __DIR__;

		return $this;
	}

	/**
	 * Override or lazy assign the compiled templates path depending on your use case
	 *
	 * @param string $cachePath
	 * @return \OneFile\Template Return this class to allow method chaining
	 */
	public function setCachePath($cachePath = null)
	{
		$this->cachePath = $cachePath ? realpath($cachePath) : null;

		return $this;
	}

	/**
	 * Add the templates path to get the full/absolute filename if required
	 *
	 * Allows using only the template's relative path/filename in compile() or render()
	 * for convienence.
	 *
	 * @param  string  $templateFilename
	 * @return string
	 */
	protected function addTemplatesPath($templateFilename)
	{
		if ( ! file_exists($templateFilename))
		{
			$templateFilename = $this->templatesPath . '/' . $templateFilename;

			if ( ! file_exists($templateFilename))
				return null;
		}

		return $templateFilename;
	}

	/**
	 * Get the path to the compiled version of a view.
	 *
	 * Note: To save CPU cycles, put your template files close to your OS root folder
	 * to shorten the resulting path strings.
	 *
	 * @param string $templatefilePath
	 * @param boolean $forceRecalc We call this function a number of times during a cycle, so we only want to re-calculate on request
	 * @param boolean $encode Change compiled filenames to MD5 encoded strings or use the same filenames as the uncompiled templates
	 * @return string
	 */
	protected function getCompiledFilePath($templatefilePath, $forceRecalc = false, $encode = true)
	{
		if ( ! $this->compiledFilePath or $forceRecalc)
		{
			$this->compiledFilename = $encode ? md5($templatefilePath) : $templatefilePath;

			$this->compiledFilePath = $this->cachePath . '/' . $this->compiledFilename;
		}

		return $this->compiledFilePath;
	}

	/**
	 * The Meta data file holds information on dependancies for each template used by isExpired()
	 * Add underscore infront of the meta-filename to make it faster to scan the cache folder for meta and NON meta files!
	 *
	 * @param type $cachefilePath
	 * @return type
	 */
	protected function getMetaFilePath($cachefilePath = null)
	{
		if ($this->compiledFilename)
		{
			$path = $this->cachePath . '/_' . $this->compiledFilename;
		}
		else
		{
			$path = $cachefilePath;
		}

		return $path . '.meta';
	}

	/**
	 * Determine if the view at the given path is expired.
	 * We assume that we are using the cache if we check for expired!
	 *
	 * @param  string  $templatefilePath
	 * @return bool
	 */
	protected function isExpired($templatefilePath)
	{
		//Always force updating the compiled file path on checking for Expired!
		//We always check for Expited before compiling making this a nice place to ensure that the path
		//is always current for compile() and render(), but still allowing the benefits of NOT re-calculating
		//the path in other places like reading and saving the compiled file.
		$compiled = $this->getCompiledFilePath($templatefilePath, true);

		// If the compiled file doesn't exist we will indicate that the view is expired
		if ( ! file_exists($compiled))
		{
			return true;
		}

		//Get the "Last Modified" timestamps of all the child templates including this this template's timestamp
		$dependancies = include($this->getMetaFilePath());

		if ( ! $dependancies)
		{
			//The compiled file has "Expired" if its timestamp is older than its source template timestamp
			return filemtime($compiled) < filemtime($templatefilePath);
		}

		foreach ($dependancies as $dependantFile => $lastTimestamp)
		{
			if ( ! file_exists($dependantFile))
				return true;

			//A dependnat file has changed if the file's last timestamp is older than its current timestamp
			//A changed dependancy === Compiled File Expired!
			if ($lastTimestamp < filemtime($dependantFile))
				return true;
		}

		return false;
	}

	/**
	 *
	 * @param string $templatefilePath
	 * @return string
	 */
	protected function getCompiledFile($templatefilePath)
	{
		if ( ! $this->isExpired($templatefilePath))
		{
			return file_get_contents($this->getCompiledFilePath($templatefilePath));
		}
		else
		{
			return $this->compile($templatefilePath);
		}
	}

	/**
	 *
	 * @param string $templateFilename
	 * @param array $data
	 * @param boolean $asString
	 * @param boolean $useCached
	 * @return string
	 */
	public function render($templateFilename, $data = array(), $asString = false, $useCached = true)
	{
		try {

			extract($data);

			//AppLog::template('Template::render(), Start Render: templatefile =  ' . $templateFilename);

			$this->templateFilePath = $this->addTemplatesPath($templateFilename);

			//AppLog::template('Template::render(), Start Render: templatefilePATH =  ' . $this->templateFilePath);

			ob_start();

			// Render freshly compiled template using eval()... :/
			if ( ! $useCached or ! $this->cachePath)
			{
				// NOTE: The compile() function will return a string instead of writing directly to a file if cache=FALSE.
				// NOTE: On some shared servers, eval() might be disabled for security reasons.
				// NOTE: Don't use this option in a production setting! The intention was for testing or running an off-line code generator.
				eval(" ?>" . $this->compile($this->templateFilePath, false) . "<?php ");

				return $asString ? ob_get_clean() : ob_flush();
			}

			// Reset the compiled filepath because we could be rendering a different file
			// with the same Template instance.
			$this->compiledFilePath = null;

			// Render a cached version of the compiled template, but re-compile it first if its content has expired!
			if ($this->isExpired($this->templateFilePath))
			{
				// Compile with CacheContents=TRUE and ReturnConents=FALSE
				$this->compile($this->templateFilePath, true, false);
			}

			include $this->getCompiledFilePath($this->templateFilePath);

			return $asString ? ob_get_clean() : ob_flush();

		}

		// Try and catch some errors here to prevent them showing in your response where they can pose a security risk.
		// Not very effective!  Fatal errors sail right through!!!  You have to register a shutdown handler to catch / stop
		// fatal errors!  Put ob_clear() in shutdown when an error is detected.
		catch (Exception $ex)
		{
			ob_clean();

			$response = "<html><body>Oops, Something went wrong rendering template: <b>$templateFilename</b>!<br>" .
			get_class($ex) . " Code {$ex->getCode()}: {$ex->getMessage()} in file: {$ex->getFile()} (Line {$ex->getLine()})<br>" .
			str_repeat('=', 50) .
			"Trace: {$ex->getTraceAsString()}</body></html>";

			AppLog::error('Template::render(), Error: ' . $response);

			return $response;
		}
	}

	/**
	 * A compiler helper function to create the compiled file's meta data file content
	 *
	 * @return string
	 */
	protected function renderDependancies()
	{
		$timestamps = "<?php return array(\n";

		foreach ($this->dependancies ? : array() as $dependancy => $timestamp)
		{
			$timestamps .= "'" . $dependancy . "' => " . $timestamp . ',' . PHP_EOL;
		}

		$timestamps .= ");?>\n";

		return $timestamps;
	}

	/**
	 * Compile the view at the given path.
	 * If we specify $cachefile_path, the cached file path will not be an encoded string, but the path name given.
	 * To save CPU cycles we can specify if we want the compiled contents returned or not.  Only applicable
	 * when Cache = TRUE.
	 *
	 * @param string $templateFilename
	 * @param boolean $cache
	 * @param string $cachefilePath
	 * @param boolean $returnContents
	 * @return string
	 */
	public function compile($templateFilename, $cache = true, $cachefilePath = null, $returnContents = true)
	{
		$this->templateFilePath = $this->addTemplatesPath($templateFilename);

		$this->dependancies[$this->templateFilePath] = filemtime($this->templateFilePath);

		//If $cache=FALSE we always return freshly compiled content as a
		//string and we don't bother with possibly expired dependancies.
		if ( ! $cache)
		{
			return $this->compileString(file_get_contents($this->templateFilePath));
		}

		$contents = $this->compileString(file_get_contents($this->templateFilePath));

		if ($this->child)
		{
			$this->child->dependancies = $this->child->dependancies + $this->dependancies;
		}

		if ($cache and ! is_null($this->cachePath) and ! $this->child)
		{
			if ($cachefilePath)
			{
				//Get compiled file path with ForceReCalc = TRUE and EncodeFilename = FALSE
				$compiledFilePath = $cachefilePath;
				file_put_contents($this->getMetaFilePath($cachefilePath), $this->renderDependancies());
			}
			else
			{
				//Get compiled file path with ForceReCalc = FALSE and EncodeFilename = TRUE (Defaults)
				$compiledFilePath = $this->getCompiledFilePath($this->templateFilePath);
				file_put_contents($this->getMetaFilePath(), $this->renderDependancies());
			}

			file_put_contents($compiledFilePath, $contents);
		}

		if ($returnContents)
			return $contents;
	}

	/**
	 * Compile the given Blade template contents.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	public function compileString($templateString)
	{
		foreach ($this->compilers as $compiler)
		{
			$templateString = $this->{"compile{$compiler}"}($templateString);
		}

		return $templateString;
	}

	/**
	 * Register a custom Blade compiler.
	 *
	 * @param  Closure  $compiler
	 * @return void
	 */
	public function extend(Closure $compiler)
	{
		$this->extensions[] = $compiler;
	}

	/**
	 * Execute the user defined extensions.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileExtensions($templateString)
	{
		foreach ($this->extensions as $compiler)
		{
			$templateString = call_user_func($compiler, $templateString, $this);
		}

		return $templateString;
	}

	/**
	 * Compile comments into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileComments($templateString)
	{
		$pattern = sprintf('/[[:blank:]]*%1$s[\s\S]*?%2$s[[:blank:]]*[\r\n]?/', $this->commentTags[0], $this->commentTags[1]);

		return preg_replace($pattern, '', $templateString);
	}

	/**
	 * Compile echos into valid PHP.
	 * Check tag lengths to ensure determine what type of compile needs to run first!
	 * First compile long tags, then short tags since short tags can be partials of the long tags!
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileEchos($templateString)
	{
		$difference = strlen($this->contentTags[0]) - strlen($this->escapedTags[0]);

		if ($difference > 0)
		{
			return $this->compileEscapedEchos($this->compileRegularEchos($templateString));
		}

		return $this->compileRegularEchos($this->compileEscapedEchos($templateString));
	}

	/**
	 * Compile the "regular" echo statements.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileRegularEchos($templateString)
	{
		$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->contentTags[0], $this->contentTags[1]);

		return preg_replace($pattern, '<?php echo $1; ?>', $templateString);
	}

	/**
	 * Compile the escaped echo statements.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileEscapedEchos($templateString)
	{
		$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->escapedTags[0], $this->escapedTags[1]);

		return preg_replace($pattern, '<?php echo htmlentities($1, ENT_QUOTES | ENT_IGNORE, "UTF-8", false); ?>', $templateString);
	}

	/**
	 * Compile the raw php statements.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileStatements($templateString)
	{
		$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->statementTags[0], $this->statementTags[1]);

		return preg_replace($pattern, '<?php $1; ?>', $templateString);
	}

	/**
	 * Compile structure openings into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileOpenings($templateString)
	{
		$pattern = '/(?(R)\((?:[^\(\)]|(?R))*\)|(?<!\w)([[:blank:]]*)@(if|elseif|foreach|for|while)(\s*(?R)+))/';

		return preg_replace($pattern, '$1<?php $2$3: ?>', $templateString);
	}

	/**
	 * Compile structure closings into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileClosings($templateString)
	{
		$pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';

		return preg_replace($pattern, '$1<?php $2; ?>$3', $templateString);
	}

	/**
	 * Compile else statements into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileElse($templateString)
	{
		$pattern = $this->createPlainMatcher('else');

		return preg_replace($pattern, '$1<?php else: ?>$2', $templateString);
	}

	/**
	 * Compile unless statements into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileUnless($templateString)
	{
		$pattern = $this->createMatcher('unless');

		return preg_replace($pattern, '$1<?php if ( !$2): ?>', $templateString);
	}

	/**
	 * Compile end unless statements into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileEndUnless($templateString)
	{
		$pattern = $this->createPlainMatcher('endunless');

		return preg_replace($pattern, '$1<?php endif; ?>$2', $templateString);
	}

	/**
	 * Compile include statements into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileIncludes($templateString)
	{
		$pattern = $this->createOpenMatcher('include');

		$matches = array();

		preg_match_all($pattern, $templateString, $matches);

		if ( ! $matches or ! $matches[0])
			return $templateString;

//		echo '<p style="color:red">Matches = ' . print_r($matches,true) . '</p>';

		$includeStatements = array();
		foreach ($matches[0] as $includeStatement)
		{
			$includeStatements[] = $includeStatement;
		}

		$indents = array();
		foreach ($matches[1] as $indent)
		{
			$indents[] = $indent;
		}

		$files = array();
		foreach ($matches[2] as $filename_match_raw)
		{
			//SubStr offset = 2 ... Jumps over (' part of string, but leaves ' at end. Hence also trim()
			$files[] = trim(substr($filename_match_raw, 2), "'\"");
		}

		foreach ($files as $i => $filename)
		{
			$filename = $this->addTemplatesPath($filename);
			$this->dependancies[$filename] = filemtime($filename);

			$content_to_include = $this->compile($filename, false);

			if (is_null($content_to_include) or $content_to_include === '')
			{
				$templateString = str_replace($includeStatements[$i], '', $templateString);
				continue;
			}

			$lines = preg_split("/(\r?\n)/", $content_to_include);

			foreach ($lines as $no => $line)
			{
//				$lines[$no] = ($no ? $indents[$i] : '') . $line;
				$lines[$no] = $indents[$i] . $line;
			}

			$templateString = str_replace($includeStatements[$i], implode(PHP_EOL, $lines), $templateString);
		}

		return $templateString;
	}

	/**
	 * Extract SectionShow blocks into a sections array to be used in Yield statements.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileSectionShow($templateString)
	{
		// Note: Any whitespace between the last content character and @end will be ignored.
		// Note: Sections can NOT be nested.
		//		Use @include to add partials inside a section.
		//		Partials may be other templates with their own layout and sections

		$matches = array();

		$pattern = '/(?<!\w)([[:blank:]]*)@section\s*\((.*?)\)\s*([\s\S]*?)\s*@show/';

		preg_match_all($pattern, $templateString, $matches, PREG_OFFSET_CAPTURE);

		if ( ! $matches or ! $matches[0])
			return $templateString;

		// We need to adjust the initial offset values since the string changes length during substitutions!
		// This problem probably has a much better solution...

		// NB: This approach will only work if the matches are ordered nearest-to-start to nearest-to-end!

		$offsetAdjust = 0;

		foreach ($matches[0] as $i => $fullMatch)
		{
			$sectionName = trim($matches[2][$i][0], "'\""); // Strip Quotes of section names

			$sectionContent = $matches[3][$i][0]; // Section content only

			$this->sections[$sectionName] = $sectionContent;


			$startOffset = $fullMatch[1] + $offsetAdjust; // Full Match Start Position. I.e including @section('name')

			$fullSectionLength = strlen($fullMatch[0]);

			$yieldStatement = $matches[1][$i][0] . "@yield('$sectionName')";

			$yieldStatementLength = strlen($yieldStatement);

			$offsetAdjust += $yieldStatementLength - $fullSectionLength;

			// Replace \s*@section('section_name')content...@show with \s*@yield('section_name') so compileYield() will render it!
			// Remember, we might have child templates overriding this section's content, so we can't just plain remove the @section..@show content!
			// We have to first save the section content as if we parsed it from a child template and see if it will be needed later.
			// If a real child template re-defines this section, the value we save here will be replaced.
			$templateString = substr_replace($templateString, $yieldStatement, $startOffset, $fullSectionLength);
		}

		return $templateString;
	}

	/**
	 * Extract SectionStop blocks into a sections array to be used in Yield statements.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileSectionStop($templateString)
	{
		// Note: Any whitespace between the last content character and @end will be ignored.
		// Note: Sections can NOT be nested.
		//		Use @include to add partials inside a section.
		//		Partials may be other templates with their own layout and sections

		$matches = array();

		$pattern = '/(?<!\w)[[:blank:]]*@section\s*\((.*?)\)\s*([\s\S]*?)\s*@stop/';

		preg_match_all($pattern, $templateString, $matches);

		if ( ! $matches or ! $matches[0])
			return $templateString;

		$sectionNames = array();

		foreach ($matches[1] as $nameMatchRaw)
		{
			$sectionNames[] = trim($nameMatchRaw, "'\""); //Removes quotes!
		}

		foreach ($matches[2] as $i => $sectionContent)
		{
			$this->sections[$sectionNames[$i]] = $sectionContent;
		}

		return $templateString;
	}

	/**
	 * Compile yield statements into valid PHP.
	 *
	 * @param  string  $templateString
	 * @return string
	 */
	protected function compileYield($templateString)
	{
		$pattern = $this->createOpenMatcher('yield');

		$matches = array();

		preg_match_all($pattern, $templateString, $matches);

		if ( ! $matches or ! $matches[0])
			return $templateString;

		$yieldStatements = array();

		// mathces[0]: [ws1]@yield[ws2]('section')
		foreach ($matches[0] as $yieldStatement)
		{
			$yieldStatements[] = $yieldStatement;
		}

		$indents = array();

		// matches[1] === ws1
		foreach ($matches[1] as $indent)
		{
			$indents[] = $indent;
		}

		$sections = array();

		// matches[2] === [ws2]('section'
		foreach ($matches[2] as $nameMatchRaw)
		{
			//Scrub Section Name Match String
			//SubStr offset = 2 ... Jumps over (' part of string, but leaves ' at end. Hence also trim()
			$sections[] = trim(substr($nameMatchRaw, 2), "'\"");
		}

		foreach ($sections as $i => $sectionName)
		{
			$hasDefaultContent = isset($this->sections[$sectionName]);
			$sectionContent = $hasDefaultContent ? $this->sections[$sectionName] : '';
			$hasContentOverride = (is_object($this->child) and isset($this->child->sections[$sectionName]));

			if ( ! $hasDefaultContent and ! $hasContentOverride)
			{
				// No content exists for this @yield statement!
				// Remove the @yield statement from the template by replacing it with an empty string.
				$templateString = str_replace($yieldStatements[$i], '', $templateString);
				continue;
			}

			if ($hasContentOverride)
			{
				// A child section was found that overrides the default/parent section content
				// If the child/override content contains a "@parent" tag, the tag will be replaced with the parent content.
				$childContent = & $this->child->sections[$sectionName];
				$parentContent = & $sectionContent;
				$sectionContent = preg_replace('/.*@parent.*/', $parentContent, $childContent);
			}

			// If no Content Override, $sectionContent will still contain the parent/default section content!

			$lines = preg_split("/(\r?\n)/", $sectionContent);

			foreach ($lines as $no => $line)
			{
				$lines[$no] = $indents[$i] . $line;
			}

			$templateString = str_replace($yieldStatements[$i], implode(PHP_EOL, $lines), $templateString);
		}

		return $templateString;
	}

	/**
	 *
	 * @param type $templateString
	 */
	protected function compileExtends($templateString)
	{
		$pattern = $this->createOpenMatcher('extends');

		$matches = array();

		preg_match($pattern, $templateString, $matches);

		if ( ! $matches or ! $matches[0])
			return $templateString;

		$parent = new self($this->templatesPath, $this->cachePath, $this);

		$parent_templatefile = trim(substr($matches[2], 1), "'\"");

		return $parent->compile($parent_templatefile);
	}

	/**
	 * Replace TABS with SPACES
	 *
	 * @param type $templateString
	 * @return type
	 */
	protected function compileTabs($templateString)
	{
		return str_replace("\t", '   ', $templateString);
	}

	/**
	 * Get the regular expression for a generic Blade function.
	 *
	 * @param  string  $function
	 * @return string
	 */
	protected function createMatcher($function)
	{
		return '/(?<!\w)([[:blank:]]*)@' . $function . '(\s*\(.*\))/';
	}

	/**
	 * Get the regular expression for getting the parameters of blade functions like:
	 * [whitespace1]@section[whitespace2]('main')[SectionContent]@stop
	 *
	 * Matches:
	 *   match[0] = [whitespace1]@section[whitespace2]('main')
	 *   match[1] = [whitespace1]
	 *   match[2] = [whitespace2]('main'
	 *
	 * Use match[1] for indenting
	 * Use match[2] to extract the function parameters or to swtich out with php equivalent.
	 * E.g. For "@if (A==B)", match[2] = " (A==B", PHP = "if (A==B)" or we add to params: "if (A==B and C==D)"
	 *
	 * @param  string  $function
	 * @return string
	 */
	protected function createOpenMatcher($function)
	{
		return '/(?<!\w)([[:blank:]]*)@' . $function . '(\s*\(.*)\)/';
	}

	/**
	 * Create a plain Blade matcher.
	 *
	 * @param  string  $function
	 * @return string
	 */
	protected function createPlainMatcher($function)
	{
		return '/(?<!\w)([[:blank:]]*)@' . $function . '([[:blank:]]*)/';
	}

	/**
	 * Sets the statement tags used for the compiler.
	 *
	 * @param  string  $openTag
	 * @param  string  $closeTag
	 * @param  bool    $escaped
	 * @return void
	 */
	public function setStatementTags($openTag, $closeTag)
	{
		$this->statementTags = array(preg_quote($openTag), preg_quote($closeTag));
	}

	/**
	 * Sets the content tags used for the compiler.
	 *
	 * @param  string  $openTag
	 * @param  string  $closeTag
	 * @param  bool    $escaped
	 * @return void
	 */
	public function setContentTags($openTag, $closeTag)
	{
		$this->contentTags = array(preg_quote($openTag), preg_quote($closeTag));
	}

	/**
	 * Sets the escaped content tags used for the compiler.
	 *
	 * @param  string  $openTag
	 * @param  string  $closeTag
	 * @return void
	 */
	public function setEscapedContentTags($openTag, $closeTag)
	{
		$this->escapedTags = array(preg_quote($openTag), preg_quote($closeTag));
	}

	/**
	 * Sets the template comment content tags used for the compiler.
	 * Template comments don't show in the compiled output!
	 *
	 * @param  string  $openTag
	 * @param  string  $closeTag
	 * @return void
	 */
	public function setCommentContentTags($openTag, $closeTag)
	{
		$this->commentTags = array(preg_quote($openTag), preg_quote($closeTag));
	}

}
