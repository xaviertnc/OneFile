/* global F1 */

/* upload.js */

(function(F1) {

  /**
   * F1 Custom File Upload - 11 Dec 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 2.1 - FT - 16 Jul 2024
   *   - Improve file type validation logic.
   *   - Also validate file type on file change event.
   */

  function log(...args) { if (F1.DEBUG > 1) console.log(...args); }

  function newEl(tag, attrs = {}) { const el = document.createElement(tag); 
     Object.entries(attrs).forEach(([key, value]) => el[key] = value); return el; }


  class Upload {

    constructor(input, config = {}) {
      this.input = input;
      this.name = input.name;
      this.required = this.input.hasAttribute('required');

      this.config = Object.assign({}, input.dataset, config);

      this.createElement();

      this.element.CONTROLLER = this;

      input.hidden = true;
      input.name = this.name + '_file';
      input.focus = () => this.valueDisplay.focus();
      input.onchange = (e) => this.handleFileChange(e);
      input.after(this.element);
    }


    preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }


    parseAcceptAttribute(accept) {
      const extensionToMimeType = {
        '.pdf': 'application/pdf',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.jpeg': 'image/jpeg',
        '.gif': 'image/gif',
      };
      const mimeTypes = accept.split(/[ ,]+/).map(type => {
        if (type.startsWith('.')) {
          return extensionToMimeType[type.toLowerCase()] || null;
        }
        if (type.endsWith('/*')) {
          const mainType = type.slice(0, -1);
          return `${mainType}*`;
        }
        return type;
      }).filter(Boolean);

      return mimeTypes;
    }


    isValidFileType(file, accept) {
      if (!accept) return true;
      const allowedMimeTypes = this.parseAcceptAttribute(accept);
      log('allowedMimeTypes:', allowedMimeTypes);
      log('file.type:', file.type);
      return allowedMimeTypes.some(mimeType => {
        if (mimeType.endsWith('/*')) {
          return file.type.startsWith(mimeType.slice(0, -1));
        }
        return file.type === mimeType;
      });
    }


    handleDrop(e) {
      this.preventDefaults(e);
      const inputAccept = this.input.accept || this.config.accept || '';
      const file = e.dataTransfer.files[0];
      if (!this.isValidFileType(file, inputAccept)) {
        alert(`Invalid file type! Please select a ${inputAccept} file.`);
        return;
      }
      const dt = e.dataTransfer;
      this.input.files = dt.files;
      this.update(e, dt.files[0]?.name || '', 'focus');
    }


    handleFileChange(e) {
      const file = this.input.files[0];
      const inputAccept = this.input.accept || this.config.accept || '';
      if (!this.isValidFileType(file, inputAccept)) {
        alert(`Invalid file type! Please select a ${inputAccept} file.`);
        this.input.value = '';  // Clear the input
        return;
      }
      this.update(e, file.name || '', 'focus');
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
      ['dragenter', 'dragover', 'dragleave'].forEach(eventName => {
        this.valueDisplay.addEventListener(eventName, this.preventDefaults, false); });
      ['dragenter', 'dragover'].forEach(eventName => {
        this.valueDisplay.addEventListener(eventName, () => this.valueDisplay.classList.add('highlight'), false); });
      ['dragleave', 'drop'].forEach(eventName => {
        this.valueDisplay.addEventListener(eventName, () => this.valueDisplay.classList.remove('highlight'), false); });
      this.valueDisplay.addEventListener('drop', this.handleDrop.bind(this), false);
    }


    getValueHtml(value) { return this.promptHtml + `<span>${value}</span>`; }


    getValue() { return this.valueInput.value; }


    update(event, value, focus) {
      const isActionKey = event.code === 'Enter' || event.code === 'Space';
      if (event.type === 'keydown' && !isActionKey) return;
      this.preventDefaults(event);
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