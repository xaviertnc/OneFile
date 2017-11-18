<?php namespace OneFile;

use Log; // Remove! Only for debugging

use Exception;

/*
 * Filter Statement Builder Class(es)
 *
 * @author: C. Moller
 * @date: 08 Jan 2017
 *
 * @update: C. Moller
 *   - Moved to OneFile 24 Jan 2017
 *
 */

class FilterExpression
{
	protected $leftArg;
	protected $operator;
	protected $rightArg;
	protected $options;
	protected $glue;

	/**
	 *
	 * @param string $glue Possible glue values: AND, OR
	 */
	public function __construct($leftArg, $operator = null, $rightArg = null, $options = [], $glue = null)
	{
		$this->leftArg = $leftArg;
		$this->operator = $operator;
		$this->rightArg = $rightArg;
		$this->options = $options;
		$this->glue = $glue;
	}

	/**
	 *
	 */
	public function build(&$params)
	{
		$glue = $this->glue ? (' ' . $this->glue . ' ') : '';
		if (is_object($this->leftArg) and ($this->leftArg instanceof FilterStatement))
			return $glue . '(' . $this->leftArg->build($params) . ')';

		switch (strtoupper($this->operator))
		{
			default:

				if (isset($this->rightArg))
				{
					$params[] = $this->rightArg;
					return $glue . $this->leftArg . $this->operator . '?';
				}

				return $glue . $this->leftArg . ' ' . $this->operator;
		}
	}
}


class FilterStatement
{
	protected $expressions = [];

	protected $tableName;
	protected $resultsCallHandler; // callback!
	protected $orderBy;
	protected $limit;

	/**
	 *
	 */
	public function __construct($tableName = null, $resultsCallHandler = null)
	{
		$this->tableName = $tableName;
		$this->resultsCallHandler = $resultsCallHandler;
	}

	/**
	 *
	 */
	public function addExpression($leftArg, $expression_operator = null, $rightArg = null, $options = [], $glue = null)
	{
		//Log::filter('FilterStatement::addExpression(), leftArg: `' . json_encode($leftArg) . "`, operator: `$expression_operator`, rightArg: `$rightArg`, glue: $glue");

		if (isset($options['ignore']))
		{
			$values_to_ignore = $options['ignore'];
			if (in_array($rightArg, $values_to_ignore)) return $this;
			//unset($options['ignore']);
		}

		if ($glue and ! $this->expressions) $glue = null;

		$this->expressions[] = new FilterExpression($leftArg, $expression_operator, $rightArg, $options, $glue);

		return $this;
	}

	/**
	 *
	 */
	public function where($leftArg, $expression_operator = null, $rightArg = null, $options = [])
	{
		return $this->addExpression($leftArg, $expression_operator, $rightArg, $options, 'AND');
	}

	/**
	 *
	 */
	public function orWhere($leftArg, $expression_operator = null, $rightArg = null, $options = [])
	{
		return $this->addExpression($leftArg, $expression_operator, $rightArg, $options, 'OR');
	}

	/**
	 * Build an order statement based on the value(s) in $orderBy.
	 *
	 * If $orderBy is an array or multi-array, assume that it contains multiple order statements.
	 * The addition of order statements should be handled outside the scope of this class.
	 *
	 * @param mixed $orderBy String / Array / Multi-Array. E.g. $orderBy = "amount desc"; ['amount desc', 'time asc']; [['amount'=>'desc'],['time'=>'asc']]
	 *
	 * @return FilterStatement
	 */
	public function orderBy($orderBy)
	{
		if (is_array($orderBy))
		{
			if (is_array($orderBy[0]))
			{
				$orderStatements = [];
				foreach ($orderBy as $orderSpec) { $orderStatements[] = implode(' ', $orderSpec); }
				$orderBy = implode(',', $orderStatements);
			}
			else
			{
				$orderBy = implode(',', $orderBy);
			}
		}

		$this->orderBy = $orderBy ? ' ORDER BY ' . $orderBy : null;

		return $this;
	}

	/**
	 *
	 */
	public function build(&$params)
	{
		$sql = '';
		foreach ($this->expressions?:[] as $expression) $sql .= $expression->build($params);
		if ($this->orderBy) $sql .= $this->orderBy;
		if ($this->limit) $sql .= $this->limit;
		return $sql;
	}

	/**
	 * Final filter method callback handler (i.e. an unknown method requested at the end of the filter chain)
	 *
	 * Calls a callback (self::$resultsCallHandler) injected on instantiation to allow the user to execute
	 * the query created, using their own DB framework. The callback also allows the user to process the results
	 * before delivery according to the name under which the callback was invoked!
	 *
	 * E.g.
	 * ->getResults()
	 * ->getResultsIndexed()
	 *
	 * @param string $method Whatever the user wants to name this call for results. e.g. getResultsIndexed, fetchResults, etc.
	 * @param array $arguments
	 *
	 */
	public function __call($method, $arguments)
	{
		Log::filter("FilterStatement::call($method), args: " . json_encode($arguments?:'none'));
		if ( ! $this->tableName)
		{
			throw new Exception("Results call: $method not allowed on a filter substatement!");
		}
		$params = [];
		$sql = $this->build($params);
		$args = [$method, $this->tableName, $sql, $params, $arguments];
		return call_user_func_array($this->resultsCallHandler, $args);
	}
}
