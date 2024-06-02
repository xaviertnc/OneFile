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

---

## Configuration Options

The popup can be configured with the following options:

| Option          | Type        | Default    | Description |
|-----------------|-------------|------------|-------------|
| `el`            | HTMLElement | `null`     | Existing HTML element to use as the popup. |
| `anchor`        | HTMLElement | `null`     | Anchor element realtive to which the popup is positioned. |
| `type`          | String      | `null`     | Type of popup: `'modal'`, `'alert'`, `'toast'`, `'notification'`, `'tooltip'`, `'dropdown'`. |
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

---

## Methods

The popup provides the following methods:

| Method                | Description |
|-----------------------|-------------|
| `show(options = {})`  | Displays the popup. Optional `options` can override `content`, `title`, and `animation`. |
| `close(options = {})` | Closes the popup. Optional `options` can specify `animation`. |
| `mount()`             | Mounts the popup element to the DOM. Typically called internally by `show()`. |
| `dismount()`          | Removes the popup element from the DOM. Typically called internally by `close()`. |

---

## Examples

### Buttons

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

### Popup Backdrops

#### Overview

1. **Backdrop Creation**: The backdrop is created if the popup is configured as a modal or if the `backdrop` property is set. The type of backdrop (e.g., `transparent`, `dim`, `opaque`) is determined by the `backdrop` configuration property.
2. **Appending Popup to Backdrop**: The popup element is appended as a child of the backdrop element. This ensures that the backdrop covers the entire screen while the popup is displayed on top of it.
3. **Event Handling for Backdrop**: The backdrop element has an event listener for click events. If the `clickAwayClose` configuration is enabled and the user clicks on the backdrop (not the popup itself), the popup will close.

#### Example

```javascript
const popupWithBackdrop = new Popup({
  modal: true,
  backdrop: 'dim',
  title: 'Popup Title',
  content: '<p>This is the popup content.</p>',
  closeX: true,
  clickAwayClose: true
});
```

#### Resulting HTML Structure

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

---

### Popup Types

#### Alert Popup
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

#### Toast Popup
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

#### Notification Popup
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

#### Draggable Modal Popup
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

#### Tooltip Popup
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

#### Dropdown Popup
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

#### Custom Close Button
```javascript
var customClose = new F1.lib.Popup({
  title: 'Custom Close Button',
  content: 'This popup has a custom close button.',
  closeX: '<span>&times;</span>',
});
customClose.show();
```

---

### Dynamic Content

#### Updating Popup Content on Show
```javascript
var dynamic = new F1.lib.Popup({
  title: 'Dynamic Content',
  content: 'Initial content.'
});
dynamic.show();
dynamic.show({ title: 'New Title', content: 'Updated content.' });
```

#### Using Existing HTML Element as Content
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

#### Using Existing Popup HTML

Popup JS can auto generate the HTML required for a new popup or use an existing HTML element.  
To initialize a popup using an existing element, pass the element to the `el` property:

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