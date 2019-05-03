<?php namespace OneFile;

use Log;
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
 * TODO: Option to NOT check dependancies (i.e. Production mode)
 * TODO: Option to ignore indenting
 * TODO: Option to minify (Removing all redundant white space and comments)
 * TODO: Add @use('file.tpl', data_array) statement. Like @include, but the content is only fetched and evaluated at runtime.
 *      - @use(..) should not compile to html, but rather compile to a PHP render function, like child templates in the old Blade system.
 *
 * By: C. Moller - 11 May 2014
 *
 * Added Tabs compiler + Improved @section / @stop regex: C. Moller - 31 May 2014
 *
 *
 * @update 27 Nov 2016
 *   - Fix issue with malformed .meta files caused by missing @include template files.
 *   - Added "$debug" option to Template::render to improve debugability of template errors.
 *
 *
 * @update: 10 Jan 2017
 *    - If e.g @foreach(..) is NOT directly followed with HTML, insert NL before content HTML! NM -10 jan 2017
 *    - Improved @open, @else and @close tag type regexes!  MUCH simpler and faster + Better output formatting.
 *    - Did same for @unless and {{~ ... ~}} statements
 *
 * TODO: Remove rarely used features. Simplify as is the OneFile motto!
 * TODO: Cleanup Code and Comments
 *
 * @update: 06 Feb 2017
 *   - Fix error with multi-line STATEMENT regex. Added single-line (/s) option to regex!  {{~ ... ~}}
 *
 * @update: 25 Mar 2017
 *   - Fix if|while|for etc. detect Regex to properly handle nested brackets!
 *
 * @update: 05 Oct 2017
 *   - @include directive arguments can now be PHP statements.
 *
 * @update: 09 Feb 2018
 *  - Fix issue with muti-level template inheritance! Sections needed to be updated instead of the templateString for mid-level compile steps.
 *  - Added a new directive: @require('some/file/name') and made @include('another/file) optional.
 *    i.e. @include won't throw an error if the file doesn't exist, but @require will.
 *
 * @update: 13 Feb 2018
 *  - Fix issue with overriding section content over multiple levels. (More like completing unfinished features,
 *     since I noted that it doesn't work way back when! The same goes for the multi-level update above. )
 *  - Fix issue with not following @parent directives all the way to the top-most level.
 *
 * @update: 25 May 2018
 *  - Change "@section .. @show" directive to "@yieldDefault .. @show and make the feature work correctly!
 *  - Added Template::leftFlushContent() to ensure DEFAULT yield content AND section content
 *    always align correctly.
 *  - Improve Template::compileYield() to properly detect and handle the top-most template level.
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
    'YieldDefault',
    'SectionStop',
    'Yield',
    'Extends',
    //'Tabs', // Optional
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
   * The parent template if this is a child template.
   *
   * @var Template
   */
  protected $parent;

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


  protected $blockInsertCount = 0;


  protected $tabSpace = '  ';


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

      // Log::template('OneFile.Template Filename = ' . $templateFilename);

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
   * @param boolean $debug Allow showing render errors on-screen!
   * @return string
   */
  public function render($templateFilename, $data = array(), $asString = false, $useCached = true, $debug = false)
  {
    $logPrefix = 'OneFile.Template::render(), ';

    try {

      ob_start();

      extract($data);

      //Log::template($logPrefix . 'templatefile =  ' . $templateFilename);

      $this->templateFilePath = $this->addTemplatesPath($templateFilename);

      Log::template($logPrefix . 'templatefilePATH =  ' . $this->templateFilePath);

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

    }

    // Try and catch some errors here to prevent them showing in your response where they can pose a security risk.
    // Not very effective!  Fatal errors sail right through!!!  You have to register a shutdown handler to catch / stop
    // fatal errors!  Put ob_clear() in shutdown when an error is detected.
    catch (Exception $ex)
    {
      if ( ! $debug) { ob_clean(); }

      $response = "Oops, Something went wrong rendering template: $templateFilename!\n" . str_repeat('=', 60) . "\n" .
      get_class($ex) . " Code {$ex->getCode()}: {$ex->getMessage()} in file: {$ex->getFile()} (Line {$ex->getLine()})\n" .
      str_repeat('=', 60) . "\nTrace: \n" . $ex->getTraceAsString() . "\n";

      Log::error($logPrefix . 'Error: ' . $response);

      if ($debug)
      {
        echo nl2br($response);
      }
      else
      {
        echo 'Oops, something went wrong rendering template: ' . $templateFilename . '<br>' . PHP_EOL;
      }
    }

    return $asString ? ob_get_clean() : ob_flush();
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
    $logPrefix = 'OneFile.Template::compile(), ';

    $this->templateFilePath = $this->addTemplatesPath($templateFilename);

    $this->dependancies[$this->templateFilePath] = filemtime($this->templateFilePath);

    Log::template($logPrefix . 'templatefilePATH =  ' . $this->templateFilePath);

    $contents = $this->compileString(file_get_contents($this->templateFilePath));

    // If $cache == FALSE, we don't bother with META data, just exit here.
    if ( ! $cache) { return $returnContents ? $contents : null; }

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

    return $returnContents ? $contents : null;
  }


  /**
   * Compile the given Blade template contents.
   *
   * @param  string  $templateString
   * @return string
   */
  public function compileString($templateString)
  {
    //$logPrefix = 'OneFile.Template::compileString(), ';

    foreach ($this->compilers as $compiler)
    {
      //Log::template($logPrefix . 'Run Compiler: ' . $compiler);
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
    /**$pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->statementTags[0], $this->statementTags[1]);
    return preg_replace($pattern, '<?php $1; ?>', $templateString); */

    $pattern = sprintf('/%s\s*(.*?)\s*%s(.?)/s', $this->statementTags[0], $this->statementTags[1]);

    return preg_replace_callback($pattern, function($matches) {
      return '<?php ' . $matches[1] . ' ?>' . $matches[2] . ( ! strlen($matches[2]) ? PHP_EOL : '');
    }, $templateString);
  }


  /**
   * Compile structure openings into valid PHP.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileOpenings($templateString)
  {
    //$pattern = '/(?(R)\((?:[^\(\)]|(?R))*\)|(?<!\w)([[:blank:]]*)@(if|elseif|foreach|for|while)(\s*(?R)+)(.?))/'; +- 4500 steps
    //$pattern = '/@(if|elseif|foreach|for|while)(\s*\(.*\))(.?)/'; // +- 40 steps

    $pattern = '/@(if|elseif|foreach|for|while)(\((?:[^\(\)]|(?-1)?)*\))(.?)/';  // +- 115 steps

    return preg_replace_callback($pattern, function($matches) {
      return '<?php ' . $matches[1] . $matches[2] . ': ?>' . $matches[3] . ( ! strlen($matches[3]) ? PHP_EOL : '');
    }, $templateString);
  }


  /**
   * Compile structure closings into valid PHP.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileClosings($templateString)
  {
    //$pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';

    $pattern = '/@(endif|endforeach|endfor|endwhile)(.?)/';

    return preg_replace_callback($pattern, function($matches) {
      return '<?php ' . $matches[1] . '; ?>' . $matches[2] . ( ! strlen($matches[2]) ? PHP_EOL : '');
    }, $templateString);
  }


  /**
   * Compile else statements into valid PHP.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileElse($templateString)
  {
    /** $pattern = $this->createPlainMatcher('else');
    return preg_replace($pattern, '$1<?php else: ?>' . PHP_EOL . '$2', $templateString); */

    $pattern = '/@else(.?)/';

    return preg_replace_callback($pattern, function($matches) {
      return '<?php else: ?>' . $matches[1] . ( ! strlen($matches[1]) ? PHP_EOL : '');
    }, $templateString);
  }


  /**
   * Compile unless statements into valid PHP.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileUnless($templateString)
  {
    /**$pattern = $this->createMatcher('unless');
    return preg_replace($pattern, '$1<?php if ( !$2): ?>' . PHP_EOL, $templateString); */

    $pattern = '/@unless\s*\((.*)\)(.?)/';

    return preg_replace_callback($pattern, function($matches) {
      return '<?php if(!('.$matches[1].')): ?>' . $matches[2] . ( ! strlen($matches[2]) ? PHP_EOL : '');
    }, $templateString);
  }


  /**
   * Compile end unless statements into valid PHP.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileEndUnless($templateString)
  {
    //$pattern = $this->createPlainMatcher('endunless');
    /**return preg_replace($pattern, '$1<?php endif; ?>' . PHP_EOL . '$2', $templateString);*/
    $pattern = '/@endunless(.?)/';

    return preg_replace_callback($pattern, function($matches) {
      return '<?php endif; ?>' . $matches[1] . ( ! strlen($matches[1]) ? PHP_EOL : '');
    }, $templateString);
  }


  /**
   * Compile include statements helper method.
   *
   * @param  string  $str
   * @return string
   */
  protected function _echo($str)
  {
    return print_r($str, true);
  }


  /**
   * Compile @include directives into valid PHP.
   *
   * @include:
   *  Adds an external file's content to the template.
   *  Includes can be optional or required.
   *  The default is optional.
   *
   * NOTE: The @include directive argument can be an ASB path, REL path OR
   *       a PHP Statement evaluating to either type of path!
   *
   * @param  string  $templateString
   * @param  boolean $required
   * @return string
   */
  protected function compileIncludes($templateString, $required = false)
  {
    if ($required)
    {
      $logPrefix = 'OneFile.Template::compileRequired(), ';
      $directive = 'require';
    }
    else
    {
      $logPrefix = 'OneFile.Template::compileIncludes(), ';
      $directive = 'include';
    }

    $pattern = $this->createOpenMatcher($directive);

    $matches = array();

    preg_match_all($pattern, $templateString, $matches);

    if ( ! $matches or ! $matches[0])
      return $templateString;

//    echo '<p style="color:red">Matches = ' . print_r($matches,true) . '</p>';

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
      // $filename_match_raw typical values:
      // ===================================
      //
      // `('some/dir/fileToInclude.ext'`
      // `('/some/abs/dir/fileToInclude.ext'`
      //
      // OR
      //
      // `(__PAGES__ . '/' . Route::$pageRef . '/some/absdir/fileToInclude.ext'`
      //
      // NOTE: Notice the missing closing bracket on match!
      //
      // NOTE2: Be careful if you make this code public, the eval() arg needs to be sanitized.
      //
      // Log::template("$logPrefix filename_match_raw = $filename_match_raw");
      //
      $file = eval('return $this->_echo' . $filename_match_raw . ');');
      $files[] = $file;
    }

    foreach ($files as $i => $filename)
    {
      //Log::template("$logPrefix @$directive($filename)");

      $filepath = $this->addTemplatesPath($filename);

      //Log::template("$logPrefix @$directive($filepath)");

      if ( ! $filepath)
      {
        if ($required)
        {
          throw new Exception("$logPrefix Can't find required file: $filename");
        }

        // Remove the @include directive from the templateString and skip this file.
        $templateString = str_replace($includeStatements[$i], '', $templateString);
        continue;

      }

      $this->dependancies[$filepath] = filemtime($filepath);

      $content_to_include = $this->compile($filepath, false);

      if (is_null($content_to_include) or $content_to_include === '')
      {
        $templateString = str_replace($includeStatements[$i], '', $templateString);
        continue;
      }

      $lines = preg_split("/(\r?\n)/", $content_to_include);

      foreach ($lines as $no => $line)
      {
//        $lines[$no] = ($no ? $indents[$i] : '') . $line;
        $lines[$no] = $indents[$i] . $line;
      }

      $templateString = str_replace($includeStatements[$i], implode(PHP_EOL, $lines), $templateString);
    }

    return $templateString;
  }


  /**
   * Compile @required directives into valid PHP.
   *
   * See the @include directive compiler above.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileRequired($templateString)
  {
    return $this->compileIncludes($templateString, 'required');
  }


  private function leftFlushContent($content)
  {
    $logPrefix = 'OneFile.Template::leftFlushContent(), ';
    $indents = [];
    $minIndent = 99;
    $content = str_replace("\t", $this->tabSpace, $content);
    $lines = explode("\n", $content);
    if (count($lines) < 2) { return $content; }
    foreach ($lines as $line)
    {
      // Log::template($logPrefix . 'line = "' . $line . '"');
      if ( ! $line) { continue; }
      $indentString = preg_match('/^\s*/', $line, $matches) ? $matches[0] : '';
      // Log::template($logPrefix . 'indentString = "' . $indentString . '"');
      $indent = strlen($indentString);
      // Log::template($logPrefix . 'indent = "' . $indent . '"');
      if ($indent < $minIndent) { $minIndent = $indent; }
    }
    $newLines = [];
    foreach ($lines as $lineno => $line)
    {
      if ( ! $line) { continue; }
      $newLines[$lineno] = substr($line, $minIndent);
    }
    // Log::template($logPrefix . 'minIndent = "' . $minIndent . '"');
    return implode("\n", $newLines);
  }


  /**
   * Basically @yield with default content!
   *
   * We can't use @yield .. @show since we don't want to be forced to put @stop after every @yield!
   * So, we use @yieldDefault .. @show instead!
   *
   * Extract YieldDefault block contents into the sections array to be used in Yield statements.
   *
   * @param  string  $templateString
   * @return string
   */
  protected function compileYieldDefault($templateString)
  {
    $logPrefix = 'OneFile.Template::compileYieldDefault(), ';

    $pattern = '/(?<!\w)([[:blank:]]*)@yieldDefault\s*\((.*?)\)([\s\S]*?)\s*@show/';
    preg_match_all($pattern, $templateString, $matches, PREG_OFFSET_CAPTURE);

    $fullMatches = $matches ? $matches[0] : null;

    if ( ! $fullMatches) { return $templateString; }

    $leadingSpaceMatches = $matches[1];
    $sectionNameMatches = $matches[2];
    $contentMatches = $matches[3];

    // Pattern = [M0:Full Match][M1:Leading Space][M2:Section Name][M3:Section Content]
    //
    // Matches = [
    //
    //   0:FullMatches: [
    //     FullPatternMatch0: [
    //       FullContent0: [match0-full-content],
    //       FullContentOffset0: [offset]
    //     ],
    //     FullPatternMatch1: [
    //       FullContent1: [match1-full-content],
    //       FullContentOffset1: [offset]
    //     ],
    //     ...
    //   ],
    //
    //   1:SubPattern1Matches: [
    //     SubPattern1Match0: [
    //       SubPatternContent0: [match0-leading-space],
    //       SubPatternContentOffset0: [offset]
    //     ],
    //     SubPattern1Match1: [
    //       SubPatternContent1: [match1-leading-space],
    //       SubPatternContentOffset1: [offset]
    //     ],
    //     ...
    //   ],
    //
    //   2:SubPattern2Matches: [
    //     SubPattern2Match0: [
    //       SubPatternContent0: [match0-section-name],
    //       SubPatternContentOffset0: [offset]
    //     ],
    //     SubPattern2Match1: [
    //       SubPatternContent1: [match1-section-name],
    //       SubPatternContentOffset1: [offset]
    //     ],
    //     ...
    //   ],
    //
    //   3:SubPattern3Matches: [
    //     SubPattern3Match0: [
    //       SubPatternContent0: [match0-content-string],
    //       SubPatternContentOffset0: [offset]
    //     ],
    //     SubPattern3Match1: [
    //       SubPatternContent1: [match1-content-string],
    //       SubPatternContentOffset1: [offset]
    //     ],
    //     ...
    //   ]
    // ]

    $charactersRemoved = 0;
    $templateStringParts = [];
    foreach ($fullMatches as $i => $fullMatch)
    {
      $fullMatchLength = strlen($fullMatch[0]);
      $fullMatchStart = $fullMatch[1] - $charactersRemoved;
      // Push tpl string part BEFORE match.
      $templateStringParts[] = substr($templateString, 0, $fullMatchStart);
      // Cut everything before match + the match from the tpl string.
      $templateString = substr($templateString, $fullMatchStart + $fullMatchLength);
      $charactersRemoved += $fullMatchStart + $fullMatchLength;
      // Reduce the overall content left indent to zero.
      $sectionContent = $this->leftFlushContent($contentMatches[$i][0]);
      // Store content in sections array
      $sectionNameMatch = $sectionNameMatches[$i][0];
      $sectionName = trim($sectionNameMatch, "'\""); // Strip quotes from section names
      $this->sections[$sectionName] = $sectionContent;
      // Generate and add yield statement
      $leadingSpace = $leadingSpaceMatches[$i][0];
      $yieldStatement = "$leadingSpace@yield('$sectionName')";
      // Log::template($logPrefix . 'yieldStatement = "' . $yieldStatement . '"');
      $templateStringParts[] = $yieldStatement;
    }

    // Add tpl string tail
    $templateString = implode('', $templateStringParts) . $templateString;

    // Log::template($logPrefix . "templateString:\n" . $templateString);
    // Log::template($logPrefix . "this->sections:\n" . print_r($this->sections, true));

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

    //$logPrefix = 'OneFile.Template::compileSectionStop(), ';

    // Note: Any whitespace between the last content character and @end will be ignored.
    // Note: Sections can NOT be nested.
    //    Use @include to add partials inside a section.
    //    Partials may be other templates with their own layout and sections

    $matches = array();

    $pattern = '/(?<!\w)[[:blank:]]*@section\s*\((.*?)\)([\s\S]*?)\s*@stop/';

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
      $sectionContent = $this->leftFlushContent($sectionContent);
      $this->sections[$sectionNames[$i]] = $sectionContent;
    }

    // Log::template($logPrefix . 'Sections found = ' . print_r(array_keys($this->sections), true));

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
    $logPrefix = 'OneFile.Template::compileYield(), ';
    $pattern = $this->createOpenMatcher('yield');

    $matches = array();

    preg_match_all($pattern, $templateString, $matches);

    if ( ! $matches or ! $matches[0])
      return $templateString;

    $yieldStatements = array();

    // mathces[0]: [ws1]@yield[ws2]('section-to-yield')
    foreach ($matches[0] as $yieldStatement)
    {
      $yieldStatements[] = $yieldStatement;
    }

    // Log::template($logPrefix . 'Yields = ' . print_r($yieldStatements, true));

    $indents = array();

    // matches[1] === ws1
    foreach ($matches[1] as $indent)
    {
      $indents[] = $indent;
    }

    $yieldNames = array();

    // matches[2] === [ws2]('section-to-yield'
    foreach ($matches[2] as $yieldNameMatchRaw)
    {
      //Scrub Section Name Match String
      //SubStr offset = 2 ... Jumps over (' part of string, but leaves ' at end. Hence also trim()
      $yieldNames[] = trim(substr($yieldNameMatchRaw, 2), "'\"");
    }

    // Log::template($logPrefix . 'Sections to yield in this template = ' . print_r($yieldNames, true));

    $isTopLevelTemplate = (substr(ltrim($templateString), 0, 8) !== '@extends');
    
    foreach ($yieldNames as $i => $yieldName)
    {
      // Log::template($logPrefix . 'Yielding Section: ' . $yieldName);

      $hasDefaultContent = isset($this->sections[$yieldName]);

      // Log::template($logPrefix . 'Section has default content = ' . ($hasDefaultContent ? 'YES' : 'NO'));

      $parent = $this;
      $child = is_object($this->child) ? $this->child : null;
      $childWithOverrideContent = null;
      while ($child)
      {
        $child->parent = $parent;
        if (isset($child->sections[$yieldName])) {
          $childWithOverrideContent = $child;
        }
        $parent = $child;
        $child = is_object($child->child) ? $child->child : null;
      }

      if ( ! $hasDefaultContent and ! $childWithOverrideContent and ! $this->sections)
      {
        // No content exists for this @yield statement!
        // Remove the @yield statement from the template by replacing it with an empty string.
        // ONLY if we are in the TOP LEVEL template. i.e. count($this->sections) = 0
        $templateString = str_replace($yieldStatements[$i], '', $templateString);
        continue;
      }

      // if ($childWithOverrideContent) {
        // Log::template($logPrefix . 'childWithOverrideContent->sections: ' . print_r($childWithOverrideContent->sections, true));
      // }

      if ($childWithOverrideContent)
      {
        // A child section was found that overrides the default/parent section content
        $childContent = & $childWithOverrideContent->sections[$yieldName];

        // If the child/override content contains a "@parent" tag, the tag will be replaced with the parent content.
        // We work out way up the tree of parents.
        while (strpos($childContent, '@parent') !== false)
        {
          //Log::template($logPrefix . 'FOUND @PARENT directive in child content: ' . substr($childContent, 0, 255));

          $parentWithContent = null; // This will become the first parent template to have content for this section.

          $parent = $childWithOverrideContent->parent; // $childWithOverrideContent will ALWAYS have a parent due to the way we set it above.

          while ($parent)
          {
            if (!empty($parent->sections[$yieldName]))
            {
              $parentWithContent = $parent;
              // Log::template($logPrefix . 'FOUND PARENT WITH CONTENT!');
              break;
            }
            $parent = $parent->parent;
          }

          if ( ! $parentWithContent)
          {
            // Log::template($logPrefix . 'No parent with content found. Remove @parent directive');
            // We did not find any parent content to replace the @parent tag with, so replace it with an empty string.
            $childContent = str_replace('@parent', '', $childContent);
            break;
          }

          $parentContent = & $parentWithContent->sections[$yieldName];

          // Log::template($logPrefix . 'Parent content: ' . substr($parentContent, 0, 255));

          $childContent = str_replace('@parent', $parentContent, $childContent);

          // Log::template($logPrefix . 'New child content: ' . substr($childContent, 0, 255));

          // Continue scanning upwards from the last parent we found with some content.
          $parent = $parentWithContent;
        }

        $yieldContent = & $childContent;
      }
      else
      {
        // If no Content Override, $sectionContent will still contain the parent/default section content!
        $yieldContent = $hasDefaultContent ? $this->sections[$yieldName] : '';
      }

      // Log::template($logPrefix . 'Section yield content: ' . substr($yieldContent, 100));

      $yieldContentLines = preg_split("/(\r?\n)/", $yieldContent);

      foreach ($yieldContentLines as $no => $line)
      {
        $yieldContentLines[$no] = $indents[$i] . $line;
      }

      // Log::template($logPrefix . 'Yield content lines = ' . print_r($yieldContentLines, true));
      
      $yieldContent = implode(PHP_EOL, $yieldContentLines);
            
      // If we are at the top level, we just update template string.
      if ($isTopLevelTemplate)
      {
        $templateString = str_replace($yieldStatements[$i], $yieldContent, $templateString);
      }
      // If we have sections in this template and we are not at the TOP LEVEL yet, we need to
      // replace all @yield statements in the section content with actual/yield content.      
      else
      {
        foreach ($this->sections as $sectionName => $sectionContent)
        {
          // Inject yield content into section content if the section contains a yield statement coresponding to the yield content.
          $this->sections[$sectionName] = str_replace($yieldStatements[$i], $yieldContent , $sectionContent);
        }
      }
    }

    $this->blockInsertCount += count($yieldNames);

    // Log::template($logPrefix . 'templateString = ' . $templateString);

    return $templateString;
  }


  /**
   *
   * @param type $templateString
   */
  protected function compileExtends($templateString)
  {
    //$logPrefix = 'OneFile.Template::compileExtends(), ';

    $pattern = $this->createOpenMatcher('extends');

    $matches = array();

    preg_match($pattern, $templateString, $matches);

    if ( ! $matches or ! $matches[0])
      return $templateString;

    $parent = new self($this->templatesPath, $this->cachePath, $this);

    $parent_templatefile = trim(substr($matches[2], 1), "'\"");

    //Log::template($logPrefix . 'Compile Parent: ' . $parent_templatefile);

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
    return str_replace("\t", $this->tabSpace, $templateString);
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
