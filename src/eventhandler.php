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
class EventHandler
{
	public
		$event_to_monitor,
		$handler_object,
		$handler_method_to_call;

	public function __construct($event_to_monitor, $handler_method_to_call, $handler_object=null)
	{
		if(is_object($handler_object))
			$class = get_class($handler_object);
		else
			$class = $handler_object;

		File::log('Events','EventHandler::__construct(), Event = '.$event_to_monitor.
			', Class = '.$class.', Method = '.$handler_method_to_call);

		$this->event_to_monitor = $event_to_monitor;
		$this->handler_method_to_call = $handler_method_to_call;
		$this->handler_object = $handler_object;
	}

	public function call(&$params,$event_creator)
	{

		if(is_object($this->handler_object))
		{
			File::log('Events','EventHandler::call(), Class = '.get_class($this->handler_object).', Method = '.$this->handler_method_to_call);
			if(method_exists($this->handler_object, $this->handler_method_to_call))
				return $this->handler_object->{$this->handler_method_to_call}($params,$event_creator);
			else
				return false;
		}
		elseif($this->handler_object)
		{
			File::log('Events','EventHandler::call(), Static Class = '.$this->handler_object.', Method = '.$this->handler_method_to_call);
			//Note: The following function returns false on any errors.  So no need to check if method exists!
			return call_user_func_array(array($this->handler_object,$this->handler_method_to_call),array($params,$event_creator));
		}
		elseif($this->handler_method_to_call)
		{
			File::log('Events','EventHandler::call(), Call Global Function = '.$this->handler_method_to_call);
			//Note: The following function returns false on any errors.  So no need to check if the function exists!
			return call_user_func_array($this->handler_method_to_call,array($params,$event_creator));
		}
		else
			return false;
	}
}