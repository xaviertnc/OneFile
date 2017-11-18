<?php namespace OneFile\MySql;

/**
 *	@author C. Moller - 24 May 2014 <xavier.tnd@gmail.com> 
 */
class Field
{
	/**
	 *
	 * @var type 
	 */
	public $name;
	
	/**
	 *
	 * @var type 
	 */
	public $type;
	
	/**
	 *
	 * @var type 
	 */
	public $allow_null;
	
	/**
	 *
	 * @var type 
	 */
	public $is_index;
	
	/**
	 *
	 * @var type 
	 */
	public $def_value;
	
	/**
	 *
	 * @var type 
	 */
	public $extras;

	/**
	 * 
	 * @param array $definition
	 */
	function __construct(array $definition)
	{
		$this->name		  = $definition[0];
		$this->type		  = $definition[1];
		$this->allow_null = $definition[2];
		$this->is_index	  = $definition[3];
		$this->def_value  = $definition[4];
		$this->extras	  = $definition[5];
	}

	function __toString()
	{
		return 'FIELD: name='.$this->name.', type='.$this->type.', allow_NULL='.$this->allow_null.
			', is_index='.$this->is_index.', def_value ='.$this->def_value.', extras = '.$this->extras;
	}
}

/**
 * @author C. Moller 28 Aug 2014 <xavier.tnc@gmail.com>
 */
class FieldsCollection
{
}