/* global F1 */

/* utils.js */

(function(F1) {

  /**
   * Utils Class - 01 Jun 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 2.0 - RC2 - 04 Dec 2023
   *   - Add titleCase()
   */

  class Utils {

    static newEl(tag, className, attrs = {}) {
      const el = document.createElement(tag); el.className = className;
      Object.entries(attrs).forEach(([key, value]) => el[key] = value);
      return el;
    }

    static getEl(id) { return document.getElementById(id);  }

    static show(el, disp = 'inline-block') { el.style.display = disp; }

    static hide(el) { el.style.display = 'none'; }

    static removeFrom(el, selector) {
      Array.from(el.querySelectorAll(selector)).forEach(found => found.remove());
    }

    static removeClassFrom(el, className) {
      Array.from(el.querySelectorAll('.' + className)).forEach(found => found.classList.remove(className));
    }
  
    static capitalizeFirstChar(str) { return str.charAt(0).toUpperCase() + str.slice(1); }

    static titleCase(str) { return str.split(/[ _-]+/).map(word => Utils.capitalizeFirstChar(word)).join(' '); }

    static generateUid(){ return Math.random().toString(36).substr(2, 9); } // e.g. '4k8b4y9w3'

    static currency(num, symbol = 'R ', sep = ' ', dec = 0, dc = '.') {
      if (num === null || num === '') return '';
      const numStr = num.toString().replace(/[^0-9.]/g, ''), decimalIndex = numStr.indexOf(dc);
      const intPart = decimalIndex !== -1 ? numStr.slice(0, decimalIndex) : numStr;
      const grpSize = 3, len = intPart.length; let rem = len % grpSize, fmtNum = '';
      let decPart = (decimalIndex !== -1 && dec) ? numStr.slice(decimalIndex) : '';
      if (dec) decPart += decPart.length ? (decPart.length === 2 ? '0' : '') : dc + '00';
      if (rem > 0) { fmtNum += intPart.slice(0, rem); if (len > grpSize) fmtNum += sep; }
      for (let i = rem; i < len; i += grpSize) { if (i !== rem) fmtNum += sep; fmtNum += intPart.slice(i, i + grpSize); }
      return symbol + fmtNum + decPart;
    }
 
  }

  F1.lib = F1.lib || {};
  F1.lib.Utils = Utils;

})(window.F1 = window.F1 || {});
