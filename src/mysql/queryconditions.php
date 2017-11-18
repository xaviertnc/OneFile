<?php namespace OneFile\MySql;

/**
 *	@author C. Moller - 26 May 2014 <xavier.tnd@gmail.com>
 */
class QueryConditions
{
	/**
	 *
	 * @var array
	 */
	protected $statements;

	/**
	 *
	 * @var boolean
	 */
	protected $as_prepared_statement;

	/**
	 *
	 * @var array
	 */
	protected $params;


	public function __construct($as_prepared_statement = true)
	{
		$this->as_prepared_statement = $as_prepared_statement;
		$this->params = array('AND' => array(), 'OR' => array());
	}

	public static function create($as_prep_statement = true)
	{
		return new static($as_prep_statement);
	}

	// Only for non-prepared queries
	protected function quote($value)
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

	// Detect if a side is just a value or a condition object - NM 08 Jan 2017
	// This solution sucks!  Rather replace this method with:
	//  - if ($this->isSubcondition($side)) {
	//		$this->params[$type] = array_merge($this->params[$type], $side->getParams());
	//		$side = '(' . $side .')';
	//	}
	protected function resolve($side, $type)
	{
		$resolved = new \stdClass();

		$resolved->isObj = is_object($side);

		if($resolved->isObj and is_a($side, get_class($this)))
		{
			if($this->as_prepared_statement)
				$this->params[$type] = array_merge($this->params[$type], $side->getParams());

			$resolved->asObj = $side;
			$resolved->asString = '(' . $side .')';
		}
		else
			$resolved->asString = $side;

		return $resolved;
	}

	//Need Comment on How this is used! - NM 13 Aug 2014
	//Propably: JOIN `t` *on* (expression) - NM 08 Jan 2017
	public function on($columns, $tables = null) //, $type = 'AND'?
	{
		if(is_array($columns))
		{
			$leftside = $columns[0];
			$rightside = $columns[1];
		}
		else
		{
			$leftside = $rightside = $columns;
		}

		if($tables)
		{
			$leftside = $tables[0] . '.' . $leftside;
			$rightside = $tables[1] . '.' .$rightside;
		}

		$this->statements['AND'][] = $leftside . ' = ' . $rightside; // $type?

		return $this;
	}

	 // If $table is a string, $leftside MUST be a string
	 // If $table == array, $leftside AND rightside MUST be strings
	public function where($leftside, $operator, $rightside, $table = null, $type = 'AND')
	{
		$leftside = $this->resolve($leftside, $type);

		// TODO: We can't accept NULL values since they will be seen as missing.  So we need to make them (string) "NULL" and check
		// for it on this side, to restore the value to actual NULL.
		if (is_null($operator))
		{
			$this->statements[$type][] = $leftside->asString;
			return $this;
		}

		// Make the operator "Equals" and the rightside == second param if we get only 2 parameters.
		if ( is_null($rightside))
		{
			$rightside = $operator;
			$operator = ' = ';
		}
		else
		{
			$operator = ' ' . trim($operator) . ' ';
		}

		$rightside = $this->resolve($rightside, $type);

		//-- PREPARED TYPE VARIANTS --
		if($this->as_prepared_statement)
		{
			if(is_array($table))
			{
				// e.g. statement = "t0.id = t1.t0_id",  leftside = "id", operator = "=",  rightside = "t0_id" ??? - NM 8 Jan 2017
				$this->statements[$type][] = $table[0] . '.' . $leftside->asString . $operator . '?';
				// What's up with "table.colname" string as param? - NM 8 jan 2017
				$this->params[$type][] = $table[1] . '.' . $rightside->asString;
				return $this;
			}

			if($table)
			{
				$this->statements[$type][] = $table . '.' . $leftside->asString . $operator . '?';
				$this->params[$type][] = $rightside->isObj ? $rightside->asString : $rightside->asString;
				return $this;
			}

			$this->statements[$type][] = $leftside->asString . $operator . '?';
			//$this->params[$type][] = $rightside->isObj ? $rightside->asObj : $rightside->asString; ??? - NM 8 Jan 2017
			$this->params[$type][] = $rightside->isObj ? $rightside->asString : $rightside->asString;
			return $this;
		}

		//-- PLAIN TYPE VARIANTS --
		if(is_array($table))
		{
			$this->statements[$type][] = $table[0] . '.' . $leftside->asString . $operator . $table[1] . '.' . $rightside->asString;
			return $this;
		}

		if($table)
		{
			$this->statements[$type][] = $table . '.' . $leftside->asString . $operator . $this->quote($rightside->asString);
			return $this;
		}

		$this->statements[$type][] = $leftside->asString . $operator . $this->quote($rightside->asString);

		return $this;
	}

	public function orWhere($leftside, $operator = null, $rightside = null, $table = null)
	{
		return $this->where($leftside, $operator, $rightside, $table, 'OR');
	}

	public function isNull($leftside, $table = null, $type = 'AND')
	{
		$this->statements[$type][] = '(' . ($table ? $table . '.' : '') . $leftside . 'IS NULL )';
		return $this;
	}

	public function isNotNull($leftside, $table = null, $type = 'AND')
	{
		$this->statements[$type][] = '(' . ($table ? $table . '.' : '') . $leftside . 'IS NOT NULL )';
		return $this;
	}

	public function raw($raw_statement, $type = 'AND')
	{
		$this->statements[$type][] = $raw_statement;
		return $this;
	}

	public function getParams()
	{
		return array_merge($this->params['AND'], $this->params['OR']);
	}

	public function build()
	{
		$conditions = '';

		if(isset($this->statements['AND']))
		{
			$conditions .= implode(' AND ', $this->statements['AND']);
		}

		if(isset($this->statements['OR']))
		{
			if($conditions) $conditions .= ' OR ';
			$conditions .= implode(' OR ', $this->statements['OR']);
		}

//		echo '<span style="color:white">', $conditions, '</span><br>';

		return (string) $conditions;
	}

	public function __toString()
	{
		return $this->build();
	}
}
