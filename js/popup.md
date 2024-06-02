# F1 Popup User Guide

## Overview

The F1 Popup is a versatile and customizable popup solution.  
It supports various configurations and can be used for modals, notifications, tooltips, and more.

## Including Popup.js

To use the F1 Popup, include the `popup.js` script in your HTML document:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>F1 Popup Example</title>
  <link rel="stylesheet" href="path/to/popup.css"> <!-- Optional, for custom styling -->
  <script src="path/to/popup.js"></script>
</head>
<body>
  <!-- Your content here -->
</body>
</html>
```

## Basic Usage

To initialize a popup using existing HTML, include the popup element when calling the constructor:

```html
<div id="myPopup" class="popup">
  <h2>Popup Title</h2>
  <p>This is a popup content.</p>
</div>
<script>
  var myPopup = new F1.lib.Popup({ el: document.getElementById('myPopup') });
  myPopup.show();
</script>
```

## Configuration Options

The popup can be configured with the following options:

| Option          | Type        | Default    | Description |
|-----------------|-------------|------------|-------------|
| `el`            | HTMLElement | `null`     | Existing HTML element to use as the popup. |
| `anchor`        | HTMLElement | `null`     | Anchor element realtive to which the popup is positioned. |
| `type`          | String      | `null`     | Type of popup: `'modal'`, `'notification'`, `'tooltip'`, `'dropdown'`. |
| `size`          | String      | `null`     | Size of the popup: `'small'`, `'medium'`, `'large'`. |
| `timer`         | Number      | `null`     | Auto-close timer in milliseconds. |
| `title`         | String      | `null`     | Popup header title text. |
| `theme`         | String      | `null`     | Theme for the popup (CSS customizable). Adds CSS class `${bcn}__${config.theme}`. 
| `content`       | String      | `null`     | Content to display inside the popup. |
| `backdrop`      | String      | `null`     | Backdrop type: `'transparent'`, `'dim'`, `'opaque'`. |
| `position`      | String      | `null`     | Position of the popup: `'center'`, `'top'`, `'bottom'`, `'bottom-right'`. |
| `animation`     | String      | `null`     | Animation for opening/closing: `'none'`, `'fade'`, `'slide'`. |
| `className`     | String      | `'popup'`  | CSS class name(s) for the popup element. |
| `mountMethod`   | String      | `'append'` | Method to mount the popup: `'append'`, `'prepend'`, `'after'`, `'before'`. |
| `closeX`        | String|Bool | `true`     | Enables the default or a custom close (`x`) style button top-right. |
| `trapFocus`     | Boolean     | `true`     | Traps TAB focus within the popup when opened. |
| `draggable`     | Boolean     | `false`    | Whether the popup is draggable. |
| `clickAwayClose`| Boolean     | `false`    | Closes the popup when clicking outside of it. |
| `escapeKeyClose`| Boolean     | `false`    | Closes the popup when the escape key is pressed. |
| `modal`         | Boolean     | `false`    | Whether the popup is modal. |
| `buttons`       | Array       | `[]`       | Array of button objects to display in the popup footer. |
| `beforeClose`   | Function    | `() => {}` | Function to call before the popup closes. |
| `afterClose`    | Function    | `() => {}` | Function to call after the popup closes. |
| `beforeOpen`    | Function    | `() => {}` | Function to call before the popup opens. |
| `afterOpen`     | Function    | `() => {}` | Function to call after the popup opens. |

### Buttons Configuration

The `buttons` property can be used to add interactive buttons to the popup. Here are various ways to use it:

1. **Simple OK and Cancel Buttons:**

    ```javascript
    var simpleButtons = new F1.lib.Popup({
      title: 'Simple Buttons',
      content: 'This popup has simple OK and Cancel buttons.',
      buttons: [
        { text: 'OK', onClick: function() { alert('OK clicked'); } },
        { text: 'Cancel', onClick: function() { simpleButtons.close(); } }
      ]
    });
    simpleButtons.show();
    ```

2. **Custom Styled Buttons:**

    ```javascript
    var styledButtons = new F1.lib.Popup({
      title: 'Styled Buttons',
      content: 'This popup has custom styled buttons.',
      buttons: [
        { text: 'Confirm', className: 'btn-confirm', onClick: function() { alert('Confirmed!'); } },
        { text: 'Dismiss', className: 'btn-dismiss', onClick: function() { styledButtons.close(); } }
      ]
    });
    styledButtons.show();
    ```

3. **Buttons with Dynamic Content:**

    ```javascript
    var dynamicContentPopup = new F1.lib.Popup({
      title: 'Dynamic Content',
      content: 'Initial content.',
      buttons: [
        { text: 'Update Content', onClick: function() {
            dynamicContentPopup.show({ content: 'Updated content.' });
          }
        },
        { text: 'Close', onClick: function() { dynamicContentPopup.close(); } }
      ]
    });
    dynamicContentPopup.show();
    ```

4. **Form Submission Button:**

    ```javascript
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
      ]
    });
    formPopup.show();
    ```

---

## Methods

The popup provides the following methods:

| Method             | Description |
|--------------------|-------------|
| `show(options = {})`  | Displays the popup. Optional `options` can override `content`, `title`, and `animation`. |
| `close(options = {})` | Closes the popup. Optional `options` can specify `animation`. |
| `mount()`          | Mounts the popup element to the DOM. Typically called internally by `show()`. |
| `dismount()`       | Removes the popup element from the DOM. Typically called internally by `close()`. |

---

## Examples

### Draggable Modal Popup
```javascript
var modal = new F1.lib.Popup({
  type: 'modal',
  title: 'Modal Title',
  content: 'This is a draggable modal popup.',
  modal: true,
  draggable: true,
  escapeKeyClose: true
});
modal.show();
```

### Alert Popup
```javascript
var alertPopup = new F1.lib.Popup({
  type: 'alert',
  title: 'Alert!',
  content: 'This is an alert popup.',
  animation: 'slide',
  backdrop: 'dim'
});
alertPopup.show();
```

### Toast Popup
```javascript
var toast = new F1.lib.Popup({
  type: 'toast',
  content: 'This is a toast message.',
  position: 'bottom-right',
  animation: 'slide'
  timer: 3000
});
toast.show();
```

### Notification Popup
```javascript
var notification = new F1.lib.Popup({
  type: 'notification',
  content: 'This is a notification message.',
  position: 'top',
  animation: 'fade',
  timer: 3000
});
notification.show();
```

### Tooltip Popup
```javascript
var tooltip = new F1.lib.Popup({
  type: 'tooltip',
  content: 'This is a tooltip.',
  position: 'bottom-right',
  animation: 'fade'
  anchor: document.getElementById('myButton')
});
tooltip.show();
```

### Dropdown Popup
```javascript
var dropdown = new F1.lib.Popup({
  type: 'dropdown',
  content: 'This is a dropdown.',
  position: 'bottom',
  animation: 'slide',
  anchor: document.getElementById('toggleDropdown'),
});
dropdown.show();
```

### Custom Close Button
```javascript
var customClose = new F1.lib.Popup({
  title: 'Custom Close Button',
  content: 'This popup has a custom close button.',
  closeX: '<span>&times;</span>',
});
customClose.show();
```

### Dynamic Content
```javascript
var dynamic = new F1.lib.Popup({
  title: 'Dynamic Content',
  content: 'Initial content.'
});
dynamic.show();
dynamic.show({ content: 'Updated content.' });
```

### HTML Element as Content
```javascript
  const Popup = F1.lib.Popup;
  const Utils = F1.lib.Utils;

  app.el.form = Utils.getEl('changePasswordForm');

  app.showChangePasswordForm = function() {
    app.el.form.reset();
    app.modal = new Popup({
      modal: true,
      backdrop: 'dim',
      animation: 'fade',
      content: app.el.form,
      anchor: app.currentPage.el,
      position: 'center',
      // size: 'large',
    });
    app.modal.show();
  };
```
