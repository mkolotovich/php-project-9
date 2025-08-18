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
$container->set('CheckRepository', new CheckRepository($container->get('db')));
$container->set('UrlRepository', new UrlRepository($container->get('db')));

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
    $id = $args['id'];
    $UrlRepository = $container->get('UrlRepository');
    $CheckRepository = $container->get('CheckRepository');
    $url = $UrlRepository->selectUrl($id);
    $checks = $CheckRepository->selectCheck($id);
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
    $validator = new Valitron\Validator($_POST);
    $validator->rule('required', 'url.name')->message("URL не должен быть пустым");
    $validator->rule('url', 'url.name')->message('Некорректный URL');
    $validator->rule('lengthMax', 'url.name', $urlMaxLen)->message('URL превышает 255 символов');
    $parsedUrl = parse_url($url['name']);
    $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
    $UrlRepository = $container->get('UrlRepository');
    $existingUrl = $UrlRepository->selectId($normalizedUrl);
    if ($existingUrl->id) {
        $this->get('flash')->addMessage('success', 'Страница уже существует');
        return $response->withRedirect($router->urlFor('urls.show', ['id' => $existingUrl->id]), 302);
    }
    if ($validator->validate()) {
        $id = $UrlRepository->insertUrl($normalizedUrl);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        return $response->withRedirect($router->urlFor('urls.show', ['id' => (string)$id]), 302);
    }
    $error = optional($validator->errors())['url.name'];
    $response = $response->withStatus(422);
    $params = [
        'errors' => [head($error)],
        'router' => $router,
    ];
    return $this->get('renderer')->render($response, "index.phtml", $params);
})->setName('urls.add');

$app->get('/urls', function (ServerRequest $request, Response $response) use ($router, $container): Response {
    $UrlRepository = $container->get('UrlRepository');
    $CheckRepository = $container->get('CheckRepository');
    $urls = $UrlRepository->selectUrls();
    $urlsWithChecks = array_map(function ($url) use ($CheckRepository) {
        [$lastCheck] = $CheckRepository->selectChecks($url->id);
        $url->status_code = $lastCheck->status_code;
        $url->last_check_date = $lastCheck->created_at;
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
    $id = $args['id'];
    $UrlRepository = $container->get('UrlRepository');
    $url = $UrlRepository->selectUrl((int)$id);
    try {
        $client = new GuzzleHttp\Client();
        $res = $client->get($url->name);
        $CheckRepository = $container->get('CheckRepository');
        $parsedData = parse($url->name);
        $CheckRepository->insertCheck($id, $res->getStatusCode(), $parsedData);
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
