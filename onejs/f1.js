/* global jQuery, $, moment, accounting */
/* jslint sloppy:true */
/*jshint browser:true */

/**
 * f1.js
 *
 * OneFile PHP Framework, Javascript Support Library
 *
 * @author: C. Moller
 * @date: 22 Jan 2018
 *
 */

window.F1 = window.F1 || {};

F1.Url = {

  base: '',
  pageref: '',
  csrfToken: '',
  query: '',
  query_params: {},
  segments: [],

    location: function() { return window.history.emulate ? window.history.location : window.location; },

    current: function() { return F1.Url.location().href; },

    normalize: function(url) {
      url = url || F1.Url.current();
      return url;
    },

  // Parse the current window location and unpack the resulting url_info into Url
  init: function()
  {
    var href = F1.Url.current();
    var url_info = F1.Url.parse(href.toString());

    F1.Url.base = url_info.base;
    F1.Url.assets = F1.Url.base + 'kd/';
    F1.Url.pageref = url_info.pageref; //Excludes Query String! - NM 10 Jun 2013
    F1.Url.query = url_info.query;
    F1.Url.query_params = url_info.query_params;
    F1.Url.segments = url_info.segments;

    window.onpopstate = F1.Url.popState;
  },

  parse: function(url)
  {
    var url_info = { current: url, base: '', pageref: '', query: '', query_params: {}, segments: [] };
    var parts = url.split('?');
    url_info.base = $('base').attr('href') || location.origin + '/';

    url_info.pageref = parts[0];

    if(parts.length > 1) {
      url_info.query = parts[1];
      var params = url_info.query.split('&');
      $.each(params, function(index, value) {
        var pair = value.split('=');
        if(pair.length > 1) {
          url_info.query_params[pair[0]] = pair[1];
        }
        else {
          url_info.query_params[pair[0]] = true;
        }
      });
    }

    url_info.segments = url_info.pageref.split('/');

    return url_info;
  },

  //This turns out to be like matching a specific route! Maybe use lib like Sammy or Backbone later...
  match: function(href)
  {
    if(href === F1.Url.pageref) { return true; } else { return false; }
  },

  make: function(params)
  {
    var url, paramindex, paramsets = [], i = 0;
    for (paramindex in F1.Url.query_params) {
      if (typeof params[paramindex] === "undefined" && paramindex !== "dlg") {
        paramsets[i] = paramindex + '=' + F1.Url.query_params[paramindex];
        i++;
      }
    }
    for (paramindex in params) {
      if (params.hasOwnProperty(paramindex)) {
        paramsets[i] = paramindex + '=' + params[paramindex];
        i++;
      }
    }
    url = F1.Url.pageref + (paramsets.length ? '?' + paramsets.join('&') : '');
    return url;
  },

  pushState: function(url, title, ignoreExitTest)
  {
    var r, state = {'url':url, 'title':title}; // 'title' not supported in most browsers!
    if (!ignoreExitTest) { r = F1.Events.beforeExit({ type: 'pushstate', url: url }); if (r === false) { return false; } }
    history.pushState(state, state.title, state.url);
    return true;
  },

  popState: function(event)
  {
    url = event.state ? event.state.url : "";
    if (F1.Events.beforeExit({ type: 'popstate', url: url }) === false) { F1.Url.pushState(F1.Url.current(), '', true); return false; }
    F1.Ajax.load(url);
    F1.Url.init(); // Before loading we pushed a new window location, so now update our Url object
    $('body > .modal-backdrop').remove();
  },

  back: function(event, distance)
  {
    F1.Events.stop(event);
    distance = distance ? (-1 * distance) : -1;
    history.go(distance);
  }
};
// end: F1.Url


//---------------------------------- AJAX -----------------------------------

F1.Ajax = {};


//---------------------------------- EVENT -----------------------------------

F1.Events = {

  exitScripts: [],
  beforeSubmitScripts: {},

  run: function (scripts, event)
  {
    var i, r;
    if ( ! scripts || ! scripts.length) { return; }
    for (i = 0; i < scripts.length; i++)
    {
      r = scripts[i](event); // run script
      if (F1.isDefined(r)) { return r; }
    }
  },

  /**
   * addBeforeSubmitScript
   *
   * @param string    formId    HTML-id of the affected form element
   * @param fn|array  script    A single function object or an array of function objects: fn(event, action, params)
   * @param boolean   prepend   Add the fn or list of functions BEFORE other existing functions.
   *
   * @return null
   */
  addBeforeSubmitScript: function(formId, script, prepend) {
    var formScripts = F1.Events.beforeSubmitScripts[formId] || [];
    var scriptsToAdd = $.type(script) === 'array' ? script : [script];
    if (prepend) { F1.Events.beforeSubmitScripts[formId] = scriptsToAdd.concat(formScripts); }
    else { F1.Events.beforeSubmitScripts[formId] = formScripts.concat(scriptsToAdd); }
  },

  beforeSubmit: function(event, action, params) {
    var lastScriptResult, form = event.target;
    if (!form.id) { return; }
    $.each(F1.Events.beforeSubmitScripts[form.id] || [], function (i, beforeSubmitScript) {
      return (lastScriptResult = beforeSubmitScript(event, action, params)); // return == false == $.each->break
    });
    return lastScriptResult;
  },

  addExitScript: function(script, prepend) {
    var scriptsToAdd = $.type(script) === 'array' ? script : [script];
    if (prepend) { F1.Events.exitScripts = scriptsToAdd.concat(F1.Events.exitScripts); }
    else { F1.Events.exitScripts = F1.Events.exitScripts.concat(scriptsToAdd); }
  },

  beforeExit: function(event)
  {
    return F1.Events.run(F1.Events.exitScripts, event);
  },

  stop: function (event)
  {
    event.stopPropagation();
    event.preventDefault();
    event.cancelBubble = true;
  }

};
// end: F1.Event


//-------------------------------- TEMPLATE -----------------------------------

F1.Template = {

  // Copyright (C) 2013 Krasimir Tsonev, http://krasimirtsonev.com
  // https://github.com/krasimir/absurd/blob/master/LICENSE
  // TODO: Safe data EVAL!? Script injection protection.
  compile: function(html, options)
  {
    var re = /<%(.+?)%>/g,
      reExp = /(^( )?(var|if|for|else|switch|case|break|{|}|;))(.*)?/g,
      code = 'with(obj) { var r=[];\n',
      cursor = 0,
      result,
      match;

    var add = function(line, js) {
      if (js) {
          code += line.match(reExp) ? line + '\n' : 'r.push(' + line + ');\n';
      } else {
        code += line !== '' ? 'r.push("' + line.replace(/"/g, '\\"') + '");\n' : '';
      }
      return add;
    };

        match = re.exec(html);
    while (match) {
      add(html.slice(cursor, match.index))(match[1], true);
      cursor = match.index + match[0].length;
      match = re.exec(html);
    }

    add(html.substr(cursor, html.length - cursor));

    code = (code + 'return r.join(""); }').replace(/[\r\t\n]/g, ' ');

        /* jshint evil:true */
    try { result = new Function('obj', code).apply(options, [options]); }
    catch(err) { throw Error("'" + err.message + "'", " in \n\nCode:\n", code, "\n"); } // throw or log error?

    return result;
  },

  trim: function(tplHtml)
  {
    return $.trim(tplHtml.replace('/**', '').replace('**/', ''));
  }

};
// end: F1.Template


//------------------------------------- FILE ---------------------------------------

F1.File = {

  /**
   * C. Moller - 23 Nov 2017
   *
   * NOTE: Before you cry about this component not working!
   *  - Uploader currently uses "F1.Url.assets" to reference default images like "unknown.svg"
   *  - Uploader assumes the upload API path is: api/upload.php! (i.e. relative to DOCROOT or whatever <base href="?"> is set to)
   *  - Uploader uses ES5+ features so don't expect it to work on <= IE10 or old Android devices without SHIMS
   *  - Uploader uplCfg.onChange option MUST be SET and its value=Fn MUST be declared BEFORE setting the option!
   *  - Uploader assumes specific error state classes, so check your CSS.
   *
   * Values like F1.Url.assets should be set from PHP via header incl. script
   * File.uploader's default images and BaseUrl can be set from either F1.Url or during initialisation through the Options param.
   *
   * There is also a PHP side to uploader in the ui.php service, Widgets/Form/fileinput and in api/uploader.php.
   * Check that file paths are correctly defined in these places too!
   *
   * Example client side init:
   * =========================
   *
   * F1.File.uploader('logo-field', { maxFiles: 2, maxSize: (256  * 1024), ref: 'photos', onChange: F1.MyPhotos.happyTestIfUploadFieldIsValid });
   *
   * @param object options {
   *     maxFiles: (int)?
   *     maxSize:  (int)? in bytes
   *     ref:      (str)? An upload-group-identifier used by the backend (server) to determine how to process any files in this group.
   *     onChange: (fn(elUploader))? used in F1.File.onUploadsChange. Very important to allow the client to react to changes!
   * }
   *
   */
  uploader: function (elUploader_id, options)
  {
    var elUploader = document.getElementById(elUploader_id), cfg = {};
    if ( ! elUploader) { throw Error('ERROR: Could not find FILE UPLOADER:', elUploader_id); }
    cfg.viewzone = elUploader.getElementsByClassName('file-viewzone')[0];
    cfg.dropzone = elUploader.getElementsByClassName('file-dropzone')[0];
    cfg.template = F1.Template.trim(elUploader.getElementsByTagName('script')[0].innerHTML);
    cfg.initialUploads = F1.File.getUploads(cfg.viewzone);
    cfg.uploads = cfg.initialUploads.slice(0); // NB: We MUST clone!
    cfg.fc = cfg.dropzone.getElementsByTagName('input')[0];
    cfg.options = options || {};
    elUploader.uplCfg = cfg;
    if (F1.isDefined(cfg.fc.files) && F1.isDefined(FileReader))
    { // HTML5 Ok :-)
      cfg.dropzone.addEventListener("dragover", F1.Events.stop, false);
      cfg.dropzone.addEventListener("dragenter", F1.Events.stop, false);
      cfg.dropzone.addEventListener("drop", function(e) { F1.File.html5Upload(e, e.dataTransfer.files, elUploader); }, false);
      cfg.fc.addEventListener("change", function(e) { F1.File.html5Upload(e, cfg.fc.files, elUploader); }, false);
      cfg.fc.addEventListener("focus", function(e) { this.parentElement.classList.add('focussed'); }, false);
      cfg.fc.addEventListener("blur", function(e) { this.parentElement.classList.remove('focussed'); }, false);
    } else
    { // Old Browser - No HTML5 :-(
      //console.warn('File uploader requires a HTML5 compatable browser.');
      $(cfg.dropzone).find('span').remove(); cfg.fc.className = 'input';
    }
    return elUploader;
  },

  html5Upload: function(event, files, elUploader)
  {
    F1.Events.stop(event);
    var i, err, maxFiles, maxSize, allowedTypes, nFiles = files.length, cfg = elUploader.uplCfg, nExisting = cfg.uploads.length;
    //console.log('F1.File.html5Upload(), event =', e, ', files =', files, ', uploader = ', elUploader);
    maxFiles = cfg.options.maxFiles || 1; maxSize = cfg.options.maxSize || (1024*115);
    allowedTypes = cfg.options.allowedTypes || ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'application/pdf'];
    if ((nExisting + nFiles) > maxFiles) { alert('Maksimum items toegelaat: ' + maxFiles); return false; }
    for (i = 0; i < nFiles; i++) { if (files[i].size > maxSize) { alert('Maksimum grootte per item: ' + F1.File.sizeToString(maxSize)); return false; } }
    for (i = 0; i < nFiles; i++) { if (allowedTypes.indexOf(files[i].type) === -1) { alert('Slegs die volgende word toegelaat:\n' + allowedTypes.toString()); return false; } }
    for (i = 0; i < nFiles; i++) { if (F1.File.inUploads(cfg.uploads, files[i])) { if (confirm(files[i].name + " is reeds opgelaai. Vervang?")) { return false; } } }
    for (i = 0; i < nFiles; i++) {
      err = F1.File.addFileView(elUploader, cfg.viewzone, files[i], i, nExisting, /^image\//.test(files[i].type), !cfg.options.manualUpload);
      if (err) { alert(err); return false; }
    }
    if (cfg.uploads.length) cfg.viewzone.classList.remove('hidden');
  },

  addFileView: function(elUploader, elViewZone, file, index, nExisting, getPreview, autoUpload)
  {
    var err, fvModel = {}, fvData = {}, uploadedFile, elFileView, elError, cfg = elUploader.uplCfg;
    fvData.index = nExisting + index;
    fvData.id   = Date.now();
    fvData.size = file.size;
    fvData.type = file.type;
    fvData.desc = file.name;
    fvData.name = file.type === 'application/pdf' ? F1.Url.assets + 'img/pdf.svg' : F1.Url.assets + 'img/unknown.svg';
    fvData.sizeAsStr = F1.File.sizeToString(file.size);
    uploadedFile = F1.File.inUploads(cfg.uploads, fvData);
    if (uploadedFile) {
      elFileView = uploadedFile.view;
      elError = elFileView.getElementsByClassName('error-text')[0];
      if (elError) { elError.remove(); }
    } else {
      elFileView = document.createElement('div');
      elFileView.className = 'file-view';
      elFileView.innerHTML = F1.Template.compile(cfg.template, fvData);
      cfg.uploads.push({ view: elFileView, name: fvData.name, size: fvData.sizeAsStr, type: fvData.typee });
    }
    //console.log('F1.File.addFileView(), getPreview = "' + getPreview + '", file =', file, ', fvData =', fvData);
    fvModel.file = file;
    fvModel.data = fvData;
    fvModel.fv = elFileView;
    fvModel.img = elFileView.getElementsByTagName('img')[0];
    fvModel.desc = elFileView.getElementsByClassName('file-description')[0];
    fvModel.progress = elFileView.getElementsByClassName('file-progress')[0];
    fvModel.elUploader = elUploader;
    elViewZone.appendChild(elFileView);
    if (getPreview || fvData.name !== (F1.Url.assets + 'img/unknown.svg')) { fvModel.progress.classList.remove('hidden'); }
    if (getPreview) { F1.File.getImageDataURL(fvModel); }
    if (autoUpload) { F1.File.ajaxSender(fvModel); }
    return err;
  },

  getUploadErrors: function (elUploader)
  {
    var i, n, cfg = elUploader.uplCfg, errors = [];
    if (!cfg) { return; }
    n = cfg.uploads.length; for (i = 0; i < n; i++) { if (cfg.uploads[i].view.classList.contains('has-error')) { errors.push(cfg.uploads[i]); break; } }
    return errors;
  },

  onUploadsChange: function(elUploader)
  {
    // console.log('F1.File.onUploadsChange(), elUploader =', elUploader, ', elUploader.uplCfg =', elUploader.uplCfg);

    var r, errors, errorTextElements, cfg = elUploader.uplCfg;

    if (!cfg) { return; }

    // NOTE: If options.onChange is defined AND we return a value (anything DEFINED, even false!)
    // from that function, we exit this method without clearing any errors the default way!
    if (cfg.options.onChange) {
      r = cfg.options.onChange(elUploader);
      // console.log('CUSTOM onChange(), result:', r);
      if (F1.isDefined(r)) { return r; }
    }

    errors = F1.File.getUploadErrors(elUploader);

    if (errors.length) {
      elUploader.classList.add('has-error');
      // console.log('F1.File.onUploadsChange(), ADDED HAS-ERROR');
    } else {
      elUploader.classList.remove('has-error');
      // NM Edit - 28 Nov 2017
      errorTextElements = elUploader.getElementsByClassName('error-text');
      for (i in errorTextElements) { errorTextElements[i].innerHTML = ''; };
      // console.log('F1.File.onUploadsChange(), REMOVED .has-error classes and error messages inside:', errorTextElements);
    }

    return errors;
  },

  ajaxSender: function(fvm)
  {
    //console.log('F1.File.ajaxSender(), fvm =', fvm);
    setTimeout(function()
    {
      var xhr = new XMLHttpRequest(), fd = new FormData(), elProgress = fvm.progress, cfg = fvm.elUploader.uplCfg;
      xhr.responseType = 'json'; fd.append('file', fvm.file); fd.append('ref', (cfg.options.ref || ''));
      xhr.upload.onprogress = function(e) {
        //console.log('xhr.upload.onprogress(), e =', e);
        if ( ! e.lengthComputable) { return; }
        var strPercent = Math.min(98, Math.round((e.loaded*100)/e.total)).toString() + '%';
        //console.log('xhr.upload.onprogress(), elProgress =', elProgress, ', strPercent = ' + strPercent);
        elProgress.firstElementChild.style = 'width:' + strPercent;
        elProgress.lastElementChild.innerHTML = '<span>' + strPercent + '%</span>';
      };
      xhr.onload = function(e) { // TODO: Handle multiple fileinfo records returned in the same request.
        var elName, elSize, elType, elLink, file;
        //console.log('xhr.onload(), Upload complete, resp =', xhr.response);
        if (xhr.status < 200 || xhr.status >= 300) { return F1.File.onError(e, xhr, fvm); }
        file = xhr.response[0];
        elProgress.firstElementChild.style = 'width:100%; background-color:chartreuse;';
        elProgress.lastElementChild.innerHTML = '<span>100%</span>';
        elName = fvm.fv.getElementsByClassName('input filename')[0];
        elSize = fvm.fv.getElementsByClassName('input filesize')[0];
        elType = fvm.fv.getElementsByClassName('input filetype')[0];
        elLink = fvm.fv.getElementsByTagName('a')[0];
        elName.value = file.uri + '/' + file.name;
        elSize.value = file.size;
        elType.value = file.type;
        elLink.href = elName.value;
        F1.File.onUploadsChange(fvm.elUploader);
        //console.log('xhr.onload(), Upload successful!');
      };
      xhr.onerror = function(e) { return F1.File.onError(e, xhr); };
      xhr.open('post', 'api/upload.php'); xhr.send(fd);
    }, 300 + fvm.data.index*300);
  },

  onError: function(event, xhr, fvm)
  {
    var r, error, elErrMsg, elActions, cfg = fvm.elUploader.uplCfg;
    if ( ! xhr.response) { error = 'Http Error ' + xhr.status.toString() + ' - ' + xhr.statusText; } else { error = xhr.response.error; }
    //console.log('F1.File.onError(), event =', event, ', xhr =', xhr, ', resp =', xhr.response, ', error =', error);
    if (cfg.options.onError) { r = cfg.options.onError(event, fvm, error); if (F1.isDefined(r)) { return r; } }
    elErrMsg = document.createElement('div'); elErrMsg.innerHTML = '<b>ERROR:</b> ' + error; elErrMsg.className = 'help-block error-text';
    elActions = fvm.desc.nextElementSibling; fvm.fv.insertBefore(elErrMsg, elActions); fvm.fv.classList.add('has-error');
    F1.File.onUploadsChange(fvm.elUploader);
    elActions.firstElementChild.focus();
    return error;
  },

  onImageRead: function(img) { return function(e) { img.src = e.target.result; }; },

  getImageDataURL: function(fvm)
  {
    var reader = new FileReader(); reader.onload = F1.File.onImageRead(fvm.img); reader.readAsDataURL(fvm.file);
  },

  isSame: function(file1, file2)
  {
    return (file1.name === file2.name && file1.size === file2.size);
  },

  getUploads: function (elViewZone)
  {
    var i, inputs, uploads = [],
    fileViews = elViewZone.getElementsByClassName('file-view'),
    n = fileViews.length;
    for (i = 0; i < n; i++) {
      inputs = fileViews[i].getElementsByClassName('input');
      uploads.push({ view: fileViews[i], name: inputs[0].value, size: inputs[1].value, type: inputs[2].value });
    }
    return uploads;
  },

  uploadsModified: function (initialUploads, uploads)
  {
    var i, n = initialUploads.length;
    if (n !== uploads.length) { return true; }
    for (i = 0; i < n; i++) { if ( ! F1.File.isSame(initialUploads[i], uploads[i])) { return true; }  }
  },

  inUploads: function (uploads, fileData)
  {
    var i, n = uploads.length; for (i = 0; i < n; i++) { if (F1.File.isSame(fileData, uploads[i])) { return uploads[i]; } }
  },

  removeFileView: function (elUploader, elFileView)
  {
    var i, cfg = elUploader.uplCfg, n = cfg.uploads.length, result = [];
    for (i = 0; i < n; i++) { if (cfg.uploads[i].view === elFileView) { cfg.uploads[i].view = null; } else { result.push(cfg.uploads[i]); } }
    elFileView.remove();
        return (cfg.uploads = result);
  },

  removeUpload: function (elRemoveBtn)
  {
    console.log('Removing Upload!');
    var r, errors, elFileView = elRemoveBtn.parentElement.parentElement,
    elUploader = elFileView.parentElement.parentElement.parentElement, cfg = elUploader.uplCfg;
    r = F1.File.removeFileView(elUploader, elFileView);
    if (!r.length) { cfg.viewzone.classList.add('hidden'); }
    errors = F1.File.onUploadsChange(elUploader);
    if (!errors || !errors.length) { cfg.fc.focus(); }
  },

  sizeToString: function(nBytes)
  {
    var result = nBytes + ' bytes', aMultiples = ['KB', 'MB', 'GB', 'TB', 'PB', 'EB'], nMultiple, nApprox = nBytes / 1024;
    for (nMultiple = 0; nApprox > 1; nApprox /= 1024, nMultiple++) { result = nApprox.toFixed(2) + " " + aMultiples[nMultiple]; }
    return result;
  },

  serializeUploads: function (uploads)
  {
    var i, n = uploads.length, file, result = '';
    for (i = 0; i < n; i++) { file = uploads[i];  result = result + (i?'|':'') + file.name + ',' + file.size; }
    return result;
  }
};
// end: F1.File


//----------------------------------- INPUT ------------------------------------

F1.Input = {

  // input == DOM::INPUT_ELMENT
  onPressEnter: function(event, input)
  {
    var keyCode = ('which' in event) ? event.which : event.keyCode;
    if (keyCode == 13) { return F1.Pager.load(input.value); }
  }
};
// end: F1.Input


//-------------------------------- SLIDE SHOW ------------------------------------

F1.SlideShow = function ($elSlideShow, cycleTime, transitionTime, cycleDirection) { // SlideShowController
  var ctrl = this, activeSlideIndex = 0;
  ctrl.$elm = $elSlideShow;
  ctrl.cycleTime = cycleTime || 7000;
  ctrl.transitionTime = transitionTime || 900;
  ctrl.cycleDirection = ctrl.cycleDirection || 1;
  //console.log('F1.SlideShow.contruct(), $elSlideShow:', $elSlideShow, ', cycleTime:', cycleTime, ', transitionTime:', transitionTime, ', cycleDirection:', cycleDirection);
  ctrl.$slides = $elSlideShow.find('.slidelist li');
  if ( ! ctrl.$slides.length) { throw new Error('F1.SlideShowController construct error! Could not find any slides!'); }
  ctrl.$activeSlide = ctrl.$slides.find('.active').eq(0);
  if (ctrl.$activeSlide.length) {
    activeSlideIndex = ctrl.$activeSlide.index();
  }
  else {
    ctrl.$activeSlide = ctrl.$slides.eq(0);
    ctrl.$activeSlide.addClass('active');
  }
  ctrl.$indicators = $elSlideShow.find('.slide-indicators li');
  ctrl.useIndicators = !!ctrl.$indicators.length;
  if (ctrl.useIndicators) {
    ctrl.$indicators.each(function (indicatorIndex) {
      var $indicator = $(this);
      if (indicatorIndex === activeSlideIndex) {
        ctrl.$activeIndicator = $indicator;
        $indicator.addClass('active');
      }
      else {
        $indicator.removeClass('active');
      }
      $indicator.on('click', function (event) {
        if (ctrl.busy) {
          ctrl.$activeSlide.finish();
          ctrl.$nextSlide.finish();
        }
        ctrl.stopTimer(event);
        ctrl.gotoSlide(ctrl.$slides.eq(indicatorIndex));
      });
    });
  }
  ctrl.$prevBtn = $elSlideShow.find('.goto-prev-slide');
  ctrl.$nextBtn = $elSlideShow.find('.goto-next-slide');
  if (ctrl.$prevBtn.length && ctrl.$nextBtn.length) {
    ctrl.$prevBtn.on('click', function (event) { ctrl.stopTimer(event); ctrl.gotoNextSlide(-1 * ctrl.cycleDirection); });
    ctrl.$nextBtn.on('click', function (event) { ctrl.stopTimer(event); ctrl.gotoNextSlide(ctrl.cycleDirection); });
  }
  ctrl.start(ctrl.cycleDirection);
};

F1.SlideShow.prototype.stopTimer = function (event) {
  var ctrl = this;
  F1.Events.stop(event);
  if (ctrl.timerHandle) { clearInterval(ctrl.timerHandle); ctrl.timerHandle = undefined; }
};

F1.SlideShow.prototype.start = function (direction) {
  var ctrl = this;
  ctrl.timerHandle = setTimeout(function () {
    ctrl.timerHandle = setInterval(function () { ctrl.gotoNextSlide(direction); }, ctrl.cycleTime);
    ctrl.gotoNextSlide(direction);
  }, (ctrl.cycleTime - ctrl.transitionTime));
};

F1.SlideShow.prototype.gotoSlide = function ($nextSlide) {
  var $nextIndicator, ctrl = this;
  ctrl.busy = true;
  ctrl.$nextSlide = $nextSlide;
  ctrl.$activeSlide.css({ 'opacity': 1 }).animate({ 'opacity': 0 }, ctrl.transitionTime, undefined, function() {
    //console.log('Active slide animation - DONE');
    ctrl.$activeSlide.removeClass('active').css({ 'visibility': 'hidden' });
  });
  ctrl.$nextSlide.css({ 'opacity': 0, 'visibility': 'visible' }).animate({ 'opacity': 1 }, ctrl.transitionTime, undefined, function() {
    //console.log('Next slide animation - DONE');
    ctrl.$activeSlide.finish();
    ctrl.$activeSlide = ctrl.$nextSlide;
    ctrl.$nextSlide.addClass('active');
    ctrl.busy = false;
  });
  if (ctrl.useIndicators) {
    if (ctrl.$activeIndicator) { ctrl.$activeIndicator.removeClass('active'); }
    $nextIndicator = ctrl.$indicators.eq(ctrl.$nextSlide.index());
    $nextIndicator.addClass('active');
    ctrl.$activeIndicator = $nextIndicator;
  }
};

F1.SlideShow.prototype.gotoNextSlide = function (direction) {
  var $nextSlide, ctrl = this;
  if (ctrl.busy) {
    ctrl.$activeSlide.finish();
    ctrl.$nextSlide.finish();
  }
  //console.log('Goto NEXT slide: ACTIVE slide:', ctrl.$activeSlide);
  $nextSlide = ctrl.$activeSlide && ctrl.$activeSlide.length ? ((direction > 0) ? ctrl.$activeSlide.next() : ctrl.$activeSlide.prev()) : ctrl.$slides.eq(0);
  if ( ! $nextSlide.length    ) { $nextSlide = (direction > 0) ? ctrl.$slides.eq(0) : ctrl.$slides.eq(ctrl.$slides.length - 1); }
  //console.log('Goto NEXT slide: NEXT slide:', $nextSlide);
  ctrl.gotoSlide($nextSlide);
};
// end: F1.SlideShow


//----------------------------------- VIDEO ------------------------------------

F1.Video = {
  // We need to handle EMBED client-side because our approach depends on the client browser!
  embed: function (vidContainerSelector) {
    var $vidContianers = $(vidContainerSelector),
      isOldIe = $('html').hasClass('ie-lt-9');
    $vidContianers.each(function () {
      var $vidContainer = $(this);
      if (isOldIe) {
        //console.log('We have an old IE browser! Insert OLD youtube player!', jQuery.browser);
        $vidContainer.append('<span>YouTube video besig om te laai...</span>'+
          '<object width="560" height="315">'+
          '<param name="movie" value="'+location.protocol+
          '//www.youtube.com/v/'+$vidContainer.data('vid')+
          '?version=3&rel=0&hl=af"></param><param '+
          'name="allowFullScreen" value="true"></param><param '+
          'name="allowscriptaccess" value="always"></param>'+
          '<embed src="'+location.protocol+'//www.youtube.com/v/'+
          $vidContainer.data('vid')+'?version=3&rel=0&hl=af" '+
          'type="application/x-shockwave-flash" width="560" '+
          'height="315" allowscriptaccess="always" '+
          'allowfullscreen="true"></embed></object>'
        );
      } else {
        $vidContainer.append('<span>YouTube video besig om te laai...</span>'+
          '<iframe src="'+location.protocol+'//www.youtube.com/embed/'+$vidContainer.data('vid')+
          '?controls=1&rel=0&hl=af" frameborder="0" allowfullscreen></iframe>'
        );
      }
    });
  }
};
// end: F1.Video


//------------------------------- UTIL FUNCTIONS --------------------------------

F1.isDefined = function (val) { return typeof val !== 'undefined'; };
