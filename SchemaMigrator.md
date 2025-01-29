# Example usage
```php

 $oldSchema = [
   'users' => [
     'columns' => [
       'id' => ['name' => 'id', 'type' => 'INT', 'nullable' => false, 'default' => null, 'auto_increment' => true],
       'username' => ['name' => 'username', 'type' => 'VARCHAR(255)', 'nullable' => false, 'default' => null, 'auto_increment' => false],
     ],
     'indexes' => [
       'PRIMARY' => ['columns' => ['id']],
     ],
   ],
 ];

 // $newSchema = [
 //   'users' => [
 //     'columns' => [
 //       'id' => ['name' => 'id', 'type' => 'INT', 'nullable' => false, 'default' => null, 'auto_increment' => true],
 //       'username' => ['name' => 'username', 'type' => 'VARCHAR(255)', 'nullable' => false, 'default' => null, 'auto_increment' => false],
 //       'email' => ['name' => 'email', 'type' => 'VARCHAR(255)', 'nullable' => true, 'default' => null, 'auto_increment' => false],
 //     ],
 //     'indexes' => [
 //       'PRIMARY' => ['columns' => ['id']],
 //       'username_index' => ['columns' => ['username']],
 //     ],
 //   ],
 // ];

 $pdo = new PDO( 'mysql:host=localhost;dbname=your_database', 'username', 'password' );
 $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

 $migrator = new F1\SchemaMigrator();
 $schemaModel = new F1\Schema( $pdo );
 $oldSchema = $migrator->normalizeSchema( $oldSchema );
 $newSchema = $migrator->normalizeSchema( $schemaModel->getTableSchema( 'users' ) );
 $queries = $migrator->generateMigrationQueries( $oldSchema, $newSchema  );

  foreach ( $queries as $query ) {
   echo $query . "\n";
 }
 ```