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
class Events
{
	public static
		$events=array(),
		$handlers=array();

	public static function raise_event($id,$params=array(),$creator=null)
	{
		File::log('Events','Events::raise_event(), Event = '.$id);

		$event = new Event($id, $params);
		self::$events[$id] = $event;

		foreach (self::$handlers as $handler)
		{
			if($handler->event_to_monitor == $id)
			{
				$event->handled = $handler->call($params,$creator);

				if ($event->handled)
					return $event->handled;
			}
		}

		return false;
	}

	public static function add_handler($event_to_monitor, $handler_method_to_call, $handler_object=null)
	{
		$handler = new EventHandler($event_to_monitor, $handler_method_to_call, $handler_object);
		self::$handlers[] = $handler;
	}

} // end: EventManager Class