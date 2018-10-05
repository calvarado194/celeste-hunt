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
//require __DIR__ . '/../src/routes.php';


$app->get('/celeste/', function (Request $request, Response $response, array $args) {
    //Generate semirandom seed based on a hash of current timestamp
    $date = date('YmdHis');
    $seed = substr(md5($date), 0, 16);
    $lang = $request->getQueryParam('lang');

    if($lang == null)
        return $response->withRedirect('/celeste/'.$seed);
    
    return $response->withRedirect('/celeste/'.$seed.'?lang='.$lang);
});

$app->get('/celeste/{seed:\w+}', function (Request $request, Response $response, array $args) {
    $seed = $args['seed'];
    $lang = $request->getQueryParam('lang');

    list($task_list, $page_text) = getTaskList($seed, $lang);
    $response = $this->renderer->render($response, 'index.phtml', ['task_list' => $task_list, 'page_text' => $page_text]);
    return $response;
});

//API methods
$app->post('/celeste/', function (Request $request, Response $response, array $args) {
    //generate semirandom seed based on a hash of current timestamp
    $date = date('YmdHis');
    $seed = substr(md5($date),0,16);

    $task_list = getTaskList($seed);

    $data = ['seed' => $seed, 'list' => $task_list];
    return $response->withJson($data);
});

$app->post('/celeste/{seed:\w+}', function (Request $request, Response $response, array $args) {
    $seed = $args['seed'];
    $task_list = getTaskList($seed);

    $data = ['seed' => $seed, 'list' => $task_list];
    return $response->withJson($data);
});



$app->run();

//randomization logic -- create task list given seed
function getTaskList($seed, $lang = 'en'){
    if($lang == null){
        // supported languages:
        // ['de', 'en', 'es', 'fr', 'it', 'ja', 'ko', 'ru', 'zh_hans']
        $lang = 'en';
    }
    $text_strings = get_text_strings($lang);

    //retrieve task library and init vars
    $task_library = json_decode(file_get_contents('task_list.json'), true);
    $task_list = [];
    $removed_task_ids = [];

    //Initialize RNG
    $seed = substr(md5('74dPU18G'.$seed),0,16);
    $rng = new SeedSpring($seed);

    $chapter_names = [
        "AREA_1_FORSAKEN_CITY",
        "AREA_2_OLD_SITE",
        "AREA_3_CELESTIAL_RESORT",
        "AREA_4_GOLDEN RIDGE",
        "AREA_5_MIRROR TEMPLE",
        "AREA_6_REFLECTION",
        "AREA_7_THE SUMMIT",
        "AREA_8_CORE"
    ];

    $chapters = [1, 2, 3, 4, 5, 6, 7];
    $chapter_container = [];
    //Get a task for each chapter
    while (count($chapters) > 0) {
        //Pick chapters in a random order so that tasks with IDs don't cluster in earlier chapters
        $index = $rng->getInt(0, count($chapters) - 1);
        $chapter = $chapters[$index];

        unset($chapters[$index]);
        $chapters = array_values($chapters);

        //Generate possible tasks for the chapter
        $merged_list = array_merge($task_library['general'], $task_library[$chapter]);

        //Exclude strawberry-related tasks from chapter 6
        if ($chapter != 6) {
            $merged_list = array_merge($merged_list, $task_library['strawberry']);
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

            $sum += $temp;
            $merged_list[$i]['weight'] = $sum;
        }

        //Obtain a task, avoiding repeating ones
        do {
            $rand = $sum * ($rng->getInt(0, 65536) / 65536);
            $rand_task = null;

            for($i = 0; $i < count($merged_list); $i++) {
                if($rand <= $merged_list[$i]['weight']) {
                    $rand_task = $merged_list[$i];
                    break;
                }
            }

            if($rand_task == null) {
                $rand_task = $merged_list[count($merged_list) - 1];
            }
        } while((array_key_exists('task_id', $rand_task) && in_array($rand_task['task_id'], $removed_task_ids)));

        //Add task ID to used list, if present
        if (array_key_exists('task_id', $rand_task)) {
            $removed_task_ids[] = $rand_task['task_id'];
        }

        $task_key = $rand_task['task_key'];

        $chapter_container[$chapter - 1] = [
            'name' => lookup_string($text_strings, $chapter_names[$chapter - 1]),
            'task' => lookup_string($text_strings, $task_key)
        ];
    }

    for($i = 0; $i < count($chapter_container); $i++){
        $chapter_data = $chapter_container[$i];
        $task_list[$chapter_data['name']] = $chapter_data['task'];

    }

    return array($task_list, $text_strings);
}

function get_text_strings($language_chosen) {
    return json_decode(file_get_contents("../I18N/{$language_chosen}_strings.json"), true);
}

function lookup_string($string_library, $string_key) {
    if($string_library == null) {
        return "STRING LIBRARY MISSING. KEY: $string_key";
    }
    if(array_key_exists($string_key, $string_library)) {
         return $string_library[$string_key];
    } else {
        return "TRANSLATION MISSING: $string_key";
    }
}

function print_r2($val){
    echo '<pre>';
    print_r($val);
    echo  '</pre>';
}
