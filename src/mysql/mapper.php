<?php namespace OneFile\MySql;

include 'field.php';
include 'record.php';
include 'table.php';

class Mapper
{
	protected $db;
	
	protected $table;
	
	
	public function __construct($database = null, $table = null)
	{
		$this->db = $database;
		$this->table = $table;
	}
}