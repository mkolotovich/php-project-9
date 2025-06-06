<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Carbon\Carbon;

session_start();
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$databaseUrl = parse_url($_ENV['DATABASE_URL']);
$port = 5432;
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
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
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

$app->get('/urls/{id}', function ($request, $response, array $args) use ($conStr) {
    $pdo = new \PDO($conStr);
    $id = $args['id'];
    $sql ='SELECT name, created_at FROM urls WHERE id=?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    [$url, $date] = $stmt->fetch();
    $messages = $this->get('flash')->getMessages();
    $params = [
        'url' => $url,
        'date' => $date,
        'id' => $id,
        'errors' => $messages,
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
    if($validator->validate()) {
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
    } else if(in_array("Url.name is not a valid URL", $validator->errors()['url.name'])) {
        $error = 'Некорректный URL';
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
    $sql = "SELECT * FROM urls ORDER BY urls.id DESC";
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

$app->run();
