<?php namespace OneFile;

/**
 * Concept:
 * Replace the HTML:: helper class in frameworks like Laravel with a dynamically generated helper class that allows changing of themes and only include
 * templates needed for a particular request.
 *
 * Use this class to output compiled and cached HTML fragments like FORM INPUT FIELDS, SELECT FIELDS, MENUS etc. at runtime OR
 * Pre-comiple template fragmanets into full templates, compile and cache OR
 * Create code/html content generators.
 *
 * Example: HTML::dropdown(name, data, options):
 *  1. Check HTML Cache Manifest for "dropdown" html
 *  2. IF Cached: ( IF Expired: Goto step 3 ELSE Return cached html fragment ) ELSE Goto step 3
 *  3. Find "dropdown" template in THEME subfolder
 *  4. Compile + Cache "dropdown" template into Minified phtml
 *  5. Add compiled template to Cache Manifest
 *  6. Add compiled template to local templates cache to speed up repeated template use
 *
 *  7. Add the compiled template to a view + Bind data to values required in the template + Echo to screen or some othe output destination. OR
 *  8. Add the compiled template to
 *
 * Bind template with model and POST depending on parameters supplied and form state etc.
 *  5. Render template!
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 8 June 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */

interface PhpTemplateCompiler
{
	/**
	 * Compiles template file content (loaded as string) into valid PHP
	 * @param string $template
	 */
	public function compileString($template);
}


class Html
{
	protected $theme;

	protected $templatesBasePath;

	protected $templateFilePattern = '%s/%s.tpl';

	protected $compiledFilePattern = 'chtml_%s_%s.php';

	protected $manifestFilePattern = '%s_manifest.php';

	protected $compiledPath;

	protected $compiler;

	protected $manifests;

	/**
	 * A memory cache / array of Compiled Templates indexed
	 * by their types for the specific theme and incrementally
	 * filled from file cache as we request templates to render.
	 *
	 * But we want to Mix themes at times!?  Make sure we also catagorise according to theme
	 *
	 * @var array
	 */
	protected $templates;

	/**
	 *
	 * @param string $theme
	 * @param string $templatesBasePath
	 * @param string $compiledPath
	 */
	public function __construct($theme, $templatesBasePath, $compiledPath, $compiler = null)
	{
		$this->theme = $theme;
		$this->templatesBasePath = $templatesBasePath;
		$this->compiledPath = $compiledPath;

		if ($compiler)
		{
			$this->setCompiler($compiler, $this->getTemplatesPath($theme), $compiledPath);
		}
	}

	protected function compile($template)
	{
		$this->compiler->compileString($template);
	}

	protected function getTemplatesPath($theme)
	{
		return $this->templatesBasePath . '/' . $theme;
	}

	protected function getCompiledFilePath($theme, $type)
	{
		return $this->compiledPath . '/' . sprintf($this->compiledFilePattern, $theme, $type);
	}

	protected function getManifestFilePath($theme)
	{
		return $this->compiledPath . '/' . sprintf($this->manifestFilePattern, $theme);
	}

	protected function getCached($key = null, $default = null)
	{
		if (is_null($key))
		{
			return $this->templates;
		}

		if (isset($this->templates[$key]))
		{
			return $this->templates[$key];
		}

		$array = & $this->templates;

		foreach (explode('.', $key) as $segment)
		{
			if ( ! is_array($array) or ! array_key_exists($segment, $array))
			{
				return $default;
			}

			$array = & $array[$segment];
		}

		return $array;
	}

	protected function updateManifestFile($theme)
	{
		return file_put_contents($this->getManifestFilePath($theme), '<?php return ' . var_export($this->manifests[$theme], true) . ';');
	}

	protected function compileTemplates($theme, $templatesPath)
	{
		var_dump($templatesPath);

		if ( ! is_dir($templatesPath)) //????
		{
			return false;
		}

		$templateFiles = glob($templatesPath . '/*.tpl');

		$matches = array();

		$contents = file_get_contents($templatesPath); //????

		var_dump($contents);

		$pattern = '/(?<!\w)\s*@html\s*\((.*)\).*[\n\r]*([\s\S]*?)\s*@endhtml/';

		preg_match_all($pattern, $contents, $matches);

		if ( ! $matches or ! $matches[0])
		{
			return false;
		}

		$template_types = array();

		foreach ($matches[1] as $templatetype_match_raw)
		{
			$template_types[] = trim($templatetype_match_raw, "'\""); //Removes quotes!
		}

		$compiled_stack = array();

		foreach ($matches[2] as $i => $template)
		{
			$compiled = $this->compiler->compileString($template);

			$type = $template_types[$i];

			$compiled_stack[$type] = $compiled;

			$this->templates[$theme][$type] = $compiled;
		}

		$compiledFilePath = $this->getCompiledFilePath($theme, $template_types[0]);

		file_put_contents($compiledFilePath, '<?php return ' . var_export($compiled_stack, true) . ';');

		$manifest_entries = $this->manifests[$theme];

		foreach($template_types as $type)
		{
			$manifest_entries[$type] = $compiledFilePath;
		}

		$this->manifests[$theme] = $manifest_entries;

		$this->updateManifestFile($theme);

		return true;
	}

	/**
	 * Note: If we don't modify data arrays, they pass by REFERENCE.
	 *  - So no need for "&" by_reference indicators!
	 *
	 * @param string $compiledTemplateString
	 * @param string $attributes
	 * @param string $options
	 */
	protected function renderTemplate($compiledTemplateString, $attributes)
	{
		extract($attributes);

		ob_start();

		eval(" ?>" . $compiledTemplateString . "<?php ");

		return ob_get_clean();
	}

	protected function updateTemplates($theme, $compiledFilePath)
	{
		$templatesToAdd = include($compiledFilePath);

		foreach($templatesToAdd as $type => $template)
		{
			$this->templates[$theme][$type] = $template;
		}
	}

	protected function loadTemplate($theme, $type)
	{
		//Ceck Compiled Templates List
		if (isset($this->manifests[$theme]))
		{
			$manifest = $this->manifests[$theme];
		}
		else
		{
			$manifestFilePath = $this->getManifestFilePath($theme);

			if (file_exists($manifestFilePath))
			{
				$manifest = include($manifestFilePath);
			}
			else
			{
				$manifest = null;
			}
		}

		if (isset($manifest[$type]))
		{
			$this->updateTemplates($theme, $manifest[$type]);

			return $this->getCached("$theme.$type");
		}

		if ($this->compileTemplates($theme, $this->getTemplatesPath($theme)))
		{
			return $this->getCached("$theme.$type");
		}
	}

	protected function getTemplate($theme, $type, $attributes)
	{
		//Check in memory
		$template = $this->getCached("$theme.$type");

		if( ! $template)
		{
			$template = $this->loadTemplate($theme, $type);
		}

		if ($template)
		{
			return $this->renderTemplate($template, $attributes);
		}

		return "Error: Failed to Find Template $theme:$type!";
	}

	public function setCompiler(PhpTemplateCompiler $compiler, $templatesPath = null, $compiledPath = null)
	{
		$this->compiler = $compiler;
		if ($templatesPath) { $this->compiler->setTemplatesPath($templatesPath); }
		if ($compiledPath) { $this->compiler->setCompiledPath($compiledPath); }
		return $this;
	}

	public function setFilePattern($name, $pattern)
	{
		$fullName = $name . 'FilePattern';
		$this->$fullName = $pattern;
		return $this;
	}

	/**
	 * Options:  type, theme, ...
	 *
	 * @param type $name
	 * @param type $value
	 * @param array $attributes
	 * @param type $options
	 * @return type
	 */
	public function input($name, $label = null, $value = null, $attributes = array(), $options = array())
	{
		extract($options);

		$attributes['name'] = $name;
		$attributes['value'] = $value;
		$attributes['options'] = $options;

		if (empty($attributes['id']))
		{
			$attributes['id'] = $name;
		}

		if ( ! $label)
		{
			$label = ucfirst($name);
		}

		$attributes['label'] = $label;

		if (empty($theme)) { $theme = $this->theme;	$attributes['theme'] = $theme; } //$theme, $type, etc from extract()!
		if (empty($type)) { $type = 'text'; $attributes['type'] = $type; }

		return $this->getTemplate($theme, $type, $attributes);
	}

}
