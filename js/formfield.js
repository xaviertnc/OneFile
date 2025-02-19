/* global F1 */

/* formfield.js */

(function(F1) {

  /**
   * F1 Form Field Base - 14 Nov 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * 
   * @version 2.4 - FT - 18 Jan 2025
   *   - Add isRequired()
   *   - Add getValidations().
   *   - Auto add custom required validation for F1ChecklistField types.
   *   - Update getFieldElement() to handle checkable option groups.
   *   - Update validateCustom(). Refactor and compact.
   *   - Update getValue() to handle option groups.
   *   - Comment out old getFieldElement() and getValue() methods.
   * 
   * @version 3.0 - FT - 19 Feb 2025
   *   - Add optional init() method to constructor. VERY IMPORTANT
   *   - Add fieldElement param to constructor to allow for custom field elements
   *     that are not the "input" like in the case of radio and checkbox groups.
   *   - Change this.type === 'radio' to this.type === 'F1RadioField' in setValue().
   *   - Remove old code.
   *   - Add debug logs.
   *  
   */

  function log(...args) { if (F1.DEBUG > 2) console.log(...args); }


  class FormField {

    constructor(form, input, fieldTypeName, fieldElement) {
      this.form = form;
      this.name = input.name;
      this.type = fieldTypeName || form.getDefaultFieldType(input);
      this.element = fieldElement || this.getFieldElement(input);
      this.inputs = [ input ];
      this.init?.();
      // log('New FormField:', { name: this.name, type: this.type, element: this.element });
    }

    updateValidationUi(valid = false, customValidationMessage = null) {
      const input = this.inputs[0];
      const feedbackElement = document.getElementById(`${this.name}Feedback`);
      const validationMessage = customValidationMessage || input.validationMessage;
      if (feedbackElement) feedbackElement.textContent = !valid ? validationMessage : '';
      this.element.classList.toggle('is-invalid', !valid);
      this.element.classList.toggle('is-valid', valid);
      return feedbackElement;
    }

    clearValidationUi() {
      log('clearValidationUi', this.name);
      this.element.classList.remove('is-valid', 'is-invalid', 'is-modified');
      const feedbackElement = document.getElementById(`${this.name}Feedback`);
      if (feedbackElement) feedbackElement.textContent = '';
    }

    updateModifiedUi(isModified) {
      log('updateModifiedUi', { field: this.name, isModified });
      this.element.classList.toggle('is-modified', isModified);
    }

    getFieldElement(input) { 
      const isCheckableOption = (input.type === 'radio') || (input.type === 'checkbox');
      const isOptionGroup = isCheckableOption && input.form.elements[input.name].length > 1;
      return isOptionGroup ? input.closest('fieldset') || input.parentElement : input;
    }

    getValidations() {
      if (this.type === 'F1ChecklistField' && this.isRequired()) {
        return this.form.addCustomValidation(this.name, (field) => {
          // Custom Required Test (F1ChecklistField)
          const isValid = field.inputs.some(input => input.checked);
          return isValid === true || 'Please select at least one option.';
        });
      }
    }

    validateCustom() {
      const tests = [...(this.form.customValidations[this.type] || []), ...(this.form.customValidations[this.name] || [])];
      if (!tests.length) return true; log('validateCustom', { field: this.name, tests });
      let validationMessage, fail = tests.some(test => (validationMessage = test(this)) !== true);
      this.inputs?.[0]?.setCustomValidity(fail ? validationMessage : '');
      log('validateCustom:', { field: this.name, isValid: !fail, ...(fail && { validationMessage }) });
      return !fail;
    }

    /**
     * checkValidity() will return false if a custom validation message is set,
     * or if the input fails any of the default HTML5 validation rules.
     */
    validateHTML5(input) {
      const isValid = input.checkValidity();
      log('validateHTML5:', { input: input.name, isValid });
      return isValid;
    }

    isModified() {
      log('checkModified', { field: this.name });
      const currentValue = this.getValue();
      const defaultValue = this.defaultValue;
      const isModified = (JSON.stringify(currentValue) !== JSON.stringify(defaultValue));
      log('checkModified', { field: this.name, currentValue, defaultValue, isModified });
      if (this.form.onModified) this.form.onModified(this, isModified);
      return isModified;
    }

    isRequired() {
      return this.inputs[0].required || this.element.hasAttribute('required');
    }

    getValue(bootstrap) {
      let value = null;
      // log('getValue:', { field: this.name, bootstrap });
      if (bootstrap && this.element.hasAttribute('data-value')) value = this.element.dataset.value;
      else if (this.inputs.length > 1) {
        if (this.type === 'F1RadioField') value = this.inputs.find(input => input.checked)?.value || null;
        else value = this.inputs.filter(input => input.checked).map(input => input.value); }
      else { const input = this.inputs[0];
        // log('getValue:', { input, value });
        if (input.type === 'checkbox') value = input.checked;
        else if (input.type === 'radio') value = input.checked ? input.value : null;
        else value = input.value;
      }
      log(`getValue: ${this.name} = "${value}"`);
      return value;
    }

    setValue(value, init = false) {
      // log(`setValue: ${this.name} = "${value}"`, init);
      if (init) this.defaultValue = value;
      if (this.inputs.length > 1) {
        if (this.type === 'F1RadioField') this.inputs.forEach(input => input.checked = input.value === value);
        else this.inputs.forEach(input => input.checked = value.includes(input.value));
      } else { const input = this.inputs[0];
        // log('setValue:', { input: input.name, value });
        if (input.type === 'checkbox') input.checked = value;
        else if (input.type === 'radio') input.checked = input.value === value;
        else if (input.type === 'file') return;
        else input.value = value;
      }
    }

    validate() {
      return this.validateCustom() && this.validateHTML5(this.inputs[0]);
    }

    reset(value) {
      this.clearValidationUi();
      if (typeof value == 'undefined') value = this.defaultValue;
      this.setValue(value, 'init');
    }
    
    clear() {
      this.clearValidationUi();
      this.setValue('', 'init');
    }

    focus() { this.inputs[0] && this.inputs[0].focus(); }

  };

  F1.lib.FormField = FormField;

})(window.F1 = window.F1 || {});  