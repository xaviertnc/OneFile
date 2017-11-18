<?php namespace OneFile;

use Countable;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

class Collection implements ArrayAccess, Countable, IteratorAggregate
{
	const ITEM_OBJ = 1;
	const ITEM_ARRAY = 2;
	const ITEM_STRING = 3;
	const ITEM_CALLABLE = 4;
	
	/**
	 * The items contained in the collection.
	 *
	 * @var array
	 */
	protected $items = array();
	
	/**
	 * Create a new collection.
	 *
	 * @param  array  $items
	 * @return void
	 */
	public function __construct(array $items = array())
	{
		$this->items = $items;
	}

	/**
	 * Create a new collection instance if the value isn't one already.
	 *
	 * @param  mixed  $items
	 * @return \Illuminate\Support\Collection
	 */
	public static function make($items)
	{
		if (is_null($items)) return new static;

		if ($items instanceof Collection) return $items;

		return new static(is_array($items) ? $items : array($items));
	}

	/**
	 * Get all of the items in the collection.
	 *
	 * @return array
	 */
	public function all()
	{
		return $this->items;
	}
	
	/**
	 * Put an item in the collection by key.
	 *
	 * @param  mixed  $key
	 * @param  mixed  $item
	 * @return void
	 */
	public function put($key, $item)
	{
		$this->items[$key] = $item;
		
		return $this;
	}	
	
	/**
	 * Put an item in the collection by key.
	 *
	 * @param  mixed  $item
	 * @return void
	 */
	public function append($item)
	{
		$this->items[] = $item;
		
		return $this;
	}
	
	/**
	 * Execute a callback over each item.
	 *
	 * @param  Closure  $callback
	 * @return \Illuminate\Support\Collection
	 */
	public function each(Closure $callback)
	{
		array_map($callback, $this->items);

		return $this;
	}
	
	/**
	 * Get the first item from the collection.
	 *
	 * @param  \Closure   $callback
	 * @param  mixed      $default
	 * @return mixed|null
	 */
	public function first($default = null)
	{
		return $this->items ? reset($this->items) : $default;
	}
	
	/**
	 * Get the last item from the collection.
	 *
	 * @param  \Closure   $callback
	 * @param  mixed      $default
	 * @return mixed|null
	 */
	public function last($default = null)
	{
		return $this->items ? end($this->items) : $default;
	}
	
	/**
	 * Get an item from the collection by key.
	 *
	 * @param  mixed  $key
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function get($key = null, $default = null)
	{
		if (is_null($key))
		{
			return $this->items;
		}
		
		if (array_key_exists($key, $this->items))
		{
			return $this->items[$key];
		}

		return $default;
	}
	
	/**
	 * 
	 * @param mixed $searchValue
	 * @param string $itemAttributeName
	 * @param type $itemType
	 * @return type
	 */
	public function find($searchValue, $itemAttributeName = 'id', $itemType = 1)
	{
		switch ($itemType)
		{
			case static::ITEM_OBJ:
				
				foreach ($this->items as $item)
				{
					if ((isset($item->$itemAttributeName) ? $item->$itemAttributeName : null) == $searchValue)
					{
						return $item;
					}
				}
				
				break;
			
				
			case static::ITEM_ARRAY:
				
				foreach ($this->items as $item)
				{
					if ((isset($item[$itemAttributeName]) ? $item[$itemAttributeName] : null) == $searchValue)
					{
						return $item;
					}
				}
				
				break;
			
			
			case static::ITEM_STRING:
				return $this->get(array_search($searchValue, $this->items));		
		}		
	}
	
	/**
	 * Count the number of items in the collection.
	 *
	 * @param integer $mode
	 * @return integer
	 */
	public function count($mode = COUNT_NORMAL)
	{
		return $mode ? : count($this->items);
	}

	/**
	 * Remove an item from the collection by key.
	 *
	 * @param  mixed  $key
	 * @return void
	 */
	public function forget($key)
	{
		unset($this->items[$key]);
	}
	
	/**
	 * Get and remove the last item from the collection.
	 *
	 * @return mixed|null
	 */
	public function pop()
	{
		return array_pop($this->items);
	}

	/**
	 * Push an item onto the beginning of the collection.
	 *
	 * @param  mixed  $value
	 * @return void
	 */
	public function prepend($value)
	{
		array_unshift($this->items, $value);
	}

	/**
	 * Push an item onto the end of the collection.
	 *
	 * @param  mixed  $value
	 * @return void
	 */
	public function push($value)
	{
		$this->items[] = $value;
	}
	
	/**
	 * Sort through each item with a callback.
	 *
	 * @param  Closure  $callback
	 * @return \Illuminate\Support\Collection
	 */
	public function sort(Closure $callback)
	{
		uasort($this->items, $callback);

		return $this;
	}
	
	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return array_key_exists($key, $this->items);
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->items[$key];
	}

	/**
	 * Set the item at a given offset.
	 *
	 * @param  mixed  $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		if (is_null($key))
		{
			$this->items[] = $value;
		}
		else
		{
			$this->items[$key] = $value;
		}
	}

	/**
	 * Unset the item at a given offset.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function offsetUnset($key)
	{
		unset($this->items[$key]);
	}

	/**
	 * Get an iterator for the items.
	 *
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->items);
	}
	
	public function __toString()
	{
		return 'Collection Says Hi! I have ' . $this->count() . ' items of type ' . gettype($this->first());
	}
}
