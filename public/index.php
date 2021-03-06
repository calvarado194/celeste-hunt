<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \ParagonIE\SeedSpring\SeedSpring;
use \Slim\Views\PhpRenderer;
use \Dflydev\FigCookies\FigRequestCookies;
use \Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;

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
    $lang = FigRequestCookies::get($request, 'lang');

    if($lang->getValue() == null){
        $response = FigResponseCookies::set($response, SetCookie::create('lang')
           ->withValue('en')
           ->withMaxAge(500)
           ->rememberForever()
           ->withPath('/celeste')
           ->withDomain('.oneninefour.cl')
           ->withSecure(true)
           ->withHttpOnly(true));
    }

    $flags = $request->getQueryParam('flags');

    return $response->withRedirect('/celeste/'.$seed.(($flags != null)? '?flags='.$flags:''));
});

$app->get('/celeste/{seed:\w+}', function (Request $request, Response $response, array $args) {
    $seed = $args['seed'];
    $lang = FigRequestCookies::get($request, 'lang');
    if($lang->getValue() == null){
        $response = FigResponseCookies::set($response, SetCookie::create('lang')
           ->withValue('en')
           ->withMaxAge(500)
           ->rememberForever()
           ->withPath('/celeste')
           ->withDomain('.oneninefour.cl')
           ->withSecure(true)
           ->withHttpOnly(true));
    }

    $flags = $request->getQueryParam('flags');
    list($task_list, $page_text) = getTaskList($seed, $lang->getValue(), $flags);

    if($flags == null){
        $flags = [];
    }

    $flags = str_split($flags);

    $lang_options = ['en' => 'English', 'es' => 'Español', 'de' => 'German'];
    $response = $this->renderer->render($response, 'index.phtml', ['task_list' => $task_list, 'page_text' => $page_text['WEBSITE'], 'lang' => $lang->getValue(), 'lang_options' => $lang_options, 'flags' => $flags]);
    return $response;
});

//API methods
$app->post('/celeste/', function (Request $request, Response $response, array $args) {
    //generate semirandom seed based on a hash of current timestamp
    $date = date('YmdHis');
    $seed = substr(md5($date),0,16);

    $parsedBody = $request->getParsedBody();

    $lang = null;
    if(array_key_exists('lang', $parsedBody)){
        $lang = $parsedBody['lang'];
    }
    else{
        $lang = 'en';
    }

    $flags = '';
    if(array_key_exists('allow_cheat', $parsedBody) && $parsedBody['allow_cheat']){
        $flags.='c';
    }
    if(array_key_exists('exclude_berries', $parsedBody) && $parsedBody['exclude_berries']){
        $flags.='s';
    }
    if(array_key_exists('exclude_pico', $parsedBody) && $parsedBody['exclude_pico']){
        $flags.='p';
    }

    list($task_list, $page_text) = getTaskList($seed, $lang, $flags);

    $data = ['seed' => $seed, 'list' => $task_list, 'lang' => $lang];

    if(array_key_exists('allow_cheat', $parsedBody)){
        $data['allow_cheat'] = $parsedBody['allow_cheat'];
    }
    if(array_key_exists('exclude_berries', $parsedBody)){
        $data['exclude_berries'] = $parsedBody['exclude_berries'];
    }
    if(array_key_exists('exclude_pico', $parsedBody)){
        $data['exclude_pico'] = $parsedBody['exclude_pico'];
    }

    return $response->withJson($data);
});

$app->post('/celeste/{seed:\w+}', function (Request $request, Response $response, array $args) {
    $seed = $args['seed'];
    $parsedBody = $request->getParsedBody();

    $lang = null;
    if(array_key_exists('lang', $parsedBody)){
        $lang = $parsedBody['lang'];
    }
    else{
        $lang = 'en';
    }

    $flags = '';
    if(array_key_exists('allow_cheat', $parsedBody) && $parsedBody['allow_cheat']){
        $flags.='c';
    }
    if(array_key_exists('exclude_berries', $parsedBody) && $parsedBody['exclude_berries']){
        $flags.='s';
    }
    if(array_key_exists('exclude_pico', $parsedBody) && $parsedBody['exclude_pico']){
        $flags.='p';
    }

    list($task_list, $page_text) = getTaskList($seed, $lang, $flags);

    $data = ['seed' => $seed, 'list' => $task_list, 'lang' => $lang];

    if(array_key_exists('allow_cheat', $parsedBody)){
        $data['allow_cheat'] = $parsedBody['allow_cheat'];
    }
    if(array_key_exists('exclude_berries', $parsedBody)){
        $data['exclude_berries'] = $parsedBody['exclude_berries'];
    }
    if(array_key_exists('exclude_pico', $parsedBody)){
        $data['exclude_pico'] = $parsedBody['exclude_pico'];
    }

    return $response->withJson($data);
});



$app->run();

//randomization logic -- create task list given seed
function getTaskList($seed, $lang = 'en', $flags = ''){
    if($lang == null){
        $lang = 'en';
    }
    $text_strings = get_text_strings($lang);

    //retrieve task library and init vars
    if($flags == null){
        $flags = '';
    }

    $flags = str_split($flags);
    $task_library = getTaskLibrary($flags);
    $task_list = [];
    $removed_task_ids = [];

    //Initialize RNG
    $seed = substr(md5('74dPU18G'.$seed),0,16);
    $rng = new SeedSpring($seed);

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
            'name' => lookup_string($text_strings['CHAPTERS'], "AREA_$chapter"),
            'task' => lookup_string($text_strings['TASKS'], $task_key)
        ];
    }

    for($i = 0; $i < count($chapter_container); $i++){
        $chapter_data = $chapter_container[$i];
        $task_list[$chapter_data['name']] = $chapter_data['task'];

    }

    return array($task_list, $text_strings);
}

function get_text_strings($language_chosen = 'en') {
    // TODO add code that gets some value of a <select> from the post params and uses that to load the correct language file. Right now its just english
    return json_decode(file_get_contents("../I18N/{$language_chosen}_strings.json"), true);
}

function get_chapter_names($lang = 'en'){
    $data = json_decode(file_get_contents("../I18N/chapter_names/chapter_names_{$lang}.json"), true);
    $data = $data['chapters'];
    $names = [];
    
    for($i = 0; $i < 8; $i++){
        $names[$i] = $data["AREA_".($i + 1)];
    }

    return $names;
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

function getTaskLibrary($flags = []){
    $task_library = json_decode(file_get_contents('task_list.json'), true);
    //remove tasks given flags
    foreach($task_library as $category => $task_list){
        foreach($task_list as $key => $task){
            if(array_key_exists('flags', $task) && $task['flags'] != []){
                foreach($task['flags'] as $flag){
                    if(in_array($flag, $flags)){
                        unset($task_library[$category][$key]);
                    }
                }
            }

            if(!in_array('c',$flags) && array_key_exists('cheat', $task)){
                unset($task_library[$category][$key]);
            }
        }
        $task_library[$category] = array_values($task_library[$category]);
    }

    return $task_library;
}

function print_r2($val){
    echo '<pre>';
    print_r($val);
    echo  '</pre>';
}
