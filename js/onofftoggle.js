/* global F1 */

/* onofftoggle.js */

(function(F1) {

  /**
   * vendors/F1/js/onofftoggle.js
   *
   * F1 OnOffToggle Control - 31 Mar 2026
   *
   * Purpose: Replaces a 2-option <select> with a sliding toggle switch.
   *
   * @package F1
   *
   * @author C. Moller <xavier.tnc@gmail.com>
   *
   * @version 1.0 - INIT - 31 Mar 2026 - Initial commit
   * @version 1.1 - FT - 31 Mar 2026 - Configurable on/off values via data-on
   */

  class OnOffToggle {

    constructor( select, config = {} ) {
      this.select = select; select.hidden = true;
      this.config = Object.assign({}, select.dataset, config);
      this.options = Array.from( select.options ).filter( o => o.value );
      this.onValue = this.config.on !== undefined ? this.config.on : this.options[1].value;
      this.select.after( this.createElement() );
      this.element.CONTROLLER = this;
    }


    newEl( tag, attrs = {} ) {
      const el = document.createElement( tag );
      Object.entries( attrs ).forEach(([ key, val ]) => el[key] = val);
      return el;
    }


    createElement() {
      const c = 'toggle', cfg = this.config;
      const cls = [ c ];
      if ( cfg.size ) cls.push( `${c}--${cfg.size}` );
      this.element = this.newEl( 'div', { className: cls.join( ' ' ) } );
      this.track = this.newEl( 'span', { className: `${c}__track` } );
      this.knob = this.newEl( 'span', { className: `${c}__knob` } );
      this.label = this.newEl( 'span', { className: `${c}__label` } );
      this.track.append( this.knob );
      this.element.append( this.track, this.label );
      this.element.addEventListener( 'click', () => this.toggle() );
      this.syncUI();
      return this.element;
    }


    isOn() { return this.select.value === this.onValue; }


    syncUI() {
      const on = this.isOn(), opts = this.options, onVal = this.onValue;
      this.element.classList.toggle( 'toggle--on', on );
      const cur = on ? opts.find( o => o.value === onVal ) : opts.find( o => o.value !== onVal );
      this.label.textContent = cur ? cur.text : '';
    }


    toggle() {
      const opts = this.options, onVal = this.onValue;
      this.select.value = this.isOn()
        ? ( opts.find( o => o.value !== onVal ) || opts[0] ).value : onVal;
      this.syncUI();
      this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
    }


    getValue() { return this.select.value; }


    setValue( value, init ) {
      this.select.value = value;
      this.syncUI();
      if ( !init ) this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
    }

  } // OnOffToggle


  F1.lib = F1.lib || {};
  F1.lib.OnOffToggle = OnOffToggle;

})(window.F1 = window.F1 || {});
