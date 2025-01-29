<?php namespace F1;

/**
 * F1 - Schema Migrator Class - 29 Jan 2025
 */

class SchemaMigrator
{

  /**
   * Normalize a schema array.
   *
   * @param array $schema The schema array.
   * @return array The normalized schema array.
   */
  public function normalizeSchema( array $schema ): array
  {
    $normalizedSchema = [];

    foreach ( $schema as $tableName => $table ) {
      $normalizedSchema[$tableName] = [
        'columns' => $this->normalizeColumns( $table['columns'] ?? [] ),
        'indexes' => $this->normalizeIndexes( $table['indexes'] ?? [] )
      ];
    }

    return $normalizedSchema;
  }


  /**
   * Normalize columns in a schema array.
   *
   * @param array $columns The columns array.
   * @return array The normalized columns array.
   */
  private function normalizeColumns( array $columns ): array
  {
    $normalizedColumns = [];

    foreach ( $columns as $column ) {
      if ( !isset( $column['name'], $column['type'] ) ) {
        throw new \InvalidArgumentException( "Column definition must include 'name' and 'type'." );
      }

      $normalizedColumns[$column['name']] = [
        'name' => $column['name'],
        'type' => $column['type'],
        'nullable' => $column['nullable'] ?? false,
        'default' => $column['default'] ?? null,
        'auto_increment' => $column['auto_increment'] ?? false
      ];
    }

    return $normalizedColumns;
  }


  /**
   * Normalize indexes in a schema array.
   *
   * @param array $indexes The indexes array.
   * @return array The normalized indexes array.
   */
  private function normalizeIndexes( array $indexes ): array
  {
    $normalizedIndexes = [];

    foreach ( $indexes as $indexName => $index ) {
      $normalizedIndexes[$indexName] = [
        'columns' => $index['columns'],
        'unique' => $index['unique'] ?? false
      ];
    }

    return $normalizedIndexes;
  }


  /**
   * Generate migration queries between two schemas.
   *
   * @param array $oldSchema The old schema.
   * @param array $newSchema The new schema.
   * @return array Array of SQL queries to migrate from the old schema to the new schema.
   */
  public function generateMigrationQueries( array $oldSchema, array $newSchema ): array
  {
    $queries = [];

    foreach ( $oldSchema as $tableName => $oldTable ) {
      if ( !isset( $newSchema[$tableName] ) ) {
        $queries[] = "DROP TABLE `$tableName`;";
        continue;
      }

      $newTable = $newSchema[$tableName];

      foreach ( $oldTable['columns'] as $columnName => $oldColumn ) {
        if ( !isset( $newTable['columns'][$columnName] ) ) {
          $queries[] = "ALTER TABLE `$tableName` DROP COLUMN `$columnName`;";
        }
      }

      foreach ( $newTable['columns'] as $columnName => $newColumn ) {
        if ( !isset( $oldTable['columns'][$columnName] ) ) {
          $queries[] = "ALTER TABLE `$tableName` ADD COLUMN `$columnName` {$this->getColumnDefinition( $newColumn )};";
        } else {
          $newColumnDef = $this->getColumnDefinition( $newColumn );
          if ( $this->getColumnDefinition( $oldTable['columns'][$columnName] ) !== $newColumnDef ) {
            $queries[] = "ALTER TABLE `$tableName` MODIFY COLUMN `$columnName` $newColumnDef;";
          }
        }
      }

      foreach ( $oldTable['indexes'] as $indexName => $oldIndex ) {
        if ( !isset( $newTable['indexes'][$indexName] ) ) {
          $queries[] = $indexName === 'PRIMARY' ? 
            "ALTER TABLE `$tableName` DROP PRIMARY KEY;" : 
            "ALTER TABLE `$tableName` DROP INDEX `$indexName`;";
        }
      }

      foreach ( $newTable['indexes'] as $indexName => $newIndex ) {
        if ( !isset( $oldTable['indexes'][$indexName] ) ) {
          $queries[] = $indexName === 'PRIMARY' ? 
            "ALTER TABLE `$tableName` ADD PRIMARY KEY ({$this->getIndexColumns( $newIndex )});" : 
            "ALTER TABLE `$tableName` ADD " . ( $newIndex['unique'] ? "UNIQUE " : "" ) . "INDEX `$indexName` ({$this->getIndexColumns( $newIndex )});";
        } else {
          if ( $this->getIndexColumns( $oldTable['indexes'][$indexName] ) !== $this->getIndexColumns( $newIndex ) ) {
            $queries[] = $indexName === 'PRIMARY' ? 
              "ALTER TABLE `$tableName` DROP PRIMARY KEY, ADD PRIMARY KEY ({$this->getIndexColumns( $newIndex )});" : 
              "ALTER TABLE `$tableName` DROP INDEX `$indexName`, ADD " . ( $newIndex['unique'] ? "UNIQUE " : "" ) . "INDEX `$indexName` ({$this->getIndexColumns( $newIndex )});";
          }
        }
      }
    }

    foreach ( $newSchema as $tableName => $newTable ) {
      if ( !isset( $oldSchema[$tableName] ) ) {
        $queries[] = $this->getCreateTableQuery( $tableName, $newTable );
      }
    }

    return $queries;
  }


  private function getColumnDefinition( array $column ): string
  {
    $definition = "`{$column['name']}` {$column['type']}";
    if ( !$column['nullable'] ) $definition .= " NOT NULL";
    if ( $column['default'] !== null ) {
      $default = is_numeric( $column['default'] ) ? $column['default'] : "'{$column['default']}'";
      $definition .= " DEFAULT $default";
    }
    if ( $column['auto_increment'] ) $definition .= " AUTO_INCREMENT";
    return $definition;
  }


  private function getIndexColumns( array $index ): string
  {
    return implode( ', ', array_map( fn( $column ) => "`$column`", $index['columns'] ) );
  }


  private function getCreateTableQuery( string $tableName, array $table ): string
  {
    $columns = array_map( fn( $column ) => $this->getColumnDefinition( $column ), $table['columns'] );

    $indexes = [];
    foreach ( $table['indexes'] as $indexName => $index ) {
      $indexes[] = $indexName === 'PRIMARY' ? 
        "PRIMARY KEY ({$this->getIndexColumns( $index )})" : 
        ( $index['unique'] ? "UNIQUE " : "" ) . "INDEX `$indexName` ({$this->getIndexColumns( $index )})";
    }

    return "CREATE TABLE `$tableName` (" . implode( ', ', array_merge( $columns, $indexes ) ) . ");";
  }

} // SchemaMigrator
