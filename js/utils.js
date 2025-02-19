/* global F1 */

/* utils.js */

(function(F1) {

  /**
   * Utils Class - 01 Jun 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * 
   * @version 2.1 - FT - 08 Jan 2025
   *   - Add percent() method
   * 
   * @version 2.2 - FT - 18 Jan 2025
   *   - Rename capitalizeFirstChar() to ucFirst()
   *   - Improve generateUid(). Prevent UIDs that can be seen as numbers.
   * 
   * @version 2.3 - UPD - 19 Feb 2025 
   *   - Update the default currency() separator to ','.
   */

  class Utils {

    static hide(el) { el.style.display = 'none'; }

    static show(el, disp = 'inline-block') { el.style.display = disp; }

    static newEl(tag, className, attrs = {}) {
      const el = document.createElement(tag); if (className) el.className = className;
      Object.entries(attrs).forEach(([key, value]) => el[key] = value);
      return el;
    }

    static getEl(id) { return document.getElementById(id);  }

    static removeFrom(el, selector) {
      Array.from(el.querySelectorAll(selector)).forEach(found => found.remove());
    }

    static removeClassFrom(el, className) {
      Array.from(el.querySelectorAll('.' + className)).forEach(found => found.classList.remove(className));
    }
  
    static ucFirst(str) { return str.charAt(0).toUpperCase() + str.slice(1); }

    static titleCase(str) { return str.split(/[ _-]+/).map(word => Utils.ucFirst(word)).join(' '); }

    static generateUid() {
      let uid, i = 0; // e.g. '4k8b4y9w3'
      do { uid = Math.random().toString(36).substr(2, 9); i++; }
      while (!isNaN(uid.replace('e','')) && i < 1000);
      return uid;
    }

    static currency(num, symbol = 'R ', sep = ',', dec = 0, dc = '.') {
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

    static percent(input, decimals = 2) {
      let sanitized = input.replace(/[^\d.]/g, '');
      let percent = Math.min(100, Math.max(0, parseFloat(sanitized) || 0));
      return percent.toFixed(decimals); // Return as a string
    }

    static clone(obj) {
      if (obj === null || typeof obj !== 'object') return obj;
      if (Array.isArray(obj)) return obj.map(Utils.clone);
      return Object.fromEntries(Object.entries(obj).map(([key, value]) => [key, Utils.clone(value)]));
    } 
 
  }

  F1.lib = F1.lib || {};
  F1.lib.Utils = Utils;

})(window.F1 = window.F1 || {});
