NOTICE: This library is archived for the sake of [froq-database](https://github.com/froq/froq-database), use that library instead.

## Oppa

Providing an easy interface, aims to simplify database CRUD operations/transactions that you tire. Oppa has also an Active Record implementation interface that sometimes make the things easier for you.

Secures user inputs sharply, carries results of your queries gently, handles errors smoothly, makes batch transactions/commits carefully and profiles your all query processes optionally for you. Oppa also provides a powerful logging mechanizm to report events that you may wonder about.

You will be enjoying while using it, promise.. :)

Before beginning;

- Set your autoloader properly
- Use PHP >= 7.1 (older versions here: [v1](https://github.com/k-gun/oppa/tree/1.26.4), [v2 (7.0)](https://github.com/k-gun/oppa/tree/2.3.2))
- Use try/catch blocks
- You can use `test.sql` in test folder
- Wiki updated for v2, v3
- Supports [MySQLi](http://php.net/manual/en/book.mysqli.php) and [PgSQL](http://php.net/manual/en/book.pgsql.php)

You can see wiki pages for more doc: https://github.com/k-gun/oppa/wiki

### Autoloading / Using Libraries

```bash
# composer
~$ composer require k-gun/oppa
```

```php
// manual
$autoload = require('<path to oppa>/src/Autoload.php');
$autoload->register();
```

### Config

```php
// simply for single databases, see wiki for more
$cfg = [
   'agent'    => 'mysql',
   'database' => [
      'host'     => 'localhost',  'name'     => 'test',
      'username' => 'test',       'password' => '********',
      'charset'  => 'utf8',       'timezone' => '+00:00',
   ]
];
```

### Simple Usage

```php
$db = new Oppa\Database($cfg);
$db->connect();

$agent = $db->getLink()->getAgent();
$agent->query('select * from `users` where `old` > ?', [25]);
dump $agent->rowsCount();
```

### Holy CRUD Stuffs

```php
// raw queries
$result = $agent->query('select * from `users`');
if ($result->hasData())
// if ($result->count() > 0)
   foreach ($result as $user)
      dump $user->name;

// or
if ($agent->rowsCount())
   foreach ($agent->getResult() as $user)
   // or foreach ($agent->getResult()->getData() as $user)
      dump $user->name;

// fetch one
$user = $agent->get('select * from `users` where `old` > ?', [50]);
dump $user->name;
// fetch all
$users = $agent->getAll('select * from `users` where `old` > ?', [50]);
foreach ($users as $user) {
   dump $user->name;
}

// or shorcut methods

// get one user
$result = $agent->select('users');
// get one users if old greater than 50
$result = $agent->select('users', '*', 'old > ?', [50]);
// get many users
$result = $agent->selectAll('users');
// get many users if old greater than 50
$result = $agent->selectAll('users', '*', 'old > ?', [50]);

// insert a user
$result = $agent->insert('user', ['name' => 'Ali', 'old' => 30]); // int: last insert id
// insert many users
$result = $agent->insertAll('user', [['name' => 'Ali', 'old' => 30], ...]); // int[]: last insert ids

// update a user
$result = $agent->update('user', ['old' => 30], 'id = ?', [123]); // int: affected rows
// update many users
$result = $agent->updateAll('user', ['old' => 30], 'id > ?', [123]); // int: affected rows

// delete a user
$result = $agent->delete('user', 'id = ?', [123]); // int: affected rows
// delete many users
$result = $agent->deleteAll('user', 'id > ?', [123]); // int: affected rows
```

### Query Builder

```php
// use and init with exists $db
use Oppa\Query\Builder as Query;

$query = new Query($db->getLink());
// set target table
$query->setTable('users u');

// build query
$query->select('u.*')
    ->aggregate('sum', 'us.score', 'sum_score')
    ->join('users_score us', 'us.user_id=u.id')
        ->selectMore('us.score')
    ->joinLeft('users_login ul', 'ul.user_id=u.id')
        ->selectMore('ul.login')
    ->whereIn('u.id', [1,2,3])
    ->whereBetween('u.old', [30,50])
    ->whereNotNull('ul.login')
    ->groupBy('u.id')
    ->orderBy('u.old')
    ->having('sum_score <= ?', [30])
    ->limit(0,10)
;
```
Gives the result below.
```sql
SELECT
   u.*
   , us.score
   , ul.login
   , sum(us.score) AS sum_score
FROM users u
JOIN users_score us ON (us.user_id=u.id)
LEFT JOIN users_login ul ON (ul.user_id=u.id)
WHERE (u.id IN(1,2,3) AND u.old BETWEEN 30 AND 50 AND ul.login IS NOT NULL)
GROUP BY u.id
HAVING (sum_score <= 30)
ORDER BY old
LIMIT 0,10
```


### Batch Actions (also Transactions)

**Single Transaction**

```php
// get batch object
$batch = $agent->getBatch();

// set autocommit=0 (not needed for pgsql)
$batch->lock();
try {
    // commit
    $batch->doQuery('insert into `users` values(null,?,?)', ['John', 25]);
} catch (\Throwable $e) {
    // rollback
    $batch->undo();
}
// set autocommit=1 (not needed for pgsql)
$batch->unlock();

// get last insert id if success
$result = $batch->getResult();
if ($result) {
    dump $result->getId();
}

// remove query queue and empty result array
$batch->reset();
```

**Bulk Transaction**

```php
// get batch object
$batch = $agent->getBatch();

// set autocommit=0 (not needed for pgsql)
$batch->lock();
try {
    $batch->queue('insert into `users` values(null,?,?)', ['John', 25]);
    $batch->queue('insert into `users` values(null,?,?)', ['Boby', 35]);
    $batch->queue('insert into `uzerz` values(null,?,?)', ['Eric', 15]); // boom!
    // commit
    $batch->do();
} catch (\Throwable $e) {
    // rollback
    $batch->undo();
}
// set autocommit=1 (not needed for pgsql)
$batch->unlock();

// get insert ids if success
foreach ($batch->getResults() as $result) {
    dump $result->getId();
}

// remove query queue and empty result array
$batch->reset();
```

### Active Record

```php
class Users extends Oppa\ActiveRecord\ActiveRecord {
   protected $table = 'users';
   protected $tablePrimary = 'id';
}

// init active record object
$users = new Users($db);

// find one that id=1
$user = $users->find(1);
dump $user;

// check user found?
if ($user->isFound()) {
   dump $user->name;
}

// find all
$users = $users->findAll();
// find many (id=1,2,3)
$users = $users->findAll([1,2,3]);
$users = $users->findAll('id in(?)', [[1,2,3]]);
$users = $users->findAll('id in(?,?,?)', [1,2,3]);
dump $users;

foreach ($users as $user) {
   dump $user->name;
}

$users = $users->findAll([-1,null,'foo']);
dump $users->hasData(); // false

// insert a user
$user = $users->entity();
$user->name = 'Ali';
$user->old  = 40;
dump $user->save();
// or $user = $users->save($user);
// here we see "id" will be filled with last insert id
dump $user;

// update a user (id=1)
$user = $users->entity();
$user->id  = 1;
$user->old = 55;
dump $user->save();
// or $users->save($user);

// update a user that already exists (id=1)
$user = $users->find(1);
if ($user->isFound()) {
    $user->old = 100;
    dump $user->save();
}

// remove a user (id=1)
$user = $users->entity();
$user->id = 1;
dump $users->remove(1);
// or $users->remove($user);

// remove a user that already exists (id=1)
$user = $users->find(1);
if ($user->isFound()) {
    dump $user->remove();
}

// remove users (id=1,2,3)
dump $users->removeAll([1,2,3]);
```

See wiki pages for more doc: https://github.com/k-gun/oppa/wiki

### Name (Oppa)

Actually, I did not know what it means but some after doing some search for its meaining, I found [this](https://www.quora.com/Korean-language-1/What-does-Oppa-mean-in-Oppa-Gangnam-Style) and must say apriciated by naming this open source project with a honorific word that means **older brother**.. :)
