F1.deferred.push(function testMethod(app) { 

  const Ajax = F1.lib.Ajax;
  const Form = F1.lib.Form;
  const Popup = F1.lib.Popup;
  const Utils = F1.lib.Utils;
  const F1SelectField = F1.lib.F1SelectField;
  const F1UploadField = F1.lib.F1UploadField;

  // Assuming you have a form element with the ID 'myForm' in your HTML
  const formElement = document.getElementById('myForm');

  // Check if the form element exists
  if (formElement) {
    // Create an instance of the Form class
    const myForm = new Form(formElement);

    // Attach an event listener to the form's submit event
    formElement.addEventListener('submit', function(event) {
      event.preventDefault(); // Prevent the default form submission behavior

      // Call the getFields method
      const fields = myForm.getFields();

      // Log the fields to the console
      console.log('Fields:', fields);

      // Display a message or handle the fields as needed
      Object.keys(fields).forEach(fieldName => {
        const field = fields[fieldName];
        console.log(`Field Name: ${fieldName}`, field);
      });

      // Optionally, show a popup or message to the user
      alert('Form fields have been logged to the console.');
    });
  } else {
    console.error('Form element with ID "myForm" not found');
  }

});
