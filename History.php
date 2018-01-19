<?php namespace OneFile;

/**
 * History Service - Ported and adapted from the KL project
 * to be more framework agnostic and general purpose.
 *
 *  Features:
 *  =========
 *  - Keep a request-url history on the server.
 *
 *  - Allows session store override
 *
 *  - Separated construct and initialize
 *
 *  - Provide methods like:
 *     - rollback([numberOfSteps])                // Trim the history by n-steps from the tail-end
 *     - back([defaultBackUrl], [numberOfSteps])  // Get the previous DIFFERENT URL accessed or one n-steps back.
 *     - beforeLast([defaultBackUrl])             // Get the "before last" DIFFERENT URL accessed
 *     - last([defaultBackUrl])
 *
 *  - Prevent back-link loops!
 *
 * @date   05 Dec 2013
 * @author By. C Moller
 *
 * @update 18 Apr 2014
 *   - Changed filename + namespace + Allow for framework specific SESSION + SERVER
 *
 * @update 04 May 2014
 *   - Total rewrite
 *   - Removed all static parts.
 *
 * @update 19 Feb 2016
 * 	- Added "before last" method + Default URL to referer method
 *
 * @update 29 Nov 2016
 *  - Moved loading state from constructor() to on-demand
 *    in update(), rollback(), last() and beforelast()
 *
 * @update 29 Dec 2016
 *  - Added "back" method as base for "last" and "beforelast"
 *  - Updated some syntax styling
 *
 * @update 04 Dec 2017
 *  - Updated more syntax styling.  Removed snake_case from
 *    all property and method names, except the "override-me" props.
 *  - Added more comments + Features list
 *
 */

class History
{
	/**
	 * The number of previous url links stored. (History depth)
	 * Accessing the same url over-and-over again in succession only stores the first attempt!
	 *
	 * @var integer
	 */
	protected $levels;

	/**
	 * Current history as retrieved in Constructor or modified via update(), rollback(), etc
	 *
	 * @var array
	 */
	protected $historyItems;

	/**
	 * The key under which the history will be stored in your choice of session manager
	 *
	 * @var string
	 */
	protected $historySessionKey;

	/**
	 * Loads or initializes history
	 *
	 * @param integer $levels
	 * @param string $historySessionKey
	 */
	public function __construct($levels = 5, $historySessionKey = '__HISTORY__')
	{
		$this->levels = ($levels < 3) ? 3 : $levels;
		$this->historySessionKey = $historySessionKey;
	}

	/**
	 * OVERRIDE! Replace with more robust implementation if necessary
	 *
	 * @return string
	 */
	protected function _http_referer()
	{
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
	}

	/**
	 * OVERRIDE! Replace with you own session driver...
	 *
	 * @param string $key
	 */
	protected function _session_forget($key)
	{
		if(session_id()) unset($_SESSION[$key]);
	}

	/**
	 * OVERRIDE! Replace with you own session driver...
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	protected function _session_read($key, $default = null)
	{
		if ( ! session_id()) session_start();
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
	}

	/**
	 * OVERRIDE! Replace with you own session driver...
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	protected function _session_write($key, $value)
	{
		if ( ! session_id()) session_start();
		$_SESSION[$key] = $value;
	}

	/**
	 * Saves an empty history array.
	 *
	 * @param array $history
	 */
	public function initialize()
	{
		$this->historyItems = $this->_session_read($this->historySessionKey);

		if ( ! $this->historyItems)
		{
			$this->historyItems = array();
			for ($i=0; $i < $this->levels; $i++) $this->historyItems[] = null;
			$this->_session_write($this->historySessionKey, $this->historyItems);
		}
	}

	/**
	 * Completely removes the history key from the session.
	 *
	 * @param array $history
	 */
	public function destroy()
	{
		$this->historyItems = array();
		$this->_session_forget($this->historySessionKey);
	}

	/**
	 * C. Moller - 27 Apr 2013
	 *
	 * Manage the dreaded "Back Button"!!!
	 *
	 * **** PS: This is a tricky piece of code that catches me every time I try to re-do it! *****
	 *
	 * Updated and significantly simplified - 05 May 2014
	 *
	 */
	public function update($current_url)
	{
		if ( ! $this->historyItems) { $this->initialize(); }

		$last_item = $this->levels - 1;

		if ($current_url != $this->historyItems[$last_item])
		{
			if ($current_url == $this->historyItems[$last_item - 1])
			{
				unset($this->historyItems[$last_item]);
				array_unshift($this->historyItems, null);
			}
			else
			{
				array_shift($this->historyItems);
				$this->historyItems[] = $current_url;
			}

			$this->_session_write($this->historySessionKey, $this->historyItems);
		}
	}

    /**
     * Trim the history by n-steps from the tail-end
     *
     * Use Case Example:
     * =================
     * If we have a "Create Page View" that is replaced by an "Edit Page View" on save,
     * we don't want any back-links on the "Edit Page View" to send us back to the previous "Create Page View"!
     * We typically want to erase the last entry (aka "Create Page View" entry) from the history so the new
     * previous entry would be the page BEFORE we moved to "Create Page View".
     *
     * @param integer $steps
     */
	public function rollback($steps = 1)
	{
		if ( ! $this->historyItems) { $this->initialize(); }

		if ($steps < $this->levels)
		{
			$last_item = $this->levels - 1;

			for ($i=0; $i < $steps; $i++)
			{
				unset($this->historyItems[$last_item]);
				array_unshift($this->historyItems, null);
			}
		}
		else
		{
			$this->initialize();
		}

		$this->_session_write($this->historySessionKey, $this->historyItems);
	}

	/**
	 * Get the previous different URL accessed
	 *
	 * @param string $default_url
	 * @param integer $backsteps
	 * @return string
	 */
	public function back($default_url = null, $backsteps = 1)
	{
		if ( ! $this->historyItems) { $this->initialize(); }
		$current_url_index = $this->levels - 1;
		$last = $this->historyItems[$current_url_index - $backsteps];
		return is_null($last) ? $default_url : $last;
	}

	/**
	 * Alias for "back"
	 *
	 * @param string $default_url
	 * @return string
	 */
	public function last($default_url = null)
	{
		return $this->back($default_url);
	}

	/**
	 * Get the "before last" different URL accessed
	 *
	 * @param string $default_url
	 * @return string
	 */
	public function beforeLast($default_url = '')
	{
		return $this->back($default_url, 2);
	}

	/**
	 * Used if we want to go back to the referring page and NOT necessarily to the previous page.
	 * After a POST to the same url, the referring page will be the same page as the current page!
	 *
	 * Note: This function should strictly not be part of this module.  It's included just because it is often needed
	 * where this module is used.
	 *
	 * @return string
	 */
	public function referer($default_url = '')
	{
        $referer = $this->_http_referer();

        if ( ! $referer)
        {
            $referer = $default_url ;
        }

        return $referer;
    }
}
