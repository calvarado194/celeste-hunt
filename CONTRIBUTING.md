# How to Contribute

## Pull Requests

1. Fork the Slim Skeleton repository
2. Create a new branch for each feature or improvement
3. Send a pull request from each feature branch to the **3.x** branch

It is very important to separate new features or improvements into separate feature branches, and to send a
pull request for each branch. This allows us to review and pull in new features or improvements individually.

## Style Guide

All pull requests must adhere to the [PSR-2 standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md).



Dev environment steps: (debian vm)
1. clone repo
2. composer update
3. remove lines from index.php (maybe not needed????)
// if (PHP_SAPI == 'cli-server') {
//     // To help the built-in PHP dev server, check if the request was actually for
//     // something which should probably be served as a static file
//     $url  = parse_url($_SERVER['REQUEST_URI']);
//     $file = __DIR__ . $url['path'];
//     if (is_file($file)) {
//         return false;
//     }
// }

4. php -S localhost:8000 -t public
5. open browser to http://localhost:8000/index.php/