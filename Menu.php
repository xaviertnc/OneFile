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
	public $firstChild;

	/**
	 *
	 * @var string
	 */
	public $lastChild;

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
		if (isset($this->$key))
		{
			return $this->$key;
		}
		else
		{
			return $default;
		}
	}


	public function __construct($id, $text, $properties = array())
	{
		$this->id = $id;

		$this->text = $text;

		if ($properties)
		{
			foreach ($properties as $key => $value)
			{
				$this->set($key, $value);
			}
		}
	}


	public function __toString()
	{
		return $this->text;
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


	function getItem($id)
	{
		return isset($this->items[$id]) ? $this->items[$id] : null;
	}


	function setItem($id, MenuItem $item)
	{
		$this->items[$id] = $item;

		return $item;
	}


	function addItem(MenuItem $newItem)
	{
		if ( ! $newItem->get('parent'))
		{
			if ( ! $this->items)
			{
				$this->first = $newItem->id;
			}
			else
			{
				$this->getItem($this->last)->next = $newItem->id;
				$newItem->prev = $this->last;
			}

			$this->last = $newItem->id;
		}

		$this->setItem($newItem->id, $newItem);

		return $newItem;
	}


	function insertItem($parentId, MenuItem $newItem)
	{
		$parent = $this->getItem($parentId);

		if ($parent)
		{
			if ( ! $parent->firstChild)
			{
				$parent->firstChild = $newItem->id;
			}
			else
			{
				$this->getItem($parent->lastChild)->next = $newItem->id;
			}

			$newItem->parent = $parentId;
			$newItem->prev = $parent->lastChild;
			$parent->lastChild = $newItem->id;

			$this->setItem($newItem->id, $newItem);

			return $newItem;
		}
	}
}
