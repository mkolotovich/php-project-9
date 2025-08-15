<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Http\ServerRequest;
use Slim\Http\Response;
use DI\Container;
use App\Connection;
use App\UrlRepository;
use App\CheckRepository;
use function App\Parser\parse;

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

$app->get('/', function (ServerRequest $request, Response $response) use ($router): Response {
    $params = [
        'router' => $router,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('root');

$app->get('/urls/{id}', function (ServerRequest $request, Response $response, array $args)
 use ($router, $container): Response {
    $pdo = $container->get('db');
    $id = $args['id'];
    $UrlDAO = new UrlRepository($pdo);
    $CheckDAO = new CheckRepository($pdo);
    $url = $UrlDAO->selectUrl($id);
    $checks = $CheckDAO->selectCheck($id);
    $messages = $this->get('flash')->getMessages();
    $params = [
        'url' => $url->name,
        'date' => $url->created_at,
        'id' => $id,
        'errors' => $messages,
        'router' => $router,
        'checks' => $checks
    ];
    return $this->get('renderer')->render($response, 'urls/viewPage.phtml', $params);
})->setName('urls.show');

$app->post('/urls', function (ServerRequest $request, Response $response) use ($router, $container): Response {
    $url = $request->getParsedBodyParam('url');
    $urlMaxLen = 255;
    $pdo = $container->get('db');
    $validator = new Valitron\Validator($_POST);
    $validator->rule('required', 'url.name')->message("URL не должен быть пустым");
    $validator->rule('url', 'url.name')->message('Некорректный URL');
    $validator->rule('lengthMax', 'url.name', $urlMaxLen)->message('URL превышает 255 символов');
    $parsedUrl = parse_url($url['name']);
    $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
    $UrlDAO = new UrlRepository($pdo);
    $existingUrl = $UrlDAO->selectId($normalizedUrl);
    if ($existingUrl->id) {
        $this->get('flash')->addMessage('success', 'Страница уже существует');
        return $response->withRedirect($router->urlFor('urls.show', ['id' => $existingUrl->id]), 302);
    }
    if ($validator->validate()) {
        $id = $UrlDAO->insertUrl($normalizedUrl);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        return $response->withRedirect($router->urlFor('urls.show', ['id' => (string)$id]), 302);
    }
    $error = optional($validator->errors())['url.name'][0];
    $response = $response->withStatus(422);
    $params = [
        'errors' => [$error],
        'router' => $router,
    ];
    return $this->get('renderer')->render($response, "index.phtml", $params);
})->setName('urls.add');

$app->get('/urls', function (ServerRequest $request, Response $response) use ($router, $container): Response {
    $pdo = $container->get('db');
    $UrlDAO = new UrlRepository($pdo);
    $CheckDAO = new CheckRepository($pdo);
    $urls = $UrlDAO->selectUrls();
    $checks = $CheckDAO->selectChecks();
    $checksIds = array_column($checks, 'url_id');
    $urlsWithChecks = array_map(function ($url) use ($checks, $checksIds) {
        if (in_array($url->id, $checksIds)) {
            $index = array_search($url->id, $checksIds);
            $url->status_code = $checks[$index]->status_code;
            $url->last_check_date = $checks[$index]->created_at;
        }
        return $url;
    }, $urls);
    $params = [
        'urls' => $urlsWithChecks,
        'router' => $router,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'urls/viewPages.phtml', $params);
})->setName('urls.index');

$app->post('/urls/{id}/checks', function (ServerRequest $request, Response $response, array $args)
 use ($router, $container): Response {
    $pdo = $container->get('db');
    $id = $args['id'];
    $UrlDAO = new UrlRepository($pdo);
    $url = $UrlDAO->selectUrl((int)$id);
    try {
        $client = new GuzzleHttp\Client();
        $res = $client->get($url->name);
        $CheckDAO = new CheckRepository($pdo);
        $parsedData = parse($url->name);
        $CheckDAO->insertCheck($id, $res->getStatusCode(), $parsedData);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (GuzzleHttp\Exception\ConnectException) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке, не удалось подключиться');
    } catch (GuzzleHttp\Exception\ClientException) {
        $this->get('flash')->addMessage('warning', 'Проверка была выполнена успешно, но сервер ответил с ошибкой');
    } catch (GuzzleHttp\Exception\ServerException) {
        $this->get('flash')->addMessage('warning', 'Ошибка 500');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }
    return $response->withRedirect($router->urlFor('urls.show', ['id' => $id]), 302);
})->setName('urls.check');

$app->run();
