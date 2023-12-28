/* global F1 */

/* upload.js */

(function(F1) {

  /**
   * F1 Custom File Upload - 11 Dec 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 1.2 - DEV - 14 Dec 2023
   *   - Fix clearX initial hidden state
   *   - Handle "required" state if we have an initial value.
   */

  function log(...args) { if (F1.DEBUG > 1) console.log(...args); }

  function newEl(tag, attrs = {}) { const el = document.createElement(tag); 
     Object.entries(attrs).forEach(([key, value]) => el[key] = value); return el; }


  class Upload {

    constructor(input, config = {}) {
      this.input = input;
      this.name = input.name;
      this.required = this.input.hasAttribute('required');
      this.input.hidden = true;

      this.config = Object.assign({}, input.dataset, config);

      this.createElement();

      this.element.CONTROLLER = this;

      input.name = this.name + '_file';
      input.focus = () => this.valueDisplay.focus();
      input.onchange = (e) => this.update(e, input.files[0]?.name || '', 'focus');
      input.after(this.element);
    }


    createElement() {
      const bcn = this.config.className || 'upload', extra = this.input?.className || '';
      const className = bcn + extra + (this.config.size === 'large' ? ` ${bcn}--large` : '');
      const value = this.input.hasAttribute('data-value') ? this.input.dataset.value : '';
      if (value) this.input.removeAttribute('required');
      this.valueInput = newEl('input', { name: this.name, type: 'hidden', value });
      this.promptHtml = `<span class="${bcn}__prompt">${this.config.prompt || 'Browse... '}</span>`;
      this.valueDisplay = newEl('button', { className: `${bcn}__value`, type: 'button', 
        innerHTML: this.getValueHtml(value), onclick: (e) => this.input.click(e), tabIndex: 0 });
      this.clearX = newEl('a', { className: `${bcn}__clear`, 'ariaLabel': 'Clear X', title: 'Clear', 
        onclick: (e) => this.update(e, '', 'focus'), onkeydown: (e) => this.update(e, '', 'focus'),
        innerHTML: this.config.clearPrompt || 'x', tabIndex: '0', hidden: value === '' });
      this.element = newEl('div', { className, 'ariaLabel': `${this.input.id || this.input.name}_${bcn}_ui` });
      this.element.append(this.valueInput, this.valueDisplay, this.clearX);
    }


    getValueHtml(value) { return this.promptHtml + `<span>${value}</span>`; }


    getValue() { return this.valueInput.value; }


    update(event, value, focus) {
      const isActionKey = event.code === 'Enter' || event.code === 'Space';
      if (event.type === 'keydown' && !isActionKey) return;
      event.preventDefault(); event.stopPropagation();
      this.valueInput.value = value;
      if (!value) this.input.required = this.required;
      this.valueDisplay.innerHTML = this.getValueHtml(value);
      if (focus) this.valueDisplay.focus();
      this.clearX.hidden = !value;
    }

  }

  F1.lib = F1.lib || {};
  F1.lib.Upload = Upload;

})(window.F1 = window.F1 || {});