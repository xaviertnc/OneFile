# Example usage
```php
 try {
   $pdo = new PDO( 'mysql:host=localhost;dbname=your_database', 'username', 'password' );
   $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

   $schema = new F1\Schema( $pdo );
   $tableSchema = $schema->getTableSchema( 'users' );

   print_r( $tableSchema );
 } catch ( PDOException $e ) {
   echo "Connection failed: " . $e->getMessage();
 }
 ```