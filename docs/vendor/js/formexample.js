

class FormHandler {
    constructor(formId) {
      this.form = document.getElementById(formId);
      this.formElements = Array.from(this.form.elements);
      this.customFieldTypes = {}; // Assuming customFieldTypes is empty for now
    }
  
    validatable(el) {
      return el.form && el.name && (
        (el.tagName === 'INPUT' && el.type !== 'submit' && el.type !== 'reset') ||
        el.tagName === 'SELECT' ||
        el.tagName === 'TEXTAREA'
      );
    }

    setValues(values) {
      console.log('setValues', values);
      Object.keys(values).forEach(name => {
          if (this.fields[name]) this.fields[name].value = values[name];
      });
  }
  
    getInputCustomType(input) {
      // Define custom logic for determining input type, if any. For simplicity, return null.
      return null;
    }
  
    getFields() {
      const fields = {};
      this.formElements.filter(this.validatable).forEach(input => {
        if (fields[input.name]) return fields[input.name].inputs.push(input);
        const fieldTypeName = this.getInputCustomType(input) || input.type;
        const FieldType = this.customFieldTypes[fieldTypeName] || FormField;
        const field = new FieldType(this, input);
        console.log('getFields:', input.name, field);
        fields[field.name] = field;
      });
      return fields;
    }
  }
  
  class FormField {
    constructor(formHandler, input) {
      this.formHandler = formHandler;
      this.input = input;
      this.name = input.name;
      this.inputs = [input];
    }
  }
  
  class Form {
    constructor(formElement) {
      this.formElement = formElement;
      this.fields = this.initializeFields();
      this.stopOnInvalid = true;
      this.formElement.addEventListener('submit', this.handleSubmit.bind(this));
    }
  
    initializeFields() {
      const fields = {};
      this.formElement.querySelectorAll('input, select, textarea').forEach(input => {
        fields[input.name] = {
          element: input,
          name: input.name,
          validate: () => input.checkValidity() && input.value.trim() !== '',
          updateValidationUi: (isValid) => {
            input.classList.toggle('invalid', !isValid);
            input.nextElementSibling.textContent = isValid ? '' : 'This field is required.';
          },
          focus: () => input.focus(),
        };
      });
      return fields;
    }
  
    validateOnSubmit() {
      return true;
    }
  
    handleSubmit(e) {
      e.preventDefault();
      console.log('handleSubmit', { form: this, event: e });
  
      let firstInvalidField = null, formValid = true;
      if (this.validateOnSubmit()) {
        for (const field of Object.values(this.fields)) {
          const isValid = field.validate();
          console.log('submitting field', { field: field.name, isValid });
          field.updateValidationUi(isValid);
          if (isValid) continue;
          formValid = false;
          if (!firstInvalidField) firstInvalidField = field;
          if (this.stopOnInvalid) break;
        }
      }
      if (!formValid) {
        firstInvalidField && this.gotoField(firstInvalidField);
        return;
      }
      
      this.submitForm();
    }
  
    gotoField(field) {
      console.log('goto field', field);
      field.element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      field.focus();
    }
  
    submitForm() {
      // Handle form submission, e.g., send data via Ajax
      console.log('Form submitted successfully');
  
      // Display popup with form data
      const formHandler = new FormHandler(this.formElement.id);
      const fields = formHandler.getFields();
      
      // Prepare a string representation of the fields with their data
      let fieldsString = '';
      for (const fieldName in fields) {
        if (fields.hasOwnProperty(fieldName)) {
          fields[fieldName].inputs.forEach((input) => {
            fieldsString += `Field Name: ${fieldName}\nValue: ${input.value}\n\n`;
          });
        }
      }
      
      // Display the fields string in the popup
      document.getElementById('popupMessage').innerText = fieldsString || 'No data entered.';
      document.getElementById('overlay').style.display = 'block';
      document.getElementById('popup').style.display = 'block';
    }
  }
  
  document.addEventListener('DOMContentLoaded', () => {
    const formElement1 = document.getElementById('exampleForm1');
    const formElement2 = document.getElementById('exampleForm2');
  
    if (formElement1) {
      formElement1.addEventListener('submit', (event) => {
        event.preventDefault();
        
        const formHandler = new FormHandler('exampleForm1');
        const fields = formHandler.getFields();
        
        // Prepare a string representation of the fields with their data
        let fieldsString = '';
        for (const fieldName in fields) {
          if (fields.hasOwnProperty(fieldName)) {
            fields[fieldName].inputs.forEach((input) => {
              fieldsString += `Field Name: ${fieldName}\n`;
            });
          }
        }
        
        // Display the fields string in the popup
        document.getElementById('popupMessage').innerText = fieldsString || 'No data entered.';
        document.getElementById('overlay').style.display = 'block';
        document.getElementById('popup').style.display = 'block';
      });
    }
  
    if (formElement2) {
      new Form(formElement2);
    }
  
    document.getElementById('closePopup')?.addEventListener('click', () => {
      document.getElementById('overlay').style.display = 'none';
      document.getElementById('popup').style.display = 'none';
    });
  });


        // Function to scroll to and focus the specified field
        function gotoField(field) {
            console.log('goto field', field);
            field.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            field.focus();
        }

        // Get all input fields in the form
        const fields = Array.from(document.querySelectorAll('#myForm .scroll-to'));
        let currentIndex = 0;

        // Event listener for the button
        document.getElementById('gotoFieldButton').addEventListener('click', function() {
            if (fields.length === 0) return;

            // Move to the next field
            gotoField(fields[currentIndex]);

            // Update currentIndex to the next field, wrapping around if necessary
            currentIndex = (currentIndex + 1) % fields.length;
        });
  
         // Field names in the order of focus
         const fieldNames = ['input1', 'input2', 'input3'];

         // Fields object mapping field names to field elements
         const fields2 = {
             input1: document.getElementById('input1'),
             input2: document.getElementById('input2'),
             input3: document.getElementById('input3')
         };
 
         // Method to focus the specified field
         function focusField(fieldName) {
             const currentIndex = fieldNames.indexOf(fieldName);
             const nextField = fields2[fieldNames[currentIndex]];
             console.log('focusNextField:', { currentField: fieldName, nextField });
             if (nextField) nextField.focus();
         }

          // JavaScript object to manage fields
        const fieldNames2 = ['input1', 'input2', 'input3'];
        const fields3 = {
            input1: document.getElementById('first'),
            input2: document.getElementById('second'),
            input3: document.getElementById('third')
        };

        // Function to set focus on a specific field
        function gotoField(field) {
            if (field) {
                field.focus();
            }
        }

        // Function to focus the first field in the sequence
        function gotoFirstField() {
            const firstField = fields3[fieldNames2[0]];
            console.log('going to first field');
            gotoField(firstField);
        }

    // Function to show the popup
    function showPopup(message) {
      document.getElementById('popupMessage').textContent = message;
      document.getElementById('overlay').style.display = 'block';
      document.getElementById('popup').style.display = 'block';
  }

  // Function to hide the popup
  function hidePopup() {
      document.getElementById('overlay').style.display = 'none';
      document.getElementById('popup').style.display = 'none';
  }

  // Handle keydown event
  function handleKeyDown(e) {
      console.log('Form: handleKeyDown', e.target.name, e.target, e);
      if (e.key !== 'Enter') return;
      e.preventDefault(); 
      showPopup('Enter key has been pressed');
  }

  // Attach event listeners
  document.getElementById('Form').addEventListener('keydown', handleKeyDown);
  document.getElementById('closePopup').addEventListener('click', hidePopup);



  //setValues
  function setValues(values) {
    console.log('setValues', values);
    const form = document.getElementById('exampleForm');
    Object.keys(values).forEach(name => {
        const field = form.elements[name];
        if (field) field.value = values[name];
    });
}

document.getElementById('setValuesButton').addEventListener('click', () => {
    setValues({
        name: 'John Doe',
        email: 'john.doe@example.com',
        age: '30'
    });
});

  