<?php namespace OneFile;

/**
 * Concept:
 * Make Template Files for Each Html Control / Component
 * 
 * Example: HTML::dropdown(name, data, options):
 *  1. Find dropdown template based on NAME + THEME
 *  2. Compile Template
 *  3. Add Compiled Template to a Collection File if not specified to be stand alone. (I.e. Most common templates are in one file!) Big ones, stand alone.
 *  4. Bind template with model and POST depending on parameters supplied and form state etc.
 *  5. Render template!
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 8 June 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 * 
 */

class Html
{
	protected $theme;
	
	protected $templatesPath;
	
	protected $templateFilePattern = '%s/%s.tpl';
	
	protected $compiledFilePattern = 'chtml_%s_%s.php';

	protected $manifestFilePattern = '%s_manifest.php';
	
	protected $compiledPath;
	
	protected $compiler;
	
	protected $manifests;
	
	/**
	 * A memory cache / array of Compiled Components indexed
	 * by their types for the specific theme and incrementally 
	 * filled from file cache as we request components to render.
	 * 
	 * But we want to Mix themes at times!?  Make sure we also catagorise according to theme
	 * 
	 * @var array
	 */
	protected $components;
	
	/**
	 * 
	 * @param string $theme
	 * @param string $templatesPath
	 * @param string $compiledPath
	 */
	public function __construct($theme, $templatesPath, $compiledPath)
	{
		$this->theme = $theme;
		$this->templatesPath = $templatesPath;
		$this->compiledPath = $compiledPath;
	}
	
	protected function getTemplateFilePath($theme, $type)
	{
		return $this->templatesPath . '/' . sprintf($this->templateFilePattern, $theme, $type);
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
			return $this->components;
		}

		if (isset($this->components[$key]))
		{
			return $this->components[$key];
		}

		$array = & $this->components;

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

	protected function compileTemplate($theme, $templateFilePath)
	{
//		var_dump($templateFilePath);
		
		if ( ! file_exists($templateFilePath))
		{
			return false;
		}

		$matches = array();
		
		$contents = file_get_contents($templateFilePath);
		
//		var_dump($contents);
		
		$pattern = '/(?<!\w)\s*@component\s*\((.*)\).*[\n\r]*([\s\S]*?)\s*@endcomp/';

		preg_match_all($pattern, $contents, $matches);

		if ( ! $matches or ! $matches[0])
		{
			return false;
		}

		$component_types = array();
				
		foreach ($matches[1] as $componenttype_match_raw)
		{
			$component_types[] = trim($componenttype_match_raw, "'\""); //Removes quotes!
		}

		$compiled_stack = array();
		
		foreach ($matches[2] as $i => $component)
		{
			$compiled = $this->compiler->compileString($component);

			$type = $component_types[$i];
			
			$compiled_stack[$type] = $compiled;
			
			$this->components[$theme][$type] = $compiled;
		}
		
		$compiledFilePath = $this->getCompiledFilePath($theme, $component_types[0]);
		
		file_put_contents($compiledFilePath, '<?php return ' . var_export($compiled_stack, true) . ';');

		$manifest_entries = $this->manifests[$theme];
		
		foreach($component_types as $type)
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
	protected function renderComponent($compiledTemplateString, $attributes)
	{
		extract($attributes);

		ob_start();

		eval(" ?>" . $compiledTemplateString . "<?php ");
		
		return ob_get_clean();
	}
	
	protected function updateComponents($theme, $compiledFilePath)
	{
		$componentsToAdd = include($compiledFilePath);
		
		foreach($componentsToAdd as $type => $component)
		{
			$this->components[$theme][$type] = $component;
		}
	}
	
	protected function loadComponent($theme, $type)
	{
		//Ceck Compiled Components List
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
			$this->updateComponents($theme, $manifest[$type]);
			
			return $this->getCached("$theme.$type");
		}
		
		if ($this->compileTemplate($theme, $this->getTemplateFilePath($theme, $type)))
		{
			return $this->getCached("$theme.$type");
		}
	}
	
	protected function getComponent($theme, $type, $attributes)
	{
		//Check in memory
		$component = $this->getCached("$theme.$type");
		
		if( ! $component)
		{
			$component = $this->loadComponent($theme, $type);
		}
		
		if ($component)
		{
			return $this->renderComponent($component, $attributes);
		}
		
		return "Error: Failed to Find Component $theme:$type!";
	}

	public function setCompiler($compiler)
	{
		$this->compiler = $compiler;
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
		
		return $this->getComponent($theme, $type, $attributes);
	}
		
}