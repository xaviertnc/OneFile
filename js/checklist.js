/* global F1 */

/* checklist.js */

(function(F1) {

  /**
   * vendors/F1/js/checklist.js
   *
   * F1 Checklist Control - 31 Mar 2026
   *
   * Purpose: Replaces a native <select multiple> with a styled checkbox list.
   *
   * @package F1
   *
   * @author C. Moller <xavier.tnc@gmail.com>
   *
   * @version 1.0 - INIT - 31 Mar 2026 - Initial commit
   * @version 1.1 - UPD - 31 Mar 2026 - Make header/search opt-in via data attrs
   * @version 1.2 - UPD - 31 Mar 2026 - Scroll opt-in via data-max-height or data-rows
   * @version 1.3 - FT - 31 Mar 2026 - Add compact/xcompact size modes via data-size
 * @version 1.4 - UPD - 31 Mar 2026 - Generalize size class from data-size attribute
 * @version 1.5 - FT - 31 Mar 2026 - Add data-indent support
   */

  function log(...args) { if (F1.DEBUG > 2) console.log(...args); }


  class Checklist {

    constructor( select, config = {} ) {
      this.select = select; select.hidden = true;
      this.config = Object.assign({}, select.dataset, config);
      this.select.after( this.createElement() );
      this.element.CONTROLLER = this;
      this.addEventListeners();
    }


    newEl( tag, attrs = {} ) {
      const el = document.createElement( tag );
      Object.entries( attrs ).forEach(([ key, val ]) => el[key] = val);
      return el;
    }


    createElement() {
      const c = 'checklist', cfg = this.config;
      const hasFrame = cfg.maxHeight || cfg.rows || cfg.toggleAll || cfg.searchable;
      const sizeCls = cfg.size ? ` ${c}--${cfg.size}` : '';
      const indentCls = cfg.indent ? ` ${c}--indent-${cfg.indent}` : '';
      this.element = this.newEl( 'div', { className: c + sizeCls + indentCls + ( hasFrame ? ` ${c}--framed` : '' ) } );
      this.itemsWrap = this.newEl( 'div', { className: `${c}__items` } );
      if ( cfg.maxHeight ) this.itemsWrap.style.maxHeight = cfg.maxHeight;
      else if ( cfg.rows ) this.itemsWrap.style.maxHeight = ( cfg.rows * 2 ) + 'em';

      if ( cfg.toggleAll ) {
        this.header = this.newEl( 'div', { className: `${c}__header` } );
        this.toggleAll = this.newEl( 'input', { type: 'checkbox', className: `${c}__toggle` } );
        this.toggleLabel = this.newEl( 'label', { className: `${c}__toggle-label` } );
        this.toggleLabel.append( this.toggleAll, document.createTextNode( ' Select All' ) );
        this.header.append( this.toggleLabel );
        if ( cfg.counter ) {
          this.counter = this.newEl( 'span', { className: `${c}__counter` } );
          this.header.append( this.counter );
        }
        this.element.append( this.header );
      }

      if ( cfg.searchable ) {
        this.search = this.newEl( 'input', {
          className: `${c}__search`, type: 'text',
          placeholder: cfg.searchPrompt ?? 'Filter...'
        });
        this.element.append( this.search );
      }

      this.items = Array.from( this.select.options ).map( opt => {
        const label = this.newEl( 'label', { className: `${c}__item` } );
        const cb = this.newEl( 'input', { type: 'checkbox', value: opt.value, checked: opt.selected } );
        label.append( cb, document.createTextNode( ' ' + ( opt.title || opt.text ) ) );
        label.dataset.value = opt.value;
        this.itemsWrap.append( label );
        return label;
      });

      this.element.append( this.itemsWrap );
      this.syncToSelect();
      return this.element;
    }


    addEventListeners() {
      this.itemsWrap.addEventListener( 'change', ( e ) => {
        if ( e.target.type !== 'checkbox' ) return;
        this.syncToSelect();
        this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
      });
      if ( this.toggleAll ) this.toggleAll.addEventListener( 'change', () => this.handleToggleAll() );
      if ( this.search ) this.search.addEventListener( 'input', () => this.filterItems() );
    }


    handleToggleAll() {
      const checked = this.toggleAll.checked;
      this.items.forEach( label => {
        if ( label.hidden ) return;
        label.firstElementChild.checked = checked;
      });
      this.syncToSelect();
      this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
    }


    filterItems() {
      const q = this.search.value.toLowerCase();
      this.items.forEach( label => {
        label.hidden = !label.textContent.toLowerCase().includes( q );
      });
      if ( this.toggleAll ) this.updateToggleState();
    }


    syncToSelect() {
      Array.from( this.select.options ).forEach( opt => {
        const label = this.items.find( l => l.dataset.value === opt.value );
        if ( label ) opt.selected = label.firstElementChild.checked;
      });
      if ( this.counter ) this.updateCounter();
      if ( this.toggleAll ) this.updateToggleState();
    }


    updateCounter() {
      const total = this.items.length;
      const checked = this.items.filter( l => l.firstElementChild.checked ).length;
      this.counter.textContent = `${checked}/${total}`;
    }


    updateToggleState() {
      const visible = this.items.filter( l => !l.hidden );
      const allChecked = visible.length > 0 && visible.every( l => l.firstElementChild.checked );
      const someChecked = visible.some( l => l.firstElementChild.checked );
      this.toggleAll.checked = allChecked;
      this.toggleAll.indeterminate = someChecked && !allChecked;
    }


    getValue() {
      return this.items.filter( l => l.firstElementChild.checked ).map( l => l.dataset.value );
    }


    setValue( values, init ) {
      log( 'Checklist::setValue', values );
      const vals = Array.isArray( values ) ? values : [];
      this.items.forEach( label => {
        label.firstElementChild.checked = vals.includes( label.dataset.value );
      });
      this.syncToSelect();
      if ( !init ) this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
    }

  } // Checklist


  F1.lib = F1.lib || {};
  F1.lib.Checklist = Checklist;

})(window.F1 = window.F1 || {});
