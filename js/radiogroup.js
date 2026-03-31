/* global F1 */

/* radiogroup.js */

(function(F1) {

  /**
   * vendors/F1/js/radiogroup.js
   *
   * F1 RadioGroup Control - 31 Mar 2026
   *
   * Purpose: Replaces a native <select> with styled radio list or button strip.
   *
   * @package F1
   *
   * @author C. Moller <xavier.tnc@gmail.com>
   *
   * @version 1.0 - INIT - 31 Mar 2026 - Initial commit
   */

  function log(...args) { if (F1.DEBUG > 2) console.log(...args); }


  class RadioGroup {

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
      const c = 'radiogroup', cfg = this.config, style = cfg.style || 'list';
      const dir = style === 'list' ? ( cfg.dir || 'vert' ) : 'horz';
      const cls = [ c, `${c}--${style}`, `${c}--${dir}` ];
      if ( cfg.seamless ) cls.push( `${c}--seamless` );
      if ( cfg.size === 'compact' ) cls.push( `${c}--compact` );
      this.element = this.newEl( 'div', { className: cls.join( ' ' ) } );
      const name = this.select.name + '_rg';

      this.items = Array.from( this.select.options ).map(( opt, i ) => {
        const item = this.newEl( 'label', { className: `${c}__item` } );
        const radio = this.newEl( 'input', { type: 'radio', name, value: opt.value } );
        if ( style === 'buttons' ) {
          const span = this.newEl( 'span' );
          span.textContent = opt.title || opt.text;
          item.append( radio, span );
        } else {
          item.append( radio, document.createTextNode( ' ' + ( opt.title || opt.text ) ) );
        }
        item.dataset.value = opt.value;
        this.element.append( item );
        return item;
      });

      if ( this.select.value ) this.selectOption( this.select.value, 'init' );
      return this.element;
    }


    addEventListeners() {
      this.element.addEventListener( 'change', ( e ) => {
        if ( e.target.type !== 'radio' ) return;
        this.select.value = e.target.value;
        this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
      });
    }


    selectOption( value, init ) {
      log( 'RadioGroup::selectOption', value );
      this.select.value = value;
      this.items.forEach( label => {
        label.firstElementChild.checked = label.dataset.value === value;
      });
      if ( !init ) this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
    }


    getValue() {
      const checked = this.items.find( l => l.firstElementChild.checked );
      return checked ? checked.dataset.value : null;
    }


    setValue( value, init ) {
      this.selectOption( value, init );
    }

  } // RadioGroup


  F1.lib = F1.lib || {};
  F1.lib.RadioGroup = RadioGroup;

})(window.F1 = window.F1 || {});
