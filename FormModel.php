<?php namespace OneFile;

use Log; // Debug only. Remove!

/**
 * FormModel Class
 *
 * A composite data structure that holds the fields of a database record query,
 * while also having meta-data on how to render each field as an HTML input.
 *
 * Useful features:
 *  1. Keeps information on the TYPE of each input field and its attributes.
 *  2. Stores field error messages and initial values in a SESSION/STATE store to
 *     enable error message display without losing already entered data.
 *  3. Automatically restores the state of the form data if a "saved state" is found.
 *  4. Can assign any number of validators to a field. (e.g. Required + Min length)
 *  5. Can assign any number of value parsers and/or formatters to a field
 *     (e.g. Convert JSON string fields into Arrays and then back to JSON again)
 *  6. Can set a GLOBAL values parser and/or formatter. (e.g. To sanitize values)
 *  7. Can extract a single dataset's values from amongst many other datasets in POST data
 *  8. Can take a DataModel object as an initial value getter. (Needs some config to use)
 *
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 16 Nov 2016
 *
 * @updated: C. Moller - 04 Jan 2017
 *   - Refactor class, simplify structure, consolodate methods, rename, reposition etc.
 *   - Moved Entity-vs-Form Model info to docs/ folder
 *   - Add method comments
 *
 * @updated: C. Moller - 12 Jan 2017
 *   - Removed dispVal() method
 *
 * @updated: C. Moller - 12 Feb 2017
 *   - Add getFieldViewValues, viewValue, validateState
 *   - Refactor / improve / fix validation
 *
 */

class FormModel
{
  protected $__props = [
    'name'      => null,
    'tag'     => null,
    'attrs'     => null,
    'valid'     => null,
    'dfLabel'   => null,
    'dfGroup'   => null,
    'dfOptions'   => null,
    'dfInputType' => null,
    'disabled'    => false,
    'getInputOk'  => false,
    'storeKey'    => '__FORM_STATE__',
    'fieldClass'  => '\OneFile\FormFieldModel',
    'method'    => 'post',
    'fields'    => [],
    'errors'    => [],
  ];


  /**
   * Init
   *
   * @param string $formName Form unique identifier.
   * @param array|string $options If typeof $options == string; $options == $attrs_str; Used like: <form {{ attrs_str }}>
   * @param array $fields An array of FormFieldModel Object instances. Guess the HTML Input Type of each field if $fields == empty.
   * @param array|object $initialFieldValues ARRAY or ENTITY MODEL instance. E.g. from DB::fetch(id)
   * @param array $currentState E.g. $_SESSION['__FORM_STATE__']
   */
  public function __construct($name = null, $options = null, $fields = null, $initialFieldValues = null, $currentState = null)
  {
    $formName = $name ?: 'form';
    $this->__props['name'] = $formName;

    //Log::form('OneFile.FormModel::construct(' . $formName . '), $initialFieldValues = ' . print_r($initialFieldValues?:'none', true)
    //  . ', $currentState = ' . print_r($currentState?:'none', true));

    // Set $options = ['dfGroup' => null, ...] if you want to DISABLE array notation on HTML Input names.
    // e.g. <input name="fieldName"> instead of <input name="dfGroup[fieldName]">
    $this->__props['dfGroup'] = $formName;

    if ($options)
    {
      if (is_string($options))
      {
        $this->__props['attrs'] = $options;
      }
      else
      {
        foreach ($options as $propName => $value) { $this->__props[$propName] = $value; }
      }
    }

    // Automatically add and initialize the form's field objects and values.
    // First try $fields to create form fields. If $fields == empty, estimate the fields using: $initialFieldValues.
    $this->addFields($fields, $initialFieldValues);

    // Get FORMSTATE (working values and errors) from $currentState or SESSION store
    // Do not set initial values, since STATE only holds CHANGED values.
    // FORM FIELDS must be defined for successful STATE load / update!
    if ($this->__props['fields'])
    {
      if (is_null($currentState))
      {
        // NO $currentState, so lets try getting it from SESSION store...
        Log::form('OneFile.FormModel::construct(' . $formName . '), Before loadState()...');
        $this->loadState();
      }
      else
      {
        Log::form('OneFile.FormModel::construct(' . $formName . '), Before setFieldValues()...');
        // We HAVE $currentState! No need to load from SESSION store.
        $this->setState($currentState);
      }
    }
    else
    {
      // Try to build fields from $currentState ... ?
    }
  }


  private function allowClosure($v = null)
  {
    return ($v instanceof \Closure) ? $v() : $v;
  }


  /**
   *
   * Override me!
   *
   * Use your own implementation of session store if it's not $_SESSION
   * NOTE: PHP automatically serializes objects in $_SESSION
   *
   */
  protected function __session_put($key, $value)
  {
    $_SESSION[$key] = $value;
  }


  /**
   *
   * Override me!
   *
   * Use your own implementation of session store if it's not $_SESSION
   * NOTE: PHP automatically unserializes objects in $_SESSION
   *
   */
  protected function __session_get($key, $default)
  {
    return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
  }


  public function hasField($fieldName)
  {
    $fields = $this->__props['fields'];
    return array_key_exists($fieldName, $fields);
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
    return isset($this->__props[$name]);
  }


  /**  As of PHP 5.1.0  */
  public function __unset($name)
  {
      unset($this->__props[$name]);
  }


  public function addGlobalParser($parser)
  {
    foreach ($this->__props['fields'] as $field)
    {
      $field->addParser($parser);
    }

    return $this;
  }


  public function addGlobalFormatter($formatter)
  {
    foreach ($this->__props['fields'] as $field)
    {
      $field->addFormatter($formatter);
    }

    return $this;
  }


  /**
   * Set ALL the form field values.
   * If a value is not provided, use the field's default/null value.
   */
  public function setFieldValues(array $fieldValues, $init = false, $parse = false)
  {
    //Log::form('FormModel::setFieldValues(), $fieldValues = ' . var_export($fieldValues ?: 'none', true));
    //Log::form('FormModel::setFieldValues(), $init = ' . var_export($init, true));
    //Log::form('FormModel::setFieldValues(), $parse = ' . var_export($parse, true));
    foreach ($this->__props['fields'] as $fieldName => $field)
    {
      $value = isset($fieldValues[$fieldName]) ? $fieldValues[$fieldName] : $field->nullvalue;

      if ($parse)
      {
        //Log::form('FormModel::setFieldValues('.$fieldName.'), Call: field->inputValue(' . var_export($value, true) . ')');
        $field->inputValue($value);
      }
      elseif ($init)
      {
        //Log::form('FormModel::setFieldValues('.$fieldName.'), Call: field->initValue(' . var_export($value, true) . ')');
        $field->initValue($value);
      }
      else
      {
        $field->setValue($value);
      }
    }

    return $this;
  }

  /**
   * Only set the field values listed in the provided valueset.
   * If no matching field exists, just ignore!
   */
  public function updateFieldValues(array $fieldValues, $parse = false)
  {
    Log::form('FormModel::updateFieldValues(), $fieldValues = ' . var_export($fieldValues ?: 'none', true));
    foreach ($fieldValues as $fieldName => $value)
    {
      $field = $this->field($fieldName);
      
      if ( ! $field) { continue; }

      if ($parse)
      {
        $field->inputValue($value);
      }
      else
      {
        $field->setValue($value);
      }
    }

    return $this;
  }


  public function initFieldValues(array $fieldValues)
  {
    return $this->setFieldValues($fieldValues, true);
  }


  /**
   * $data is assumed to be an assoc array of key=>value pairs.
   *
   * TODO: Allow $data to be a pure values array without fieldname keys.
   *
   */
  public function getInputData($data = null) //, $extract_group = false
  {
    Log::form('FormModel::getInputData(), data-group: ' . $this->__props['dfGroup']);
    //Log::form('FormModel::getInputData(), data: ' . print_r($data, true));

    if (isset($data))
    {
      $this->__props['getInputOk'] = true;
    }
    else
    {
      $data = [];
      $this->__props['getInputOk'] = false;
    }

    // Assign non-array $data to the value of the FIRST field (and propably the only field)
    if ( ! is_array($data))
    {
      if (count($this->__props['fields']))
      {
        $this->getFields(0)->inputValue($data);
        return $this;
      }

      $this->__props['getInputOk'] = false;

      return $this;
    }

    // dfGroup === formName (default)
    // dfGroup => default input group to extract
    // If $data is an array AND we have dfGroup set, we assume $fieldValues == $data[dfGroup]
    // where $data and we extract only the dfGroup key from $data as inputs.
    elseif ($group = $this->__props['dfGroup'])
    {
      if (isset($data[$group]))
      {
        $groupData = $data[$group];

        if ( ! is_array($groupData))
        {
          if (count($this->__props['fields']))
          {
            $this->getFields(0)->inputValue($groupData);
            return $this;
          }

          $this->__props['getInputOk'] = false;

          return $this;
        }

        return $this->setFieldValues($groupData, false, true);
      }

      // Added: NM - 28 Nov 2017
      // We get here if we received NO POST DATA, but we DO have "dfGroup" set.
      // i.e. isset($data[$group]) == false
      elseif (count($this->getFields()))
      {
        // We have some fields but NO input. Therefore, set all field values to: NO VALUE!
        $this->clear();
      }

      $this->__props['getInputOk'] = false;

      return $this;
    }

    // dfGroup == NULL
    // If dfGroup === NULL, it means NO grouping. So drop extract and just take ALL INPUT DATA.
    return $this->setFieldValues($data, false, true);
  }


  public function getFieldValues()
  {
    $fieldValues = [];
    foreach ($this->__props['fields'] as $fieldName => $field)
    {
      $fieldValues[$fieldName] = $field->getValue();
    }
    return $fieldValues;
  }


  public function getFieldViewValues()
  {
    $fieldViewValues = [];
    foreach ($this->__props['fields'] as $fieldName => $field)
    {
      $fieldViewValues[$fieldName] = $field->getViewValue();
    }
    return $fieldViewValues;
  }


  /**
   * Examples:
   * $form->getFields(0) // First field
   * $form->getFields(['except' => ['id']])
   * $form->getFields(['only' => ['name', 'age']])
   * $form->getFields(['map' => ['id'=>'order_id']])
   * $form->getFields(['only' => ['orderno', 'firstname'], 'map' => ['orderno'=>'Order No.', 'firstname'=>'Name:']])
   */
  public function getFields($options = null)
  {
    if ($options and is_array($options))
    {
      $fields = $this->__props['fields'];
      if (isset($options['except'])) $fields = array_diff_key($fields, array_flip($options['except']?:[]));
      if (isset($options['only'])) $fields = array_intersect_key($fields, array_flip($options['only']?:[]));
      if (isset($options['map']))
      {
        $map = is_array($options['map']) ? $options['map'] : [$options['map']];
        $fields = array_map(function($field) use ($map) {
          if(isset($map[$field->name])) $field->name = $map[$field->name];
          return $field;
        }, $fields);
      }
      return $fields;
    }
    elseif (is_numeric($options))
    {
      return array_values($this->__props['fields'])[$options];
    }
    return $this->__props['fields'];
  }


  public function getFieldsByType($type)
  {
    return array_filter($this->__props['fields'], function($field) use ($type) { if ($field->type == $type) return $field; });
  }


  public function isDirty()
  {
    foreach ($this->__props['fields'] as $field)
    {
      if ($field->dirty()) { return true; }
    }

    return false;
  }


  public function reset()
  {
    foreach ($this->__props['fields'] as $field)
    {
      $field->reset();
    }
  }


  public function clear()
  {
    foreach ($this->__props['fields'] as $field)
    {
      $field->clear();
    }
  }


  public function clearErrors()
  {
    $this->__props['errors'] = [];
    return $this;
  }

  
  public function setErrors(array $errors, $fieldName = null)
  {
    //Log::form('OneFile.FormModel::setErrors(' . $this->__props['name'] . '), $fieldName = ' . $fieldName . ', $errors = ' . json_encode($errors?:'none'));

    if (is_null($fieldName))
    {
      $this->__props['errors'] = $errors;
    }
    else
    {
      $this->__props['errors'][$fieldName] = $errors;
    }
    return $this;
  }


  public function addError($fieldName, $errorMessage)
  {
    $errors = $this->__props['errors'];
    $field = $this->field($fieldName);
    $field->addError($errorMessage);
    $this->__props['errors'][$fieldName] = $field->getErrors();
    return $this;
  }


  public function getErrors($fieldName = null, $noErrors = [])
  {
    if ($fieldName)
    {
      $errors = isset($this->__props['errors'][$fieldName]) ? $this->__props['errors'][$fieldName] : $noErrors;
    }
    else
    {
      $errors = $this->__props['errors'] ?: $noErrors;
    }

    //Log::form('OneFile.FormModel::getErrors(' . $this->__props['name'] . '), $fieldName = ' . $fieldName . ', $errors = ' . json_encode($errors?:'none'));

    return $errors;
  }


  private function array_flatten(array $array) {
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
  }


  public function getFirstError($fieldName = null, $noErrors = null)
  {
    if ($fieldName)
    {
      $fieldErrors = $this->getErrors($fieldName);
      //Log::form('OneFile.FormModel::getFirstError(), $fieldName = ' . $fieldName . ', $errors = ' . json_encode($fieldErrors?:'none'));
      return $fieldErrors ? $fieldErrors[0] : $noErrors;
    }
    $errors = $this->array_flatten($this->getErrors());
    $firstError = isset($errors[0]) ? $errors[0] : null;
    return $firstError;
  }


  /**
   * Adds a validation function to any fields listed in the $fieldnames array.
   *
   * @param array $fieldnames
   * @param Closure $validateFn
   * @param string $messageTemplate
   * @param mixed $aux Any extra info required for this specific validation's case
   *
   * @return FormModel
   *
   */
  public function addValidation($fieldnames, $validateFn, $messageTemplate = null, $aux = null)
  {
    if ( ! is_array($fieldnames)) $fieldnames = [$fieldnames];

    foreach ($fieldnames as $fieldname)
    {
      $field = $this->field($fieldname);
      if ($field and $field->name !== 'error') { $field->addValidator($validateFn, $messageTemplate, $aux); }
    }

    return $this;
  }


  /**
   * Validate the entire state of the form.
   * The result of this validation belongs to the ENITRE FORM and not any specific field.
   *
   * @param Closure $validateFn
   * @param mixed $auxData Extra info for advanced validations.
   * @param boolean $returnErrors Return $errors Array instead of True/False
   * @param mixed $testState To test if a state is valid without changing the current state.
   * @param string $msgTemplate A sprintf decorator string template for feedback/error messages
   *
   * @return boolean|array
   *
   * TODO: Add __params['state-validators'] and itterate...
   */
  public function validateState($validateFn, $auxData = null, $returnErrors = false, $testState = '_?_', $msgTemplate = null)
  {
    $isTest = ($testState !== '_?_');
    $errors = $validateFn($this, $auxData, $msgTemplate, $testState);
    if ($errors and ! ($isTest or $returnErrors)) $this->__props['errors']['form'] = $errors;
    return $returnErrors ? $errors : empty($errors);
  }


  /**
   *
   * Validate only a single field's value.
   *
   * If $testValue is provided, it will be used in-place of the current field value and
   * no error messages will be added to the form or field model.
   *
   * @param string $fieldName
   * @param boolean $returnErrors Return $errors Array instead of True/False
   * @param mixed $testValue To test if a field value is valid without changing the current value.
   * @param mixed $aux Any extra/custom info required for this specific validation.
   *
   * @return boolean|array
   */
  public function validateField($fieldName, $returnErrors = false, $testValue = '_?_', $aux = null)
  {
    $field = $this->field($fieldName);
    if ($field->name !== 'error')
    {
      $isTest = ($testValue !== '_?_');
      $result = $field->validate($this, $returnErrors, $testValue, $aux);
      if ($returnErrors) { return $result; }
      if ($result) { return true; }
      $this->__props['errors'][$fieldName] = $field->getErrors();
      return false;
    }
    // Pass validation of an non-existing field.. ?
    return $returnErrors ? [] : true;
  }


  /**
   * Validate all the fields in one request.
   *
   * @param array $formValues Used when a field requires cross-field validation
   * @return boolean
   */
  public function validate($returnErrors = false)
  {
    $errors = [];

    foreach ($this->__props['fields'] as $fieldName => $field)
    {
      $result = $field->validate($this, $returnErrors);

      if ($returnErrors)
      {
        if ($result) { $errors[$fieldName] = $result; }
        continue;
      }

      if ( ! $result) { $this->__props['errors'][$fieldName] = $field->getErrors(); }
    }

    return $returnErrors ? $errors : empty($this->__props['errors']);
  }


  public function isValid()
  {
    return $this->validate();
  }


  public function isInvalid()
  {
    return ( ! $this->validate());
  }


  /**
   *
   * @param object $field FormFieldModel Object/Model
   * @param string $group Field group
   * @param mixed  $value Field value
   *
   * @return FormModel
   *
   */
  public function addField($field, $group = null, $initialValue = null)
  {
    if ($group) { $field->setOption('group', $group); }
    if ($initialValue) { $field->initValue($initialValue); }
    $this->__props['fields'][$field->name] = $field;
    return $this;
  }


  /**
   * Automatically adds and initializes the form's field objects and values.
   *
   * @param array $fields An array of FormFieldModel Object instances. Guess the HTML Input Type of each field if $fields == null.
   * @param array|object $initialFieldValues This param should be an ARRAY of fieldname=>value pairs or an ENTITY/DATA MODEL instance.
   *
   * @return FormModel
   *
   */
  public function addFields($fields = null, $initialFieldValues = null)
  {
    //Log::form('OneFile.FormModel::addFields(), $fields = ' . json_encode($fields) . ', $initialFieldValues = ' . json_encode($initialFieldValues)); //var_export($initialFieldValues?:'none', true));

    if ($fields and is_array($fields))
    {
      foreach ($fields as $field)
      {
        if (is_null($field->group))
        {
          $field->group = $this->dfGroup;
        }
        $this->__props['fields'][$field->name] = $field;
      }

      if ($initialFieldValues)
      {
        if (is_object($initialFieldValues))
        {
          // $initialFieldValues == DataModel
          $this->initFieldValues($initialFieldValues->getData());
        }
        else
        {
          // $initialFieldValues == Array
          $this->initFieldValues($initialFieldValues);
        }
      }
    }
    else
    {
      $dfLabel = $this->__props['dfLabel'];
      $dfOptions = $this->__props['dfOptions'];
      $fieldClass = $this->__props['fieldClass'];

      if ($initialFieldValues and is_object($initialFieldValues)) // E.g. KD\Models\DataModel
      {
        $model = $initialFieldValues;
        // Since we have a MODEL to work with, we can get the
        // FIELD TYPES from the model's column definitions!
        foreach ($model->getData() as $fieldName => $value)
        {
          $field = new $fieldClass($fieldName, $model->getInputType($fieldName), $dfLabel ? $this->allowClosure($dfLabel) : $fieldName, $dfOptions);
          $this->addField($field, $this->__props['dfGroup'], $value);
        }
      }
      else
      {
        // Since we have only have an array of FIELDNAMES and VALUES, we can't tell what input type to use for each field,
        // so we revert to the FORM DEFAULT INPUT TYPE.
        $dfInputType = $this->__props['dfInputType'] ?: 'text';
        foreach ($initialFieldValues?:[] as $fieldName => $value)
        {
          $field = new $fieldClass($fieldName, $dfInputType, $dfLabel ? $this->allowClosure($dfLabel) : $fieldName, $dfOptions);
          $this->addField($field, $this->__props['dfGroup'], $value);
        }
      }
    }

    return $this;
  }


  /**
   * Get a formfield object by name.
   *  - Short for getField()
   *
   * @param string $fieldName
   *
   * @return FormFieldModel
   *
   */
  public function field($fieldName)
  {
    if ( ! $this->__props['fields'])
    {
      throw new \Exception('No form fields defined!');
    }

    if (isset($this->__props['fields'][$fieldName]))
    {
      return $this->__props['fields'][$fieldName];
    }
    else
    {
      $fieldClass = isset($this->__props['fields'][0]) ? get_class($this->__props['fields'][0]) : $this->__props['fieldClass'];
      Log::form('OneFile.FormModel::field(), ERROR: Unknown field: ' . $fieldName . ', Return error field of class: ' . $fieldClass);
      // REMEMBER: We assume that the FormFieldModel class implements our expected Interface here!
      return new $fieldClass('error', 'static', 'Error:', ['value' => 'Field '. $fieldName . ' Not Found!']);
    }
  }


  public function initField($fieldName, $value)
  {
    $this->field($fieldName)->initValue($value);
    return $this;
  }


  /**
   * Get a form field modelValue instead of the entire field object.
   *  - Short for getFieldValue()
   *
   * @param  string $fieldName    Form field name
   * @param  mixed  $nullDispValue  Default value to return if form-field-model-value === null
   *
   * @return mixed Returns the form field's modelvalue.
   *
   */
  public function val($fieldName, $nullDispValue = null)
  {
    $value = $this->field($fieldName)->getValue();
    return is_null($value) ? $nullDispValue : $value;
  }


  /**
   * Get a form field viewValue instead of the entire field object.
   *  - Short for getFieldViewValue()
   *
   * @param  string $fieldName    Form field name
   * @param  mixed  $nullDispValue  Default value to return if form-field-model-value === null
   *
   * @return mixed Returns the form field's modelvalue.
   *
   */
  public function viewValue($fieldName, $nullDispValue = null)
  {
    $value = $this->field($fieldName)->getViewValue();
    return is_null($value) ? $nullDispValue : $value;
  }


  public function setStoreKey($storeKey)
  {
    $this->__props['storeKey'] = $storeKey;
    return $this;
  }


  public function getStoreKey()
  {
    return $this->__props['storeKey'];
  }


  /**
   * Override me.
   *
   * @param array $state e.g. $state = [ 'fields' => (array) $formFieldValues, 'errors' => (array) $formErrors ]
   * @return FormModel
   */
  public function setState(array $state)
  {
    $formFieldValues = isset($state['fields']) ? $state['fields'] : [];
    $formErrors = isset($state['errors']) ? $state['errors'] : [];

    if ($formFieldValues)
    {
      $this->setFieldValues($formFieldValues);  // Only set values! Don't set initialvalues +
    }

    $this->setErrors($formErrors);

    return $this;
  }


  /**
   * NB: Form fields must be defined BEFORE loading the state!
   *
   * Gets the flashed inputs values and error messages from the application state store.
   * Does not set initial values, since STATE only holds CHANGED values.
   *
   * @param string $customStoreKey
   * @return FormModel
   */
  public function loadState($customStoreKey = null)
  {
    $storeKey = $customStoreKey ?: $this->getStoreKey();
    $state = $this->__session_get($storeKey, null);
    return is_null($state) ? $this : $this->setState($state);
  }


  /**
   * Override me.
   *
   */
  public function compileState()
  {
    $formFieldValues = $this->getFieldValues();
    $formErrors = $this->getErrors();
    return ['fields' => $formFieldValues, 'errors' => $formErrors, 'tag' => $this->__props['tag']];
  }


  /**
   * NOTE: If $state == empty, $state defaults to: [ 'fields' => $this->getFieldValues(), 'errors' => $this->getErrors() ]
   *
   * @param string $customStoreKey
   * @param array $state e.g. $state = [ 'fields' => (array) $formFieldValues, 'errors' => (array) $formErrors ]
   * @return FormModel
   */
  public function saveState($customStoreKey = null, $state = null)
  {
    $storeKey = $customStoreKey ?: $this->getStoreKey();
    if ( ! is_array($state)) $state = $this->compileState();
    $this->__session_put($storeKey, $state);
    return $this;
  }


  /**
   * Override me.
   *
   * Use your own flash-key-prefix or state / flash implementation.
   *
   * @param string $customStoreKey
   * @param array $state e.g. $state = [ 'fields' => (array) $formFieldValues, 'errors' => (array) $formErrors ]
   * @return FormModel
   */
  public function flashState($customStoreKey = null, $state = null)
  {
    $storeKey = $customStoreKey ?: $this->getStoreKey();
    return $this->saveState("flash.$storeKey", $state);
  }


  /**
   * Override me!
   *
   * Hook to run before redirecting back to self to show errors / messages.
   *
   */
  public function onRedirectToSelf()
  {
    $this->flashState();
  }
}
