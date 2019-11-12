<?php
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Tutorial\Controllers\HomeController;

$app->group('/tutorial', function(RouteCollectorProxy $group) {
  $group->get('', function(Request $request, Response $response) { 
        echo "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
          <meta charset=\"UTF-8\">
          <title>Glued Tutorial</title>
          <style>
            * { font-family: Arial; }
            body { padding: 40px; }
          </style>
        </head>
        <body>
          <h1>Glued Tutorial</h1> 
          <p style='width: 50%'>This tutorial aims to ease novice PHP developers into basic concepts used throughout Glued. Continue <a href='/tutorial/home'>here</a>.<br />Source code for this page: <a href='https://github.com/vaizard/glued-skeleton/blob/master/glued/Tutorial/routes.php'>glued/Tutorial/routes.php</a>.</p>
        </body>
        </html>";
    return $response; // this return is mandatory if you use echo & friends.
  })->setName('tutorial');
  $group->get('/home[/{name}]', HomeController::class)->setName('tutorial/home');
  $group->get('phpinfo', function(Request $request, Response $response) {
      phpinfo(); 
      return $response; 
  })->setName('tutorial/phpinfo');

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




