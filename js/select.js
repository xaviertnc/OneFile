/* global F1 */

/* select.js */

(function(F1) {

  /**
   * F1 Custom Select - 17 Nov 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 1.2 - FIX - 16 May 2024
   *   - Fix issue with selectOption() not matching the value correctly. === vs. ==
   * 
   */

  function log(...args) { if (F1.DEBUG > 2) console.log(...args); }

  class Select {

    constructor(select, config = {}) {
      this.select = select; select.hidden = true;
      if (select.hasAttribute('data-value')) select.value = select.dataset.value;
      this.config = Object.assign({}, select.dataset, config); 
      this.selectPrompt = this.config.selectPrompt ?? 'Select...';
      this.select.after(this.createElement()); this.element.CONTROLLER = this;
      this.handleGlobalClickBound = this.handleGlobalClick.bind(this);
      this.select.focus = () => this.valueDisplay.focus();
      if (!this.select.disabled) this.addEventListeners(); }

    estop(e) { e.preventDefault(); e.stopPropagation(); };

    newEl(tag, attrs = {}) { const el = document.createElement(tag); 
     Object.entries(attrs).forEach(([key, value]) => el[key] = value); return el; }

    createElement() {
      const bcn = this.config.className || 'select', extra = this.select?.className || '';
      const className = bcn + extra + (this.config.size === 'large' ? ` ${bcn}--large` : '');
      this.element = this.newEl('div', { className, 'ariaLabel': `${this.select.id || this.select.name}_${bcn}_ui` });
      this.valueDisplay = this.newEl('button', { className: `${bcn}__value`, type: 'button', 'ariaHasPopup': 'combobox', 
      'ariaExpanded': 'false', 'ariaDisabled': this.select.disabled, innerHTML: `<span>${this.selectPrompt}</span>` });
      this.dropdown = this.newEl('div', { role: 'combobox', 'ariaLabel': 'Dropdown' });
      this.searchInput = this.newEl('input', { role: 'searchbox', placeholder: this.config.searchPrompt ?? 'Search...' });
      this.utilBar = this.newEl('div', { className: `${bcn}__utilbar`, innerHTML: this.config.utilBarContent ?? '<p>- no results -</p>' });
      this.optsList = this.newEl('ul', { role: 'listbox', 'ariaLabel': 'Options', innerHTML: this.getOptionsHtml(), tabIndex: '-1' });
      this.clearX = this.newEl('a', { className: `${bcn}__clear`, 'ariaLabel': 'Clear X', tabIndex: '0', hidden: true });
      this.options = Array.from(this.optsList.children); this.clearX.innerHTML = this.config.clearPrompt ?? '';
      this.element.classList.toggle('select--noresults', this.options.length === 0);
      if (this.select.value) this.selectOption(this.select.value, 'init');
      if (this.config.searchable) this.dropdown.append(this.searchInput);
      this.dropdown.append(this.utilBar, this.optsList);
      this.element.append(this.valueDisplay, this.dropdown, this.clearX); return this.element; }

    updateOptions() { this.optsList.innerHTML = this.getOptionsHtml(); this.options = Array.from(this.optsList.children); }

    getOptionsHtml() { return Array.from(this.select.options).map(opt => `<li data-value="${opt.value}" role="option" ${
      opt.value == this.select.value ? 'aria-selected="true"' : ''} tabindex="0">${opt.title || opt.text}</li>` ).join(''); }

    handleGlobalClick(e) {
      if (!this.element.contains(e.target)) this.toggleDropdown('closed', 'global');
      else if (e.button === 0 && this.optsList.contains(e.target)) { // Left mouse button only
        const el = e.target.closest('li'); if (el) this.selectAndClose(e, el.dataset?.value); } }

    handleValueKeyDown(e) {
      if (e.code === 'Space' || e.code === 'ArrowDown') { this.estop(e); this.toggleDropdown('open'); }
      else if (e.code === 'Enter') { this.estop(e); this.select.dispatchEvent( new KeyboardEvent('keydown', 
        { code: 'Enter', key: 'Enter', keyCode: 13, bubbles: true })); } }

    handleSearchKeyDown(e) {
      if (e.code === 'ArrowDown' || e.code === 'Tab') this.focusNext(e.code, e);
      else if (e.code === 'Escape') { this.estop(e); this.toggleDropdown('closed'); }
      else if (e.code === 'Enter') { this.estop(e); if (this?.selectableOptions === 1) {
        const firstSelectableOption = this.options.find(opt => !opt.hidden);
        this.selectAndClose(e, firstSelectableOption.dataset.value); } else
        this.toggleDropdown('closed'); } }

    handleOptionsKeyDown(e) {
      if (e.code === 'Escape') this.toggleDropdown('closed');
      else if (e.code === 'ArrowDown' || e.code === 'ArrowUp') this.focusNext(e.code, e);
      else if (e.code === 'Enter') this.selectAndClose(e, document.activeElement.dataset.value);
      else if (e.code === 'Tab') this.focusNext(e.shiftKey ? 'ArrowUp' : 'ArrowDown', e); }

    handleReset() { setTimeout(() => this.selectOption(this.select.defaultValue, 'init'), 0); }

    handleClear(e) { if (e.code === 'Enter' || e.type === 'click') this.selectAndClose(e, ''); }

    addEventListeners() {
      this.valueDisplay.addEventListener('keydown', this.handleValueKeyDown.bind(this));
      this.valueDisplay.addEventListener('click', () => this.toggleDropdown());
      this.optsList.addEventListener('keydown', this.handleOptionsKeyDown.bind(this));
      this.select?.form.addEventListener('reset', this.handleReset.bind(this));
      if (this.config.clearPrompt) {
        this.clearX.addEventListener('keydown', this.handleClear.bind(this));
        this.clearX.addEventListener('click', this.handleClear.bind(this)); }
      if (this.config.searchable) {
        this.searchInput.addEventListener('keydown', this.handleSearchKeyDown.bind(this));
        this.searchInput.addEventListener('input', () => this.filterOptions()); }
      if (this.config?.onUtilBarKeyDown) {
        this.utilBar.addEventListener('keydown', this.config.onUtilBarKeyDown.bind(this)); } 
    }

    firstVisibleOption(opts) {
      const scrollbox = this.optsList.getBoundingClientRect();    
      for (let i = 0; i < opts.length; i++) { const opt = opts[i], optRect = opt.getBoundingClientRect();
        if (optRect.top > (scrollbox.top - optRect.height * 0.34)) return opt; } }

    toggleDropdown(state = null, source = 'internal') {
      const currentState = this.valueDisplay.getAttribute('aria-expanded') === 'true' ? 'open' : 'closed';
      const newState = state ?? (currentState === 'open' ? 'closed' : 'open'); // Toggle if not specified
      this.valueDisplay.setAttribute('aria-expanded', newState === 'open');
      this.element.classList.toggle('select--open', newState === 'open');
      if (newState === 'open') { if (currentState === newState) return;
        const r0 = this.dropdown.getBoundingClientRect(), spaceDown = window.innerHeight - r0.bottom,
        spaceUp = r0.top, isDropup = spaceDown < r0.height && spaceUp > r0.height;
        this.element.classList.toggle('select--dropup', isDropup);
        const selOpt = this.optsList.querySelector('li[aria-selected="true"]');
        if (selOpt) { const r1 = this.optsList.getBoundingClientRect(), r2 = selOpt.getBoundingClientRect(); 
          this.optsList.scrollTop += r2.top - r1.top - (r1.height / 2) + (r2.height / 2); }
        if (this.config.searchable) this.searchInput.focus();
        else if (selOpt) selOpt.focus();
        else this.focusNext('ArrowDown'); // If no option is selected, focus the first option
        document.addEventListener('mousedown', this.handleGlobalClickBound);
      } else { 
        this.valueDisplay.focus();
        this.element.classList.remove('select--dropup');
        document.removeEventListener('mousedown', this.handleGlobalClickBound);
      } }

    selectAndClose(e, v) { this.estop(e); this.selectOption(v); this.toggleDropdown('closed'); }

    filterOptions() {
      this.selectableOptions = 0; const searchText = this.searchInput.value.toLowerCase();
      this.options.forEach(item => { const showItem = item.textContent.toLowerCase().includes(searchText);
        if (showItem) this.selectableOptions++; item.hidden = !showItem; });
      this.element.classList.toggle('select--noresults', this.selectableOptions === 0);
      this.optsList.hidden = this.selectableOptions === 0; }

    selectOption(value, init) {
      // log('selectOption:', { value, init });
      const selOpt = Array.from(this.select.options).find(option => option.value == value); // == Important!
      this.select.value = value; if (selOpt) selOpt.selected = true; this.clearX.hidden = !this.config.clearPrompt || !value;
      this.valueDisplay.firstElementChild.innerHTML = selOpt ? selOpt.title || selOpt.text : this.selectPrompt;
      this.options.forEach(li => li.setAttribute('aria-selected', li.dataset.value == value));
      if (init) return this.select.defaultValue = value; setTimeout(() => 
        this.select.dispatchEvent(new Event('change', { bubbles: true })));
      this.searchInput.value = ''; this.filterOptions(); }

    focusNext(key, e) {
      if (this.config.beforeFocusNext?.bind(this)(key, e)) return;
      if (e) this.estop(e); const opts = this.options.filter(li => !li.hidden), i = opts.indexOf(document.activeElement);
      if (i === -1) return this.firstVisibleOption(opts)?.focus(); // from search/display to options list
      if (i === 0 && this.config.searchable && (key === 'ArrowUp' || e?.shiftKey && e.code === 'Tab')) return this.searchInput.focus();
      const next = key === 'ArrowUp' ? (i - 1 + opts.length) % opts.length : (i + 1) % opts.length; opts[next].focus(); }
  }

  F1.lib = F1.lib || {};
  F1.lib.Select = Select;

})(window.F1 = window.F1 || {});