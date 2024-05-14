<?php namespace F1;

use PDO;
use DateTime;
use Exception;
use PDOException;

/**
 * F1 - Database Class - 20 Jun 2023
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 1.14 - FT - 13 May 2024
 *   - Upgrade / fix count() to work with where clauses.
 */

class Database {

  public $pdo;
  public $table;
  public $sql;
  public $params = [];
  public $primaryKey = 'id';
  public $whereClauses = [];
  public $columns = [];
  public $select = '*';
  public $orderBy = '';
  public $limit = '';
  public $cmd = '';

  public function __construct( array $config = [] ) {
    $defaults = ['dbhost' => 'localhost', 'dbname' => '', 'username' => '', 'password' => ''];
    $config = array_merge($defaults, $config);
    $this->connect( $config['dbhost'], $config['dbname'], $config['username'], $config['password'] );
  }

  public function connect( $dbHost, $dbName, $username, $password ) {
    $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName;
    try {
      $this->pdo = new PDO( $dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] );
    } catch ( PDOException $e ) {
      throw new PDOException( $e->getMessage(), (int) $e->getCode() );
    }
  }

  public function table( $tableName ) { $this->table = $tableName; return $this; }
  public function select( $select ) { $this->select = $select; return $this; }
  public function orderBy( $order ) { $this->orderBy = ' ORDER BY ' . $order; return $this; }
  public function limit( $limit ) { $this->limit = ' LIMIT ' . $limit; return $this; }

  public function primaryKey($pk ) { $this->primaryKey = $pk; return $this; }

  public function safeRollback() { return $this->pdo->inTransaction() && $this->pdo->rollBack(); }

  private function buildNestedCondition( array $condition, $isFirstInGroup ) {
    return [
      'column' => $condition[0],
      'operator' => $condition[1],
      'value' => $condition[2],
      'logic' => $isFirstInGroup ? null : $condition[3] ?? 'AND'
    ];
  }

  // $results = $db->table( 'tccs' )
  //   ->where( 'client_id', 1 )
  //   ->where( 'size', '=', 'large' )
  //   ->where( 'deleted_at', 'IS', null )
  //   ->where( [ [ 'YEAR(`date`)', '=', $year ], [ 'rollover', '>', 0, 'OR'] ] )
  //   ->getAll();
  public function where( $condition, $operator = null, $value = '*=*', $logic = 'AND' ) {
    if ( is_array( $condition ) ) {
      $group = [ 'type' => 'group', 'conditions' => [], 'logic' => $logic ];
      foreach ( $condition as $index => $item ) {
        $group['conditions'][] = $this->buildNestedCondition( $item, $index == 0 );
      }
      $this->whereClauses[] = $group;
    } else {
      $nop = $value === '*=*';
      $this->whereClauses[] = [
        'type' => 'condition',
        'column' => $condition,
        'operator' =>  $nop ? '=' : $operator,
        'value' => $nop ? $operator : $value,
        'logic' => $logic
      ];
    }
    return $this;
  }

  public function orWhere( $column, $operator, $value ) {
    return $this->where($column, $operator, $value, 'OR');
  }

  public function count() {
    if ( $this->whereClauses ) { $this->select = 'COUNT(*)'; $this->buildQuery(); }
    else { $this->sql = 'SELECT COUNT(*) FROM ' . $this->table; }
    return $this->query()[0]->{'COUNT(*)'};
  }

  public function getColumns() {
    if ( empty( $this->columns ) ) {
      $stmt = $this->pdo->prepare( 'SHOW COLUMNS FROM ' . $this->table );
      $stmt->execute();
      $this->columns = $stmt->fetchAll( PDO::FETCH_ASSOC );
    }
    return $this->columns;
  }

  public function getColumnNames() { return array_column($this->getColumns(), 'Field'); }

  public function getColumnType( $type ) {
    if ( in_array( $type, ['date', 'bool', 'blob', 'float', 'time'] ) ) return $type;
    if ( in_array( $type, ['datetime', 'timestamp'] ) ) return 'datetime';
    static $typesMap = ['int' => 'integer', 'decimal' => 'float' ];
    foreach ( $typesMap as $key => $value ) { if ( strpos( $type, $key ) !== false ) return $value; }
    return 'string';
  }

  public function getColumnTypes() {
    $columns = $this->getColumns(); $columnTypes = [];
    foreach ( $columns as $column ) { $columnTypes[$column['Field']] = $this->getColumnType( $column['Type'] ); }
    return $columnTypes;
  }

  public function getChangedValues( array $new, array $old, $pk, $only = null ) {
    $changed = []; $keys = $only ?: array_keys( $new );
    foreach ( $keys as $key ) if ( $old[$key] != $new[$key] ) $changed[$key] = [ $old[$pk], $old[$key], $new[$key] ];
    return $changed;
  }

  public function formatIntInput( $input ) { return $input !== '' ? floor( $this->formatFloatInput( $input ) ) : null; }
  public function formatFloatInput( $input ) { return $input !== '' ? floatval( preg_replace( '/[^0-9.]/', '', $input ) ) : null; }
  public function formatBoolInput( $input ) { return !!$input; }

  public function formatDateTimeInput( $dateTimeInput, $asDateOnly = false ) {
    if ( ! $dateTimeInput ) return null;
    if ( strpos( $dateTimeInput, '/' ) !== false ) {
      $pos = strpos( $dateTimeInput, ' ' );
      if ( $asDateOnly and $pos ) $dateTimeInput = substr( $dateTimeInput, 0, $pos );
      $dateTime = DateTime::createFromFormat( $asDateOnly ? 'd/m/Y' : 'd/m/Y H:i:s' , $dateTimeInput );
      if ( $dateTime !== false ) return $dateTime->format( 'Y-m-d' . ( $asDateOnly ? '' : ' H:i:s' ) );
    }
    $timestamp = strtotime( $dateTimeInput );
    if ( $timestamp === false ) return null;
    return date( 'Y-m-d' . ( $asDateOnly ? '' : ' H:i:s' ), $timestamp );
  }

  // Remove data keys that don't match table column names and correct data value types if possible.
  public function filterAndTypeCorrectData( array $data ) {
    $columnNames = $this->getColumnNames();
    $columnTypes = $this->getColumnTypes();
    $validData = [];
    foreach ( $data as $key => $value ) {
      if ( ! in_array( $key, $columnNames ) ) continue;
      $columnType = $columnTypes[$key];
      $valueType = gettype( $value );
      if ( $valueType === 'string' ) {
        if ( $columnType === 'integer' ) $value = $this->formatIntInput( $value );
        elseif ( $columnType === 'float' ) $value = $this->formatFloatInput( $value );
        elseif ( $columnType === 'bool' ) $value = $this->formatBoolInput( $value );
        elseif ( $columnType === 'datetime' ) $value = $this->formatDateTimeInput( $value );
        elseif ( $columnType === 'date' ) $value = $this->formatDateTimeInput( $value, true );
        elseif ( $value === '' ) $value = null;
      }
      $validData[$key] = $value;
    }
    return $validData;
  }

  public function autoStamp( array $data, array $options, $operation ) {
    $autoStamp = $options['autoStamp'] ?? null;
    if ( ! $autoStamp ) return $data;
    if ( is_scalar( $autoStamp ) ) $autoStamp = [];
    $at = $operation . '_at';
    $now = date( 'Y-m-d H:i:s' );
    debug_log( $at . ': ' . $now, 'db::autoStamp(), ', 3 );
    $data[$autoStamp[$at] ?? $at] = $now;
    $asUser = $options['user'] ?? null;
    if ( ! $asUser ) return $data;
    $by = $operation . '_by';
    debug_log( $by . ': '. $asUser, 'db::autoStamp(), ', 3 );
    $data[$autoStamp[$by] ?? $by] = $asUser;
    return $data;
  }

  public function insert( array $data, $options = [] ) {
    debug_log( $data, 'db::insert(), data: ', 3 );
    unset( $data[$this->primaryKey] );
    $data = $this->filterAndTypeCorrectData($data);
    $data = $this->autoStamp( $data, $options, 'created' );
    $columns = array_keys( $data );
    $columnsStr = implode( ', ', $columns );
    $placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );
    $this->sql = "INSERT INTO `{$this->table}` ( $columnsStr ) VALUES ( $placeholders )";
    $this->params = array_values( $data );
    $affectedRows = $this->execute();
    return [ 'status' => 'inserted', 'id' => $this->pdo->lastInsertId(), 
      'affected' => $affectedRows ];
  }

  public function update( array $data, $options = [] ) {
    debug_log( $data, 'db::update(), data: ', 3 );
    $pkValue = $data[$this->primaryKey];
    if ( empty( $pkValue ) ) throw new Exception( 'Primary key invalid.' );
    unset( $data[$this->primaryKey] );
    $data = $this->filterAndTypeCorrectData( $data );
    $data = $this->autoStamp( $data, $options, 'updated' );
    debug_log( $data, 'db::update(), data (processed): ', 4 );
    $assignments = array_map( function ( $col ) { return "`$col` = ?"; }, array_keys( $data ) );
    $assignmentsStr = implode( ', ', $assignments );
    $this->sql = "UPDATE `{$this->table}` SET $assignmentsStr WHERE `{$this->primaryKey}` = ?";
    $this->params = array_values($data); $this->params[] = $pkValue;
    $affectedRows = $this->execute();
    return [ 'status' => 'updated', 'id' => $pkValue,
      'affected' => $affectedRows ];
  }

  public function upsert( array $data, $upsertOn, $options = [] ) {  
    if ( ! array_key_exists( $upsertOn, $data ) )
      throw new Exception( "Upsert key '$upsertOn' not found in data." );
    $upsertKeyValue = $data[$upsertOn];
    debug_log( "$upsertOn = $upsertKeyValue", 'db::upsert() ', 2 );
    debug_log( $options, 'db::upsert(), options: ', 3 );
    debug_log( $data, 'db::upsert(), data: ', 3 );
    $existing = $this->where( $upsertOn, '=', $upsertKeyValue )->getFirst();
    if ( empty( $existing ) ) return $this->insert( $data, $options );
    // else: update existing.
    $pk = $this->primaryKey;
    $data[$pk] = $existing->{$pk};
    if ( isset( $options['onchange'] ) ) {
      // Do we need to do this before checking for changed values?
      // $data = $this->filterAndTypeCorrectData( $data );
      $changedKeys = $options['onchange'];
      $changed = $this->getChangedValues( $data, (array) $existing, $pk, $changedKeys );
      if ( count( $changed ) === 0 ) return [ 'status' => 'unchanged', 'id' => $data[$pk], 'affected' => 0 ];
      debug_log( $changed, 'db::upsert(), Value changes detected: ', 2 );
    }
    return $this->primaryKey( $upsertOn )->update( $data, $options );
  }

  public function save( array $data, $options = [] ) {
    debug_log( $options, 'db::save() ', 2 );
    return empty( $data[$this->primaryKey] )
      ? $this->insert( $data, $options )
      : $this->update( $data, $options );
  }

  public function delete( $id = null ) {
    debug_log( $id, 'db::delete(), id: ', 2 );
    $this->cmd = 'DELETE';
    if ( $id ) $this->where( $this->primaryKey, '=', $id );
    return $this->execute(); // Returns affected rows.
  }

  private function resetQueryParts( $primaryKey = null ) {
    $this->sql = '';
    $this->params = [];
    $this->primaryKey = $primaryKey?:'id';
    $this->whereClauses = [];
    $this->columns = [];
    $this->select = '*';
    $this->orderBy = '';
    $this->limit = '';
    $this->cmd = '';
  }

  private function buildQueryWhere() {
    if ( empty( $this->whereClauses ) ) return;

    foreach ( $this->whereClauses as $index => $whereClause ) {
      $logic = $index > 0 ? ' ' . $whereClause['logic'] . ' ' : ' WHERE ';
      if ( $whereClause['type'] === 'group' ) {
        $this->sql .= $logic . '(';
        foreach ( $whereClause['conditions'] as $innerIndex => $condition ) {
          $conditionLogic = $innerIndex > 0 ? ' ' . $condition['logic'] . ' ' : '';
          $this->sql .= $this->buildConditionClause( $condition, $conditionLogic );
        }
        $this->sql .= ')';
      } else {
        $this->sql .= $this->buildConditionClause( $whereClause, $logic );
      }
    }
  }

  private function buildConditionClause( $condition, $logic ) {
    if ( ( $condition['operator'] === 'IN' or $condition['operator'] === 'NOT IN' )
     && is_array( $condition['value'] ) ) {
      $placeholders = implode( ', ', array_fill( 0, count( $condition['value'] ), '?' ) );
      foreach ( $condition['value'] as $value ) $this->params[] = $value;
      return $logic . $condition['column'] . ' ' . $condition['operator'] . ' (' . $placeholders . ')';
    } 
    if ( $condition['operator'] === 'BETWEEN' && is_array( $condition['value'] ) ) {
      $this->params[] = $condition['value'][0];
      $this->params[] = $condition['value'][1];
      return $logic . $condition['column'] . ' ' . $condition['operator'] . ' ? AND ?';
    }
    else {
      $this->params[] = $condition['value'];
      return $logic . $condition['column'] . ' ' . $condition['operator'] . ' ?';
    }
  }

  private function buildQuery() {
    debug_log( 'db::buildQuery()', '', 3 );
    $this->sql = ( $this->cmd ?: 'SELECT ' . $this->select ) . ' FROM ' . $this->table;
    $this->buildQueryWhere();
    $this->sql .= $this->orderBy . $this->limit;
  }

  public function query( $sql = null, $params = null ) {
    debug_log( 'db::query()', '', 3 );
    if ( isset( $sql ) ) $this->sql = $sql;
    if ( isset( $params ) ) $this->params = $params;
    if ( ! $this->sql ) $this->buildQuery();
    debug_log( [ $this->sql, json_encode($this->params) ], 'db::query(), ', 3 );
    $stmt = $this->pdo->prepare( $this->sql );
    $stmt->execute( $this->params );
    $results = $stmt->fetchAll( PDO::FETCH_OBJ );
    $this->resetQueryParts(); // Must be after fetchAll()
    return $results;
  }

   public function execute( $sql = null, $params = null ) {
    debug_log( 'db::execute()', '', 3 );
    if ( isset( $sql ) ) $this->sql = $sql;
    if ( isset( $params ) ) $this->params = $params;
    if ( ! $this->sql ) $this->buildQuery();
    debug_log( [ $this->sql, json_encode($this->params) ], 'db::execute(), ', 3 );
    $stmt = $this->pdo->prepare( $this->sql );
    $stmt->execute( $this->params );
    $this->resetQueryParts();
    return $stmt->rowCount();
  }

  public function getFirst( $table = null, $id = null ) {
    if ( isset( $table ) ) { $this->table( $table )->where( $this->primaryKey, '=', $id ); }
    $results = $this->limit( 1 )->query();
    return $results ? $results[0] : null;
  }

  public function getLookupBy( $indexBy = null, $columns = null, $valueFn = null ) {
    $columns = is_string( $columns ) ? explode( ',', $columns ) : $columns;
    $columns = array_map( function ( $col ) { return trim( $col ); }, $columns );
    if ( ! in_array( $indexBy, $columns ) ) $columns[] = $indexBy;
    $rows = $this->select( implode( ',', $columns ) )->query();
    $lookup = [];
    foreach ( $rows as $row ) {
      $key = $row->{$indexBy};
      if ( $valueFn ) $lookup[$key] = $valueFn( $row );
      else $lookup[$key] = $row->{$columns[0]}; // Default to first column.
    }
    return $lookup;
  }

  public function getAll( $select = null ) {
    return $select ? $this->select( $select )->query() : $this->query();
  }

}