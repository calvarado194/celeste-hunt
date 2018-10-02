<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \ParagonIE\SeedSpring\SeedSpring;
use \Slim\Views\PhpRenderer;

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

// Run app

$app->get('/celeste/', function (Request $request, Response $response, array $args) {
    //generate semirandom seed based on a hash of current timestamp
    $date = date('YmdHis');
    $seed = substr(md5($date),0,16);

    return $response->withRedirect('/celeste/'.$seed);
});
$app->get('/celeste/{seed:\w+}', function (Request $request, Response $response, array $args) {
    $seed = $args['seed'];
    //hash and substring input to get seed
    $seed = substr(md5('74dPU18G'.$seed),0,16);

    $task_list = getTaskList($seed);
    $response = $this->renderer->render($response, 'index.phtml', ['task_list' => $task_list]);
    return $response;
});

$app->run();

//randomization logic -- create task list given seed
function getTaskList($seed){
    //retrieve task library and init vars 
    $task_library = json_decode(file_get_contents('task_list.json'), true);
    $task_list = [];
    $removed_task_ids = [];

    //init RNG
    $rng = new SeedSpring($seed);

    $chapter_names = [
        "Forsaken City",
        "Old Site",
        "Celestial Resort",
        "Golden Ridge",
        "Mirror Temple",
        "Reflection",
        "Summit"
    ];

    //get task per chapter
    for($chapter = 1;$chapter <=7; $chapter++){
        //gen possible tasks for the chaptr
        $merged_list = array_merge($task_library['general'],$task_library[$chapter]);

        //exclude strawberry-related tasks from chapter 6
        if ($chapter != 6) {
            $merged_list = array_merge($merged_list,$task_library['strawberry']);
        }

        //Replace list weights with accumulated weight values
        
        $sum = 0;
        for ($i = 0; $i < count($merged_list); $i++) {
            $item = $merged_list[$i];
            if (array_key_exists('weight',$item)) {
                $temp = $item['weight'];
            }
            //Use default weight of 1 if not specified
            else {
                $temp = 1;
            }
            $item['weight'] = $sum;
            $sum += $temp;
        }

        //obtain task avoiding repeating ones
        do {
            $rand = $sum*($rng->getInt(0, 100)/100);
            $rand_task = null;
            for($i = 0; $i < count($merged_list)-1; $i++) {
                if($rand <= $merged_list[$i+1]['weight']){
                    $rand_task = $merged_task[$i];
                    break;
                }
            }
            if($rand_task == null){
                $rand_task = $merged_list[count($merged_list)-1];
            }

        } while((array_key_exists('task_id', $rand_task) && in_array($rand_task['task_id'],$removed_task_ids)));
        //Add task ID to used list, if present
        if (array_key_exists('task_id', $rand_task)) {
            $removed_task_ids[] = $rand_task['task_id'];
        }

        $task_list[$chapter_names[$chapter -1]] = $rand_task['task_description'];
    }
    
    return $task_list;
}
