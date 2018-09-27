<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \ParagonIE\SeedSpring\SeedSpring;

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app

$app = new \Slim\App;
$app->get('/', function (Request $request, Response $response, array $args) {
    $date = date('YmdHis');
    $seed = substr(md5($date),0,16);

    return $response->withRedirect('/celeste/public/'.$seed);
});
$app->get('/{seed}', function (Request $request, Response $response, array $args) {
    $seed = $args['seed'];
    $seed = substr(md5('74dPU18G'.$seed),0,16);

    $task_library = json_decode(file_get_contents('task_list.json'), true);
    $task_list = [];
    $removed_task_ids = [];

    $rng = new SeedSpring($seed);

    for($chapter = 1;$chapter <=7; $chapter++){
        $merged_list = array_merge($task_library['general'],$task_library[$chapter]);

        //exclude strawberry-related tasks from chapter 6
        if($chapter == 6){
            foreach($merged_list as $key => $task){
                if(array_key_exists('strawb',$task)){
                    unset($merged_list[$key]);
                }
            }

            $merged_list = array_values($merged_list);
        }

	do{
            $rand_task = $merged_list[$rng->getInt(0, count($merged_list) -1)];
        }while(in_array($rand_task['task_id'],$removed_task_ids) || $rand_task == null);

        $removed_task_ids[] = $rand_task['task_id'];
        $task_list[] = $rand_task['task_description'];
    }

    $chapter_names = [
        "Forsaken City",
        "Old Site",
        "Celestial Resort",
        "Golden Ridge",
        "Mirror Temple",
	"Reflection",
        "Summit"
    ];

    foreach($task_list as $key=>$task){
        $response->getBody()->write('<strong>'.$chapter_names[$key]."</strong>: ".$task."<br>");
    }

    return $response;
});

$app->run();
