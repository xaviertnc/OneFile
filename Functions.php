<?php

//Laravel4 Illuminate/Support/helpers.php
if ( ! function_exists('with'))
{
  /**
   * Return the given object. Useful for chaining.
   *
   * @param  mixed  $object
   * @return mixed
   */
  function with($object)
  {
    return $object;
  }
}

//Laravel4 Illuminate/Support/helpers.php
if ( ! function_exists('value'))
{
  /**
   * Return the default value of the given value.
   *
   * @param  mixed  $value
   * @return mixed
   */
  function value($value)
  {
    return $value instanceof Closure ? $value() : $value;
  }
}

//Laravel4 Illuminate/Support/helpers.php
if ( ! function_exists('array_get'))
{
  /**
   * Get an item from an array using "dot" notation.
   * NOTE: If you don't change $array inside this function, $array will be passed by reference
   * and NO COPY of array will be made!
   *
   * @param  array   $array
   * @param  string  $key
   * @param  mixed   $default Could be a closure!
   * @return mixed
   */
  function array_get($array, $key, $default = null)
  {
    if (is_null($key))
      return $array;

    if (isset($array[$key]))
      return $array[$key];

    foreach (explode('.', $key) as $segment)
    {
      if ( ! is_array($array) or ! array_key_exists($segment, $array))
        return value($default);

      $array = $array[$segment];
    }

    return $array;
  }
}

//Adapted version of array_get
if ( ! function_exists('array_has'))
{
  /**
   * Checks for an item from an array using "dot" notation.
   * NOTE: If you don't change $array inside this function, $array will be passed by reference
   * and NO COPY of array will be made!
   *
   * @param  array   $array
   * @param  string  $key
   * @return boolean
   */
  function array_has($array, $key)
  {
    if (isset($array[$key]))
      return true;

    foreach (explode('.', $key) as $segment)
    {
      if ( ! is_array($array) or ! array_key_exists($segment, $array))
        return false;

      $array = $array[$segment];
    }

    return true;
  }
}

if ( ! function_exists('array_set'))
{
  /**
   * Set an array item using "dot" notation.
   * NOTE: $array is passed by reference!
   *
   * @param  array   $array
   * @param  string  $key
   * @param  mixed   $value Could be a closure!
   * @return mixed
   */
  function array_set(&$array, $key, $value)
  {
    if (strpos($key, '.') === false)
    {
      $array[$key] = value($value);
    }
    else
    {
      $current = & $array;

      foreach (explode('.', $key) as $key)
      {
        $current = & $current[$key];
      }

      $current = value($value);
    }
  }
}


//http://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array
//http://stackoverflow.com/a/1320156/5084736
if ( ! function_exists('array_flatten'))
{
  function array_flatten(array $array) {
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
  }
}


/**
 * Returns the first item in an array if your'e not sure if the keys will
 * be numerical or strings.
 *
 * I.e. $array[0] might NOT be the first item!
 *
 * @param  array $array
 * @return mixed
 *
 */
function array_first($array = null)
{
  if (empty($array)) return $array;
  foreach ($array as $item) return $item;
}


/**
 * Forces any value into an array.
 *
 * @param mixed $value
 * @result array
 */
function array_ensure($value = null)
{
  return is_null($value) ? array() : (is_array($value) ? $value : array($value));
}


/**
 * Only return array elements with keys in the whitelist
 * Note: $only can NOT be a closure. Use php's array_filter() instead!
 *
 * @param array  $array
 * @param mixed  $only E.g. null, 'keystr', [], ['id','desc']
 * @return array $only == null or $only == [] returns $array unmodified.
 */
function array_only($array = null, $only = null)
{
  return ($array and $only) ? array_intersect_key($array, array_flip(array_ensure($only))) : $array;
}


/**
 * Only return array elements with keys not in the exception list
 * Note: $except can NOT be a closure. Use php's array_filter() instead!
 *
 */
function array_except($array = null, $except = null)
{
  return ($array and $except) ? array_diff_key($array, array_flip(array_ensure($except))) : $array;
}


/**
 * Rename the keys of an $array according to a new $keymap.
 * Only partially map if $keymap doesn't cover all the existing keys.
 * Only add missing keys if $add_missing_keys == true.
 *
 * Examples:
 * array_map_keys($arr, ['curkey1'=>'newkey1', 'curkey2'=>'newkey2', ...] or fn($curkey(n)){ ... return $newkey(n); }
 *
 * @param  array $array
 * @param  mixed $keymap
 * @param  bool  $add_missing_keys
 * @param  mixed $missing_default     Default value to use when adding missing items
 *
 * @return array
 */
function array_map_keys($array = null, $keymap = null, $add_missing_keys = null, $missing_default = null)
{
  if ( ! $array or ! $keymap) return $array;

  $mapped = [];
  $keys = array_keys($array);
  $keymap = is_array($keymap) ? $keymap : (is_callable($keymap) ? array_map($keymap, $keys) : null);
  if ( ! $keymap) return $array;

  foreach ($keys as $key)
  {
     $newkey = isset($keymap[$key]) ? $keymap[$key] : $key;
     // $newkey = fn($key) allows keys to be decorated. E.g. '1' -> 'item_1'
     if (is_callable($newkey)) $newkey = $newkey($key);
     $mapped[$newkey] = $array[$key];
  }

  if ($add_missing_keys)
  {
    $missing = array_diff($keys, $map);
    foreach ($missing as $key) { $mapped[$key] = $missing_default; }
  }

  return $mapped;
}


function obj_map($obj = null, $propmap = null, $add_missing_props = null, $missing_default = null)
{
  $objArr = (array) $obj;
  $objArr = array_map_keys($objArr, $propmap, $add_missing_props, $missing_default);
  return (object) $objArr;
}


/**
 * Examples:
 * array_map_items($arr, ['propName1'=>'newPropName1', 'propName2'=>'newPropName2', ...]
 *    or fn($curPropName(n)){ ... return $newPropName(n); }
 *
 * @update: 29 Nov 2017: Allow an empty $map param to re-index the array according to a
 *          property value in the item objects. (e.g. The "id" property)
 *
 * @param  array  $array
 * @param  array  $map
 * @param  string $index_prop  Index / Re-index the array using the specified array item property's value
 * @param  bool   $add_missing
 * @param  mixed  $missing_default  Default value to use when adding missing items
 *
 * @return array
 */
function array_map_items($array = null, $map = null, $index_prop = null, $add_missing = null, $missing_default = null)
{
  if ( ! $array) return $array;

  $mapped = [];

  $itemsAreObjects = is_object(array_first(array_ensure($array)));

  foreach ($array as $index => $item)
  {
    if ($map)
    {
      $mapped_item = $itemsAreObjects ? obj_map($item, $map, $add_missing, $missing_default) : array_map_keys($item, $map, $add_missing, $missing_default);
    }
    else
    {
      // We only want to re-index the array...
      // Maybe we should create a new function: array_reindex()?
      // Also considder array_map_keys()?
      $mapped_item = $item;
    }
    $mapped_index = $index_prop ? ($itemsAreObjects ? $mapped_item->{$index_prop} : array_get($mapped_item, $index_prop, $index)) : $index;
    $mapped[$mapped_index] = $mapped_item;
  }

  return $mapped;
}


/**
 * Allow performing multiple transformations on an array in one step.
 * Note: $options should NOT be a closure. Use php's array_filter() function for that!
 *
 * Examples:
 * $lst = array_adapt($arr, ['only' => ['fullname', 'age']])
 * $lst = array_adapt($arr, ['except' => 'id', 'map' => ['name' => 'label']])
 * $lst = array_adapt($arr, ['map-keys'  => ['id' => 'no', 'description' => 'label']])
 * $lst = array_adapt($arr, ['map-items' => ['id' => 'no', 'description' => 'label']])
 *
 * @param  array $array
 * @param  array $options
 * @return array
 */
function array_adapt($array = null, $options = null)
{
  if (empty($array)) return array();
  if (empty($options)) return $array;

  $result = $array;

  if (isset($options['only'])) { $result = array_only($result, $options['only']); }
  if (isset($options['except'])) { $result = array_except($result, $options['except']); }
  if (isset($options['map-keys'])) { $result = array_map_keys($result, $options['map-keys']); }
  if (isset($options['map-items'])) { $result = array_map_items($result, $options['map-items'], array_get($options, 'index-by')); }

  return $result;
}


/**
 * array_list() returns an array listing the values of a single property in each collection item,
 * indexed naturally/numerically or by another property's value.
 *
 * @param  array   $array
 * @param  string  $prop_name
 * @param  string  $index_by
 * @return array
 */
function array_list($array = null, $prop_name = null, $index_by = null)
{
  if ( ! ($array and $prop_name and is_array($array))) return array();

  $itemsAreObjects = is_object(array_first($array));

  if ( ! $index_by)
  {
    return $itemsAreObjects
      ? array_map(function($obj) use($prop_name) { return $obj->{$prop_name}; }, $array)
      : array_map(function($arrItem) use($prop_name) { return $arrItem[$prop_name]; }, $array);
  }

  $list = [];

  if ($itemsAreObjects)
  {
    foreach ($array as $obj)
    {
      if ($index_by) { $list[$obj->{$index_by}] = $obj->{$prop_name}; } else { $list[] = $obj->{$prop_name}; }
    }
  }
  else
  {
    foreach ($array as $arrItem)
    {
      if ($index_by) { $list[$arrItem[$index_by]] = $arrItem[$prop_name]; } else { $list[] = $arrItem[$prop_name]; }
    }
  }

  return $list;
}


function obj_extend($obj1, $obj2)
{
  if (is_object($obj1) && is_object($obj2))
  {
    return (object) array_merge((array) $obj1, (array) $obj2);
  }
  return (object) $obj1;
}


function indent($n = 1, $dent = "\t")
{
  return str_repeat($dent, $n);
}


function redirect($url = '')
{
  header('location:' . $url);
  exit(0);
}
