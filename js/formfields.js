/* global F1 */

/* formfields.js */

(function(F1) {

  /**
   * F1 Form - Custom Field Types - 03 Dec 2023
   * @version 1.1 - DEV -  25 Mar 2024
   *   - Change the utilBarContent in selectCtrlConfig to include "Edit" and "Delete" buttons.
   *   - Remove the "Manage Options" button from the utilBarContent in selectCtrlConfig.
   */

  function log(...args) { if (F1.DEBUG > 2) console.log(...args); }  


  const selectCtrlConfig = {

    /* Custom "beforeFocusNext" hook */
    beforeFocusNext: function(key, e) {
      if (key==='Tab' && !this.utilBar.hidden) {
        const firstVisibleButton = this.utilBar.querySelector('button:not([hidden])');
        e.preventDefault(); firstVisibleButton.focus(); return true; }
    },

    /* Custom "onUtilBarKeyDown" hook */
    onUtilBarKeyDown: function(e) {
      if (e.code === 'Escape') this.toggleDropdown('closed');
      else if (e.code === 'Tab') {
        const visibleButtons = this.utilBar.querySelectorAll('button:not([hidden])'),
        firstVisibleButton = visibleButtons[0], lastVisibleButton = visibleButtons[visibleButtons.length - 1];
        log({ visibleButtons, firstVisibleButton, lastVisibleButton });
        if (e.target === lastVisibleButton && this.selectableOptions === 0) {
          e.preventDefault(); if (visibleButtons.length > 1) { firstVisibleButton.focus(); }
          else { this.searchInput.focus(); }
        }
      }
    },

    /* Custom Util Bar */
    utilBarContent:
      '<button class="select__add" type="button" onclick="F1.app.addSelectOption(event)">' +
      '<i class="fa fa-plus-circle"></i> Add option</button>' +
      '<button class="select__edit" type="button" onclick="F1.app.editSelectOption(event)">' +
      '<i class="fa fa-pencil"></i> Edit</button>' +
      '<button class="select__delete" type="button" onclick="F1.app.deleteSelectOption(event)">' +
      '<i class="fa fa-times"></i> Delete</button>' +
      '<p>- no results -</p>'
  };



  /**
   * F1 Select Field - 03 Dec 2023
   *
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 2.0 - RC1 - 03 Dec 2023
   *   - Switch from object literal to Class
   *
   */
  class F1SelectField extends F1.lib.FormField {

    getFieldElement(stdSelectEl) {
      const selectCtrl = new F1.lib.Select(stdSelectEl, selectCtrlConfig);
      if (stdSelectEl.dataset?.manageOptions !== 'on') {
        if (stdSelectEl.dataset?.addOption !== 'on') selectCtrl.utilBar.children[0].hidden = true;
        if (stdSelectEl.dataset?.editOption !== 'on') selectCtrl.utilBar.children[1].hidden = true;
        if (stdSelectEl.dataset?.deleteOption !== 'on') selectCtrl.utilBar.children[2].hidden = true;        
      }
      return selectCtrl?.element;
    }

    setValue(value, init) {
      const selectCtrl = this.element.CONTROLLER;
      // We don't set the value on bootstrap. selectCtrl handles that.
      if (init !== 'init-bootstrap') selectCtrl.selectOption(value, init);
      if (init) this.defaultValue = value;
    }

  } // F1SelectField



  /**
   * F1 Upload Field - 11 Dec 2023
   *
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 1.0 - INIT - 11 Dec 2023
   *
   */
  class F1UploadField extends F1.lib.FormField {

    getFieldElement(fileInputEl) {
      log('F1UploadField::getFieldElement()', fileInputEl);
      const uploadCtrl = new F1.lib.Upload(fileInputEl, {});
      uploadCtrl.valueDisplay.onkeydown = (e) => {
        if ( e.code !== 'Enter' ) return;
        e.preventDefault(); this.form.focusNextField(this);
      };
      return uploadCtrl.element;
    }

    getValue(bootstrap) {
      // "bootstrap" is used to signal that we need to get the "initial state"
      // somewhere. This is only relevant if the field doesn't have it's
      // own controller. In this case, uploadCtrl handles getting the state
      // on instantiation, and we just read the value it already set.
      const uploadCtrl = this.element.CONTROLLER;
      const value = uploadCtrl.getValue();
      log(`F1UploadField::getValue(), ${this.name} = "${value}"`, bootstrap);
      return value;
    }

    setValue(value, init) {
      log(`F1UploadField::setValue(), ${this.name} = "${value}"`, init);
      const uploadCtrl = this.element.CONTROLLER;
      if (init !== 'init-bootstrap') uploadCtrl.update(new Event('change'), value);
      if (init) this.defaultValue = value;
    }

  } // F1UploadField


  F1.lib = F1.lib || {};
  F1.lib.F1SelectField = F1SelectField;
  F1.lib.F1UploadField = F1UploadField;

})(window.F1 = window.F1 || {});  