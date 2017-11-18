<?php namespace OneFile;

use Log; // Debug only!


class Collection
{

    protected $items = [];

	/**
	 * Override me!
	 *
	 * Extend this class and implement your own constructor to initialise
	 * the items's content.
	 *
	 */
    public function __construct($items = null)
    {
		if ($items and is_array($items)) { $this->items = $items; }
	}


    /**
     * Deep merge two arrays
     *
     * @param  array $collection_items
     * @param  array $extra_items
     * @param  number $maxDepth
     * @return array
     */
    private function deepMerge(array &$collection_items, array $extra_items, $maxDepth)
	{
        foreach ($extra_items as $key => $item)
        {
            if (isset($collection_items[$key]) and is_array($item) and $maxDepth > 0)
            {
                $this->deepMerge($collection_items[$key], $item, $maxDepth - 1);
            }
            else {
                $collection_items[$key] = $item;
            }
        }
    }


    /**
     * Merges an external array into the internal $items array
     * Use to layer items files by environment or just add seperate module itemss.
     *
     * @param array $extra_items
     * @return \OneFile\Config
     */
    public function merge(array $extra_items, $maxDepth = 5)
    {
        $this->deepMerge($this->items, $extra_items, $maxDepth);
        return $this;
    }


    /**
     * Sets a items value with dot-notation allowed
	 *
	 * If no key is given, the entire array will be replaced.
	 *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
		if (is_null($key))
		{
			return $this->items = $value;
		}

		$array = & $this->items;

		$keys = explode('.', $key);

		while (count($keys) > 1)
		{
			$key = array_shift($keys);

			// If the key doesn't exist at this depth, we will just create an empty array
			// to hold the next value, allowing us to create the arrays to hold final
			// values at the correct depth. Then we'll keep digging into the array.
			if ( ! isset($array[$key]) || ! is_array($array[$key]))
			{
				$array[$key] = array();
			}

			$array = & $array[$key];
		}

		$array[array_shift($keys)] = $value;

		return $array;
    }


    /**
     * Resets / initializes the items's content AFTER instantiation.
     *
     * @param array $items
     */
	public function init(array $items)
	{
		$this->items = $items;
	}


    /**
     * Checks if a items value exists with dot-notation allowed
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        if (isset($this->items[$key]))
        {
            return true;
        }

        $array = & $this->items;

        foreach (explode('.', $key) as $segment)
        {
            if ( ! is_array($array) or ! array_key_exists($segment, $array))
            {
                return false;
            }

            $array = & $array[$segment];
        }

        return true;
    }


    /**
     * Gets a items value with dot-notation allowed
     * Uses code from laravel array_get() helper
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        if (is_null($key) || $key == '*')
        {
			//Log::collection('Collection::get(), items = ' . print_r($this->items, true));
            return $this->items;
        }

        if (isset($this->items[$key]))
        {
            return $this->items[$key];
        }

        $array = & $this->items;

        foreach (explode('.', $key) as $segment)
        {
            if ( ! is_array($array) or ! array_key_exists($segment, $array))
                return $default;

            $array = & $array[$segment];
        }

        return $array;
    }


	/**
	 * Removes all or a single element from the items.
	 */
	public function forget($key = null)
	{
		if (is_null($key))
		{
			$this->items = array();
			return;
		}

		if (isset($this->items[$key]))
		{
			unset($this->items[$key]);
			return;
		}

		$array = & $this->items;

		$keys = explode('.', $key);

		while (count($keys) > 1)
		{
			$topmostKeySegment = array_shift($keys);

			if ( ! isset($this->items[$topmostKeySegment]) || ! is_array($this->items[$topmostKeySegment]))
			{
				return;
			}

			$array =& $array[$topmostKeySegment];
		}

		$targetKey = array_shift($keys);

		unset($array[$targetKey]);
	}


	/**
	 * Returns the entire items array!
	 */
	public function all()
	{
		return $this->get();
	}


	/**
	 * Clears the entire items!
	 */
	public function clear()
	{
		$this->forget();
	}


	/**
	 * Pushes a new item onto the end of a saved collection. (i.e. an Array-of-Items)
	 *
	 * If "$collection_key" can't found, a new collection will be created containing the
	 * supplied item.
	 *
	 * If "$collection_key" exists, but is NOT a collection, it will be replaced with a
	 * new collection containing the original value (as first item) + the supplied item.
	 *
	 */
    public function push($collection_key, $collection_item)
    {
		$collection = $this->get($collection_key);

		if ($collection)
		{
			if (is_array($collection))
			{
				// Just add it!
				$collection[] = $collection_item;
			}
			else {
				// Convert to colection
				$collection = array($collection, $collection_item);
			}
		}
		else
		{
			// Create new collection
			$collection = array($collection_item);
		}

		$this->set($collection_key, $collection);
	}


	/**
	 * Finds and returns the FISRT matching item from within a saved collection.
	 *
	 * Collection items MUST be searchable Objects or associative Arrays.
	 *
	 */
	public function pick($collection_key, $search_term, $search_field = 'id')
	{
		if ( ! $collection = $this->get($collection_key)) { return; }

		$picked_item = null;

		if (is_array($collection[0]))
		{	// typeof Item == Array
			// Could use array_filter() or and itterator class here... This is just very easy to follow and depends on NOTHING
			foreach ($collection as $item)
			{
				$field_value = $item[$search_field];
				if ($field_value == $search_term) { $picked_item = $item; break; }
			}
		}
		else
		{	// typeof Item == Object
			foreach ($collection as $item)
			{
				$field_value = $item->{$search_field};
				if ($field_value == $search_term) { $picked_item = $item; break; }
			}
		}

		return $picked_item;
	}


	/**
	 * REMOVES and returns the FIRST matching item from within a saved collection.
	 *
	 * Collection items MUST be searchable Objects or associative Arrays.
	 *
	 */
	public function pluck($collection_key, $search_term, $search_field = 'id')
	{
		$logPrefix = 'Collection::pluck(), ';
		Log::collection($logPrefix . "collection-key: $collection_key, search-term: $search_term, search-field: $search_field");

		// Get collection at "$collection_key" or Abort
		if ( ! $collection = $this->get($collection_key)) { return; }

		//Log::collection($logPrefix . 'Collection (before): ' .print_r($collection, true));

		$plucked_item = null;
		$remaining_items = array();

		if (is_array($collection[0]))
		{	// typeof Item == Array
			//Log::collection($logPrefix . 'Collection Items Type == ARRAY');
			foreach ($collection as $item)
			{
				$field_value = $item[$search_field];
				if ($field_value == $search_term and !$plucked_item) { $plucked_item = $item; continue; }
				$remaining_items[] = $item;
			}
		}
		else
		{	// typeof Item == Object
			//Log::collection($logPrefix . 'Collection Items Type == OBJECT');
			foreach ($collection as $item)
			{
				$field_value = $item->{$search_field};
				if ($field_value == $search_term and !$plucked_item) { $plucked_item = $item; continue; }
				$remaining_items[] = $item;
			}
		}

		// Replace collection with the result collection
		$this->set($collection_key, $remaining_items);

		//Log::collection($logPrefix . 'Collection (after): ' .print_r($this->get($collection_key), true));
		//Log::collection($logPrefix . 'Plucked Item: ' .print_r($plucked_item, true));

		return $plucked_item;
	}


	/**
	 * Pops and returns the last value added to a collection saved in this items. (i.e. an Array-of-Items)
	 *
	 * If the target is not an array, the result will be the target's value
	 *
	 * If the target can't be found, the result will be NULL
	 *
	 */
    public function pop($collection_key, $default = null)
    {
		$collection = $this->get($collection_key);

		if ($collection)
		{
			if (is_array($collection))
			{
				$result = array_pop($collection);
				$this->set($collection_key, $collection);
				return $result;
			}
			else
			{
				$result = $collection;
				$this->set($collection_key, array());
				return $result;
			}
		}

		return $default;
	}


	/**
	 * Filters a collection using the provided TEST FUNCTION
	 *
	 * All items that produce a FALSY TEST RESULT will be removed
	 *
	 * If NO TEST FUNCTION is provided, all items that evaluate FALSY will be removed
	 *
	 * @param  string   $collection_key
	 * @param  \Closure $fn_test
	 * @return array
	 */
	public static function filter($collection_key, $fn_test = null)
	{
		$logPrefix = 'Collection::filter(), ';
		Log::collection($logPrefix . "collection-key: $collection_key");

		// Get collection at "$collection_key" or Abort
		if ( ! $collection = $this->get($collection_key)) { return; }

		$filtered_collection = [];

		if (is_null($fn_test))
		{
			foreach ($collection as $item)
			{
				if ($item) { $filtered_collection[] = $item; }
			}
		}
		else
		{
			foreach ($collection as $item)
			{
				if ($fn_test($item)) { $filtered_collection[] = $item; }
			}
		}

		return $filtered_collection;
	}


	/**
	 * Similar to Collection::filter(), but more specific and limited in implementation.
	 *   + No annonymous function!
	 *
	 * Finds and returns ALL items within a saved collection that matches the "search term".
	 *
	 * Collection items MUST be searchable Objects or associative Arrays.
	 *
	 * @param string   $collection_key
	 * @param string   $search_term
	 * @param string   $search_field
	 * @param boolean  $precise_match
	 * @return array
	 */
	public function search($collection_key, $search_term, $search_field = 'id', $precise_match = true)
	{
		// Get collection at "$collection_key" or Abort
		if ( ! $collection = $this->get($collection_key)) { return; }

		$matching_items = array();

		$items_are_objects = is_object($collection[0]);

		if ($precise_match and $items_are_objects)
		{
			// Precise Match + Item type: Object
			foreach ($collection as $item)
			{
				$field_value = $item->{$search_field};
				if ($field_value == $search_term) { $matching_items[] = $item; }
			}
		}
		elseif ($precise_match)
		{
			// Precise Match + Item type: Array
			foreach ($collection as $item)
			{
				$field_value = $item[$search_field];
				if ($field_value == $search_term) { $matching_items[] = $item; }
			}
		}
		elseif ($items_are_objects)
		{
			// "Like" Match + Item type: Object
			foreach ($collection as $item)
			{
				$field_value = $item->{$search_field};
				if (strpos($field_value, $search_term) !== false) { $matching_items[] = $item; }
			}
		}
		else
		{
			// "Like" Match + Item type: Array
			foreach ($collection as $item)
			{
				$field_value = $item[$search_field];
				if (strpos($field_value, $search_term) !== false) { $matching_items[] = $item; }
			}
		}

		return $matching_items;
	}


	/**
	 * REMOVES and returns ALL items within a saved collection that matches the "search term".
	 *
	 * Collection items MUST be searchable Objects or associative Arrays.
	 *
	 */
	public function extract($collection_key, $search_term, $search_field = 'id')
	{
		// Get collection at "$collection_key" or Abort
		if ( ! $collection = $this->get($collection_key)) { return; }

		$extracted_items = array();
		$remaining_items = array();

		if (is_array($collection[0]))
		{	// typeof Item == Array
			foreach ($collection as $item)
			{
				$field_value = $item[$search_field];
				if ($field_value == $search_term) { $extracted_items[] = $item; } else { $remaining_items[] = $item; }
			}
		}
		else
		{	// typeof Item == Object
			foreach ($collection as $item)
			{
				$field_value = $item->{$search_field};
				if ($field_value == $search_term) { $extracted_items[] = $item; } else { $remaining_items[] = $item; }
			}
		}

		// Replace collection with the result collection
		$this->set($collection_key, $remaining_items);

		return $extracted_items;
	}


	/**
	 * Remove items from this items that do not appear in
	 * another items ($diff_with) used as comparator.
	 *
	 */
    public function diff(array $diff_with)
    {
		if ( ! $diff_with) { return; }
        foreach ($diff_with as $key => $value) { unset($this->items[$key]); }
    }

}
