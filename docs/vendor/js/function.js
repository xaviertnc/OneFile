//Simple OK and Cancel Buttons
document.getElementById('open-popup-button').addEventListener('click', function() {
    var popupOutput = document.querySelector('.popup-output');
    var simpleButtons = new F1.lib.Popup({
        title: 'Simple Buttons',
        content: 'This popup has simple OK and Cancel buttons.',
        buttons: [
            { text: 'OK', onClick: function() { alert('OK clicked'); } },
            { text: 'Cancel', onClick: function() { simpleButtons.close(); } }
        ],
        anchor: popupOutput, 
        mountMethod: 'append'
    });
    simpleButtons.show();
});

//Custom Styled Button
document.getElementById('open-popup-styled').addEventListener('click', function() {
    var popupOutput = document.querySelector('.popup-styled');
    var styledButtons = new F1.lib.Popup({
        title: 'Styled Buttons',
        content: 'This popup has custom styled buttons.',
        buttons: [
            { text: 'Confirm', className: 'btn-confirm', onClick: function() { alert('Confirmed!'); } },
            { text: 'Dismiss', className: 'btn-dismiss', onClick: function() { styledButtons.close(); } }
        ],
        anchor: popupOutput, 
        mountMethod: 'append' 
    });
    styledButtons.show();
});

//Buttons with Dynamic Content
document.getElementById('open-popup-dynamic').addEventListener('click', function() {
    var popupOutput = document.querySelector('.popup-dynamic');
    var dynamicContentPopup = new F1.lib.Popup({
        title: 'Dynamic Content',
        content: 'Initial content.',
        buttons: [
            { text: 'Update Content', onClick: function() {
                dynamicContentPopup.show({ content: 'Updated content.' });
            }
            },
            { text: 'Close', onClick: function() { dynamicContentPopup.close(); } }
        ],
        anchor: popupOutput,
        mountMethod: 'append' 
    });
    dynamicContentPopup.show();
});

//Form Submission Button
document.getElementById('open-popup-form').addEventListener('click', function() {
    var popupOutput = document.querySelector('.popup-form');
    var formPopup = new F1.lib.Popup({
        title: 'Form Submission',
        content: `
            <form id="popupForm">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name"><br><br>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email"><br><br>
            <input type="submit" value="Submit">
            </form>
        `,
        buttons: [
            { text: 'Submit', onClick: function() {
                var form = document.getElementById('popupForm');
                alert('Form submitted: ' + form.name.value + ', ' + form.email.value);
                }
            },
            { text: 'Close', onClick: function() { formPopup.close(); } }
        ],
        anchor: popupOutput, 
        mountMethod: 'append' 
    });
    formPopup.show();
});

//Alert
document.getElementById('open-popup-alert').addEventListener('click', function() {
    var alertPopup = new F1.lib.Popup({
        theme: 'danger',
        type: 'alert',
        backdrop: 'dim',
        title: 'Alert!',
        animation: 'fade',
        content: 'This is an alert popup.',
    });
    alertPopup.show();
});

//Toast
document.getElementById('open-popup-toast').addEventListener('click', function() {
    var toast = new F1.lib.Popup({
        type: 'toast',
        timer: 3000, // Close delay
        position: 'bottom-right',
        animation: 'slide-up',
        content: 'This is a toast message.',
    });
    toast.show();
});


//Notification
document.getElementById('open-popup-notification').addEventListener('click', function() {
    var notification = new F1.lib.Popup({
        theme: 'info',
        type: 'notification',
        position: 'top',
        content: 'This is a notification message.',
    });
    notification.show();
});

//Tooltip
document.getElementById('open-popup-tooltip').addEventListener('click', function() {
    var popupOutput = document.querySelector('.popup-tooltip-button');
    var tooltip = new F1.lib.Popup({
        type: 'tooltip',
        position: 'left',
        content: 'This is a tooltip.',
        anchor: popupOutput, 
    });
    tooltip.show();
});

//Dropdown
document.getElementById('open-popup-dropdown').addEventListener('click', function() {
    var popupOutput = document.querySelector('.popup-dropdown-button');
    var dropdown = new F1.lib.Popup({
        type: 'dropdown',
        position: 'bottom',
        animation: 'slide-down',
        content: 'This is a dropdown.',
        anchor: popupOutput,
        mountMethod: 'append',
    });
    dropdown.show();
});

//Draggable Modal
document.getElementById('open-popup-draggable').addEventListener('click', function() {
    var modal = new F1.lib.Popup({
        type: 'modal',
        title: 'Modal Title',
        content: 'This is a draggable modal popup.',
        escapeKeyClose: true,
        draggable: true,
        modal: true,
    });
    modal.show();
});