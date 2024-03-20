# F1 Custom Select User Guide

## Introduction

F1 Custom Select is a custom UI component that replaces the native select dropdown with a customizable, styleable dropdown component. It enhances form UI by providing support for features like searching, clearing selection, and custom styling.

## Version

- **Author**: C. Moller <xavier.tnc@gmail.com>
- **Version**: 1.1 - RC10
- **Release Date**: 01 Dec 2023
- **Features Added**:
  - Drop Up support for dropdowns with insufficient space below.

## Setup

To use the F1 Custom Select component, include the `select.js` script in your HTML document and initialize it with the global `F1` namespace.

```html
<script src="path/to/select.js"></script>
```

## Initialization

The `Select` class can be initialized with a native select element and an optional configuration object.

### Example:

```javascript
const selectElement = document.querySelector('select');
const customSelect = new F1.lib.Select(selectElement, {
  className: 'my-custom-select',
  selectPrompt: 'Choose an option',
  searchPrompt: 'Search options',
  searchable: true,
  clearPrompt: 'Clear',
});
```

## Configuration Options

- `className`: Custom class name for the select element. Default is 'select'.
- `selectPrompt`: Placeholder text when no option is selected. Default is 'Select...'.
- `searchPrompt`: Placeholder text for the search input. Appears only if `searchable` is `true`.
- `searchable`: Enables a search input to filter options. Default is `false`.
- `clearPrompt`: Text for the clear selection button. If not provided, the clear functionality is disabled.
- `utilBarContent`: HTML content for the utility bar. This is shown inside the dropdown but above the options list.
- `size`: Specify 'large' for a larger dropdown.

## Methods

- `updateOptions()`: Updates the dropdown options. Use this if the options in the native select element have changed.
- `selectOption(value, init)`: Selects an option with the specified value. If `init` is set to 'init', it initializes the select without triggering change events.
- `toggleDropdown(state, source)`: Toggles the dropdown open or closed. The `state` parameter can be 'open', 'closed', or null (to toggle based on current state). The `source` parameter indicates the source of the toggle action (internal or global).

## Events

Custom Select listens and reacts to various events, including keydown for navigation and selection, click for opening and selecting options, and form reset to revert to the default selected option.

## Styling

Custom Select can be styled via CSS. It supports custom class names and comes with modifiers like `--large` for size and `--open` for the open state. Utilize the provided structure and class names for deep customization.

## License

F1 Custom Select is licensed under [LICENSE_NAME]. For more information, contact the author at xavier.tnc@gmail.com.

---

# F1 Custom Select User Guide - Extended Examples

## Introduction

F1 Custom Select enhances HTML forms by replacing native select elements with customizable, styleable dropdowns. It supports searching, clear selection, and custom styling.

## Basic HTML Setup

Before initializing F1 Custom Select, you need a standard HTML select element. Here's an example:

```html
<select id="mySelect">
  <option value="">Select an option...</option>
  <option value="1">Option 1</option>
  <option value="2">Option 2</option>
  <option value="3">Option 3</option>
</select>
```

## Initialization with Data Attributes

You can pass initial configuration settings directly through data attributes on the select element:

```html
<select id="mySelect" data-select-prompt="Choose an option" data-search-prompt="Search..." data-searchable="true">
  <!-- options here -->
</select>
```

Then, initialize the Select component in your JavaScript:

```javascript
const selectElement = document.querySelector('#mySelect');
const customSelect = new F1.lib.Select(selectElement);
```

## Advanced Configuration Example

For more control, you can initialize the Select component with a configuration object:

```javascript
const selectElement = document.querySelector('#myAdvancedSelect');
const customSelect = new F1.lib.Select(selectElement, {
  className: 'custom-select-style',
  selectPrompt: 'Select an item',
  searchPrompt: 'Type to search...',
  searchable: true,
  clearPrompt: 'Reset',
  utilBarContent: '<button class="custom-action">Custom Action</button>',
  size: 'large',
});
```

This setup provides a select dropdown with a custom class name, prompts, a searchable input field, a clear button, a utility bar with custom HTML, and a larger dropdown size.

## Styling with CSS

Once you have initialized your select components, you can further customize their appearance with CSS. Given the `className` setting in the configuration, you can target elements like so:

```css
.custom-select-style {
  /* Custom styles for the outer select container */
}

.custom-select-style__value {
  /* Styles for the displayed select value */
}

.custom-select-style--large {
  /* Additional styles for large selects */
}
```

## HTML Structure

After initialization, F1 Custom Select generates a structured HTML setup for styling and interaction:

```html
<div class="select custom-select-style select--large" aria-label="mySelect_select_ui">
  <button class="select__value custom-select-style__value" aria-haspopup="combobox" aria-expanded="false">Select an item</button>
  <div role="combobox" aria-label="Dropdown">
    <!-- Optional search input -->
    <input role="searchbox" placeholder="Type to search...">
    <!-- Utility bar -->
    <div class="select__utilbar"><button class="custom-action">Custom Action</button></div>
    <!-- Options list -->
    <ul role="listbox" aria-label="Options">
      <li data-value="1" role="option" tabindex="0">Option 1</li>
      <!-- More options -->
    </ul>
  </div>
  <!-- Optional clear button -->
  <a class="select__clear" aria-label="Clear X" tabindex="0">Reset</a>
</div>
```

This structure is dynamically created based on the initial select element and the configuration provided. Customize it through CSS or adjust the configuration to fit your design needs.

---

With these examples, you should have a comprehensive understanding of how to set up and use F1 Custom Select to enhance your web forms.
```