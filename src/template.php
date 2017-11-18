<?php namespace OneFile;

use Closure;

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
 *    Like @include, but for partial templates. Each @use template is
 *    given its own scope and data so that it can be used more than once in the same view.
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
		'Yield',
		'Section',
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
	 * @param Template $child_template A child template from which to import sections referenced in this template.
	 */
	public function __construct($templatesPath = null, $cachePath = null, $child_template = null)
	{
		$this->templatesPath = $templatesPath ? realpath($templatesPath) : __DIR__;

		$this->cachePath = $cachePath ? realpath($cachePath) : null;

		$this->child = $child_template;

		//Initialize here to allow using preg_quote()
		$this->commentTags = array(preg_quote('{*'), preg_quote('*}'));
	}

	/**
	 * Add the templates path to get the full/absolute filename if required
	 * 
	 * Allows using only the template's relative path/filename in compile() or render()
	 * for convienence.
	 *
	 * @param  string  $template_filename
	 * @return string
	 */
	protected function addTemplatesPath($template_filename)
	{
		if ( ! file_exists($template_filename))
		{
			$template_filename = $this->templatesPath . '/' . $template_filename;

			if ( ! file_exists($template_filename))
					return null;
		}

		return $template_filename;
	}

	/**
	 * Get the path to the compiled version of a view.
	 * 
	 * Note: To save CPU cycles, put your template files close to your OS root folder
	 * to shorten the resulting path strings.
	 * 
	 * @param string $templatefile_path
	 * @param boolean $force_recalc
	 * @param boolean $encode
	 * @return string
	 */
	protected function getCompiledFilePath($templatefile_path, $force_recalc = false, $encode = true)
	{
		if (! $this->compiledFilePath or $force_recalc)
		{
			$this->compiledFilename = $encode ? md5($templatefile_path) : $templatefile_path;
			$this->compiledFilePath = $this->cachePath . '/' . $this->compiledFilename;
		}

		return $this->compiledFilePath;
	}

	/**
	 * The Meta data file holds information on dependancies for each template used by isExpired()
	 * Add underscore infront of the meta-filename to make it faster to scan the cache folder for meta and NON meta files!
	 * 
	 * @param type $cachefile_path
	 * @return type
	 */
	protected function getMetaFilePath($cachefile_path = null)
	{
		if ($this->compiledFilename)
		{
			$path = $this->cachePath . '/_' . $this->compiledFilename;
		}
		else
		{
			$path = $cachefile_path;
		}

		return $path . '.meta';
	}

	/**
	 * Determine if the view at the given path is expired.
	 * We assume that we are using the cache if we check for expired!
	 *
	 * @param  string  $templatefile_path
	 * @return bool
	 */
	protected function isExpired($templatefile_path)
	{
		//Always force updating the compiled file path on checking for Expired!
		//We always check for Expited before compiling making this a nice place to ensure that the path
		//is always current for compile() and render(), but still allowing the benefits of NOT re-calculating 
		//the path in other places like reading and saving the compiled file.
		$compiled = $this->getCompiledFilePath($templatefile_path, true);

		// If the compiled file doesn't exist we will indicate that the view is expired
		if (!file_exists($compiled))
		{
			return true;
		}

		//Get the "Last Modified" timestamps of all the child templates including this this template's timestamp
		$dependancies = include($this->getMetaFilePath());

		if (!$dependancies)
		{
			//The compiled file has "Expired" if its timestamp is older than its source template timestamp
			return filemtime($compiled) < filemtime($templatefile_path);
		}

		foreach ($dependancies as $dependant_file => $last_timestamp)
		{
			if ( ! file_exists($dependant_file))
					return true;

			//A dependnat file has changed if the file's last timestamp is older than its current timestamp
			//A changed dependancy === Compiled File Expired! 
			if ($last_timestamp < filemtime($dependant_file))
					return true;
		}

		return false;
	}

	/**
	 * 
	 * @param string $templatefile_path
	 * @return string
	 */
	protected function getCompiledFile($templatefile_path)
	{
		if ( ! $this->isExpired($templatefile_path))
		{
			return file_get_contents($this->getCompiledFilePath($templatefile_path));
		}
		else
		{
			return $this->compile($templatefile_path);
		}
	}

	/**
	 * 
	 * @param string $template_filename
	 * @param array $data
	 * @param boolean $as_string
	 * @param boolean $use_cached
	 * @return string
	 */
	public function render($template_filename, $data = array(), $as_string = false, $use_cached = true)
	{
		extract($data);

		$this->templateFilePath = $this->addTemplatesPath($template_filename);

		//Reset the compiled filepath because we could be rendering a different file
		//with the same Template instance.
		$this->compiledFilePath = null;

		if ($as_string)
		{
			ob_start();
		}

		if ($use_cached and $this->cachePath)
		{
			if ($this->isExpired($this->templateFilePath))
			{
				//Compile with CacheContents=TRUE and ReturnConents=FALSE
				$this->compile($this->templateFilePath, true, false);
			}

			include $this->getCompiledFilePath($this->templateFilePath);
		}
		else
		{
			//Compile with CacheContents=FALSE.  Contents will always be returned as string if Cache=FALSE.
			eval(" ?>" . $this->compile($this->templateFilePath, false) . "<?php ");
		}

		if ($as_string)
		{
			return ob_get_clean();
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
	 * @param string $template_filename
	 * @param boolean $cache
	 * @param string $cachefile_path
	 * @param boolean $return_contents
	 * @return string
	 */
	public function compile($template_filename, $cache = true, $cachefile_path = null, $return_contents = true)
	{
		$this->templateFilePath = $this->addTemplatesPath($template_filename);

		$this->dependancies[$this->templateFilePath] = filemtime($this->templateFilePath);

		//If $cache=FALSE we always return freshly compiled content as a
		//string and we don't bother with possibly expired dependancies.
		if (!$cache)
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
			if ($cachefile_path)
			{
				//Get compiled file path with ForceReCalc = TRUE and EncodeFilename = FALSE
				$compiledFilePath = $cachefile_path;
				file_put_contents($this->getMetaFilePath($cachefile_path), $this->renderDependancies());
			}
			else
			{
				//Get compiled file path with ForceReCalc = FALSE and EncodeFilename = TRUE (Defaults)
				$compiledFilePath = $this->getCompiledFilePath($this->templateFilePath);
				file_put_contents($this->getMetaFilePath(), $this->renderDependancies());
			}

			file_put_contents($compiledFilePath, $contents);
		}

		if ($return_contents) 
				return $contents;
	}

	/**
	 * Compile the given Blade template contents.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public function compileString($value)
	{
		foreach ($this->compilers as $compiler)
		{
			$value = $this->{"compile{$compiler}"}($value);
		}

		return $value;
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
	 * @param  string  $value
	 * @return string
	 */
	protected function compileExtensions($value)
	{
		foreach ($this->extensions as $compiler)
		{
			$value = call_user_func($compiler, $value, $this);
		}

		return $value;
	}

	/**
	 * Compile Blade comments into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileComments($value)
	{
		$pattern = sprintf('/[[:blank:]]*%1$s[\s\S]*?%2$s[[:blank:]]*[\r\n]?/', $this->commentTags[0], $this->commentTags[1]);

		return preg_replace($pattern, '', $value);
	}

	/**
	 * Compile Blade echos into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileEchos($value)
	{
		$difference = strlen($this->contentTags[0]) - strlen($this->escapedTags[0]);

		if ($difference > 0)
		{
			return $this->compileEscapedEchos($this->compileRegularEchos($value));
		}

		return $this->compileRegularEchos($this->compileEscapedEchos($value));
	}

	/**
	 * Compile the "regular" echo statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileRegularEchos($value)
	{
		$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->contentTags[0], $this->contentTags[1]);

		return preg_replace($pattern, '<?php echo $1; ?>', $value);
	}

	/**
	 * Compile the escaped echo statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileEscapedEchos($value)
	{
		$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->escapedTags[0], $this->escapedTags[1]);

		return preg_replace($pattern, '<?php echo htmlentities($1, ENT_QUOTES | ENT_IGNORE, "UTF-8", false); ?>', $value);
	}

	/**
	 * Compile the raw php statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileStatements($value)
	{
		$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->statementTags[0], $this->statementTags[1]);

		return preg_replace($pattern, '<?php $1; ?>', $value);
	}

	/**
	 * Compile Blade structure openings into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileOpenings($value)
	{
		$pattern = '/(?(R)\((?:[^\(\)]|(?R))*\)|(?<!\w)(\s*)@(if|elseif|foreach|for|while)(\s*(?R)+))/';

		return preg_replace($pattern, '$1<?php $2$3: ?>', $value);
	}

	/**
	 * Compile Blade structure closings into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileClosings($value)
	{
		$pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';

		return preg_replace($pattern, '$1<?php $2; ?>$3', $value);
	}

	/**
	 * Compile Blade else statements into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileElse($value)
	{
		$pattern = $this->createPlainMatcher('else');

		return preg_replace($pattern, '$1<?php else: ?>$2', $value);
	}

	/**
	 * Compile Blade unless statements into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileUnless($value)
	{
		$pattern = $this->createMatcher('unless');

		return preg_replace($pattern, '$1<?php if ( !$2): ?>', $value);
	}

	/**
	 * Compile Blade end unless statements into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileEndUnless($value)
	{
		$pattern = $this->createPlainMatcher('endunless');

		return preg_replace($pattern, '$1<?php endif; ?>$2', $value);
	}

	/**
	 * Compile Blade include statements into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileIncludes($value)
	{
		$pattern = $this->createOpenMatcher('include');

		$matches = array();

		preg_match_all($pattern, $value, $matches);

		if ( ! $matches or ! $matches[0])
			return $value;

//		echo '<p style="color:red">Matches = ' . print_r($matches,true) . '</p>';

		$replaceble_strings = array();
		foreach ($matches[0] as $replace)
		{
			$replaceble_strings[] = $replace;
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
				$value = str_replace($replaceble_strings[$i], '', $value);
				continue;
			}

			$lines = preg_split("/(\r?\n)/", $content_to_include);

			foreach ($lines as $no => $line)
			{
				$lines[$no] = $indents[$i] . $line;
			}

			$value = str_replace($replaceble_strings[$i], implode('', $lines), $value);
		}

		return $value;
	}

	/**
	 * Compile Blade yield statements into valid PHP.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileYield($value)
	{
		if (!$this->child)
				return $value;

		$pattern = $this->createOpenMatcher('yield');

		$matches = array();

		preg_match_all($pattern, $value, $matches);

		if ( ! $matches or ! $matches[0])
				return $value;

		$replaceble_strings = array();
		foreach ($matches[0] as $replace)
		{
			$replaceble_strings[] = $replace;
		}

		$indents = array();
		foreach ($matches[1] as $indent)
		{
			$indents[] = $indent;
		}

		$sections = array();
		foreach ($matches[2] as $sectionname_match_raw)
		{
			//Scrub Section Name Match String
			//SubStr offset = 2 ... Jumps over (' part of string, but leaves ' at end. Hence also trim()
			$sections[] = trim(substr($sectionname_match_raw, 2), "'\"");
		}

		foreach ($sections as $i => $section_name)
		{
			$section_content = isset($this->child->sections[$section_name]) ? $this->child->sections[$section_name] : '';

			if ($section_content === '')
			{
				$value = str_replace($replaceble_strings[$i], '', $value);
				continue;
			}

			$lines = preg_split("/(\r?\n)/", $section_content);

			foreach ($lines as $no => $line)
			{
				$lines[$no] = $indents[$i] . $line;
			}

			$value = str_replace($replaceble_strings[$i], implode('', $lines), $value);
		}

		return $value;
	}

	/**
	 * Extract Blade Section blocks into a sections array to be used in Yield statements.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function compileSection($value)
	{
		//Note: @section MUST be on it's own line to preserve content formatting! 
		//		Everything on the line after @section will be ignored.
		//		Any whitespace before @end will be ignored.
		//		
		//Note: Sections can NOT be nested.
		//		Use @include to add partials inside a section.
		//		Partials may be other templates with their own layout and sections

		$matches = array();

		$pattern = '/(?<!\w)\s*@section\s*\((.*)\).*[\n\r]*([\s\S]*?)\s*@stop/';

		preg_match_all($pattern, $value, $matches);

		if ( ! $matches or ! $matches[0])
			return $value;

		$section_names = array();
				
		foreach ($matches[1] as $sectionname_match_raw)
		{
			$section_names[] = trim($sectionname_match_raw, "'\""); //Removes quotes!
		}

		foreach ($matches[2] as $i => $section_content)
		{
			$this->sections[$section_names[$i]] = $section_content;
		}

		return $value;
	}

	/**
	 * 
	 * @param type $value
	 */
	protected function compileExtends($value)
	{
		$pattern = $this->createOpenMatcher('extends');

		$matches = array();

		preg_match($pattern, $value, $matches);

		if ( ! $matches or ! $matches[0])
			return $value;

		$parent = new self($this->templatesPath, $this->cachePath, $this);

		$parent_templatefile = trim(substr($matches[2], 1), "'\"");

		return $parent->compile($parent_templatefile);
	}

	/**
	 * 
	 * @param type $value
	 * @return type
	 */
	protected function compileTabs($value)
	{
		return str_replace("\t", '   ', $value);
	}

	/**
	 * Get the regular expression for a generic Blade function.
	 *
	 * @param  string  $function
	 * @return string
	 */
	protected function createMatcher($function)
	{
		return '/(?<!\w)(\s*)@' . $function . '(\s*\(.*\))/';
	}

	/**
	 * Get the regular expression for a generic Blade function.
	 *
	 * @param  string  $function
	 * @return string
	 */
	protected function createOpenMatcher($function)
	{
		return '/(?<!\w)(\s*)@' . $function . '(\s*\(.*)\)/';
	}

	/**
	 * Create a plain Blade matcher.
	 *
	 * @param  string  $function
	 * @return string
	 */
	protected function createPlainMatcher($function)
	{
		return '/(?<!\w)(\s*)@' . $function . '(\s*)/';
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
