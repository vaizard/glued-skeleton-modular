<?php


use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

foreach (glob(__ROOT__ . '/glued/*/routes.php') as $filename) {
  include_once $filename;
}


// Run the app.
$app->get('/_/phpinfo', function(Request $request, Response $response) {
    phpinfo(); 
    return $response; 
})->setName('_phpinfo');

$app->get('/_/test', function(Request $request, Response $response) {
    echo "<h1>Quick&Dirty TEST PAGE</h1>";
    echo $this->get('settings')['glued']['timezone'];
    return $response;
    // write your code here
})->setName('_test');

$app->get ('/_/mysqli', function (Request $request, Response $response, $c) {
    echo "1";
    $db = $this->get('mysqli');
    echo "2";
    if ($this->has('mysqli')) {
        echo "2";
        $conn = $this->get('mysqli');
        $sql = "SELECT * FROM test";
        $result = $conn->query($sql);
        echo "ok";
        while($row = mysqli_fetch_assoc($result)) {
           echo "id: " . $row['id']. " - Name: " . $row['name']. "<br>";
        }
        echo "done";
        return $response;
    }
})->setName('_mysqli');



$app->run();
