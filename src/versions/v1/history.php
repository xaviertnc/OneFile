<?php namespace OneFile;

/**
 * 
 * By. C Moller - 2013 - 05 Dec 2013
 * Ported and adapted from the KL project to be more framework agnostic and general purpose.
 * Updated 18 Apr 2014 - Changed filename + namespace + Allow for framework specific SESSION + SERVER
 * Totally re-written - 04 May 2014 - Removed all static parts!
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
	protected $history_items;
	
	/**
	 * The key under which the history will be stored in your choice of session manager
	 * 
	 * @var string
	 */
	protected $history_session_key;

	/**
	 * Loads or initializes history
	 * 
	 * @param integer $levels
	 * @param string $history_session_key
	 */
	public function __construct($levels = 5, $history_session_key = '__HISTORY__')
	{	
		$this->levels = ($levels < 3)?3:$levels;
		$this->history_session_key = $history_session_key;
		$this->history_items = $this->_session_read($history_session_key);
		if(!$this->history_items) $this->initialize();

	}
	
	/**
	 * OVERRIDE! Replace with more robust implementation if necessary
	 * 
	 * @return string
	 */
	protected function _http_referer()
	{
		return isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:null;
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
		if(!session_id()) session_start();
		return isset($_SESSION[$key])?$_SESSION[$key]:$default;
	}
	
	/**
	 * OVERRIDE! Replace with you own session driver...
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	protected function _session_write($key, $value)
	{
		if(!session_id()) session_start();
		$_SESSION[$key] = $value;
	}

	/**
	 * Saves an empty history array.
	 * 
	 * @param array $history
	 */
	public function initialize()
	{	
		$this->history_items = array();
		for($i=0; $i < $this->levels; $i++) $this->history_items[] = null;
		$this->_session_write($this->history_session_key, $this->history_items);
	}		

	/**
	 * Completely removes the history key from the session.
	 * 
	 * @param array $history
	 */
	public function destroy()
	{	
		$this->history_items = array();
		$this->_session_forget($this->history_session_key);
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
		$last_item = $this->levels - 1;
				
		if($current_url != $this->history_items[$last_item])
		{
			if($current_url == $this->history_items[$last_item - 1])
			{
				unset($this->history_items[$last_item]);
				array_unshift($this->history_items, null);
			}
			else
			{
				array_shift($this->history_items);
				$this->history_items[] = $current_url;
			}
			
			$this->_session_write($this->history_session_key, $this->history_items);
		}		
	}
	
	/**
	 * Trace back on your steps and remove any number of recent history entries.
	 * Used to remove history on "Create Resource Page" links that convert to "Edit Resource" links after save.
	 * We don't want to go back to the "Create" page again when we press "back" on the "Edit" page.
	 * We want to go back to the page BEFORE the Create page in ONE step. i.e. Create Page history must be removed!
	 * 
	 * @param integer $steps
	 */
	public function rollback($steps = 1)
	{		
		if($steps < $this->levels)
		{
			$last_item = $this->levels - 1;
			
			for($i=0; $i < $steps; $i++)
			{
				unset($this->history_items[$last_item]);
				array_unshift($this->history_items, null);
			}
		}
		else
			$this->initialize();

		$this->_session_write($this->history_session_key, $this->history_items);
	}
	
	/**
	 * Get the previous different URL accessed
	 * 
	 * @param string $default_url
	 * @return string
	 */
	public function last($default_url = '')
	{
		$last = $this->history_items[$this->levels - 2];
		return is_null($last)?$default_url:$last;
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
	public function referrer()
	{
		return $this->_http_referer();
	}
}