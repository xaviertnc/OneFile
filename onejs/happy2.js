/*global jQuery*/
/**
 * HappyJs2 jQuery Plugin
 * A heavily modified version of HappyJS - Copyright (c) 2011 Henrik Joreteg - http://projects.joreteg.com
 * @author: C. Moller <xavier.tnc@gmail.com>
 * @date: 27 Mar 2017
 * @license: MIT
 * @updated: 12 Apr 2017 - RC1
 * @updated: 20 Apr 2017 - RC2
 * @updated: 27 Apr 2017 - RC3
 * @updated: 23 May 2017 - RC4
 *
 * Todo: Make Errors and Messages HappyItems Too
 *  + Allow adding an error message to any element. I.e. extract away from UpdateDOM
 *
 * Todo: Add HappyJs2 HELP text and examples!
 *
 */
(function happyJs2($) {
  'use strict';

  var defaultHappyDoc, nextHappyId = 1;

  function defined(val) { return typeof val !== 'undefined'; }

  function getInstanceOf(happyType, jqData, happyObjId) { var i;
    for (i in jqData) { if (jqData.hasOwnProperty(i) && (jqData[i].happyType === happyType) && (!happyObjId || jqData[i].id === happyObjId)) { return jqData[i]; } }
  }

  function unserialize (s) { var a, obj = {}; // Note: s === '0' is not a valid input value and will returm the same as s === '' or undefined
    if (s && s.length) { a = s.split(';'); $.each(a, function (i, v) { var b = v.split('|'); if(b.length > 1) { obj[b[0]] = b[1]; } else { obj[b[0]] = true; } }); }
    //console.log('unserialize(), s:', s, ', obj:', obj);
    return obj;
  }

  //function jQueryObj(selector) { return (!selector || selector instanceof jQuery) ? selector : $(selector); }

  function castArray(val) { return (!val || val.constructor === Array) ? (val || []) : [val]; }

  function HappyMessage(field, error) {
    var $defaultAnchor = (field.input && error.$target === field.$elm) ? field.input.$elm : error.$target;
    var $defaultPlacement = (field.input && error.$target === field.$elm) ? 'after' : 'append';
    this.id = field.resolveOpt('messageId') || field.id + '_unhappy';
    this.classAttr = field.resolveOpt('messageClass') || 'unhappyMessage';
    this.placement = field.resolveOpt('messagePlacement') || $defaultPlacement;
    this.$anchor = field.findElement(field.resolveOpt('messageAnchor'), field.$elm) || $defaultAnchor;
    this.$elm = field.findElement(field.messageSelector(this), this.$anchor);
    //console.log('HappyMessage.construct(), id:', this.id, ', $elm:', this.$elm);
    this.text = this.old = null;
  }
  HappyMessage.prototype = {
    remove: function () { if (this.$elm) { this.$elm.remove(); } this.$elm = null; this.text = this.old = null; },
    replace: function (newEl) { var $newEl = $(newEl); this.$elm.replaceWith($newEl); this.$elm = $newEl; this.old = this.text; },
    changed: function () { return this.text !== this.old; }
  };

  function HappyError(field) {
    this.type = '';
    this.field = field;
    this.errorClass = field.resolveOpt('errorClass') || 'unhappy';
    this.$target = field.findElement(field.resolveOpt('errorTarget'), field.$elm) || field.$elm;
    this.message = new HappyMessage(field, this);
  }
  HappyError.prototype.remove = function () {
    this.type = '';
    this.field.lastUnhappy = null;
    this.field.unhappy = '';
    this.field.removeClass(this.$target[0], this.errorClass);
    this.message.remove();
  };

  function HappyItem(happyType, childItemsType, $happyItem, options, parent) {
    var opt;
    this.id = null;
    this.ancestor = HappyItem.prototype;
    this.happyType = happyType;
    this.$elm = $happyItem;
    this.happyParent = parent;
    this.unhappy = '';
    this.modified = '';
    this.unhappyItems = [];
    this.modifiedItems = [];
    this.childItemsType = childItemsType;
    this[childItemsType] = this.childItems = [];
    $.extend(this, options);
    this.id = this.id || this.resolveOpt(happyType + 'Id');
    if (parent) { this[parent.happyType] = parent; parent.childItems.push(this); }
    this.modifiedClass = this.resolveOpt(happyType + 'ModifiedClass') || 'modified';
    this.unhappyClass = this.resolveOpt(happyType + 'UnhappyClass') || 'unhappy';
    opt = this.getOpt(happyType + 'BeforeValidate');  if (opt) { this.beforeValidate = opt; }
    opt = this.getOpt(happyType + 'Validate'      );  if (opt) { this.validate = opt;       }
    opt = this.getOpt(happyType + 'Unhappy'       );  if (opt) { this.onUnhappy = opt;      }
    opt = this.getOpt(happyType + 'Happy'         );  if (opt) { this.onHappy = opt;        }
    opt = this.getOpt(happyType + 'Mount'         );  if (opt) { this.mount = opt;          }
    opt = this.getOpt(happyType + 'Dismount'      );  if (opt) { this.dismount = opt;       }
    opt = this.getOpt(happyType + 'BeforeUpdate'  );  if (opt) { this.beforeUpdate = opt;   }
    opt = this.getOpt(happyType + 'AfterValidate' );  if (opt) { this.aftrerValidate = opt; }
  }
  HappyItem.prototype = {
    getOpt: function (optName) { return defined(this[optName]) ? this[optName] : this.happyParent.getOpt(optName); },
    resolve: function (source, args) { return $.isFunction(source) ? source.apply(this, args) : source; },
    resolveOpt: function (optName) { return this.resolve(this.getOpt(optName)); },
    mount: function () { $.each(this.childItems, function mountChildItems(i, item) { item.mount(); }); },
    dismount: function (ignoreParentStates) {
      //console.log('HappyItem::dismount(), this:', this, ', ignoreParentStates:', ignoreParentStates);
      var parent = this.happyParent || {};
      if (this.childItems) { $.each(this.childItems, function dismountChildItems(i, item) { item.dismount(true); }); } // console.log('dismounting item:', i, ':', item);
      if (!ignoreParentStates) {
        this.unhappy = this.modified = ''; this.updateParentStates(); this.updateDOM();
        parent[parent.childItemsType] = parent.childItems = this.removeItem(parent.childItems || [], this);
      }
      if (this.$elm) { this.$elm.removeData(this.id); this.$elm = null; }
    },
    getClass: function (elm) { return (elm.getAttribute && elm.getAttribute('class')) || ''; },
    setClass: function (elm, cln) { return cln ? elm.setAttribute('class', cln) : elm.removeAttribute('class'); },
    addClass: function (elm, cln) {
      //console.log('ADD Class-before: cln:', cln, ', elm:', elm);
      if (elm.classList) { elm.classList.add(cln); return; } // console.log('ADD Class-after: cln:', cln, ', elm:', elm);
      var cls = this.getClass(elm), cll = cls.split(' '), rcls = this.removeItem(cll, cln).join(' ');
      if (rcls === cls) { this.setClass(elm, rcls ? (rcls + ' ' + cln) : cln); return true; }
    },
    removeClass: function (elm, cln) { if (elm.classList) { return elm.classList.remove(cln); }
      var cls = this.getClass(elm), cll = cls.split(' '), rcls = this.removeItem(cll, cln).join(' ');
      if (rcls !== cls) { this.setClass(elm, rcls); return true;  }
    },
    toggleClass: function (elm, cln, state) { return state ? this.addClass(elm, cln) : this.removeClass(elm, cln); },
    removeItem: function (list, val) {
      var i, n = list.length, rlist = []; for (i = 0; i < n; i++) { if (list[i] !== val) { rlist.push(list[i]); } }
      return rlist;
    },
    //getChildItem: function (childItemId) { var i, n = this.childItems.length; for (i = 0; i < n; i++) { if (this.childItems[i].id === childItemId) { return this.childItems[i]; } } },
    updateStateType: function (stateType, addItem, itemId) {
      var stateItemsProp = stateType + 'Items', stateItems = this[stateItemsProp], curState = this[stateType], newState;
      stateItems = this.removeItem(stateItems, itemId);
      if (addItem) { stateItems.push(itemId); }
      //console.log('HappyItem.updateStateType(' + stateType + '), stateItems-after =', stateItems);
      newState = stateItems.join(',');
      if (curState !== newState) { this[stateType] = newState; this[stateItemsProp] = stateItems; return true; }
    },
    updateDOM: function () {
      //console.log('HappyItem.updateDOM(), this:', this.id);
      var parent = this.happyParent || {}, updHappy = this.unhappy !== this.lastUnhappy, updModified = this.modified !== this.lastModified;
      //console.log('HappyItem.UPDATE-DOM(), this.id =', this.id, ', this.unhappyItems =', this.unhappyItems, ', updHappy:', updHappy, ', updModified:', updModified);
      //console.log('HappyItem.UPDATE-DOM(), parent.id =', parent.id, ', parent.unhappyItems =', parent.unhappyItems, ', parent.happyType:', parent.happyType);
      if (updHappy && this.$elm) { this.toggleClass(this.$elm[0], this.unhappyClass, this.unhappy.length > 0); this.lastUnhappy = this.unhappy; }
      if (updModified && this.$elm) { this.toggleClass(this.$elm[0], this.modifiedClass, this.modified.length > 0); this.lastModified = this.modified; }
      if ((updHappy || updModified) && parent && parent.happyType) { parent.updateDOM(); }
    },
    updateParentStates: function () {
      var parent = this.happyParent || {}, updHappy = this.unhappy !== this.lastUnhappy, updModified = this.modified !== this.lastModified;
      //console.log('HappyItem.UPDATE-PARENT-STATES(), this:', this.id, ', this.unhappyItems:', this.unhappyItems, ', updHappy:', updHappy, ', updModified:', updModified);
      if (!parent || !parent.happyType) { return; }
      if (updHappy) { updHappy = parent.updateStateType('unhappy', this.unhappy.length > 0, this.id); }
      if (updModified) { updModified = parent.updateStateType('modified', this.modified.length > 0, this.id); }
      //console.log('HappyItem.UPDATE-PARENT-STATES(), parent:', parent.id, ', parent.unhappyItems:', parent.unhappyItems, ', parent.lastUnhappy:',
        //parent.lastUnhappy, ', parent.lastModified:', parent.lastModified);
      if (updHappy || updModified) { parent.updateParentStates(); }
    },
    firstUnhappyChild: function () { var i, n = this.childItems.length; for (i = 0; i < n; i++) { if (this.childItems[i].unhappy) { return this.childItems[i]; } } },
    firstUnhappy: function (happyType) { var item = this; if (!happyType) { happyType = 'input'; } console.log('HappyItem.firstUnhappy(), item:', item);
      while (item && item.happyType !== happyType && item.unhappy) { item = item.firstUnhappyChild(); console.log('HappyItem.firstUnhappy(), next item:', item); }
      //if (item && item.unhappy) { return (!happyType && item.happyType === 'field' && item.input) ? item.input : item; }
      if (item && item.unhappy) { return item; }
    },
    isHappy: function (event, isSubmit) { return !this.isUnhappy(event, isSubmit); },
    isUnhappy: function (event, isSubmit) {
      //console.log('HappyItem.isUnhappy(), this:', this.id);
      var i, n, r, o = this;
      if (this.beforeValidate) { r = this.beforeValidate(event, isSubmit); if (r) { return r; } }   // Before-validate hook
      if (o.validate && !o.preventValidate) { o.validate(event, isSubmit); }                        // Validate (Only HappyFields have a "validate" method by default)
      else { for (i = 0, n = o.childItems.length; i < n; i++) {                                     // Keep drilling down until we get to "field" level.
        if (o.childItems[i].isUnhappy(event, isSubmit) < 0) { return -1; } } }                      // return -1 ==> We found an unhappy child! Abort remaining tests.
      if (o.unhappy) { if (o.onUnhappy) { o.onUnhappy(event, isSubmit); } }                         // Unhappy hook
      else { if (o.onHappy) { o.onHappy(event, isSubmit); } }                                       // Happy hook
      if (o.modified) { if (o.onModified) { o.onModified(event, isSubmit); } }                      // Modified hook
      if (o.beforeUpdate) { r = o.beforeUpdate(event, isSubmit); if (r) { return r; } }             // After-validate but BEFORE updating parents and DOM hook
      if (o.happyType === 'field') { o.updateParentStates(); o.updateDOM(); }
      if (o.afterValidate) { r = o.afterValidate(event, isSubmit); if (r) { return r; } }           // After-validate hook
      return isSubmit ? (o.unhappy ? -1 : 0) : o.unhappy;                                           // -1 + Submit -> Quit validation loop / Error found / Only set on child level
    }
  };

  function HappyInput(field, id, $happyInput) {
    HappyItem.call(this, 'input', 'values', $happyInput, {}, field);
    this.field = this.happyParent;
    this.id = this.id || $happyInput[0].id || field.id + '_' + id;
    this.type = this.$elm.attr('type') || this.$elm.data('type') || 'text';
    this.testWhen = this.resolveOpt('testWhen') || ['blur']; // Do we use this?
    this.clean = this.getOpt('clean');
    this.$elm.data(this.id, this);
    //console.log('New HappyInput:', this);
  }
  HappyInput.prototype = Object.create(HappyItem.prototype);
  HappyInput.prototype.val = function inputVal(initial) {
    var val, beforeClean;
    switch (this.type) {
    case 'radio':
    case 'checkbox': val = this.$elm.prop('checked') ? (this.$elm.val() || 1) : '';
    // console.log('type:', this.type, ', val:', val);
    break;
    case 'option': val = this.$elm.prop('selected') ? (this.$elm.val() || 1) : ''; break;
    default:
      val = this.$elm.val();
      if (this.clean) {
        beforeClean = val; val = this.clean(val);
        if (val !== beforeClean) { this.$elm.val(val); }
      }
    }
    if (initial || this.field.preventModified) { this.initialValue = val; }
    this.modified = (val !== this.initialValue);
    this.lastValue = this.cachedValue;
    return (this.cachedValue = val);
  };
  HappyInput.prototype.reset = function resetInputValue() {
    this.$elm.val(this.initialValue); this.cachedValue = this.initialValue;
    this.modified = this.lastModified = ''; this.unhappy = this.lastUnhappy = '';
  };
  HappyInput.prototype.undo = function restoreLastInputValue() {
    this.$elm.val(this.lastValue); this.cachedValue = this.lastValue;
  };
  HappyInput.prototype.dismount = function dismountInput(ignoreParentStates) {
    var input = this;
    $.each(input.testWhen, function (i, testWhen) {
      if (testWhen === 'blur.field') { input.$elm.off('focus.' + input.id); }
      input.$elm.off(testWhen + '.' + input.id);
    });
    this.ancestor.dismount.call(this, ignoreParentStates);
  };
  HappyInput.prototype.mount = function mountInput() {
    var input = this, field = input.field, happyForm = field.form;
    // console.log('Input.testWhen for:', input.id, ' =', input.testWhen);
    $.each(castArray(input.testWhen), function (i, testWhen) {
      if (testWhen === 'blur.field') {
        // console.log('Setting onFocus for:', input.id);
        input.$elm.on('focus.' + input.id, function handleFocusInput(event) {
          if (happyForm.validateTimer) {
            clearTimeout(happyForm.validateTimer);
            if (happyForm.currentField && happyForm.currentField !== field && happyForm.currentField !== field.next) {
              happyForm.currentField.isUnhappy(event, false);
            }
          }
          happyForm.currentField = field;
        });
        input.$elm.on('change.' + input.id, function handleChangeInput(event) {
          if (field.unhappy) { field.isUnhappy(event, false); }
        });
      }
      input.$elm.on(testWhen + '.' + input.id, function handleValidateInput(event) {
        if (testWhen === 'blur.field')  {
          if (happyForm.validateTimer) { clearTimeout(happyForm.validateTimer); }
          happyForm.validateTimer = setTimeout(function () { field.isUnhappy(event, false); }, 300);
        } else {
          field.isUnhappy(event, false);
        }
      });
    });
  };

  // NB: Property assign order matters for this class!
  function HappyField(happyForm, $happyField, options) {
    var opt;
    HappyItem.call(this, 'field', 'inputs', $happyField, options, happyForm);
    if ( ! this.id) { this.id = this.happyType + nextHappyId++; }
    $.extend(this, unserialize(this.$elm[0].getAttribute('data-happy')));
    this.childItems = this.inputs = this.getInputs();
    this.input = this.inputs[0];
    this.$elm.data(this.id, this);
    this.label = this.getOpt('label') || this.$elm.data('label');
    this.error = new HappyError(this);
    this.required = this.$elm[0].hasAttribute('required') || this.getOpt('required') || (this.input && this.input.$elm[0].hasAttribute('required'));
    this.type = this.$elm.data('type') || this.resolveOpt('fieldType') || (this.input && this.input.type) || 'text'; // OR textarea, checkgroup, radiogroup, etc.
    opt = this.happyParent.getOpt('messageSelector' );  if (opt) { this.messageSelector = opt;  }  // only override the existing proto method if an alternative is provided!
    opt = this.happyParent.getOpt('messageTemplate' );  if (opt) { this.messageTemplate = opt;  }
    opt = this.happyParent.getOpt('messageText'   );  if (opt) { this.messageText = opt;    }
    this.initialValue = this.val(true);
    //console.log('New HappyField:', this);
  }
  HappyField.prototype = Object.create(HappyItem.prototype);
  HappyField.prototype.val = function (initial) {
    var field = this, values = [];
    $.each(field.inputs, function addInputVal(i, input) { var v = input.val(initial); if (v || v === 0) { values.push(v); } });
    field.lastValue = field.cachedValue; field.cachedValue = values.length ? values.join(',') : '';
    field.modified = (initial || field.cachedValue === field.initialValue || field.preventModified) ? '' : 'yes';
    return field.cachedValue;
  };
  HappyField.prototype.reset = function (ignoreParentStates) {
    var field = this; $.each(field.inputs, function addInputVal(i, input) { input.reset(); });
    field.cachedValue = field.lastValue = field.initialValue = field.val(true); field.error.remove();
    field.lastModified = null; field.modified = '';
    if (!ignoreParentStates) { field.updateParentStates(); field.updateDOM(); }
  };
  HappyField.prototype.undo = function () {
    var field = this; $.each(field.inputs, function addInputVal(i, input) { input.undo(); });
    field.cachedValue = field.lastValue; field.cachedValue = field.val();
    return this.isUnhappy({}, false); // re-Validate!
  };
  HappyField.prototype.getInputs = function () {
    var field = this, $inputs, inputs = []; // , inputSelector = field.$elm.attr('data-inputselector')
    if ( ! defined(this.inputSelector)) { this.inputSelector = field.resolveOpt('inputSelector') || ':input'; }
    if (this.inputSelector.length) {
      $inputs = field.$elm.is(this.inputSelector) ? field.$elm : field.$elm.find(this.inputSelector);
      $inputs.each(function addInput(i, input) { inputs.push(new HappyInput(field, i + 1, $(input))); });
    }
    return inputs;
  };
  HappyField.prototype.findElement = function (query, referenceElement) {
    var selectors, selectorArgs, selector, arg, i = 0, field = this, $target, $ref = referenceElement ? $(referenceElement) : null;
    //console.log('HappyField.findElement(), query:', query, ', $ref:', $ref);
    if ( ! query || ! $ref) { return null; } // findElement params invalid
    if (query instanceof jQuery || typeof query === 'string' || query.nodeName) { $target = $(query); return $target.length ? $target : null; }
    selectors = castArray(query.selector); // selectors Array ['A','B','C'] => select A relative to $ref, then B rel to A, then C rel to B, etc.
    selectorArgs = castArray(query.arg); // e.g. selectors === ['next', 'find'], selectorArgs === ['tr', 'td']
    do { arg = selectorArgs[i];
       switch (selectors[i]) {
       case 'prev': $target = $.isNumeric(arg) ? $ref.prevAll().eq(arg) : (arg ? $ref.prevAll(arg).eq(0) : $ref.prev()); break;
       case 'next': $target = $.isNumeric(arg) ? $ref.nextAll().eq(arg) : (arg ? $ref.nextAll(arg).eq(0) : $ref.next()); break;
       case 'parent': $target = $.isNumeric(arg) ? $ref.parents().eq(arg) : (arg ? $ref.parents(arg).eq(0) : $ref.parent()); break;
       case 'find': $target = $ref.find(arg).eq(0); break;
       case 'field':  $target = field.$elm; break; }
       $ref = $target; i++; }
    while (i < selectors.length);
    return ($target && $target.length) ? $target : null;
  };
  HappyField.prototype.messageSelector = function (message) { return '#' + message.id; };
  HappyField.prototype.messageText = function (message) {
    //if (!message || $.type(message) === 'string') { return message; }
    //console.log('HappyField.messageText(), message:', message, ', this:', this);
    if (message.text !== null) { return message.text; } // we have a custom error
    message.text = (this.resolve(this.required) && !this.cachedValue.length) ? (this.resolveOpt('requiredText') || 'required') : (this.resolveOpt('errorText') || 'invalid value');
    return message.text;
  };
  HappyField.prototype.messageTemplate = function (message) { return '<span class="' + message.classAttr +'" id="' + message.id + '">' + this.resolve(this.messageText, [message]) + '</span>'; };
  HappyField.prototype.updateDOM = function () {
    var field = this, error = field.error, n = 0, addAnimateClass = function() { field.addClass(error.message.$elm[0], 'animate'); };
    //console.log('ERROR CHANGED:', field.error.message.changed(), ', ERROR:', field.error);
    while (!n && (field.unhappy !== field.lastUnhappy || field.error.message.changed())) {
      // KEEP, REPLACE or REMOVE the current error message
      if (error.message.$elm && error.message.$elm.length) {
        if (field.unhappy) {
          error.message.replace(field.messageTemplate(error.message));
          setTimeout(addAnimateClass);
        } else {
          error.remove();
        }
        break;
      }
      // INSERT a new error message
      if (field.unhappy) {
        //console.log('HappyField.updateDOM(), NEW Error');
        if (error.$target && error.$target[0]) { field.addClass(error.$target[0], error.errorClass); }
        error.message.$elm = $(field.messageTemplate(error.message));
        switch (error.message.placement) {
        case 'before':  error.message.$anchor.before(error.message.$elm);  break;
        case 'append':  error.message.$anchor.append(error.message.$elm);  break;
        case 'prepend': error.message.$anchor.prepend(error.message.$elm); break;
        default: error.message.placement = 'after'; error.message.$anchor.after(error.message.$elm); }
        error.message.old = error.message.text;
        setTimeout(addAnimateClass);
        break;
      }
      n++;
    }
    this.ancestor.updateDOM.call(this);
  };
  HappyField.showError = function (errorText, errorType) {
    this.error.type = errorType; this.error.message.text = errorText;
    field.unhappy = 'yes'; field.updateParentStates(); field.updateDOM();
  };
  HappyField.prototype.validate = function (event, isSubmit) {
    var i, n, field = this, isHappy = true,
      error = field.error, errorParts,
      testArgs = castArray(field.testArgs),
      happyTests = castArray(field.testHappy),
      fieldValue = field.val();
    error.type = null;
    error.message.text = null;
    if (field.resolve(field.required) && !fieldValue.length) {
      isHappy = false;
      error.type = 'required';
      error.message.text = null;
    } else {
      if (happyTests.length) {
        //console.log('HappyField.validate(), field:', field.id, ', Array of tests :', happyTests, ', testArgs:', testArgs, ', fieldValue:', fieldValue);
        for (i = 0, n = happyTests.length; i < n; i++) {
          //console.log('Test No.', i, ',', happyTests[i]);
          isHappy = happyTests[i].call(field, fieldValue, testArgs[i], isSubmit, event); // happyTests[i](), this === field
          if (isHappy !== true) { break; }
        }
      }
      if ( ! isHappy && ! this.error.message.text && this.errorText) { this.error.message.text = this.errorText; }
    }
    //console.log('HappyField::validate(), IS HAPPY:', isHappy);
    if (isHappy === true) {
      error.message.text = null;
      field.unhappy = '';
      $.each(field.inputs, function (i, input) { input.unhappy = ''; });
      return true;
    }
    if (isHappy instanceof Error) { // testHappy returned a custom error message
      errorParts = isHappy.message.split('|');
      if (errorParts[1]) {
        this.error.type = errorParts[0];
        this.error.message.text = errorParts[1];
      } else {
        this.error.type = '';
        this.error.message.text = errorParts[0];
      }
    }
    field.unhappy = 'yes';
    if ( ! field.firstUnhappyChild()) { field.input.unhappy = 'yes'; } // Makes item.firstUnhappy() work!
    return false;
  };
  HappyField.prototype.handleValidate = function (event) {
    var field = event.data;
    //console.log('HappyField.' + field.id + '.HANDLE-VALIDATE');
    field.isUnhappy(event, false);
  };
  HappyField.prototype.dismount = function (ignoreParentStates) {
    this.error.remove();
    this.ancestor.dismount.call(this, ignoreParentStates);
  };

  function HappyForm($happyForm, options, parent) {
    var opts = $.extend({}, options), fieldsetOptions = opts.fields || {};
    delete opts.fields;
    HappyItem.call(this, 'form', 'fields', $happyForm, opts, parent);
    this.id = this.id || (this.happyType + nextHappyId++);
    if (this.$elm) { this.$elm.data(this.id, this); }
    this.fieldsetOptions = fieldsetOptions;
    //console.log('New HappyForm:', this);
  }
  HappyForm.prototype = Object.create(HappyItem.prototype);
  HappyForm.prototype.mount = function () {
    var form = this, $fieldset, fieldElements = [], combinedFieldOptions = [], lastField, lastInput;
    $.each(form.fieldsetOptions, function setUpdateFieldsetOptions(fieldName, fieldOptions) {
      $fieldset = $(fieldOptions.fieldSelector || fieldName, (form.$elm || $(window)));
      $fieldset.each(function setUpdateFieldOptions(i, elField) {
        var elFieldIndex = $.inArray(elField, fieldElements);
        if (elFieldIndex >= 0) {
          var newOptionsObj = $.extend({}, combinedFieldOptions[elFieldIndex], fieldOptions);
          combinedFieldOptions[elFieldIndex] = newOptionsObj;
        } else {
          fieldElements.push(elField);
          combinedFieldOptions.push(fieldOptions);
        }
      });
    });
    $.each(fieldElements, function makeHappyAndMount(i, elField) {
      var happyField = new HappyField(form, $(elField), combinedFieldOptions[i]);
      happyField.mount();
      $.each(happyField.inputs, function linkHappyInput(i, happyInput) {
        happyInput.prev = lastInput;
        happyInput.next = null;
        if (lastInput) { lastInput.next = happyInput; }
        lastInput = happyInput;
      });
      happyField.prev = lastField;
      happyField.next = null;
      if (lastField) { lastField.next = happyField; }
      lastField = happyField;
    });
    form.childElements = fieldElements;
  };

  function HappyGroup($happyGroup, options, doc) {
    HappyItem.call(this, 'group', 'forms', $happyGroup, options, doc || defaultHappyDoc);
    this.id = this.id || (this.happyType + nextHappyId++);
    if (this.$elm) { this.$elm.data(this.id, this); }
    //console.log('New HappyGroup:', this);
  }
  HappyGroup.prototype = Object.create(HappyItem.prototype);

  function HappyDocument($happyDoc, options) {
    HappyItem.call(this, 'doc', 'groups', $happyDoc, options, { id: 'dummy', getOpt: $.noop, childItems: [] });
    this.id = this.id || (this.happyType + nextHappyId++);
    if (this.$elm) { this.$elm.data(this.id, this); }
    //console.log('New HappyDocument:', this);
  }
  HappyDocument.prototype = Object.create(HappyItem.prototype);

  defaultHappyDoc = new HappyDocument(null, { id: 'defaultDoc' });

  $.fn.extend({
    isHappyDocument: function (options) {
      var doc = new HappyDocument(this, options);
      doc.mount();
      return this;
    },
    isHappyGroup: function (options, $doc, docId) {
      var group, doc = getInstanceOf('doc', $doc.data(), docId);
      group = new HappyGroup(this, options, doc);
      group.mount();
      return this;
    },
    isHappyForm: function (options, $parent, parentId) {
      var form, group, doc, jqData;
      //console.log('HAPPY FORM Start:', this);
      if ($parent) {
        jqData = $parent.data();
        group = getInstanceOf('group', jqData, parentId);
        if (!group) {
          doc = getInstanceOf('doc', jqData, parentId);
          //group = new HappyGroup(null, {}, doc);
        }
      }
      form = new HappyForm(this, options, group || doc || defaultHappyDoc);
      form.mount();
      return this;
    }
  });

}(this.jQuery || this.Zepto));
