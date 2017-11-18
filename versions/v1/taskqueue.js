/* 
 * Sync, Async or Sync+Async Capable Function Queue
 * Example:
 *
 *	function fetchNotice(ajaxTask)
 *	{
 *		$.get(controller.baseUrl, { r: "notices", do: "ajaxGet", id: $("#nid").html() }).then(function(noticeObj)
 *		{
 *			ajaxTask.done(noticeObj);
 *		});
 *	};
 *
 * 	function fetchNoticeFields(ajaxTask, asyncResultsArr, lastSyncResult)
 *	{
 *		$.get(controller.baseUrl, { r: "noticeFields", do: "ajaxGet" }).then(function(noticeFieldsArr)
 *		{
 *			controller.noticeFields = new NoticeFieldsMapper(noticeFieldsArr, asyncResultsArr);
 *			ajaxTask.done();
 *		});
 *	}
 *
 * 	var ajaxLoader = new TaskQueue();
 * 	ajaxLoader.add(fetchNotice);
 * 	ajaxLoader.add(fetchRegexPatterns);
 * 	ajaxLoader.addSync(fetchNoticeFields);
 * 	ajaxLoader.start(function(error) {
 * 		if (error) {
 * 			debug.log('error', 'AJAX Loader Error: ' + error );
 * 			return error;
 * 		}
 * 		// App Initialize Code Goes Here...
 * 	}, this);
 *
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 06 Nov 2014
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 */

;(function(context) {

	var debug = true;


	function Log()
	{
		if (debug && typeof window.console && window.console) { window.console.log.apply(window.console, arguments); }
	}


	function Task(func, context, type, queue)
	{
		Log('Task::construct()');

		this.args = [];
		this.func = func;
		this.context = context;
		this.type = type || 'async';
		this.queue = queue;
		this.result = null;

		this.done = function(taskCallbackResult)
		{
			Log('Task::done()');
			this.result = taskCallbackResult || null;
			this.queue.update(this);
			this.queue.next(this);
		};

		this.next = function(taskCallbackResult)
		{
			this.done(taskCallbackResult);
		};

		this.endQueue = function(taskCallbackResult)
		{
			Log('Task::endQueue()');
			this.result = taskCallbackResult || null;
			this.queue.update(this);
			this.queue.done();
		};

		this.error = function(error)
		{
			Log('Task::error(), message = ' + error);
			this.queue.error = error;
			this.queue.update(this);
			this.queue.done();
		};

		this.cancelQueue = function()
		{
			Log('Task::cancelQueue()');
			this.queue.initialize();
		};

		this.run = function(lastAsyncResults, lastSyncResult)
		{
			Log('Task::run()');

			this.args.push(this);

			if (this.type === 'sync')
			{			
				if (typeof lastAsyncResults !== 'undefined') this.args.push(lastAsyncResults);
				if (typeof lastSyncResult !== 'undefined') this.args.push(lastSyncResult);
			}

			this.func.apply(this.context, this.args);
		};
	}


	function TaskQueue()
	{
		this.initialize = function()
		{
			Log('TaskQueue::initialize()');
			this.taskCount = 0;
			this.busyCount = 0;
			this.nextTaskIndex = 0;
			this.lastSyncResult = null;
			this.lastAsyncResults = [];
			this.doneCallback = null;
			this.doneContext = this;
			this.tasks = [];
			this.error = false;
		};

		this.add = function(func, context, type)
		{
			Log('TaskQueue::add(), type = [' + (type ? type : 'async') + ']');
			this.tasks.push(new Task(func, context, type, this));
			this.taskCount++;
		};

		this.addSync = function(func, context)
		{
			this.add(func, context, 'sync');
		};

		this.start = function(doneCallback, doneContext)
		{
			Log('TaskQueue::start()');
			this.doneCallback = doneCallback;
			this.doneContext = doneContext;
			this.next();
		};

		this.update = function(completedTask)
		{
			Log('TaskQueue::update()');
			if (completedTask.type === 'sync')
			{
				this.lastSyncResult = completedTask.result;
				this.lastAsyncResults = [];
			}
			else
			{
				this.lastAsyncResults.push(completedTask.result);
			}
			
			this.busyCount--;
			this.taskCount--;
		};

		this.next = function()
		{
			Log('TaskQueue::next(), taskCount=%i, busyCount=%i', this.taskCount, this.busyCount);
			if (this.taskCount > 0)
			{
				var task = this.tasks[this.nextTaskIndex];

				if (task.type === 'sync')
				{
					if ( ! this.busyCount)
					{
						// Only at a synchronized point in the queue does it make sense to
						// want to use the results from previous steps/tasks. At any
						// other time, the results could be incomplete!

						task.run(this.lastAsyncResults, this.lastSyncResult);

						this.busyCount++;
						this.nextTaskIndex++;
					}
				}
				else // task.type === 'async'
				{
					task.run();

					this.busyCount++;
					this.nextTaskIndex++;

					if (this.nextTaskIndex < this.taskCount) this.next();
				}
			}
			else
			{
				this.done();
			}
		};

		this.done = function()
		{
			Log('TaskQueue::done()');

			//Send the Queue itseld as parameter for DONE CALLBACK so we can access all results and the finishing state.
			this.doneCallback.call(this.doneContext, this);
			
			this.initialize();

			// Why Initialize Again?
			//	- It just feels better to clean up after use.
			//
			// Does it reduce memory usage / leakage?
			//	- Not likely, but maybe... :0)
			//
			// It does however allow re-using the same class instance for another task sequence!
		};

		// *** Constructor ***
		this.initialize();
	};

	context.TaskQueue = TaskQueue;

}(this));


