# F1 Popup User Guide

## Overview

The F1 Popup is a versatile and customizable popup solution.  
It supports various configurations and can be used for modals, notifications, tooltips, and more.  
F1 Popup automatically handles proper stacking order of popups and backdrops.

Typical implementation modes include:

### JS Only
Use JavaScript to display a quick alert or notification without the need for HTML markup. 
The new popup will by default be added to the DOM as a direct child of the document body. 
Use the `anchor` option to "mount" the popup relative to different anchor element. *(Aslo see the `mountMethod`)* 
Use the `content` or `message` option to set popup content. 
Use the `buttons` option to add interactive buttons. 
Use the `title` option to set the title. 

*See Configuration Options for more.*

### HTML Mode
Get the popup element from the DOM and pass it to the popup class using the `el`  constructor option.
The default `content` and `title` will match what is in the existing HTML element.
Show method options can override defaults, if the content and title elements have the appropriate classes.
Show the popup using the `show` method.

### Content HTML Mode
Let the popup class generate the structural HTML, but set the content using an HTML string or an HTML element reference on initialization.
If the `content` option is an HTML element, the element will be appended (i.e. moved) to the popup content container.
The popup will by default be added to the DOM as a direct child of the document body unless the `anchor` option is set.
Show the popup using the `show` method. Show options will override defaults.


## Getting Started

To use the F1 Popup, include the `popup.js` script in your HTML document:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>F1 Popup Example</title>
  <link rel="stylesheet" href="vendors/F1/css/popup.css">
  <script src="vendors/F1/js/popup.js"></script>
</head>
<body>
  <!-- Your content here -->

  <!-- JS Only -->
  <script>
    const alertPopup = new F1.lib.Popup({
      type: 'alert',
      theme: 'warning',
      title: 'Alert',
      timer: 3000, // 3s auto close delay
      message: 'This is an alert message',
      buttons: [
        { text: 'OK', onClick: () => alertPopup.close() }
      ]
    });
    alertPopup.show();
  </script>

  <!-- HTML Mode -->
  <div id="my-popup" class="popup">
    <div class="popup__header">
      <h2 class="popup__title">Existing Title</h2>
      <button class="popup__close">&times;</button>
    </div>
    <div class="popup__content">Existing content</div>
    <div class="popup__footer">
      <button class="popup__button">Close</button>
    </div>
  </div>

  <script>
    const existingPopup = new F1.lib.Popup({ el: document.getElementById('my-popup') });
    existingPopup.show({ title: 'New Title', content: 'New content' });
  </script>

  <!-- Content HTML Mode -->
  <template id="popup-content-template">
    <p>This is the content of the popup.</p>
  </template>

  <script>
    const template = document.getElementById('popup-content-template');
    const htmlContentPopup = new F1.lib.Popup({
      content: template.innerHTML,
      title: 'Popup with HTML Content'
    });
    htmlContentPopup.show();
  </script>
</body>
</html>
```

The F1 Popup component allows for multiple popups to be displayed simultaneously and automatically manages the `z-index` of the popups to ensure proper stacking order.

The minimum required anatomy of a Popup's HTML should include an HTML element with class `popup` (or your custom base class) and at least one child element with class `popup__content`. 
Other component elements like `popup__title` are optional.


## Configuration Options

The popup can be configured with the following options:

| Option          | Type        | Default    | Description |
|-----------------|-------------|------------|-------------|
| `el`            | HTMLElement | `null`     | Existing HTML element to use as the popup. |
| `anchor`        | HTMLElement | `null`     | Anchor element realtive to which the popup is positioned. |
| `mountMethod`   | String      | `'append'` | Method to mount the popup: `'append'`, `'prepend'`, `'after'`, `'before'`. |
| `className`     | String      | `'popup'`  | The base CSS class name(s) for the popup element. `{bcn}` |
| `theme`         | String      | `null`     | Adds a theme specific `${bcn}__${theme}` CSS class to the popup element. |
| `type`          | String      | `null`     | Type of popup: `'modal'`, `'alert'`, `'toast'`, `'notification'`, `'tooltip'`, `'dropdown'`. |
| `size`          | String      | `null`     | Size of the popup: `'small'`, `'medium'`, `'large'`. |
| `modal`         | Boolean     | `false`    | Whether the popup is modal. |
| `title`         | String      | `null`     | Popup header title text. |
| `message`       | String      | `null`     | Popup message text. |
| `content`       | String      | `null`     | Content to display inside the popup. |
| `buttons`       | Array       | `[]`       | Array of button objects to display in the popup footer. |
| `timer`         | Number      | `null`     | Auto-close timer in milliseconds. |
| `backdrop`      | String      | `null`     | Backdrop type: `'transparent'`, `'dim'`, `'opaque'`. |
| `position`      | String      | `null`     | Position of the popup: `'center'`, `'top'`, `'bottom'`, `'bottom-right'`. |
| `animation`     | String      | `null`     | Animation for opening/closing: `'none'`, `'fade'`, `'slide'`. |
| `closeX`        | String/Bool | `true`     | Enables the default or a custom close (`x`) style button top-right. |
| `draggable`     | Boolean     | `false`    | Whether the popup is draggable. |
| `clickAwayClose`| Boolean     | `false`    | Closes the popup when clicking outside of it. |
| `escapeKeyClose`| Boolean     | `false`    | Closes the popup when the escape key is pressed. |
| `trapFocus`     | Boolean     | `true`     | Traps TAB focus within the popup when opened. |
| `beforeClose`   | Function    | `() => {}` | Function to call before the popup closes. |
| `afterClose`    | Function    | `() => {}` | Function to call after the popup closes. |
| `beforeOpen`    | Function    | `() => {}` | Function to call before the popup opens. |
| `afterOpen`     | Function    | `() => {}` | Function to call after the popup opens. |


## CSS Classes

The F1 Popup component comes with a set of CSS styles and classes that can be used to customize its appearance. 
The default base class name is `popup`, but you can change it via the `className` constructor option if it clashes with other components. 
Here are some of the key classes, with `{bcn}` representing the base class name:

- `{bcn}`: Base class for the popup.
- `{bcn}__header`: Container for the popup header.
- `{bcn}__title`: Title of the popup.
- `{bcn}__close`: Button to close the popup.
- `{bcn}__content`: Container for the main content of the popup.
- `{bcn}__footer`: Container for the footer buttons.
- `{bcn}__button`: Styles for buttons within the popup.
- `{bcn}--small`, `{bcn}--medium`, `{bcn}--large`: Size modifiers for the popup.
- `{bcn}--center`, `{bcn}--top`, `{bcn}--bottom`, `{bcn}--bottom-right`: Position modifiers for the popup. 

The minimum required anatomy of a Popup's HTML should include a parent HTML element with class `{bcn}` and,
at least one child element with class `{bcn}__content`. Other elements like `{bcn}__title` are optional.

Example:

```javascript
const customPopup = new F1.lib.Popup({
  className: 'custom-popup',
  title: 'Custom Popup',
  content: 'This is a custom styled popup.'
});
customPopup.show();
```

With this setup, you can create and use custom styles for your popups by defining the `.custom-popup` class and related classes in your CSS.


## Methods

The popup provides the following methods:

| Method                | Description |
|-----------------------|-------------|
| `show(options = {})`  | Displays the popup. Optional `options` can override `content`, `title`, and `animation`. |
| `close(options = {})` | Closes the popup. Optional `options` can specify `animation`. |
| `mount()`             | Mounts the popup element to the DOM. Typically called internally by `show()`. |
| `dismount()`          | Removes the popup element from the DOM. Typically called internally by `close()`. |


## Buttons

The `buttons` property can be used to add interactive buttons to the popup. Here are various ways to use it:

### Simple OK and Cancel Buttons
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

### Custom Styled Buttons
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

### Buttons with Dynamic Content
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

### Form Submission Button
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

## Popup Types

### Alert
```javascript
var alertPopup = new F1.lib.Popup({
  theme: 'danger',
  type: 'alert',
  backdrop: 'dim',
  title: 'Alert!',
  animation: 'fade',
  content: 'This is an alert popup.',
});
alertPopup.show();
```

### Toast
```javascript
var toast = new F1.lib.Popup({
  type: 'toast',
  timer: 3000, // Close delay
  position: 'bottom-right',
  animation: 'slide-up',
  content: 'This is a toast message.',
});
toast.show();
```

### Notification
```javascript
var notification = new F1.lib.Popup({
  theme: 'info',
  type: 'notification',
  position: 'top',
  content: 'This is a notification message.',
});
notification.show();
```

### Tooltip
```javascript
var tooltip = new F1.lib.Popup({
  type: 'tooltip',
  position: 'top',
  content: 'This is a tooltip.',
  anchor: document.getElementById('myButton'),
});
tooltip.show();
```

### Dropdown
```javascript
var dropdown = new F1.lib.Popup({
  type: 'dropdown',
  position: 'bottom',
  animation: 'slide-down',
  content: 'This is a dropdown.',
  anchor: document.getElementById('ddToggle'),
});
dropdown.show();
```

### Draggable Modal
```javascript
var modal = new F1.lib.Popup({
  type: 'modal',
  title: 'Modal Title',
  content: 'This is a draggable modal popup.',
  escapeKeyClose: true,
  draggable: true,
  modal: true,
});
modal.show();
```

## Backdrops

### Overview

#### Backdrop Creation
The backdrop is created if the popup is configured as a modal or if the `backdrop` property is set. 
The type of backdrop (e.g., `transparent`, `dim`, `opaque`) is determined by the `backdrop` configuration property.

#### Appending Popup to Backdrop
The popup element is appended as a child of the backdrop element. 
This ensures that the backdrop covers the entire screen while the popup is displayed on top of it.

#### Event Handling for Backdrop
The backdrop element has an event listener for click events. 
If the `clickAwayClose` configuration is enabled and the user clicks on the backdrop (not the popup itself), the popup will close.

### Example

```javascript
const popupWithBackdrop = new Popup({
  backdrop: 'dim',
  title: 'Popup Title',
  content: '<p>This is the popup content.</p>',
  clickAwayClose: true,
  closeX: true,
  modal: true,
});
```

Resulting HTML:

```html
<div class="popup__backdrop popup__backdrop--dim">
  <div id="popup-0" class="popup popup__type popup__theme popup__position popup__size" aria-modal="true" tabindex="-1" role="dialog">
    <div class="popup__header">
      <div id="popup-0-title" class="popup__title">Popup Title</div>
      <button type="button" class="popup__close">&times;</button>
    </div>
    <div class="popup__content">
      <p>This is the popup content.</p>
    </div>
    <div class="popup__footer popup__footer--no-buttons"></div>
  </div>
</div>
```

## Dynamic Content

### Updating Popup Content on Show
```javascript
var dynamic = new F1.lib.Popup({
  title: 'Dynamic Content',
  content: 'Initial content.'
});
dynamic.show();
dynamic.show({ title: 'New Title', content: 'Updated content.' });
```

## Accessibility

The F1 Popup component includes several accessibility features to ensure a better user experience for all users:

### Aria Attributes
The popup includes `aria-modal`, `aria-labelledby`, and `aria-describedby` attributes to provide context to screen readers.

### Focus Management
The popup manages focus appropriately, ensuring focus is trapped within the popup when it is open and returned to the trigger element when closed.

### Keyboard Navigation
The popup supports keyboard navigation, allowing users to open, close, and navigate through the popup using the keyboard.

#### Custom Close Button
```javascript
var customClose = new F1.lib.Popup({
  title: 'Custom Close Button',
  content: 'This popup has a custom close button.',
  closeX: '<span>&times;</span>',
});
customClose.show();
```

## More Examples

### Existing HTML Element as Content
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

### Existing HTML Element as Popup

To initialize a popup using an existing element, pass the element to the `el` constructor option.

```html
<!-- Popup #0 -->
<div id="popup-0" class="popup">
  <div class="popup__header">
    <h2 class="popup__title">Popup Title</h2>
    <button type="button" class="popup__close">&times;</button>
  </div>
  <div class="popup__content">
    <p>This is a popup content.</p>
  </div>
  <div class="popup__footer">Popup Footer</div>
</div>

<!-- Popup #1 -->
<div class="popup__backdrop popup__backdrop--dim popup--fade-in" style="z-index: 9901;">
  <div id="popup-1" class="popup popup--center" aria-modal="true" tabindex="-1" role="dialog">
    <div class="popup__header popup__header--no-title">
      <button type="button" class="popup__close">×</button>
    </div>
    <div class="popup__content">
      <form id="changePasswordForm" onsubmit="F1.app.submitChangePassword(event)" hidden="">
        <input type="hidden" name="userId" value="{user_id}">
        <label for="newPassword">New Password</label>
        <input type="password" id="newPassword" name="newPassword" required="">
        <label for="confirmPassword">Confirm New Password</label>
        <input type="password" id="confirmPassword" name="confirmPassword" required="">
        <input type="submit" class="btn-primary" value="Change Password">
      </form>
    </div>
    <div class="popup__footer popup__footer--no-buttons"></div>
  </div>
</div>

<!-- Popup #2 -->
<div class="popup__backdrop popup__backdrop--transparent" style="z-index: 9902;">
  <div id="popup-2" class="popup popup__warning popup--center popup--small" aria-modal="true" tabindex="-1" role="dialog">
    <div class="popup__header">
      <div id="popup-1-title" class="popup__title">You have unsaved changes!</div>
      <button type="button" class="popup__close">×</button>
    </div>
    <div class="popup__content">Discard changes?</div>
    <div class="popup__footer">
      <button type="button" class="popup__button btn--primary">Yes</button>
      <button type="button" class="popup__button btn--secondary">No</button>
    </div>
  </div>
</div>

<script>
  const p0 = new F1.lib.Popup({ el: document.getElementById('popup-0') });
  const p1 = new F1.lib.Popup({ el: document.getElementById('popup-1') });
  const p2 = new F1.lib.Popup({ el: document.getElementById('popup-2') });
  p0.show();
  p0.close();
  /* Dynamic content */
  p2.show({ content: '<span class="warning">Are you sure?</span>' });
</script>
```

## TODO

- [ ] Add a section for the Popup component's animations and transitions.
- [ ] Add a section for the Popup component's positioning and alignment.
- [ ] Add a section on Popup object(s) lifecycle and memory management.