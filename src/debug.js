/*globals window */

/**
 * debug.log() Usage:
 *  > debug.log();
 *  > debug.log('My Message');
 *  > debug.log('info', 'My Message is: %s', 'Hello World');
 *  > debug.log('error', 'Oops! Something went wrong. See Line: %i', errorLineNumber);
 *  > debug.log('warn', 'Something looks funny!?', 'Another Thing Looks Funny!?', 'I dont think d should have happened!');
 *  > debug.log('debug', document.body.firstElementChild);
 *  > debug.log('debug', '1st Element = %O', document.body.firstElementChild);
 *  > debug.log('debug', '%cThis will be formatted with large, blue text', 'color: blue; font-size: x-large');
 *
 * Formatted string parameter types:
 *
 * %s	Formats the value as a string.
 * %d or %i	Formats the value as an integer.
 * %f	Formats the object as a floating point value.
 * %o	Formats the value as an expandable DOM element (as in the Elements panel).
 * %O	Formats the value as an expandable JavaScript object.
 * %c	Applies CSS style rules to output string specified by the second parameter.
 *
 *
 * @author: C. Moller 04 Nov 2014 <xavier.tnc@gmail.com>
 *
 * @param {Object} context
 * @returns {undefined}
 */

;(function (context) {

var debug = {

	logTypes	: [],
	blankLine	: '-',
	enabled		: false,
	baseTypes	: ['log', 'info', 'debug', 'warn', 'error'],
	logger		: window.console || null,


	log: function()
	{
		var message;
		var messages = [];
		var type = 'log';

		var allowed = function() { return (debug.logTypes.length === 0 || debug.logTypes.indexOf(type) >= 0); };

		switch (arguments.length)
		{
			case 0:
				messages.push(debug.blankLine);
				break;

			case 1:
				message = arguments[0] || debug.blankLine;
				messages.push(message);
				break;

			default:
				type = arguments[0] || 'log';

				if (debug.baseTypes.indexOf(type) < 0)
				{
					messages.push("[" + type + "]:\t");
					messages.concat(Array.prototype.slice.call(arguments, 1)); // Add Custom Prefix + Skip arguments[0]
				}
				else
				{
					messages = Array.prototype.slice.call(arguments, 1); // Skip arguments[0]!
				}
				break;
		}
	
		if (debug.canlog() && allowed()) { debug.logger[type].apply(debug.logger, messages); }
	},


	printf: function(value)
	{
		debug.log('debug::printf(), type of Value = ' + typeof value);
		
		switch(typeof value)
		{
			case 'function':
				return "\nfn = " + debug.printf(value());

			case 'array':
				return "\n" + _.reduce(value, function(memo, item, index) {
					return memo + 'a[' + index + '] = ' + debug.printf(item) + "\n";
				}, '');

			case 'object':
				return "\n" + _.reduce(value, function(memo, item, key) {
					return memo + 'obj.' + key + ' = ' + debug.printf(item) + "\n";
				}, '');

			case 'boolean':
				return debug.yesNo(value);

			default:
				return value;
		}
	},


	yesNo: function(bool) { return bool ? 'Yes' : 'No';	},

	canlog: function() { return (debug.enabled && debug.logger !== null); },

	dump: function() { if (debug.canlog()) debug.logger.dir.apply(debug.logger, Array.prototype.slice.call(arguments)); },

	assert: function() { if (debug.canlog()) debug.logger.assert.apply(debug.logger, Array.prototype.slice.call(arguments)); },

	startGroup: function() { if (debug.canlog()) debug.logger.group.apply(debug.logger, Array.prototype.slice.call(arguments)); },

	startGroupCollapsed: function() { if (debug.canlog()) debug.logger.groupCollapsed.apply(debug.logger, Array.prototype.slice.call(arguments)); },

	endGroup: function() { if (debug.canlog()) debug.logger.groupEnd.apply(debug.logger, Array.prototype.slice.call(arguments)); },


	// NOTE: defaultTypes ONLY apply for []! If a single type is set, other defaultTypes will NOT be added.
	setAllowedTypes: function(logTypes) { debug.logTypes = logTypes || debug.baseTypes;	}

};

context.debug = debug;

}(this));