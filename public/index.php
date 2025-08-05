<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use App\Select;
use App\Insert;
use App\UrlsWithChecks;

session_start();

$container = new Container();
$container->set('renderer', function () {
    $renderer = new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    $renderer->setLayout('layout.php');
    return $renderer;
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('db', Connection::get()->connect());

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) use ($router) {
    $params = [
        'router' => $router,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('main');

$app->get('/urls/{id}', function ($request, $response, array $args) use ($router, $container) {
    $pdo = $container->get('db');
    $id = $args['id'];
    $tableSelector = new Select($pdo);
    [$url, $date] = $tableSelector->selectUrlWithDate($id);
    $checks = $tableSelector->selectCheck($id);
    $messages = $this->get('flash')->getMessages();
    $params = [
        'url' => $url,
        'date' => $date,
        'id' => $id,
        'errors' => $messages,
        'router' => $router,
        'checks' => $checks
    ];
    return $this->get('renderer')->render($response, 'urls/viewPage.phtml', $params);
})->setName('renderUrlPage');

$app->post('/urls', function ($request, $response) use ($router, $container) {
    $url = $request->getParsedBodyParam('url');
    $urlMaxLen = 255;
    $pdo = $container->get('db');
    $validator = new Valitron\Validator($_POST);
    $validator->rule('required', 'url.name')->message("URL не должен быть пустым");
    $validator->rule('url', 'url.name')->message('Некорректный URL');
    $validator->rule('lengthMax', 'url.name', $urlMaxLen)->message('URL превышает 255 символов');
    if ($validator->validate()) {
        $parsedUrl = parse_url($url['name']);
        $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
        $tableSelector = new Select($pdo);
        [$notUniqueUrlName] = $tableSelector->selectId($normalizedUrl);
        if ($notUniqueUrlName) {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $notUniqueUrlName]), 302);
        } else {
            $tableInserter = new Insert($pdo);
            $id = $tableInserter->insertUrl($normalizedUrl);
            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
            return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => (string)$id]), 302);
        }
    }
    if (is_array($validator->errors())) {
        $error = $validator->errors()['url.name'][0];
        $response = $response->withStatus(422);
        $params = [
            'errors' => [$error],
            'router' => $router,
        ];
        return $this->get('renderer')->render($response, "index.phtml", $params);
    }
});

$app->get('/urls', function ($request, $response) use ($router, $container) {
    $pdo = $container->get('db');
    $tableSelector = new Select($pdo);
    $urls = $tableSelector->selectUrls();
    $checks = $tableSelector->selectChecks();
    $urlsWithChecks = array_map(function ($url) use ($checks) {
        foreach ($checks as $key => $value) {
            if ($url->id === $value->url_id) {
                return new UrlsWithChecks($url->id, $url->name, $value->status_code, $value->created_at);
            }
        }
        return new UrlsWithChecks($url->id, $url->name);
    }, $urls);
    $params = [
        'urls' => $urlsWithChecks,
        'router' => $router,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'urls/viewPages.phtml', $params);
})->setName('urls.index / urls');

$app->post('/urls/{id}/checks', function ($request, $response, array $args) use ($router, $container) {
    $pdo = $container->get('db');
    $id = $args['id'];
    $tableSelector = new Select($pdo);
    [$name] = $tableSelector->selectUrl($id);
    try {
        $client = new GuzzleHttp\Client();
        $res = $client->get($name);
        $tableInserter = new Insert($pdo);
        $tableInserter->insertCheck($id, $res->getStatusCode(), $name);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
        return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $id]), 302);
    } catch (\Exception $e) {
        if (str_contains($e->getMessage(), "cURL")) {
            $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке, не удалось подключиться');
        } else {
            $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
        }
        return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $id]), 302);
    }
})->setName('checkPage');

$app->run();
