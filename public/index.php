<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use App\Url;
use App\Check;

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
})->setName('root');

$app->get('/urls/{id}', function ($request, $response, array $args) use ($router, $container) {
    $pdo = $container->get('db');
    $id = $args['id'];
    $urlSelector = new Url($pdo);
    $checkSelector = new Check($pdo);
    $url = $urlSelector->selectUrlWithDate($id);
    $checks = $checkSelector->selectCheck($id);
    $messages = $this->get('flash')->getMessages();
    $params = [
        'id' => $id,
        'errors' => $messages,
        'router' => $router,
        'checks' => $checks
    ];
    $params['url'] = $url->name ?? null;
    $params['date'] = $url->created_at ?? null;
    return $this->get('renderer')->render($response, 'urls/viewPage.phtml', $params);
})->setName('urls.show');

$app->post('/urls', function ($request, $response) use ($router, $container) {
    $url = $request->getParsedBodyParam('url');
    $urlMaxLen = 255;
    $pdo = $container->get('db');
    $validator = new Valitron\Validator($_POST);
    $validator->rule('required', 'url.name')->message("URL не должен быть пустым");
    $validator->rule('url', 'url.name')->message('Некорректный URL');
    $validator->rule('lengthMax', 'url.name', $urlMaxLen)->message('URL превышает 255 символов');
    $parsedUrl = parse_url($url['name']);
    $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
    $urlSelector = new Url($pdo);
    $notUniqueUrl = $urlSelector->selectId($normalizedUrl);
    if ($notUniqueUrl->id) {
        $this->get('flash')->addMessage('success', 'Страница уже существует');
        return $response->withRedirect($router->urlFor('urls.show', ['id' => $notUniqueUrl->id]), 302);
    } elseif ($validator->validate()) {
        $id = $urlSelector->insertUrl($normalizedUrl);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        return $response->withRedirect($router->urlFor('urls.show', ['id' => (string)$id]), 302);
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
})->setName('urls.add');

$app->get('/urls', function ($request, $response) use ($router, $container) {
    $pdo = $container->get('db');
    $urlSelector = new Url($pdo);
    $checkSelector = new Check($pdo);
    $urls = $urlSelector->selectUrls();
    $checks = $checkSelector->selectChecks();
    $urlsWithChecks = array_map(function ($url) use ($checks) {
        $obj = new stdClass();
        $obj->id = $url->id;
        $obj->name = $url->name;
        $ids = array_column($checks, 'url_id');
        if (in_array($url->id, $ids)) {
            $index = array_search($url->id, $ids);
            $obj->status_code = $checks[$index]->status_code;
            $obj->last_check_date = $checks[$index]->created_at;
        }
        return $obj;
    }, $urls);
    $params = [
        'urls' => $urlsWithChecks,
        'router' => $router,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'urls/viewPages.phtml', $params);
})->setName('urls.index');

$app->post('/urls/{id}/checks', function ($request, $response, array $args) use ($router, $container) {
    $pdo = $container->get('db');
    $id = $args['id'];
    $urlSelector = new Url($pdo);
    $url = $urlSelector->selectUrl((int)$id);
    try {
        $client = new GuzzleHttp\Client();
        $res = $client->get($url->name ?? null);
        $checkInserter = new Check($pdo);
        $checkInserter->insertCheck($id, $res->getStatusCode(), $url->name ?? null);
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
