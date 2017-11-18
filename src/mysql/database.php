<?php namespace OneFile\MySql;

use PDO;
use PDOException;

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
		if(!$config)
			$this->handle_error('Database Config Required');
		
		if(is_array($config))
		{
			$this->config = $config;
		}
		elseif(file_exists($config))
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
			
			if(!$this->config)
				$this->handle_error('Config File Invalid');
		}
		else
			$this->handle_error('Config Invalid');

		try
		{
			parent::__construct(
				'mysql:host=' . $this->config['DBHOST'] . ';dbname=' . $this->config['DBNAME'],
				$this->config['DBUSER'],
				$this->config['DBPASS'], 
				array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'')
			);
			
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
		catch(PDOException $e)
		{
			$this->handle_error($e->getMessage(), $e->getCode());
        }
    }	

	/**
	 * 
	 * @param string $error
	 * @param integer $code
	 */
	protected function handle_error($error = null, $code = null)
	{
		die('<br><span style="color:red">MySql Database Error! Code: ' . $code . ', Message: ' . $error . '</span>');
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

//->fetch(PDO::FETCH_ASSOC);
//->fetch(PDO::FETCH_OBJ);