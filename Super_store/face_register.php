```php
<?php

session_start();

include "db.php";

echo "<h1>Face Register Page is Working</h1>";

echo "<p>Database connection is working.</p>";

echo "<p>User ID: " . ($_GET["id"] ?? "No ID") . "</p>";

?>
```
