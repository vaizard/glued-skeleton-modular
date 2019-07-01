<?php

use Slim\App;

foreach (glob(__ROOT__ . '/glued/*/routes.php') as $filename) {
  include_once $filename;
}


// Run the app.
$app->get('/_/phpinfo', function() { phpinfo(); })->setName('_phpinfo');

$app->get('/_/test', function($c) {
  echo "<h1>Quick&Dirty TEST PAGE</h1>";
  echo $this->get('settings')['glued']['timezone'];
  // write your code here
})->setName('_test');

$app->get ('/_/mysqli', function () {
    if ($this->has('mysqli')) {
        $mysqli = $this->get('mysqli');
        $sql = "SELECT * FROM test";
        $result = $conn->query($sql);
        echo "ok";
    }
})->setName('_mysql');



$app->run();
