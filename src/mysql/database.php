<?php namespace OneFile\MySql;

use PDO;
use Exception;

/**
 * @author C. Moller 24 May 2014 <xavier.tnc@gmail.com>
 */
class Database extends PDO
{	     
	/**
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * 
	 * @param string|array $config
	 */
	function __construct($config = null)
    {
		try {
			
			if (is_string($config) and file_exists($config))
			{ 
				/**
				 * Config File Content
				 * -------------------
				 * return array(
				 *	'DBHOST'=>'...',
				 *	'DBNAME'=>'...',
				 * 	'DBUSER'=>'...',
				 * 	'DBPASS'=>'...'
				 * );
				 */
				$this->config = include($config);				
			}
			else
			{
				$this->config = $config;
			}

			if ( ! is_array($config) or count($config) != 4) trigger_error("Database Configuration Invalid!", E_USER_WARNING);
			
			parent::__construct(
				'mysql:host=' . $this->config['DBHOST'] . ';dbname=' . $this->config['DBNAME'],
				$this->config['DBUSER'],
				$this->config['DBPASS'], 
				array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'')
			);
			
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
		catch(Exception $e)
		{
			trigger_error('Database Error! ' . $e->getMessage(), E_USER_ERROR);
        }
    }	

	
	/**
	 * 
	 * @param string $query
	 * @param array $params
	 * @return \PDOStatement
	 */
	public function exec_prepared($query, $params = null)
	{
		$prepared = $this->prepare($query);
		
		if($prepared->execute($params))
			return $prepared;
	}
}