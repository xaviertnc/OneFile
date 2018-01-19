/* 
 * Sync, Async or Sync+Async Capable Function Queue
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 06 Nov 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */
;(function(){


function Log()
{
	var logEnabled = true;
	if (logEnabled && typeof window.console === 'function')	{ window.console.log.apply(window.console, arguments); }
}


function QueueItem(id, func, args, type, context)
{
	this.id = id;
	this.func = typeof func === "function" ? func : null;
	this.args = args || [];
	this.type = type || 'async';
	this.context = context || window;

	this.run = function(queue)
	{
		var busyCount = queue.busyItems.length;
		var waitingCount = queue.waitingItems.length;

		Log('QueueItem::run(), queue.busyItems:' + busyCount + ', queue.waitingItems:' + waitingCount);

		if (this.type === 'sync' && queue.busyCount)
		{
			queue.waitingItems.push(this);
			queue.items.pop();
			Log('QueueItem::run(), Add item to waiting list! (We have busy items), Id: ' + this.id + ', Type: [sync]');

		}
		else
		{
			queue.busyItems.push(this);

			Log('QueueItem::run(), Run Now! (We have no busy items), Id :' + this.id + ', Type: [' + this.type + ']');

			queue.done = this.func.apply(this.context, this.args);

			if ( ! queue.done)
			{
				
			}
		}

		return queue.done;
	}
}


function Queue(doneCallback)
{
	this.doneCallback = doneCallback;
	this.items = [];
	this.busyItems = [];
	this.waitingItems = [];
	this.nextItemId = 0;
	this.catchup = false;
	this.done = false;
}

/**
 *
 * @param {Function} func
 * @param {Array} args
 * @param {String} type If type === 'sync', WAIT for all the currently BUSY functions to complete before Execute!
 * @param {Object} context
 * @param {String} id
 * @returns {QueueItem}
 */
Queue.prototype.add = function(func, args, type, context, id)
{
	id = id || this.nextItemId++;
	var item = new QueueItem(id, func, args, type, context);
	this.items.push(item);
	return item;
};

Queue.prototype.start = function()
{
	var next = this.items.shift();

	while (next.run());
};

Queue.prototype.next = function()
{
	var next = this.items.shift();

	if (next)
	{		

		next.func.apply(next.context, next.args);
		if (!this.waitingItems.length) { this.next(); }
	}
};

Queue.prototype.onFunctionDone = function(func)
{
	Log('Queue::onFunctionDone(), func.id:%s, func.type:%s', func.id, (func.wait ? 'sync/wait' : 'async'));

	this.busyItems.pop();

	if (func.wait)
	{
		this.waitingItems.pop();
		if (this.busyItems.length && !this.waitingItems.length) { this.catchup = true; }
	}

	if (this.busyItems.length)
	{
		Log('Queue::onFunctionDone(), Some other item is still loading!');
		this.drop(func);
		if (!this.catchup && !this.waitingItems.length)
		{
			this.next();
		}
		else
		{
			Log('Queue::onFunctionDone(), We have to WAIT for an outstanding item to Catch Up!');
		}
	}
	else
	{
		Log('Queue::onFunctionDone(), No items left Loading... Goto Next item OR execute Finished Callback');
		this.catchup = false;
		if (!this.items.length) { this.doneCallback.call(this); } else { this.next(); }
	}
};

Queue.prototype.drop = function(item)
{
	Log('Queue: Drop item ID = '+item.id+' from queue.');
	delete this.items[item];
};


this.Queue = Queue;

}.call(this));