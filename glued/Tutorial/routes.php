<?php
// Requirements
use Slim\Routing\RouteCollectorProxy;

// Controllers (of this microservice)
use Tutorial\Controllers\BasicController;
//use Skeleton\Controllers\ComplexController;


// Define the app routes.
//$app->group('/example', function (RouteCollectorProxy $group) {
//    $group->get('', HomeController::class)->setName('home');
//    $group->get('hello/{name}', HelloController::class)->setName('hello');
//});

$app->group('/tutorial/', function(RouteCollectorProxy $group) {
  $group->get('', function() { 
    echo "<h1>The Skeleton Microservice</h1>
          <div>Welcome! This microservice serves as a skeleton / example to help you quickly develop stuff.</div>
          <div>Kindly review the source of glued/Skeleton/routes.php to see how this page is generated.</div>
          <div>For more examples, follow the links below:</div>
          <ul>
            <li><a href='./basic'>Basic controller</a></li>
          </ul>";
  })->setName('tutorial/home');
  $group->get('basic', BasicController::class)->setName('tutorial/basic');

});
