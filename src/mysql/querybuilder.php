<?php namespace OneFile\MySql;

include 'queryjoin.php';

/**
 *	@author C. Moller - 25 May 2014 <xavier.tnd@gmail.com> 
 */
class QueryBuilder
{
	/**
	 *
	 * @var string
	 */
	protected $as_prepared_statement;
	
	/**
	 *
	 * @var array|string
	 */
	protected $selects;
	
	/**
	 *
	 * @var array|string
	 */
	protected $commands;
	
	/**
	 *
	 * @var string
	 */
	protected $froms;
	
	/**
	 *
	 * @var array
	 */
	protected $joins;
	
	/**
	 *
	 * @var QueryConditions
	 */
	protected $conditions;
		
	/**
	 *
	 * @var array|string
	 */
	protected $sorts;
	
	/**
	 *
	 * @var array|string
	 */
	protected $groups;
	
	/**
	 *
	 * @var string
	 */
	protected $limit;
			
	/**
	 *
	 * @var array
	 */
	protected $params;

	/**
	 *
	 * @var string
	 */
	protected $target;
	
	 // Choose output type: PREPARED(true) or DIRECT(false)
	 // DIRECT == Plain query. No "?"s with bindings and No automatically escaped values!
	public function __construct($as_prepared_statement = true)
	{
		$this->as_prepared_statement = $as_prepared_statement;
		$this->params = array('DATA' => array(), 'JOINS' => array(), 'OPTIONS' => array());
	}
	
	public static function create($as_prepared_statement = true)
	{
		return new static($as_prepared_statement);
	}

	public static function with($tableName, $as_prepared_statement = true)
	{
		$query = new static($as_prepared_statement);
		return $query->using($tableName);
	}

	public static function insertInto($tableName, array $dataset, $ignore_duplicates = false, $as_prepared_statement = true)
	{
		$query = new static($as_prepared_statement);
		return $query->using($tableName)->insert($dataset, $ignore_duplicates);
	}

	public static function deleteFrom($tableName, $as_prepared_statement = true)
	{
		$query = new static($as_prepared_statement);
		return $query->delete()->from($tableName);
	}
	
	// Only for non-prepared queries
	public function quote($value)
	{
		if($value === null)
			return 'NULL';
		else
		{
			if(is_string($value))
				return "'" . mysql_escape_string($value) . "'";
			else
				return mysql_escape_string($value);
		}
	}
	
	protected function getConditions()
	{
		if(!$this->conditions)
			$this->conditions = QueryConditions::create($this->as_prepared_statement);
			
		return $this->conditions;
	}
	
	public function select($columns = '*', $table = null)
	{
		if(!is_array($columns)) $columns = explode(',', $columns);

		$prefix = $table ? $table . '.' : '';
			
		foreach($columns as $key => $column)
		{
			$columns[$key] = $prefix . $column;
		}
		
		$this->selects[] = implode(',', $columns);
		return $this;
	}
	
	public function count( $column = '*', $table= null, $alias = 'rows')
	{
		$prefix = $table ? $table . '.' : '';
		$this->selects[] = "COUNT($prefix$column) as $alias";
		return $this;
	}
	
	/**
	 * Gives you various ways to define the query source
	 * 1. As a raw string
	 * 2. One tablename + Optional alias at a time (Can repeat "froms" to add more)
	 * 3. All tablenames at once in an array and Optional array of Table aliases
	 */
	public function from($source, $alias = null)
	{
		if(is_array($source))
		{
			foreach($source as $i => $tablename)
			{
				$this->from[] = $tablename . ($alias ? " as $alias[$i]" : '');
			}
			
			return $this;
		}
			
		$this->froms[] = $source . ($alias ? " as $alias" : '');
		return $this;
	}
	
	public function using($tableName, $alias = null)
	{
		$this->target = $tableName;
		return $this->from($tableName, $alias);
	}

	public function delete()
	{
		$this->commands[] = "DELETE";
		return $this;
	}
	
	public function insert(array $dataset, $ignore_duplicates = false)
	{
		foreach($dataset as $column => $value)
		{
			$columns_array[] = $column;

			if($this->as_prepared_statement)
			{
				$values_array[] = '?';
				$this->params['DATA'][] = $value;
			}
			else
				$values_array[] = $this->quote($value);
		}
		
		$columns = implode(',', $columns_array);
		$values = implode(',', $values_array);
		
		$ignore = $ignore_duplicates?' IGNORE':'';
		
		$this->commands[] = "INSERT$ignore INTO $this->target ($columns) VALUES ($values)";
		return $this;
	}

	public function batchInsert($table, array $columns, array $datasets, $ignore_duplicates = false)
	{
		$valuesets = '';
		
		foreach($datasets as $dataset)
		{
			foreach($dataset as $value)
			{
				if($this->as_prepared_statement)
				{
					$values_array[] = '?';
					$this->params['DATA'][] = $value;
				}
				else
					$values_array[] = $this->quote($value);
			}
			
			if($valuesets) $valuesets .= ',';
			
			$valuesets .= '(' . implode(',', $values_array) . ')';
			$values_array = array();
		}
		
		$columns = implode(',', $columns);
		$ignore = $ignore_duplicates?' IGNORE':'';
		$this->commands[] = "INSERT$ignore INTO $table ($columns) VALUES $valuesets";
		return $this;
	}
	
	public function update($table, array $dataset)
	{
		foreach($dataset as $column => $value)
		{
			if($this->as_prepared_statement)
			{
				$assignments_array[] = $column.'=?';
				$this->params['DATA'][] = $value;
			}
			else
				$assignments_array[] = "$column=" . $this->quote($value);
		}
		
		$assignments = implode(',', $assignments_array);
		$this->commands[] = "UPDATE $table SET $assignments";
		return $this;
	}	

	public function join($tables, $on, $aliases = null, $type = 'LEFT')
	{
		$join = new QueryJoin($tables, $on, $aliases, $type, $this->as_prepared_statement);
		$this->params['JOINS'] = array_merge($this->params['JOINS'], $join->getParams());
		$this->joins[] = $join;
		return $this;
	}	
	
	public function where($column, $operator = null, $value = null, $table = null)
	{
		$this->getConditions()->where($column, $operator, $value, $table, 'AND');
		return $this;
	}
	
	public function orWhere($column, $operator = null, $value = null, $table = null)
	{
		$this->getConditions()->where($column, $operator, $value, $table, 'OR');
		return $this;
	}
			
	public function search($column, $operator = null, $value = null, $ignore = null, $table = null)
	{
		if($value !== $ignore)
			$this->getConditions()->where($column, $operator, $value, $table, 'AND');		
		return $this;
	}
	
	public function orSearch($column, $operator = null, $value = null, $ignore = null, $table = null)
	{
		if($value !== $ignore)
			$this->getConditions()->where($column, $operator, $value, $table, 'OR');		
		return $this;
	}
	
	public function isNull($leftside, $table = null, $glue = 'AND')
	{
		$this->getConditions()->isNull($leftside, $table, $glue);
		return $this;
	}

	public function isNotNull($leftside, $table = null, $glue = 'AND')
	{
		$this->getConditions()->isNotNull($leftside, $table, $glue);
		return $this;
	}
	
	public function raw($raw_statement, $glue = 'AND')
	{
		$this->getConditions()->raw($raw_statement, $glue);
		return $this;
	}	
	
	/**
	 * If you supply an array of field names and want to add aliases or table specifiers, you 
	 * also have to supply those as arrays.
	 * 
	 * You can specify a raw string if you leave the $alias and $table parameters empty.
	 */
	public function groupBy($column, $alias = null, $table = null)
	{
		if(is_array($column))
		{
			foreach($column as $i => $name)
			{
				$this->groups[] = ($table ? "$table[$i]." : '') . $name . ($alias ? " as $alias[$i]" : '');
			}
			
			return $this;
		}

		$this->groups[] = ($table ? "$table." : '') . $column . ($alias ? " as $alias" : '');
		
		return $this;		
	}
	
	public function orderBy($column, $dir = null, $table = null)
	{
		if(is_array($column))
		{
			foreach($column as $i => $name)
			{
				$this->sorts[] = ($table ? "$table[$i]." : '') . $name . ($dir ? " $dir[$i]" : '');
			}
			
			return $this;
		}

		$this->sorts[] = ($table ? "$table." : '') . $column . ($dir ? " as $dir" : '');
		
		return $this;
	}
	
	public function take($limit, $offset = 0)
	{
		if($this->as_prepared_statement)
		{
			$this->limit = '?';
			$this->params['OPTIONS'][] = $limit;

			if($offset)
			{
				$this->limit .= ',?';
				$this->params['OPTIONS'][] = $offset;
			}
		}
		else
		{
			$this->limit = intval($limit);

			if($offset)
				$this->limit .= ',' . intval($offset);
		}
		
		return $this;
	}
	
	public function getParams()
	{
		if($this->conditions)
			return array_merge($this->params['DATA'], $this->params['JOINS'], $this->conditions->getParams(), $this->params['OPTIONS']);
		else
			return array_merge($this->params['DATA'], $this->params['JOINS'], $this->params['OPTIONS']);
	}
	
	// If No Commands or Selects specified, we use "SELECT *"!
	public function build()
	{
		$statement = '';
		
		if($this->commands) $statement .= implode(',', $this->commands);
		if($this->selects) $statement .= 'SELECT ' . implode(',', $this->selects);
		if(!$statement) $statement = 'SELECT *';
		if($this->froms) $statement .= ' FROM ' . implode(',', $this->froms);
		if($this->joins) $statement .= ' ' . implode(',', $this->joins);
		if($this->conditions) $statement .= ' WHERE ' . $this->conditions;
		if($this->groups) $statement .= ' GROUP BY ' . implode(',', $this->groups);
		if($this->sorts) $statement .= ' ORDER BY ' . implode(',', $this->sorts);
		if($this->limit) $statement .= ' LIMIT ' . $this->limit;
		
		return $statement;
	}
	
	public function __toString()
	{
		return $this->build();
	}
}