/* global F1 */

/* popup.js */

(function(F1) {

  /**
   * F1 Popup - 01 Jun 2023
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * 
   * @version 2.3.1 - DEV - 02 Jun 2024
   *   - Add popup types comment.
   *   - Update and improve Popup documentation. i.e. popup.md
   *
   * TODO:
   *  - Finish support for animation: none, fade, slide
   *  - Add support for draggable: true, false
   */

  function log(...args) { if (F1.DEBUG > 1) console.log(...args); }


  class Popup {

    static nextId = 0;
    static nextZIndex = 9900;
    static popups = new Set();

    constructor(config = {}) {

      const defaultConfig = {
        type: null, // 'modal', 'dropdown', 'alert', 'toast', 'notification', 'tooltip'
        title: null,
        theme: null,
        modal: false,
        content: null,
        className: 'popup',
        escapeKeyClose: false,
        clickAwayClose: false,
        beforeClose: () => {},
        afterClose: () => {},
        beforeOpen: () => {},
        afterOpen: () => {},
        animation: null, // 'fade', 'slide'
        backdrop: null,  // 'transparent', 'dim', 'opaque'
        position: null,  // 'center', 'top', 'bottom', 'bottom-right'
        size: null,      // 'small', 'medium', 'large'
        draggable: false,
        trapFocus: true,
        closeX: true,
        buttons: [],
        timer: null,
        el: null,
      };

      this.config = Object.assign(defaultConfig, config);

      this.id = this.config.id || ('popup-' + Popup.nextId++);

      if (this.config.el) this.element = this.config.el;
      else this.createElement(this.config);

      this.handleKeyUp = this.handleKeyUp.bind(this);

      this.element.CONTROLLER = this;
    }


    newEl(tag, attrs = {}) { const el = document.createElement(tag); 
     Object.entries(attrs).forEach(([key, value]) => el[key] = value); return el; }


    getAriaRole(popupType) {
      var roles = { 'modal': 'modal', 'dropdown': 'listbox', 'alert': 'alert', 
        'toast': 'status', 'notification': 'status', 'tooltip': 'tooltip' };
      return roles?.[popupType] || 'dialog';
    }


    createElement(config) {
      log('popup.createElement(), config =', config);

      /** Popup **/
      const id = this.id;
      const bcn = config.className;
      const typeClass = config.type ? ` ${bcn}__${config.type}` : '';
      const themeClass = config.theme ? ` ${bcn}__${config.theme}` : '';
      const sizeClass = config.size ? ` ${bcn}--${config.size}` : '';
      const positionClass = config.position ? ` ${bcn}--${config.position}` : '';
      const popupClass = bcn + typeClass + themeClass + positionClass + sizeClass;
      const popup = this.newEl('div', {id, className: popupClass});
      // Popup ARIA attributes
      const ariaAttributes = ['aria-modal', 'tabindex', 'role'];
      const ariaValues = [config.modal, -1, this.getAriaRole(config.type)];
      ariaAttributes.forEach((attr, index) => popup.setAttribute(attr, ariaValues[index]));      
      this.popup = popup;

      /** Popup Header **/
      let headerClass = `${bcn}__header`;
      if (config.title) {
        const popupTitle = this.newEl('div', {id: id + '-title', 
          className: `${bcn}__title`, innerHTML: config.title});
        this.title = popupTitle;
      } else headerClass += ` ${bcn}__header--no-title`;
      if (config.closeX) {
        const closeX = this.newEl('button', {type: 'button', 
          className: `${bcn}__close`, innerHTML: config.closeX === true ? '&times;' : config.closeX });
        closeX.addEventListener('click', (e) => this.close({event: e, src: 'closeX'}));
        this.closeX = closeX; }
      const popupHeader = this.newEl('div', { className: headerClass });
      if (this.title) popupHeader.appendChild(this.title);
      if (this.closeX) popupHeader.appendChild(this.closeX);
      popup.appendChild(popupHeader);
      this.header = popupHeader;

      /** Popup Content **/
      const popupContent = this.newEl('div', {className: `${bcn}__content`, innerHTML: ''});
      if (config.content instanceof HTMLElement) popupContent.appendChild(config.content);
      else popupContent.innerHTML = config.content || config.message;
      popup.appendChild(popupContent);
      this.content = popupContent;

      /** Popup Footer **/
      let footerClass = `${bcn}__footer`;
      if (!config.buttons.length) footerClass += ` ${bcn}__footer--no-buttons`;
      const popupFooter = this.newEl('div', { className: footerClass });
      config.buttons.forEach(btn => {
        const button = this.newEl('button', {type: 'button', innerHTML: btn.text,  
           className: `${bcn}__button ${btn.className}`});
        button.addEventListener('click', btn.onClick || (event => this.close({event, src: 'button'})));
        popupFooter.appendChild(button); });
      popup.appendChild(popupFooter);
      this.footer = popupFooter;

      /** Popup Backdrop **/
      if (this.config.modal || this.config.backdrop) {
        const backdropType = this.config.backdrop || 'transparent';
        const backdrop = this.newEl('div', { className: `${bcn}__backdrop` });
        if (backdropType) backdrop.classList.add(`${bcn}__backdrop--${backdropType}`);
        backdrop.addEventListener('click', (event) => {
          if (event.target === backdrop && this.config.clickAwayClose) {
            this.close({event, src: 'backdrop'}); }
        });
        backdrop.appendChild(popup);
        this.backdrop = backdrop;
      }

      /** Popup Main **/
      const zIndex = this.config.zIndex || Popup.nextZIndex++;
      this.element = this.backdrop || popup;
      this.element.style.zIndex = zIndex;

      return this.element;
    }


    mount() {
      const anchor = this.config.anchor || document.body;
      const mountMethod = this.config.mountMethod || 'append';
      anchor[mountMethod](this.element);
      this.mounted = true;
    }


    dismount() {
      this.element.remove();
      this.mounted = false;
    }


    trapFocus() {
      const focusableSelector = 'button, [href], input:not([type="hidden"]):not([disabled]), ' +
       'select, textarea, [tabindex]:not([tabindex="-1"])';
      const focusableElements = this.popup.querySelectorAll(focusableSelector);
      const firstFocusableInPopup = focusableElements[0];
      const lastFocusableInPopup = focusableElements[focusableElements.length - 1];
      const firstFocusableInContent = this.content.querySelector(focusableSelector);
      // log({ firstFocusableInPopup, lastFocusableInPopup, firstFocusableInContent });
      this.firstFocusable = firstFocusableInContent || firstFocusableInPopup;
      this.firstFocusable.focus();
      this.popup.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
          if (e.shiftKey) {
            if (document.activeElement === firstFocusableInPopup) {
              e.preventDefault(); lastFocusableInPopup.focus();
            }
          } else {
            if (document.activeElement === lastFocusableInPopup) {
              e.preventDefault(); firstFocusableInPopup.focus();
            }
          }
        }
      });
    }


    handleKeyUp(event) {
      log('popup.handleKeyUp()');
      if (event.key === 'Escape' && this.config.escapeKeyClose) {
        this.close({event, src: 'escape'});
      }
    }


    show(options = {}) {
      log('popup.show(), options =', options);
      if (this.config.beforeOpen(this, options) === 'abort') return;
      Popup.popups.add(this);
      const bcn = this.config.className;
      const content = options.content || options.message;
      const anim = this.config.animation || options.animation;
      if (!this.title) this.title = this.element.querySelector(`.${bcn}__title`);
      if (!this.content) this.content = this.element.querySelector(`.${bcn}__content`);
      if (options.title && this.title) this.title.innerHTML = options.title;
      if (content && this.content) {
        this.content.innerHTML = '';
        if (content instanceof HTMLElement) this.content.appendChild(content);
        else this.content.innerHTML = content; }
      if (anim) {
        const animClass = `${bcn}--${anim}-in`;
        this.element.classList.add(animClass);
        const showEnd = () => { this.element.classList.remove(animClass); };
        this.popup.addEventListener('animationend', showEnd, {once: true}); }
      if (!this.mounted) this.mount();
      document.addEventListener('keyup', this.handleKeyUp);
      requestAnimationFrame(() => this.element.classList.add(`${bcn}--visible`));
      if (this.config.timer) this.timer = setTimeout(() => this.close({src:'timer'}), this.config.timer);
      if (this.config.trapFocus) this.trapFocus();
      return this.config.afterOpen(this);
    }


    close(options = {}) {
      log('popup.close(), options =', options);
      if (!Popup.popups.has(this)) return log('popup.close(), already closed... ignore');
      if (this.config.beforeClose(this, options) === 'abort') return;
      document.removeEventListener('keyup', this.handleKeyUp);
      if (this.timer) clearTimeout(this.timer);
      Popup.popups.delete(this);
      const bcn = this.config.className;
      const anim = this.config.animation || options.animation;
      const closeEnd = (animClass) => {
        if (animClass) this.popup.classList.remove(animClass);
        this.element.classList.remove(`${bcn}--visible`);
        this.dismount();
        this.config.afterClose(this, options); };
      if (anim) {
        const animClass = `${bcn}--${anim}-out`;
        this.element.classList.add(animClass);
        this.element.addEventListener('animationend', () => closeEnd(animClass), {once: true});
      } else closeEnd();
    }

  }

  F1.lib = F1.lib || {};
  F1.lib.Popup = Popup;

})(window.F1 = window.F1 || {});