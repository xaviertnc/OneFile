<?php defined( '__ENTRY_POINT_OK__' ) or die( 'Invalid Entry Point' );
/**
 *
 * File: events.php
 * Desc: PHP Events Manager Class
 *
 * @author: C. Moller 21 Jun 2012
 *
 * Enables "attaching" code to specific points/events in the process flow
 *
 */
class Event
{
	public
		$id,
		$params,
		$creator,
		$handled;

	public function __construct($id,$params=array(),$creator=null)
	{
		$this->id = $id;
		$this->params = $params;
		$this->creator = $creator;
		$this->handled = false;
	}
}