<?php namespace OneFile;

use Log; // Debug only. Remove!

/**
 * Form Field Model
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 16 Nov 2016
 *
 * @updated: C. Moller - 04 Jan 2017
 *   - Refactor class, simplify structure, consolodate methods, rename, reposition etc.
 *   - Moved Entity-vs-Form Model info to docs/ folder
 *   - Add method comments
 *
 * @updated: C. Moller - 12 Jan 2017
 *   - Added __set() + __get() methods
 *   - Added __props + metas
 *   - Added setMeta, getMeta, setMetas, getMetas, setValue, isEqual, reset methods
 *   - Dropped support for setting props from inspecting the Attrs string.
 *   - Dropped support for formatters.
 *
 * @updated: C. Moller - 12 Feb 2017
 *   - Restore support for formatters
 *   - Refactor / improve validation
 *   - Distinguish between ModelValue, ViewValue and DisplayValue
 *
 * @updated: C. Moller - 24 Nov 2017
 *   - Add using the "field name" as fallback in validation messages if no "field label" is specified!
 */


class FormFieldModel
{
	protected $__props = [
		'name'		 => null,
		'type'		 => null,
		//'lBefore'	 => null,
		'label'		 => null,
		'lAfter'	 => null,
		//'iBefore'	 => null,
		'value'		 => null,
		//'iAfter'	 => null,
		'helptext'	 => null,
		'attrs'		 => null,
		'group'		 => null,
		'nullvalue'	 => null,
		'nulldisp'	 => null,
		'initvalue'	 => null,
		'disabled'	 => false,
		'visible'	 => true,
		'metas'		 => [],
		'errors'	 => [],
		'parsers'	 => [],
		'formatters' => [],
		'validators' => [],
	];


	/**
	 * @param string $fieldName
	 * @param string $fieldType
	 * @param string $fieldLabel
	 * @param string|array $options Can be: Attributes string OR Array of properties
	 * 		e.g. String : 'data-index="1" disabled checked'
	 * 		e.g. String : 'placeholder="Enter Something..." min="0" required'
	 * 		e.g. String : 'style="color:red" class="my-class" role="some-role"'
	 *      e.g  Array  : ['prop1'=>$val1, 'attrs'=>['placeholder="Enter something..." min="0" required']
	 *      e.g  Array  : ['metas'=>['meta1'=>$val, ...], 'prop2'=>$val2, 'errors'=>['err1','err2',...], 'initialValue'=>$initVal]
	 *      e.g  Array  : ['validators'=>[Validate::required(), Validate::email(), ...], 'parsers'=>[new ParseCurrency('R',2,' '), ...]]
	 */
	public function __construct($fieldName, $fieldType = null, $fieldLabel = null, $options = null)
	{
		$this->__props['name'] = $fieldName;
		$this->__props['type'] = $fieldType ? : 'text';
		$this->__props['label'] = is_null($fieldLabel) ? ucfirst($fieldName) : $fieldLabel;
		$this->extendOptions($options);
	}


    public function __set($name, $value)
    {
        $this->__props[$name] = $value;
    }


    public function __get($name)
    {
        if (array_key_exists($name, $this->__props))
        {
            return $this->__props[$name];
        }

        return $this->{$name};
    }


    /**  As of PHP 5.1.0  */
    public function __isset($name)
    {
		//Log::field('FormFieldModel::__isset(), name = ' . $name);
        return isset($this->__props[$name]);
    }


    /**  As of PHP 5.1.0  */
    public function __unset($name)
    {
        unset($this->__props[$name]);
    }


	public function setOption($option, $value)
	{
		//Log::field('FormFieldModel::setOption(), option = ' . $option . ', value = ' . json_encode($value));
		switch ($option)
		{
			case 'value': $this->initValue($value); break;
			default: $this->__props[$option] = $value;
		}

		return $this;
	}


	public function extendOptions($options = null)
	{
		if ( ! $options) return $this;

		if (is_string($options)) { $this->__props['attrs'] = $options; return $this; }

		foreach ($options as $option => $value) { $this->setOption($option, $value); }

		return $this;
	}


	/**
	 * Convert a field value form its VIEW_MODEL_FORMAT to its DATA_MODEL_FORMAT.
	 *
	 * Note: MODEL_VALUE vs. VIEW_VALUE vs. DISPLAY_VALUE
	 *  - MODEL_VALUE can be any data structure in the format it get's saved to DB or State.
	 *  - VIEW_VALUE can be any data structure in a format required by the current VIEW being rendered.
	 * 	- DISPLAY_VALUE is the final string value returned by the VIEW RENDER FUNCTION.
	 *
	 * @param $viewValue E.g. $viewValue = Array of selected items vs. $modelValue = JSON String
	 * @param $nullDispValue E.g. 'none', null, false, ' - Select Item - ', '<i>empty</i>', [], ...
	 *
	 * @return mixed
	 */
	public function parse($viewValue, $nullDispValue = null)
	{
		//Log::field('FormFieldModel::parse(), viewValue = ' . var_export($viewValue, true));

		$nullDispValue = is_null($nullDispValue) ? $this->__props['nulldisp'] : $nullDispValue;

		$modelValue = ($viewValue === $nullDispValue) ? null : $viewValue;

		if (is_null($modelValue) or empty($this->__props['parsers']))
		{
			return $modelValue;
		}

		foreach ($this->__props['parsers'] as $parser)
		{
			//Log::field('FormFieldModel::parse(), modelValue(before) = ' . var_export($modelValue, true));
			$modelValue = $parser($modelValue);
			//Log::field('FormFieldModel::parse(), modelValue(after) = ' . var_export($modelValue, true));
		}

		return $modelValue;
	}


	/**
	 * Convert a field value from its DATA_MODEL_FORMAT into a format suitable for rendering its view.
	 *
	 * @param $modelValue E.g. $modelValue = JSON String vs. $viewValue = Array of selected items
	 * @param $nullDispValue E.g. 'none', null, false, ' - Select Item - ', '<i>empty</i>', [], ...
	 *
	 * @return mixed
	 */
	public function format($modelValue, $nullDispValue = null)
	{
		$viewValue = is_null($modelValue) ? $nullDispValue : $modelValue;

		if ( ! $this->formatters) { return $viewValue; }

		foreach ($this->formatters as $formatter)
		{
			//Log::field('FormFieldModel::format(), viewValue(before) = ' . var_export($viewValue, true));
			$viewValue = $formatter($viewValue);
			//Log::field('FormFieldModel::format(), viewValue(after) = ' . var_export($viewValue, true));
		}

		return $viewValue;
	}


	/**
	 * Run all assigned validators against the field's value or a test value and collect errors.
	 * Promote errors to field errors if not in test mode.
	 * Return all errors instead of just success/fail bool if required.
	 *
	 * @param mixed $auxData Extra info for advanced field validations. e.g. $auxData = $_POST or Form::getFieldValues() to enable cross-field validation
	 * @param boolean $returnErrors Return $errors Array instead of True/False
	 * @param mixed $testValue To test if a field value is valid without changing the current value.
	 * @return boolean|array
	 */
	public function validate($auxData = null, $returnErrors = false, $testValue = '_?_')
	{
		//Log::field('FormFieldModel::validate(), name = ' . $this->name);

		if (empty($this->__props['validators'])) return true;

		$errors = [];

		$isTest = ($testValue !== '_?_');

		foreach ($this->__props['validators'] as $validator)
		{
			$validate = $validator['fn']; $errorTemplate = $validator['tpl'];
			// NM Edit - 24 Nov 2017: Add using field-name as fallback if no field-label is specified!
			// The field label will be set to the field name ONLY if the label-value is set to NULL.
			// Setting label-value = '' gives NO LABEL! But what about validation messages!?
			// Therefore, to address this problem, we check JUST before validation if a label exists.
			// If not, we set the validation message label == the field name
			$result = $validate($isTest ? $testValue : $this->getValue(), ($this->__props['label']?:$this->__props['name']), $auxData, $errorTemplate);
			if (empty($result)) continue;
			$errors[] = $result;
		}

		//Log::field('FormFieldModel::validate() - done, errors = ' . print_r($errors, true) . ', isTest = ' . \Format::yesNo($isTest));

		if ($errors and ! ($isTest or $returnErrors)) $this->__props['errors'] = $errors;

		return $returnErrors ? $errors : empty($errors);
	}


	public function isValid($auxData = null) { return $this->validate($auxData); }
	public function isInvalid($auxData = null) { return ! $this->validate($auxData); }

	public function setValue($modelValue) { $this->__props['value'] = $modelValue; }
	public function initValue($modelValue) { $this->__props['value'] = $modelValue; $this->__props['initvalue'] = $modelValue; }
	public function inputValue($viewValue) { $this->setValue($this->parse($viewValue)); }
	public function setParsers(array $parsers) { $this->__props['parsers'][] = $parsers; return $this; }
	public function setFormatters(array $formatters) { $this->__props['formatters'][] = $formatters; return $this; }
	public function setValidators(array $validators) { $this->__props['validators'][] = $validators; return $this; }
	public function setMeta($metaKey, $metaValue) { $this->__props['metas'][$metaKey] = $metaValue; return $this; }
	public function setMetas($metaValues) { $this->__props['metas'] = $metaValues; return $this; }

	public function addParser($parser) { $this->__props['parsers'][] = $parser; return $this; }
	public function addFormatter($formatter) { $this->__props['formatters'][] = $formatter; return $this; }
	public function addValidator($validateFn, $msgTemplate = null) { $this->__props['validators'][] = ['fn' => $validateFn, 'tpl' => $msgTemplate]; return $this; }
	public function addMeta($metaKey, $metaValue) { $this->__props['metas'][$metaKey] = $metaValue; return $this; }
	public function addError($error) { $this->__props['errors'][] = $error; return $this; }

	public function getParsers() { return $this->__props['parsers']; }
	public function getFormatters() { return $this->__props['formatters']; }
	public function getValidators() { return $this->__props['validators']; }
	public function getValue($default=null) { return is_null($this->__props['value']) ? $default : $this->__props['value']; }
	public function getViewValue($nullDispValue=null) { return $this->format($this->__props['value'], $nullDispValue); }
	public function getInitialValue($default=null) { return is_null($this->__props['initvalue']) ? $default : $this->__props['initvalue']; }
	public function getInitialViewValue($nullDispValue=null) { return $this->format($this->__props['initvalue'], $nullDispValue); }
	public function getFirstError() { return $this->__props['errors'] ? $this->__props['errors'][0]: null; } // Override me if you want to implement this differently.
	public function getMeta($metaKey, $default=null) { return isset($this->__props['metas'][$metaKey]) ?  $this->__props['metas'][$metaKey] : $default; }
	public function getErrors() { return $this->__props['errors']; }
	public function getMetas() { return $this->__props['metas']; }

	public function isEqual($valA, $valB) { return ($valA == $valB); } // Override me.
	public function dirty() { return ! $this->isEqual($this->getValue(), $this->getInitialValue()); }
	public function reset() { $this->setValue($this->getInitialValue()); }
	public function clear() { $this->initValue(null); }
}
