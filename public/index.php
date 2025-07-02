<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Carbon\Carbon;
use DiDom\Document;

session_start();
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
if (file_exists(realpath(implode('/', [__DIR__ . '/../', '.env'])))) {
    $dotenv->load();
    $databaseUrl = parse_url($_ENV['DATABASE_URL']);
} else {
    $databaseUrl = parse_url(getenv('DATABASE_URL'));
}

$port = array_key_exists('port', $databaseUrl) ? $databaseUrl['port'] : 5432;

$conStr = sprintf(
    "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
    $databaseUrl['host'],
    $port,
    ltrim($databaseUrl['path'], '/'),
    $databaseUrl['user'],
    $databaseUrl['pass']
);

$container = new Container();
$container->set('renderer', function () {
    $renderer = new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    $renderer->setLayout('layout.php');
    return $renderer;
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $params = [
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('main');

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/urls/{id}', function ($request, $response, array $args) use ($conStr, $router) {
    $pdo = new \PDO($conStr);
    $id = $args['id'];
    $sql = 'SELECT name, created_at FROM urls WHERE id=?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    [$url, $date] = $stmt->fetch();
    $sql = "SELECT id, status_code, h1, title, description, created_at FROM url_checks WHERE url_id=? ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $checks = $stmt->fetchAll();
    $messages = $this->get('flash')->getMessages();
    $params = [
        'url' => $url,
        'date' => $date,
        'id' => $id,
        'errors' => $messages,
        'router' => $router,
        'checks' => $checks
    ];
    return $this->get('renderer')->render($response, 'viewPage.phtml', $params);
})->setName('renderUrlPage');

$app->post('/urls', function ($request, $response) use ($router, $conStr) {
    $url = $request->getParsedBodyParam('url');
    $urlMaxLen = 255;
    $pdo = new \PDO($conStr);
    $validator = new Valitron\Validator($_POST);
    $validator->rule('required', 'url.name');
    $validator->rule('url', 'url.name');
    $validator->rule('lengthMax', 'url.name', $urlMaxLen);
    if ($validator->validate()) {
        $parsedUrl = parse_url($url['name']);
        $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
        $sql = 'SELECT id FROM urls WHERE name=?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$normalizedUrl]);
        [$id] = $stmt->fetch();
        if (!$id) {
            $sql = 'INSERT INTO urls(name, created_at) VALUES(:name, :date)';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':name', $normalizedUrl);
            $stmt->bindValue(':date', Carbon::now());
            $stmt->execute();
            $id = $pdo->lastInsertId();
            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
            return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $id]), 302);
        } else {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $id]), 302);
        }
    }
    if (in_array("Url.name is required", $validator->errors()['url.name'])) {
        $error = 'URL не должен быть пустым';
    } elseif (in_array("Url.name is not a valid URL", $validator->errors()['url.name'])) {
        $error = 'Некорректный URL';
        $response = $response->withStatus(422);
    } else {
        $error = 'URL превышает 255 символов';
    }
    $params = [
        'errors' => [$error]
    ];
    return $this->get('renderer')->render($response, "index.phtml", $params);
});

$app->get('/urls', function ($request, $response) use ($router, $conStr) {
    $pdo = new \PDO($conStr);
    $sql = "SELECT urls.id, urls.name, MAX(url_checks.created_at), MAX(status_code) FROM urls LEFT JOIN url_checks ON
                      urls.id=url_checks.url_id GROUP BY urls.id ORDER BY urls.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $urls = $stmt->fetchAll();
    $params = [
        'urls' => $urls,
        'router' => $router,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'viewPages.phtml', $params);
})->setName('main');

$app->post('/urls/{id}/checks', function ($request, $response, array $args) use ($router, $conStr) {
    $pdo = new \PDO($conStr);
    $id = $args['id'];
    $sql = "SELECT name FROM urls WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    [$name] = $stmt->fetch();
    try {
        $client = new GuzzleHttp\Client();
        $res = $client->get($name);
        if ($res->getStatusCode()) {
            $html = new Document($name, true);
            $sql = <<<EOT
            INSERT INTO url_checks(url_id, status_code, h1, title, description, created_at) 
            VALUES(:id, :status, :h1, :title, :description, :date)
            EOT;
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':status', $res->getStatusCode());
            $stmt->bindValue(':h1', $html->first('h1::text'));
            $stmt->bindValue(':title', $html->first('title::text'));
            $stmt->bindValue(':description', optional($html->first('meta[name=description]'))->getAttribute('content'));
            $stmt->bindValue(':date', Carbon::now());
            $stmt->execute();
            $this->get('flash')->addMessage('success', 'Страница успешно проверена');
            return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $id]), 302);
        }
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке');
        return $response->withRedirect($router->urlFor('renderUrlPage', ['id' => $id]), 302);
    }
})->setName('checkPage');

$app->run();
