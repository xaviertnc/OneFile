<?php namespace OneFile\MySql;

/**
 * @author C. Moller 24 May 2014 <xavier.tnc@gmail.com>
 */
class Record
{		
	/**
	 *
	 * @var MySql
	 */
	protected $db;
	
	/**
	 *
	 * @var Table
	 */
	protected $dbtable;
	
	
	/**
	 * 
	 * @param \OneFile\MySql\MySql $db
	 * @param string $tablename
	 */
	public function __construct(MySql $db, $tablename)
	{
		$this->db = $db;
		$this->table = new Table($db, $tablename);
	}
	
//	public function fetch($where)
//	{
//		$q = $this->db->prepare('SELECT * FROM ' . $this->tale->name . ' WHERE ' . $where);
//	}
//	
//	public function fetchBy($fieldname, $value)
//	{
//		$this->fetch($fieldname .' = ' . $this->db->quote$value . );
//	}
}

/**
 * @author C. Moller 24 May 2014 <xavier.tnc@gmail.com>
 */
class RecordsCollection
{
}