<?php namespace OneFile;

/**
 * By. C Moller: 27 Apr 2014
 */
class MenuItem
{
	/**
	 *
	 * @var string
	 */
	public $id;
		
	/**
	 *
	 * @var string
	 */
	public $text;
	
	/**
	 *
	 * @var string
	 */
	public $parent;

	/**
	 *
	 * @var string
	 */
	public $prev;
	
	/**
	 *
	 * @var string
	 */
	public $next;
	
	/**
	 *
	 * @var string
	 */
	public $first_child;
	
	/**
	 *
	 * @var string
	 */
	public $last_child;
	
	/**
	 *
	 * @var string
	 */
	public $path;
	
	
	function set($key, $value)
	{
		$this->$key = $value;
		return $this;
	}
	
	
	function get($key, $default = null)
	{
		if(isset($this->$key))
			return $this->$key;
		else
			return $default;
	}
	
	
	public function __construct($id, $text, $properties = array())
	{
		$this->id = $id;
		
		$this->text = $text;
		
		if($properties)
		{
			foreach($properties as $key=>$value)
			{
				$this->set($key, $value);
			}
		}
	}
}

/**
 * By. C Moller: 27 Apr 2014
 */
class Menu
{	
	/**
	 *
	 * @var string
	 */
	public $name;
		
	/**
	 *
	 * @var array of MenuItem
	 */
	public $items = array();
	
	/**
	 *
	 * @var string
	 */
	public $first;

	/**
	 *
	 * @var string
	 */
	public $last;
	
	
	/**
	 * 
	 * @param string $name
	 */
	public function __construct($name)
	{
		$this->name = $name;
	}
	
	
	function get($id)
	{
		return isset($this->items[$id])?$this->items[$id]:null;
	}
	
	
	function set($id, MenuItem $item)
	{
		$this->items[$id] = $item;
		
		return $item;
	}
	
	
	function add($id, $text, $properties = array())
	{
		$new_item = new MenuItem($id, $text, $properties);
						
		if(!$new_item->get('parent'))
		{
			if(!$this->items)
				$this->first = $id;
			else
			{
				$this->get($this->last)->next = $id;
				$new_item->prev = $this->last;
			}
			
			$this->last = $id;
		}
		
		$this->set($id, $new_item);
		
		return $new_item;
	}
	
	
	function add_to($parent_id, $id, $text, $properties = array())
	{
		$properties['parent'] = $parent_id;
		
		$parent = $this->get($parent_id);
		
		if($parent)
		{
			if(!$parent->first_child)
				$parent->first_child = $id;
			else
				$this->get($parent->last_child)->next = $id;

			$item = $this->add($id, $text, $properties);
			$item->prev = $parent->last_child;
			$parent->last_child = $id;
			
			return $item;
		}
	}
}