/* global jQuery, $, moment, accounting, Happy2Ext */
/* jslint sloppy:true */
/*jshint browser:true */

/**
 *
 * f1.js
 *
 * OneFile PHP Framework, Javascript Support Library
 *
 * @author: C. Moller
 * @date: 28 Oct 2016
 *
 * Updated and extended from OneFile main.js - 03 Mar 2013
 * Updated continuously Oct 2016 - May 2017
 * Updated 07 Jun 2017 - IE9 / History.js support (Check if templates work... != to !== issue)
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
        //console.log('F1.Url:normalize(), start url:', url);
        url = url || F1.Url.current();
        //console.log('F1.Url:normalize(), end url:', url);
        return url;
    },

	// Parse the current window location and unpack the resulting url_info into Url
	init: function()
	{
	    var href = F1.Url.current();
	    // if (window.history.emulate && href != window.location.href ) { window.history.replaceState({}, null, window.location.href); }
		// console.log('F1.Url:init(), current location:', href); //,', BaseUri:', F1.Config.baseuri);
        // console.dir(window.history);
		var url_info = F1.Url.parse(href.toString());

		F1.Url.base = url_info.base;
		F1.Url.assets = F1.Url.base + 'kd/';
		F1.Url.pageref = url_info.pageref; //Excludes Query String! - NM 10 Jun 2013
		F1.Url.query = url_info.query;
		F1.Url.query_params = url_info.query_params;
		F1.Url.segments = url_info.segments;

		window.onpopstate = F1.Url.popState;
		//console.log('F1.Url = ', F1.Url);
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
		//console.log('F1.Url.make(), query =', F1.Url.query);
		//console.log('F1.Url.make(), params =', params);
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
		//console.log('F1.Url.make(), url =', url);
		return url;
	},

	pushState: function(url, title, ignoreExitTest)
	{
		var r, state = {'url':url, 'title':title}; // 'title' not supported in most browsers!
		//console.log('F1.Url.pushState(), START - url:', url, ', ignoreExitTest:', ignoreExitTest);
		if (!ignoreExitTest) { r = F1.Document.beforeExit({ type: 'pushstate', url: url }); if (r === false) { return false; } }
		//console.log('F1.Url.pushState(), PUSH STATE:', state);
		history.pushState(state, state.title, state.url);
		return true;
	},

	popState: function(event)
	{
		//var $page_links, $active_link,
		url = event.state ? event.state.url : "";
		//console.log('F1.Url.popState(), state:', event.state, ', url:', url);
		if (F1.Document.beforeExit({ type: 'popstate', url: url }) === false) { F1.Url.pushState(F1.Url.current(), '', true); return false; }
		//$page_links = F1.Document.getAllPageLinks();
		//$active_link = F1.Menu.update($page_links, url) || F1.Document.findPageLink($page_links, url);
		//F1.Document.setTitleUsingLink($active_link);
		F1.Ajax.load(url);
		F1.Url.init(); // Before loading we pushed a new window location, so now update our Url object
		$('body > .modal-backdrop').remove();
	},

	back: function(event, distance)
	{
		//var re = new RegExp("/^.*uitstaller\/.*$/");
		//referrerIsPrivate = re.test(document.referrer);
		//distance = referrerIsPrivate ? -2 : -1;
		F1.Event.stop(event);
		distance = distance ? (-1 * distance) : -1;
		//console.log('F1.Url.back(), distance:', distance);
		history.go(distance);
	}
};
// end: F1.Url


//----------------------------------- AJAX ------------------------------------

F1.Ajax = {

	initContainerSelectors: function(containerSelectors)
	{
		// console.log('F1.Ajax.initContainerSelectors(), containerSelectors(before):', containerSelectors);
		if ( ! containerSelectors) {
			containerSelectors = {};
			containerSelectors.src  = ['#content']; // default
			containerSelectors.dest = containerSelectors.src;
		}
		else if (typeof containerSelectors === "string") {
			var tempSelectorStr = containerSelectors;
			containerSelectors = {};
			containerSelectors.src  = [tempSelectorStr];
			containerSelectors.dest = containerSelectors.src;
		}
		// console.log('F1.Ajax.initContainerSelectors(), containerSelectors:', containerSelectors);
		return containerSelectors;
	},

	// Handle redirect after Ajax POST
	redirect: function(respdata)
	{
		//console.log('F1.Ajax.update() successful! respdata = ', respdata);
		var urlChanged, currentUrl = F1.Url.current(), requestedUrl = respdata.redirect ? respdata.redirect : currentUrl;
		//console.log('F1.Ajax.update(), HTTP 202 - Redirect after Ajax POST, currentUrl:', currentUrl, ', requestedUrl:', requestedUrl);
		urlChanged = (currentUrl !== requestedUrl);
		if (urlChanged) { F1.Url.pushState(requestedUrl, '', true); } // NB: pushState MUST be before Ajax.load()!
		F1.Ajax.load(requestedUrl);
		if (urlChanged) { F1.Url.init(); } // Before loading we pushed a new window location, so now update our Url object
	},

	/**
	 *
	 * If NO container selectors are provided, default to "#content"
	 *
	 * containerSelectors = { src: ["#title", "#content", ...], dest: ["#title", "#modal-content", ...] }
	 * onSuccessScript = "$('body').setClass('has-modal');" i.e. == JS Code String
	 *
	 */
	update: function(respdata, containerSelectors, onSuccessScript)
	{
		var i, $backdrop, $newpage, regex, matches, newcsrf, newtitle, newstyles, $newalerts, $newtopnav, $content;

        // Remove any modal-backdrop if we had a popup open while exiting the last page...
		// $backdrop = $('body').children('.modal-backdrop');
		// if ($backdrop.length) { $backdrop.remove(); }

		// regex = /ken" content="(.*)"[\s\S]+?tle>(.*)<\/ti[\s\S]+?css">\s*([\s\S]+?)<\/st/;
        // matches = regex.exec(respdata);
        // newcsrf = matches[1] || '<!-- Empty -->';
        // newtitle = matches[2] || '<!-- Empty -->';
        // newstyles = matches[3] || '<!-- Empty -->';

		$newpage = $('<response></response>').html(respdata);
        newcsrf = $newpage.find('meta[name="x-csrf-token"]').attr('content');
        newtitle = $newpage.find('title').html();
        newstyles = $newpage.find('style[data-rel="content"]').html();

		// console.log('F1.Ajax.update(), newcsrf = ', newcsrf);
		// console.log('F1.Ajax.update(), newtitle = ', newtitle);
		// console.log('F1.Ajax.update(), newstyles = ', newstyles);

		// Always replace the following page sections:
		// Can't we just DROP server side alerts!?
		$newalerts = $newpage.find('#frmAlerts');
		$newtopnav = $newpage.find('.topnav-inner');

 		//console.log('F1.Ajax.update(), $head = ', F1.Document.$head);

		F1.Document.$head.find('meta[name="x-csrf-token"]').attr('content', newcsrf);
		F1.Document.$head.find('title').html(newtitle);
		// F1.Document.$head.find('link[rel="canonical"]').attr('href', F1.Url.current);
		// F1.Document.$head.find('meta[property="og:url"]').attr('content', F1.Url.current);
		F1.Document.$head.find('style[data-rel="content"]')[0].innerHTML = newstyles;
    	F1.Document.$alerts.html($newalerts.html());
    	F1.Document.$topnav.html($newtopnav.html());

        // Rebind updated sections! Look at delegated events on these?...
		F1.Bind.alerts(F1.Document.$alerts.find('.alert-row'));
		F1.Bind.pageLinks(F1.Document.$topnav);
        F1.Menu.setActiveItem(F1.Document.$topnav);

		// Replace current content-areas with new content-areas
		for (i = 0; i < containerSelectors.src.length; i++)
		{
			//console.log('F1.Ajax.update(), containerSelectors.src[i] = ', containerSelectors.src[i]);
			$content = $newpage.find(containerSelectors.src[i]);
			$(containerSelectors.dest[i]).html($content.html());
			//$content.fadeIn(3000);
		}

		// Clear dynamic hooks and events .. Please fix this spagetti code! NM - 27 Apr 2017
		//  - Rather look at dynamic namespace that gets reset before each page load...
		//  - Keep away from "remote controlling" modules!
		F1.Document.exitScripts = [];
		F1.Form.beforeSubmitHooks = {};

		// Rebind newly inserted HTML events!
		F1.Event.run(F1.bindScripts);
		for (i = 0; i < containerSelectors.dest.length; i++) {
			F1.Bind.pageLinks(containerSelectors.dest[i]);
			F1.Bind.forms(containerSelectors.dest[i]);
		}

		// Google analytics... record pageview
		if (ga) {
		    ga('send', 'pageview', F1.Url.current());
	    }

        /* jshint evil:true */
		// if (onSuccessScript) { eval(onSuccessScript); }
	},

	error: function(resp)
	{
		// Handle any actual errors...
		var $errors, containerSelector = '#content';
		//console.error('F1.Ajax.error(), resp =', resp);
		$errors = $('<div></div>').append(resp.responseText).find('.error-wrapper');
		if ($errors.length)
		{
			$(containerSelector).html('').append($errors);
		}
		else
		{
			$(containerSelector).html('<h2>Oops, something went wrong!</h2><h3 style="color:red">Error ' +
			    resp.status	+ ' - ' + resp.statusText + '<br><small>F1.Ajax.load() failed.</small></h3><br><br>');
		}
	},

	always: function(resp)
	{
	    //console.log('F1.Ajax.always(), resp =', resp);
 		F1.Document.$favicon.attr('href', F1.Url.assets + 'img/favicon.ico');
 		$('body').removeClass('busy');
	},

	/**
	 *
	 * Fetch the current page's HTML code from the server,
	 * but only insert the parts specified by the "containerSelectors"
	 * parameter.
	 *
	 */
	load: function(url, containerSelectors, onSuccessScript)
	{
		url = F1.Url.normalize(url);
		F1.bindScripts = [];
		if (window.reviveAsync) { window.reviveAsync = undefined; }
		//console.log('F1.Ajax.load(), url:', url);
		//console.log('F1.Ajax.load(), onSuccessScript:', onSuccessScript);
		//console.log('F1.Ajax.load(), F1.Url.assets:', F1.Url.assets, ', loading.ico url:', F1.Url.base + 'img/loading.ico');
		F1.Document.$favicon.attr('href', F1.Url.assets + 'img/loading.ico');
 		$('body').addClass('busy');
		containerSelectors = F1.Ajax.initContainerSelectors(containerSelectors);
		$.ajax({ 'url': url, 'method': 'GET', 'cache': false, 'success': function(data) { F1.Ajax.update(data, containerSelectors, onSuccessScript); }, 'error': F1.Ajax.error }).then(F1.Ajax.always);
	},

	post: function(url, postdata, containerSelectors, onSuccessScript)
	{
		url = F1.Url.normalize(url);
		F1.bindScripts = [];
		postdata = postdata || {};
		//console.log('F1.Ajax.post(), url:', url, ', data:', postdata);
		//console.log('F1.Ajax.post(), onSuccessScript:', onSuccessScript);
		//console.log('F1.Ajax.post(), F1.Url.assets:', F1.Url.assets, ', loading.ico url:', F1.Url.base + 'img/loading.ico');
		F1.Document.$favicon.attr('href', F1.Url.assets + 'img/loading.ico');
 		$('body').addClass('busy');
		containerSelectors = F1.Ajax.initContainerSelectors(containerSelectors);
		$.ajax({
		    'url': url, 'method': 'POST', 'data': postdata, 'dataType': 'json',
		    'headers': { 'x-csrf-token': F1.Url.csrfToken, 'x-http-referer': F1.Url.current() },
		    'success': function(respdata, status, jqXHR) {
		        //console.log('F1.Ajax.post(), success! status:', status, ', jqXHR:', jqXHR);
		        if (jqXHR.status === 202) {
		            F1.Ajax.redirect(respdata);
		        } else {
		            F1.Ajax.update(respdata, containerSelectors, onSuccessScript);
		        }
		    },
		    'error': F1.Ajax.error
		}).then(F1.Ajax.always);
	}
};
// end: F1.Ajax


//----------------------------------- BIND ------------------------------------

F1.Bind = {

	pageLinks: function(scopeSelector, linkSelector)
	{
		scopeSelector = scopeSelector || 'body';
		linkSelector = linkSelector || '.pagelink';
		//console.log('F1.Bind.pageLinks(), scopeSelector:', scopeSelector, ', linkSelector:', linkSelector);
		$(linkSelector, scopeSelector).not('.nobind').click(function(event)
		{
			var currentUrl = F1.Url.current(), requestedUrl = this.href, $this_link, $page_links, $active_link, $parent_submenu;
			//console.log('F1.Bind.pageLinks.onPageLinkClick(), Current page:', currentUrl, ', Requested page:', requestedUrl);
			if (requestedUrl === currentUrl) {
				//console.log('Already on the requested page. Skip AJAX load!');
				return false;
			}
			$this_link = $(this);
			if (F1.Document.isExternal($this_link)) { $this_link.blur(); return true; }
			// NB: pushState() MUST be before Ajax.load() + pushState() FAIL MUST stop event!
			if ( ! F1.Url.pushState(requestedUrl)) { F1.Event.stop(event); return false; }
			$this_link.blur();
			F1.Event.stop(event);
			$page_links = F1.Document.getAllPageLinks();
			// Menu.update():
			// First remove any "active" classes from the menu system.
			// Next, find the menu item that matches our requestedUrl and return it's element or NULL
			// Set the menu item/link found as the "active" link via setting related parent menu element classes.
			//$active_link = F1.Menu.update($page_links, requestedUrl);
			$active_link = F1.Document.$topnav.find('.active:first');
			if ($active_link.length) { // if the requested URL has a corresponding menuitem
				if ($(window).outerWidth() < 768) {
					// AND we are in minimized menu mode, hide the hamburger dropdown menu!
					$('.topnav-inner').removeClass('open');
				} else {
					// AND we are in CSS dropdown mode, remove the "enabled" class from the menu for 1 sec.
					$parent_submenu = $active_link.parents('.submenu').first();
					$parent_submenu.removeClass('enabled');
					setTimeout(function() { $parent_submenu.addClass('enabled'); }, 1000);
				}
			}
			else {
				$active_link = $this_link;
			}
			//$('.topnav-content').removeClass('open');
			F1.Document.setTitleUsingLink($active_link);
			F1.Ajax.load(requestedUrl);
			F1.Url.init(); // Before loading we pushed a new window location, so now update our Url object
			return false;
		});
	},

	forms: function(scopeSelector, formSelector)
	{
		scopeSelector = scopeSelector || 'body';
		formSelector = formSelector || 'form';
		var $form = $(formSelector, scopeSelector).not('.noajax-post');
		$form.click(F1.Form.onClick);
		$form.submit(F1.Form.onSubmit); // i.e. <form onsubmit="F1.Form.onSubmit(event)">
	},

	alerts: function($alert_rows)
	{
		//console.log('F1.Bind.alerts(), alert_rows: ', $alert_rows);
		if ( ! $alert_rows) { $alert_rows = $('.alert-row'); }
		$alert_rows.each(function() {
			var $alert_row = $(this), ttl = $alert_row.data('ttl');
			if (ttl > 0) {	F1.Alert.autoDismiss($alert_row, ttl); }
		});
	},

	getJQueryEventsObj: function ($elm) {
		return $._data($elm[0], 'events') || {};
	},

	setJQueryEventsObj: function ($elm, eventsObj) {
		$._data($elm[0], 'events', eventsObj);
	},

	prependEvent: function(obj, $elm, eventName, handler, data)
	{
		var elm = $elm[0], oldEventsObj = F1.Bind.getJQueryEventsObj($elm), newEventsObj = {}, etype, inlineEvent;
		F1.Bind.setJQueryEventsObj($elm, newEventsObj); $elm.on(eventName+'.'+obj.name, data || obj, handler); inlineEvent = 'on' + eventName;
		if (obj.demoteInlineEvents && $.isFunction(elm[inlineEvent])) { $elm.on(eventName+'.inline', data || obj, elm[inlineEvent]); elm[inlineEvent]=null; }
		for (etype in oldEventsObj) { if (oldEventsObj.hasOwnProperty(etype)) { $.merge(newEventsObj[etype] ? newEventsObj[etype] : (newEventsObj[etype]=[]), oldEventsObj[etype]); } }
		//console.log('F1.Event.prepend(), eventName:', eventName, ', oldEventsObj:',oldEventsObj,', newEventsObj:',newEventsObj, ', $elm:', $elm);
	}

};
// end: F1.Bind


//---------------------------------- EVENT -----------------------------------

F1.Event = {

	run: function (scripts, event)
	{
		var i, r;
		if ( ! scripts || ! scripts.length) { return; }
		// console.log('F1.Event.run(), scripts.length:', scripts.length);
		for (i = 0; i < scripts.length; i++)
		{
			r = scripts[i](event); // run script
			if (F1.isDefined(r)) { return r; }
		}
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


//----------------------------------- UI -------------------------------------

F1.Ui = {

	wysNota: function(event, elm)
	{
		if ( ! $(event.target).is(':input')) { $(elm).find(('.nota')).toggle(); F1.Event.stop(event); }
	},

	wysGroep: function(event, elm)
	{
		$(elm).toggleClass('collapsed').parents('tr:first').next().toggleClass('hidden'); F1.Event.stop(event);
	}

};
// end: F1.Ui


//--------------------------------- DOCUMENT ----------------------------------

F1.Document = {

	$head: null,
	$aftel: null,
    $alerts: null,
	$topnav: null,
	$favicon: null,

	exitScripts: [],

	addExitScript: function(script, prepend) {
		var scriptsToAdd = $.type(script) === 'array' ? script : [script];
		//console.warn('addExitScript, scriptsToAdd:', scriptsToAdd);
		if (prepend) { F1.Document.exitScripts = scriptsToAdd.concat(F1.Document.exitScripts); }
		else { F1.Document.exitScripts = F1.Document.exitScripts.concat(scriptsToAdd); }
	},

	beforeExit: function(event)
	{
		//console.log('F1.Document.beforeExit() - RUN'); //event:', event
		return F1.Event.run(F1.Document.exitScripts, event);
	},

	setTitle: function(title)
	{
		try { document.getElementsByTagName('title')[0].innerHTML = title; }
		catch ( Exception ) { }
		document.title = title;
	},

	setTitleUsingLink: function($link, newPageTitle)
	{
		if (!$link) {
			newPageTitle = newPageTitle || 'KragDag Ekspo';
		}
		else {
			if (F1.Document.isMenuItem($link)) {
				newPageTitle = $link.data('page-title') || $link.find('span').first().text();
			}
			else {
				newPageTitle = $link.data('page-title') || $link.text();
			}
		}
		F1.Document.setTitle(newPageTitle + ' - KragDag');
	},

	isMenuItem: function($link) { return !!$link ? $link.hasClass('menuitem') : false; },

	isExternal: function($link) { return !!$link ? $link.hasClass('extlink') : false; },

	getAllPageLinks: function() { return $('.pagelink'); },

	findPageLink: function($page_links, href_to_match)
	{
		//console.log('F1.Menu.findPageLink(), href to match =', href_to_match);
		var $page_link = $page_links.not('.menuitem').map(function() {
			var $me = $(this);
			if (this.href === href_to_match || $me.attr('href') === href_to_match) { return $me; }
		});
		//console.log('F1.Menu.findPageLink(), page link item :', $page_link);
		if ($page_link.length) { return $page_link[0]; }
		//console.log('F1.Menu.findPageLink(), Page Link NOT found!');
	},

	moveIntoView: function($elm, showAction, $bsTabElm) {
		//console.log('F1.Document.moveIntoView(), $elm:', $elm, ', showAction:', showAction, ', $bsTabElm:', $bsTabElm);
		if ($bsTabElm) { $bsTabElm.tab('show'); }
		if (showAction) { setTimeout(function() { $elm[showAction](); }, 1000); }
	}

};
// end: F1.Document


//----------------------------------- MENU ------------------------------------

F1.Menu = {

	triggerFirstOption: function(event, elm)
	{
		//console.log("event :", event);
		//console.log("elem :", elm);
		F1.Event.stop(event);
		var $me=$(elm), $myParent=$me.parent();
		$me.blur();
		if ( ! $myParent.hasClass('has-active'))
		{
			var $firstOption=$me.next().find('.pagelink:first');
			//console.log('1st Option:', $firstOption[0]);
			$firstOption.trigger('click');
		}
	},

	getItems: function() {return $('.menuitem'); },

	findItem: function($menu_items, href_to_match)
	{
		//console.log('F1.Menu.findItem(), href to match =', href_to_match);
		var $menu_item = $menu_items.not('.nobind').map(function() {
			var $me = $(this);
			if (this.href === href_to_match || $me.attr('href') === href_to_match) { return $me; }
		});
		//console.log('F1.Menu.findItem(), menu item :', $menu_item);
		if ($menu_item.length) { return $menu_item[0]; }
		//console.log('F1.Menu.findItem(), Item NOT found!');
	},

	activateItem: function($menu_item)
	{
		if ($menu_item && $menu_item.length)
		{
			$menu_item.parent().addClass('active');
			$menu_item.parents('.submenu-parent').addClass('has-active');
		}
	},

	setActiveItem: function($menu)
	{
	    $('.submenu .active', $menu).parents('.submenu-parent').addClass('has-active');
    },

	reset: function($menu_items)
	{
		//console.log('F1.Menu.reset(), Start'); // menu items =', $menu_items);
		if ($menu_items.length) { $menu_items.parent().removeClass('active has-active'); }
	},

	update: function($page_links, href)
	{
		//console.log('F1.Menu.update(), href =', href);
		var $menu_items, $targetItem;
		$menu_items = F1.Menu.getItems();
		F1.Menu.reset($menu_items);
		$targetItem = F1.Menu.findItem($menu_items, href);
		F1.Menu.activateItem($targetItem);
		return $targetItem;
	}
};
// end: F1.Menu


//----------------------------------- FORM ------------------------------------

F1.Form = {

	beforeSubmitHooks: {},

	addBeforeSubmitHook: function(formId, submitHookFn, prepend) {
		var formHooks = F1.Form.beforeSubmitHooks[formId],
		hooksToAdd = $.type(submitHookFn) === 'array' ? submitHookFn : [submitHookFn];
		if (! formHooks) { formHooks = F1.Form.beforeSubmitHooks[formId] = []; }
		if (prepend) { F1.Form.beforeSubmitHooks[formId] = hooksToAdd.concat(formHooks); }
		else { F1.Form.beforeSubmitHooks[formId] = formHooks.concat(hooksToAdd); }
		//console.log('F1.Form.addBeforeSubmitHook(), hooks:', F1.Form.beforeSubmitHooks);
	},

	runBeforeSubmitHooks: function(event, action, params) {
		var lastHookResult, form = event.target;
		//console.log('F1.Form.runBeforeSubmitHooks(), action:', action, ', params:', params); //, event:', event, ', form:', form, '
		if (!form.id) { return; }
		$.each(F1.Form.beforeSubmitHooks[form.id] || [], function runSubmitHook(i, hook) {
			//console.log('Running submit hook no.', i,', for', form.id);
			return (lastHookResult = hook(event, action, params)); // return == false == $.each->break
		});
		return lastHookResult;
	},

	submit: function(formSelector, submitUrl, action, params)
	{
		var $form = $(formSelector), serializedData;
		if (F1.Document.noAjaxPageLoad) {
            $form.append('<input type="hidden" name="__ACTION__" value="' + action + '">');
            $form.append('<input type="hidden" name="__PARAMS__" value="' + params + '">');
		} else {
    		serializedData = $form.serialize() || '';
    		//console.log('F1.Form.submit(), $form =', $form, ', data =', serializedData);
    		if (action) { serializedData += (serializedData.length ? '&' : '') + '__ACTION__=' + action + '&__PARAMS__=' + params; }
    		F1.Ajax.post(submitUrl, serializedData);
		}
	},

	onSubmit: function (event, action, params)
	{
		if (!F1.Document.noAjaxPageLoad) { F1.Event.stop(event); event.stopImmediatePropagation(); }
		//console.log('F1.Form.onSubmit(), event =', event, ', action =', action, ', params =', params);
		if (F1.Form.runBeforeSubmitHooks(event, action, params) === false) { return false; }
		F1.Form.submit(this, $(this).attr('action'), action, params);
	},

	values: function (form)
	{
		var $form = (form instanceof jQuery) ? form : $(form), values = {};
		$form.find(':input').each(function(index, input){ values[input.name] = input.value; });
		return values;
	},

	// We need this event to detect which action triggered the form submit
	// and to add the action's information to the form.
	onClick: function (event)
	{
		var $form = $(this), action = 'submit', params = '',
		$target = $(event.target || event.srcElement),
		$submitter = ($target.attr('type') === 'submit') ? $target : $target.parents('[type="submit"]');
		//console.log('F1.Form.onClick(), $target:', $target, ', $submitter:', $submitter);
		if ($submitter.length) {
			var submitterName = $submitter.attr('name');
			if (submitterName === 'do') { action = $submitter.val(); }
			else if (submitterName) { action = submitterName; params = $submitter.val(); }
			if (action === 'dismiss-alert') { $('#'+params).remove(); }
			//console.log('F1.Form.onClick(), TRIGGER ON-SUBMIT: action =', action, ', params =', params);
			F1.Event.stop(event);
			return $form.trigger('submit', [action, params]);
		}
	},

	focus: function (scopeSelector)
	{
		//console.log('Fokus op die eerste relevante invoer veld...');
		var $vorm = $(scopeSelector), $errors = $vorm.find('.has-error .input').not('[type="hidden"]'), $target;
		if ($errors.length) {
			$target = $errors.first();
			//console.log('Selekteer die eerste veld met `n foutboodskap: ', $target);
			$target.focus();
		}
		else {
			$target = $vorm.find('input').not('[type="hidden"]').first();
			//console.log('Geen foute. Stel fokus op die eerste beskikbare invoer veld: ', $target);
			$target.focus();
		}
	},

	preventSubmitOnEnter: function (event, altAction)
	{
		var keyCode = ('which' in event) ? event.which : event.keyCode, target = event.target;
		if (keyCode === 13 && target.tagName === 'INPUT' && target.getAttribute('type') !== 'file') {
			F1.Event.stop(event);
			//console.log('Enter Key Blocked!');
			if (typeof altAction === 'function') { altAction.call(this, event); }
			return false;
		}
	}
};
// end: F1.Form


//------------------------------------- FILE ---------------------------------------

F1.File = {

	uploader: function (elUploader_id, options)
	{
		var elUploader = document.getElementById(elUploader_id), cfg = {};
		if ( ! elUploader) { throw Error('ERROR: Could not find FILE UPLOADER:', elUploader_id); }
		cfg.viewzone = elUploader.getElementsByClassName('file-viewzone')[0];
		cfg.dropzone = elUploader.getElementsByClassName('file-dropzone')[0];
		cfg.template = F1.Template.trim(elUploader.getElementsByTagName('script')[0].innerHTML);
		cfg.initialUploads = F1.File.getUploads(cfg.viewzone);
		cfg.uploads = cfg.initialUploads;
		cfg.fc = cfg.dropzone.getElementsByTagName('input')[0];
		cfg.options = options || {};
		elUploader.uplCfg = cfg;
		if (F1.isDefined(cfg.fc.files) && F1.isDefined(FileReader))
		{ // HTML5 Ok :-)
			cfg.dropzone.addEventListener("dragover", F1.Event.stop, false);
			cfg.dropzone.addEventListener("dragenter", F1.Event.stop, false);
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
		F1.Event.stop(event);
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
		fvData.name = file.type === 'application/pdf' ? 'img/pdf.svg' : 'img/unknown.svg';
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
		if (getPreview || fvData.name !== 'img/unknown.svg') { fvModel.progress.classList.remove('hidden'); }
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
		//console.log('F1.File.onUploadsChange(), elUploader = ', elUploader);
		var r, errors, cfg = elUploader.uplCfg; if (!cfg) { return; }
		if (cfg.options.onChange) { r = cfg.options.onChange(elUploader); if (F1.isDefined(r)) { return r; } } // console.log('CUSTOM onChange(), result:', r);
		errors = F1.File.getUploadErrors(elUploader); if (errors.length) { elUploader.classList.add('has-error'); } // console.log('F1.File.onUploadsChange(), ADDED HAS-ERROR');
		else { elUploader.classList.remove('has-error'); } // console.log('F1.File.onUploadsChange(), REMOVED HAS-ERROR');
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


//----------------------------------- SELECT -------------------------------------

F1.Select = {

	validate: function(e, ff)
	{
		e.stopPropagation();
		if (e.target.tagName !== 'INPUT') return false;
		var $ff = $(ff), checks = $ff.find(':checked').length;
		//console.log('F1.File.validate(), checks = ', checks, ', event = ', e);
		if (checks) { $ff.find('.error-text').html(''); $ff.removeClass('has-error'); } else { $ff.addClass('has-error'); $ff.find('.error-text').html('Word benodig'); }
	}
};
// end: F1.Select


//----------------------------------- PAGER ------------------------------------

F1.Pager = {
	load: function(pageno)
	{
		var url = F1.Url.make({'p' : $('#'+pageno+' input').val()});
		F1.Url.pushState(url);
		F1.Ajax.load(url);
		F1.Url.init();
		return false;
	},

	ifKeyIsEnter: function(event, pageno)
	{
		var keyCode = ('which' in event) ? event.which : event.keyCode;
		if (keyCode == 13) { return F1.Pager.load(pageno); }
	}
};
// end: F1.Pager


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



//----------------------------------- SOCIAL ------------------------------------

F1.Social = {

	updateUrl: function(link)
	{
        //console.log('F1.Social.updateUrl(), link =', link);
	    var href = link.href, parts = [];
	    if (href) {
	        parts = href.split('=');
	        if (parts.length > 1) {
	            parts.pop();
	            link.href = parts.join('=') + '=' + encodeURIComponent(F1.Url.pageref);
	            //console.log('F1.Social.updateUrl(), link (after) =', link);
        	    return true;
	        }
	    }
	}
};
// end: F1.Social


//-------------------------------- SLIDE SHOW ------------------------------------

F1.SlideShow = function SlideShowController($elSlideShow, cycleTime, transitionTime, cycleDirection)
{
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
    F1.Event.stop(event);
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


//----------------------------------- ALERT ------------------------------------

F1.Alert = {
	dismiss: function(alert_id)
	{
		var $alerts = $('.alerts');
		if ( ! $alerts.length) { return; }
		$alerts.find('#' + alert_id).remove();
	},

	autoDismiss: function($alert_row, ttl)
	{
		//console.log('F1.Alert.autoDismiss(), alert-row:', $alert_row, ', ttl:', ttl);
		setTimeout(function () { $alert_row.fadeOut(3000, function() { $alert_row.remove(); });	}, ttl);
	},

	add: function(alert_id, message, type, ttl)
	{
		var $alertRow, $alert, $messageContainer,
		$alerts = $('.alerts'),
		alertTemplateFn = function(message, type) {
			return '<div class="alert alert-dismissible alert-' + type + '  fade in" role="alert">' +
				'<button class="close" name="dismiss-alert" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>' +
				'<div class="message-container">' + message + '</div><a href="javascript:void(0)" class="more-messages hidden" onclick="F1.Alert.showMore(this)">Wys meer...</a></div>';
		};
		if ( ! $alerts.length) { return; }
		$alertRow = $alerts.find('#' + alert_id).first();
		if ($alertRow.length) {
			$alert = $alertRow.find('.alert').first();
			if (! $alert.length) { $alertRow.append(alertTemplateFn(message, type)); }
			else {
				$messageContainer = $alert.find('.message-container');
				$messageContainer.append(message);
				if ($messageContainer[0].childElementCount > 2) {
					$messageContainer.next().removeClass('hidden');
				}
			}
		} else {
			$alertRow = $('<div id="' + alert_id +'" class="alert-row" data-ttl="' + ttl + '">' + alertTemplateFn(message, type) + '</div>');
			$alerts.append($alertRow);
			if (ttl) { F1.Alert.autoDismiss($alertRow, ttl); }
		}
	},

	showMore: function (elm) {
		var $elm = $(elm), $messageContainer = $elm.prev();
		$messageContainer.toggleClass('expanded');
		if ($messageContainer.is('.expanded')) { $elm.html('Wys minder...'); } else { $elm.html('Wys meer...'); }
	}
};
// end: F1.Alert



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



//----------------------------------- AFTEL ------------------------------------

F1.Aftel = {
	daeTotKragDag: function()
	{
		var antwoord, nou = new Date();
		antwoord = (F1.ekspoDatum.getTime() - nou.getTime()) / F1.msPerDag;
		if (antwoord < 0) { antwoord = 0; }
		antwoord = Math.ceil(antwoord);
		return antwoord;
	}
};
// end: F1.Aftel


//----------------------------------- FORMAT ------------------------------------

F1.Format = {
	currency: function(value, decimals, sep, sym, placeholder)
	{
		sep = sep || ' ';
		sym = sym || 'R';
		decimals = decimals || 2;
		return accounting.formatMoney(value, sym, decimals, ' ', '.');
    }
};
// end: F1.Format


//------------------------------- UTIL FUNCTIONS --------------------------------

F1.isDefined = function (val) { return typeof val !== 'undefined'; };

F1.debounce = function (func, wait, immediate) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        var later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
};


//------------------------------------ INIT -------------------------------------

F1.init = function()
{
	//console.log("KragDag JS - Initialize...");
	F1.bindScripts = F1.bindScripts || [];

	// Run Global Scripts
	F1.msPerDag = 24*60*60*1000; // Ure per dag * Minute per uur * Sekondes per minuut * Millisekondes per sekonde
	F1.ekspoDatum = new Date(2018, 7, 9, 0, 0, 0, 0);
	F1.Document.$head = $('head');
	F1.Document.$aftel = $('#aftel');
    F1.Document.$alerts = $('#frmAlerts');
    F1.Document.$topnav = $('.topnav-inner');
	F1.Document.$favicon = $('#favicon', F1.Document.$head);
    F1.Url.csrfToken = $('meta[name="x-csrf-token"]', F1.Document.$head).attr('content');
	F1.Document.$aftel.html(F1.Aftel.daeTotKragDag());
	F1.Document.noAjaxPageLoad = !window.history || !window.history.pushState;
	F1.Url.init();

	// Bind Global Events
	setInterval(function(){ F1.Document.$aftel.html(F1.Aftel.daeTotKragDag()); }, 60000);
	$(document).on('touchend', function(){}); // sticky hover fix in iOS ... note: touchend ==> touch-end
	window.onbeforeunload = F1.Document.beforeExit;

	F1.Bind.alerts();
 	if (!F1.Document.noAjaxPageLoad) { F1.Bind.pageLinks(); }
	F1.Bind.forms();
 	F1.Menu.setActiveItem(F1.Document.$topnav);

	// Run Page Specific Scripts
	F1.Event.run(F1.bindScripts);

	//console.log("KragDag JS - Ready!");
};
// end: F1.init


$(document).ready(F1.init());
