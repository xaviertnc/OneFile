<?php namespace F1;

use PDO;


/**
 * F1 - MySql Database Table Schema Class - 29 Jan 2025
 */

class Schema {

  private $pdo;

  /**
   * Constructor.
   *
   * @param PDO $pdo A PDO connection to the MySQL database.
   */
  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Get the schema array for a specific table.
   *
   * @param string $tableName The name of the table.
   * @return array The schema array for the table.
   */
  public function getTableSchema(string $tableName): array
  {
    $schema = [
      'columns' => $this->getColumns($tableName),
      'indexes' => $this->getIndexes($tableName),
    ];

    return $schema;
  }

  /**
   * Get the columns for a specific table.
   *
   * @param string $tableName The name of the table.
   * @return array Array of column definitions.
   */
  private function getColumns(string $tableName): array
  {
    $columns = [];

    $stmt = $this->pdo->prepare("
      SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName
    ");
    $stmt->execute(['tableName' => $tableName]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $columns[$row['COLUMN_NAME']] = [
        'name' => $row['COLUMN_NAME'],
        'type' => $row['COLUMN_TYPE'],
        'nullable' => $row['IS_NULLABLE'] === 'YES',
        'default' => $row['COLUMN_DEFAULT'],
        'auto_increment' => strpos($row['EXTRA'], 'auto_increment') !== false,
      ];
    }

    return $columns;
  }

  /**
   * Get the indexes for a specific table.
   *
   * @param string $tableName The name of the table.
   * @return array Array of index definitions.
   */
  private function getIndexes(string $tableName): array
  {
    $indexes = [];

    $stmt = $this->pdo->prepare("
      SELECT INDEX_NAME, COLUMN_NAME
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName
      ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ");
    $stmt->execute(['tableName' => $tableName]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $indexName = $row['INDEX_NAME'];
      if (!isset($indexes[$indexName])) {
        $indexes[$indexName] = ['columns' => []];
      }
      $indexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
    }

    return $indexes;
  }

} // Schema