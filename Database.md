## F1 Database

### Initialization
```php
$config = ['dbhost' => 'localhost', 'dbname' => 'db_name', 'username' => 'user', 'password' => 'pass'];
$db = new F1\Database($config);
```

### Table Selection
```php
$db->table('users');
```

### Setting Primary Key
```php
$db->primaryKey('user_id'); // Set the default primary key to 'user_id'.
```

### SELECT Queries
```php
$db->table('users')->getAll(); // select * from users
$db->table('users')->select('id, name')->limit(10)->orderBy('id DESC')->getAll();
$db->table('users')->select('id, name')->limit('5,2')->orderBy('id DESC')->getAll();
$db->table('users')->getLookupBy('user_id', 'name, age', function($row) { return $row->name . ': ' . $row->age; } );
$db->table('users')->getLookupBy('user_id', 'name'); // Returns an ASSOC ARRAY of names keyed by user_id.
$db->table('users')->where('id', 1)->getFirst();
$db->getFirst('users', 1); // select * from users where {$db->primaryKey} = 1
```

### WHERE Conditions
```php
$db->table('users')->where('id', 1); // Same as '='
$db->table('users')->where('name', 'LIKE', 'Jo%');
$db->table('users')->where('deleted_at', 'IS', null);
$db->table('users')->where('status', 'IN', ['Active', 'Suspended']);
$db->table('users')->where('role', 'NOT IN', ['Client', 'Accountant', 'Trader']);
$db->table('users')->where('date', 'BETWEEN', ['2024-01-01', '2024-12-31']); // inclusive
$subConditions = [];
$subConditions[] = ['age', '>', 18];
$subConditions[] = ['status', '=', 'active', 'AND'];
$db->table('users')->where('role', 'super')->orWhere($subConditions);
```

### Counting Rows
```php
$count = $db->table('users')->count();
$count = $db->table('users')->where('status', 'active')->count();
```

### Inserting Data
```php
// If the primary key is set in $data, it will be ignored.
$data = ['name' => 'John', 'age' => 30];
$db->table('users')->insert($data); // Minimum required example, but can also accept options.
$resp = $db->table('users')->insert($data, ['autoStamp' => true, 'user' => $app->user->name]);
// $resp = e.g. ['status' => 'inserted', 'id' => 1, 'affected' => 1] OR null

```

### Updating Data
```php
$user = $app->user->name;
$data = ['id' => 1, 'name' => 'Joe', 'status' => 'suspended'];
// Setting 'autoStamp' will automatically update the 'updated_at' and 'updated_by' fields,
// assuming the required columns exist. If no user name is specified, 'updated_by' will be ignored.
$db->table('users')->update($data);
$db->table('users')->update($data, ['autoStamp' => true]);
$db->table('users')->update($data, ['autoStamp' => true, 'user' => $user]);
$customStamp = ['updated_at' => 'deleted_at', 'updated_by' => 'deleted_by'];
$resp = $db->table('users')->update($data, ['autoStamp' => $customStamp, 'user' => $user]);
// $resp = e.g. ['status' => 'updated', 'id' => 1, 'affected' => 1] OR null
```

### Save (Update or Insert)
```php
// Assumes the primary column is: $db->primaryKey. Default is 'id'.
// If $data[$db->primaryKey] exists and is "truthy", UPDATE, else INSERT.
$db->table('users')->save($data);
$db->table('users')->save($data, ['autoStamp' => true]);
$resp = $db->table('users')->save($data, ['autoStamp' => true, 'user' => $app->user->name]);
// $resp = e.g. ['status' => 'inserted', 'id' => 1, 'affected' => 1] OR null
// $resp = e.g. ['status' => 'updated', 'id' => 1, 'affected' => 1] OR null
```

### Upsert (Update or Insert on Custom Key)
```php
// Like save, but we explicity specify an upsert (unique) key, which is not necessarily the primary key.
// We also have the option to specify a list of columns to check for changes, before performing an upsert.
// NB: The upsertKey column MUST be IN the $data array! (i.e. $data[$upsertKey] must exist)
$upsertKey = 'id_number';
$changeCheckList = ['created_at', 'updated_at']; // Only upsert if any of the listed columns changed. (optional)
$data = ['id' => 1, 'name' => 'Johnny', 'age' => 23, 'id_number' => '2012015250011', 'updated_at' => '2023-12-01 00:00:00', ... ];
$upsertOptions = ['autoStamp' => true, 'user' => $app->user->name, 'onchange' => $changeCheckList ];
$db->table('users')->upsert($data, $upsertKey);
$resp = $db->table('users')->upsert($data, $upsertKey, $upsertOptions);
// $resp = e.g. ['status' => 'unchanged', 'id' => 1, 'affected' => 0] OR null
// $resp = e.g. ['status' => 'inserted', 'id' => 1, 'affected' => 1] OR null
// $resp = e.g. ['status' => 'updated', 'id' => 1, 'affected' => 1] OR null
```

### Execute Query
```php
$sql = $sql ?? 'SELECT * FROM users WHERE status = ?';
$params = $params ?? ['active'];
$users = $db->query( $sql, $params );
$users = $db->query( 'SELECT * FROM users WHERE status = ?', ['active'] );
$roles = $db->query( 'SELECT * FROM sys_roles WHERE id = ?', [1], );
$allUsersCount = $db->query( 'SELECT COUNT(*) FROM users' );
$countries = $db->query( 'SELECT * FROM countries' );
```

### Execute Command
```php
$sql = $sql ?? 'SET UTF8';
$params = $params ?? [];
$affectedRowCount = $db->execute( $sql, $params );
$db->execute( 'SET UTF8' );
$db->execute( 'CREATE TABLE users (id INT)' );
$db->execute( 'INSERT INTO users (name) VALUES (?)', ['John'] );
$db->execute( 'UPDATE users SET name = ? WHERE id = ?', ['Joe', 1] );
$db->execute( 'DELETE FROM users WHERE id = ?', [1] );
```