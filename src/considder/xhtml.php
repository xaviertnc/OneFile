<?php namespace OneFile;

/**
 * By C. Moller - 21 Apr 2014
 */

abstract class xHtml
{
	/**
	 *
	 * @var stdClass
	 */
	public $model;

	/**
	 * null = basic, bootstrap3, etc
	 * @var string 
	 */
	public $theme = 'plain';
	
	/**
	 * config scripts required
	 * @var array
	 */
	public $scripts = array();
	
	/**
	 * config scripts required
	 * @var array
	 */
	public $styles = array();
	
	/**
	 *
	 * @var array
	 */
	public $templates = array(
		
		'plain' => array(
			'wrapper'	=> '<div%s>%s</div>',
			'has-error' => ' class="error"',
			'label'		=> '<label for="%s">%s</label>',
			'error'		=> '<p class="error">%s</p>',
			'help'		=> '<p class="help">%s</p>',
//			'class'     => function(&$attr) { return ''; },
		),
		
		'bootstrap3' => array(
			'wrapper'	=> '<div class="form-group%s">%s</div>',
			'has-error' => ' has-error',
			'label'		=> '<label for="%s" class="control-label">%s</label>',
			'error'		=> '<p class="error-block text-danger">%s</p>',
			'help'		=> '<p class="help-block text-info">%s</p>',
//			'class'		=> function(&$attr) { if(!empty($attr['class'])) return $attr['class'].' form-control'; else return 'form-control'; },
		),
	);
	
	/**
	 * 
	 * @param type $theme
	 * @param type $model
	 */
	public function __construct($theme = null, $model = null)
	{
		$this->theme = $theme;
		$this->model = $model;
	}
	
	/**
	 * Get/Hydrate the specified field value from DB , FLASH , POST , GET , DEFAULT depending on the type of request
	 * IMPLEMENT this method with framework specific code.
	 * 
	 * @param string $fieldname
	 * @param mixed $default
	 * @param object $model
	 * @return mixed
	 */
	protected abstract function _hydrate($fieldname, $default = null, $model = null);
	
	/**
	 * 
	 * @param type $attributes
	 * @param type $options
	 */
	protected function _input_basics(&$attributes, &$options)
	{
		$name = $attributes['name'];
		
		$model = isset($options['model'])?$options['model']:$this->model;
		
		$attributes['value'] = isset($attributes['value'])?
			$this->_hydrate($name, $attributes['value'], $model):
			$this->_hydrate($name, null, $model);

		$options['error'] = isset($options['error'])?
			$this->_error($name, $options['error'], $model):
			$this->_error($name, null, $model);
		
		if(empty($attributes['id']))
			$attributes['id'] = $name;
			
		if(is_null($options['label']))
			$options['label'] = ucfirst($attributes['id']);
				
		if(empty($options['help']))
			$options['help'] = null;		
	}
	
	/**
	 * 
	 * @param type $input_html
	 * @param type $options
	 * @param type $tpl
	 * @return type
	 */
	protected function _input_wrapper($input_html, &$attributes, &$options)
	{
		$templates = &$this->templates[$this->theme];
		
		$label = $options['label']?sprintf($templates['label'], $attributes['id'], $options['label']):'';
		$error = $options['error']?sprintf($templates['error'], $options['error']):'';
		$help = $options['help']?sprintf($templates['help'], $options['help']):'';
		
		$has_error = $error?$templates['has-error']:'';
		
		return sprintf($templates['wrapper'], $has_error, $label.$input_html.$error.$help) . PHP_EOL;
	}
	
	/**
	 * 
	 * @param type $data
	 * @param type $selected
	 * @return string
	 */
	protected function _select_options(&$data, $selected)
	{
		if(!$data) return;
		
		$html = '';
		
		foreach($data as $option)
		{
			if(is_object($option))
			{
				$id = $option->name;
				$text = $option->text;
			}
			elseif(is_array($option))
			{
				$id = $option[0];
				$text = $option[1];
			}
			else
			{
				$id = $option;
				$text = $option;
			}
			
			if($id == $selected)
				$html .= '<option value="' . $id . '" selected>' . $text . '</option>';
			else
				$html .= '<option value="' . $id . '">' . $text . '</option>';
		}
		
		return $html;
	}
	
	/**
	 * Get the error message(s) that need to be displayed for the specified field. (if any)
	 * IMPLEMENT! this method with framework specific code.
	 * 
	 * @param string $fieldname
	 * @param string $default
	 * @param object $model
	 * @return string
	 */
	protected abstract function _error($fieldname, $default = null, $model = null);	
	
	/**
	 * Taken From Laravel FormBuilder
	 * 
	 * @param type $key
	 * @param type $value
	 * @return type
	 */
	protected function _attr($key, $value)
	{
		if(is_numeric($key))
			$key = $value;

		if(!is_null($value))
			return $key.'="'.$this->encode($value).'"';
	}
	
	/**
	 * Taken From Laravel FormBuilder
	 * 
	 * @param type $attributes
	 * @return type
	 */
	public function attributes($attributes)
	{
		$html = array();

		// For numeric keys we will assume that the key and the value are the same
		// as this will convert HTML attributes such as "required" to a correct
		// form like required="required" instead of using incorrect numerics.
		foreach((array) $attributes as $key => $value)
		{
			$element = $this->_attr($key, $value);

			if(!is_null($element))
				$html[] = $element;
		}

		return count($html) > 0 ? ' '.implode(' ', $html) : '';
	}
	
	/**
	 * IMPLEMENT! this function with framework specific code to modify any href into a full url or relative uri
	 * "href" can be any type of string that references an internet resource. Use this method to convert your
	 * references to the correct format.
	 * 
	 * @param string $href
	 * @param boolean $secure
	 * @param boolean $rel
	 * @return string
	 */
	public abstract function href($href, $secure = false, $rel = true);

	/**
	 * IMPLEMENT! this function with framework specific code to change any route reference into a full url or relative uri
	 * 
	 * @param string $route
	 * @param boolean $secure
	 * @param boolean $rel
	 * @return string
	 */
	public abstract function route($route, $secure = false, $rel = true);
	
	/**
	 * 
	 * @param type $text
	 * @return type
	 */
	public function encode($text)
	{
		return htmlentities($text, ENT_QUOTES | ENT_IGNORE, 'UTF-8', false);
	}
	
	/**
	 * Taken From Laravel FormBuilder
	 * Obfuscate a string to prevent spam-bots from sniffing it.
	 * 
	 * @param type $value
	 * @return type
	 */
	public function obfuscate($value)
	{
		$safe = '';

		foreach(str_split($value) as $letter)
		{
			if(ord($letter) > 128) return $letter;

			// To properly obfuscate the value, we will randomly convert each letter to
			// its entity or hexadecimal representation, keeping a bot from sniffing
			// the randomly obfuscated letters out of the string on the responses.
			switch(rand(1, 3))
			{
				case 1:	$safe .= '&#'.ord($letter).';'; break;
				case 2:	$safe .= '&#x'.dechex(ord($letter)).';'; break;
				case 3:	$safe .= $letter;
			}
		}

		return $safe;
	}
	
	/**
	 * 
	 * @param type $name
	 * @param type $label
	 * @param type $attributes
	 * @param type $options
	 * @return type
	 */
	public function input($name, $label = null, &$attributes = array(), &$options = array())
	{		
		$attributes['name'] = $name;
		$options['label'] = $label;
		
		$this->_input_basics($attributes, $options);
		
//		$att
		
		return $this->_input_wrapper('<input' . $this->attributes($attributes) . '>', $attributes, $options);
	}
	
	/**
	 * Note: Use CSS to set all text-area labels to vertical-align:top! textarea:prev {}
	 * 
	 * @param type $name
	 * @param type $label
	 * @param type $attributes
	 * @param type $options
	 * @return type
	 */
	public function textarea($name, $label = null, $attributes = array(), $options = array())
	{		
		$attributes['name'] = $name;
		$options['label'] = $label;
		
		$this->_input_basics($attributes, $options);
		
		$text = $attributes['value'];
		
		unset($attributes['value']);	
		
		return $this->_input_wrapper('<textarea' . $this->attributes($attributes) . '>' . $text . '</textarea>',
			$attributes, $options);
	}
	
	/**
	 * 
	 * @param type $name
	 * @param type $label
	 * @param type $data
	 * @param type $attributes
	 * @param type $options
	 * @return type
	 */
	public function select($name, $label = null, &$data = array(), &$attributes = array(), &$options = array())
	{		
		$attributes['name'] = $name;
		$options['label'] = $label;
		
		$this->_input_basics($attributes, $options);
		
		$value = $attributes['value'];
		
		unset($attributes['value']);	

		return $this->_input_wrapper('<select'. $this->attributes($attributes) . '>' . 
			$this->_select_options($data, $value) . '</select>', $attributes, $options);		
	}
	
	/**
	 * 
	 * @param type $name
	 * @param type $attributes
	 * @param type $options
	 * @return type
	 */
	public function hidden($name, $attributes = array(), $options = array())
	{
		$attributes['type'] = 'hidden';
		return $this->input($name, '', $attributes, $options);
	}

	
	public function text($name, $label = null, $attributes = array(), $options = array())
	{
		$attributes['type'] = 'text';		
		return $this->input($name, $label, $attributes, $options);
	}
	
	
	public function number($name, $label = null, $attributes = array(), $options = array())
	{
		$attributes['type'] = 'number';		
		return $this->input($name, $label, $attributes, $options);
	}
	
	
	public function decimal($name, $label = null, $attributes = array(), $options = array())
	{
		$attributes['type'] = 'decimal';		
		return $this->input($name, $label, $attributes, $options);
	}
	
	
	public function email($name, $label = null, $attributes = array(), $options = array())
	{
		$attributes['type'] = 'email';
		return $this->input($name, $label, $attributes, $options);
	}
	
	
	public function date($name, $label = null, $attributes = array(), $options = array())
	{
		$attributes['type'] = 'date';
		return $this->input($name, $label, $attributes, $options);
	}
	
	
	public function dropdown($name, $label = null, $data = array(), $attributes = array(), $options = array())
	{
		return $this->select($name, $label, $data, $attributes, $options);
	}
	
	/**
	 * Extend this class and add your own elements if required!
	 */
}
