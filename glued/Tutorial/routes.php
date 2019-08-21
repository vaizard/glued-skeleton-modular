<?php
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Tutorial\Controllers\BasicController;
use Tutorial\Controllers\HelloController;

$app->group('/tutorial/', function(RouteCollectorProxy $group) {
  $group->get('', function(Request $request, Response $response) { 
        echo "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
          <meta charset=\"UTF-8\">
          <title>Glued / Tutorial</title>
        </head>
        <body>
          <h1>Glued Tutorial</h1> 
          <p>This tutorial aims to introduce you to the basic concepts used throughout glued. Please refer to <a href='https://github.com/vaizard/glued-skeleton/blob/master/glued/Tutorial/routes.php'>glued/Tutorial/routes.php</a> to the source of this page (and all other pages in the tutorial).</p>
          <h2>Code structure and modules</h2>
          ...
          <h2>Basic tasks</h2>
            Glued's setup is s" . $this->get('settings')['glued']['timezone'] . "
          <h2>Index</h2>
          <ul>
            <li><a href='/tutorial/'>Code structure and modules</a></li>
            <li><a href='/tutorial/phpinfo'>Phpinfo()</a></li>
            <li><a href='/tutorial/01'>Controller basics</a></li>
            <li><a href='/tutorial/02'>Twig</a></li>
            <li><a href='/tutorial/03'>http decoration (file downloads, etc.)</a></li>
          </ul>
        </body>
        </html>";
    return $response;
  })->setName('tutorial/');
  $group->get('phpinfo', function(Request $request, Response $response) {
      phpinfo(); 
      return $response; 
  })->setName('tutorial/phpinfo');
  $group->get('01', BasicController::class)->setName('tutorial/01');
  $group->get('hello/{name}', HelloController::class)->setName('tutorial/hello');
});

$app->get ('/_/mysqli', function (Request $request, Response $response, $c) {
    $db = $this->get('mysqli'); // fetching handler from container
    echo "* mysqli handler from container fetched<br />";
    if ($this->has('mysqli')) {
        $conn = $this->get('mysqli');
        $sql = "SELECT * FROM test";
        $result = $conn->query($sql);
        echo "* query result:<br />";
        while($row = mysqli_fetch_assoc($result)) {
           print_r($row); echo "<br />";
        }
    }
    echo "* testing joshcam/mysqli-database-class (fetch first row)<br />";
    $data = $this->get('db')->getOne('test');
    print_r($data);
    return $response;
})->setName('_mysqli');




