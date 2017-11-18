<?php namespace OneFile\MySql;

/**
 *	@author C. Moller - 24 May 2014 <xavier.tnd@gmail.com> 
 */
class Table
{
	/**
	 *
	 * @var string
	 */
	public $name;
	
	/**
	 *
	 * @var array
	 */
	public $fields;
	
	/**
	 *
	 * @var array
	 */
	public $fieldnames;
	
	/**
	 *
	 * @var boolean
	 */
	public $autoincrement;
	
	/**
	 *
	 * @var DbField
	 */
	public $pk;

	/**
	 * 
	 * @param \OneFile\MySql\MySql $db
	 * @param string $tablename
	 */
	function __construct(MySql $db, $tablename)
	{
		$this->name = $tablename;
		
		$column_definitions = $db->query('SHOW COLUMNS FROM ' . $db->quote($tablename));
		
		while($column_definition = $column_definitions->fetch(PDO::FETCH_NUM))
		{
			$field = new Field($column_definition);
			
			if($field->is_index == 'PRI')
				$this->pk = $field;
			
			$this->fields[$field->name] = $field;
			$this->fieldnames[] = $field->name;
		}
		
		if($this->pk && $this->pk->extras == 'auto_increment')
		{
			$this->autoincrement = true;
		}
		else
		{
			$this->autoincrement = false;
		}
	}

	function __toString()
	{
		$result = 'TABLE '.$this->name.NL;
		$i = 0;
		foreach ($this->fields as $name=>$field) {
			if ($i) $result .= NL;
			$result .= $name.' - '.$field->type.'';
			$i++;
		}
		if ($this->pk) $result .= NL.NL.'Primary Key = '.$this->pk->name;
		return $result;
	}	
}

/**
 * @author C. Moller 28 Aug 2014 <xavier.tnc@gmail.com>
 */
class TablesCollection
{
}