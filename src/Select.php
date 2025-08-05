<?php

namespace App;

use App\Checks;

class Select
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function selectUrl(string $id)
    {
        $sql = "SELECT name FROM urls WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        [$name] = $stmt->fetch();
        return [$name];
    }

    public function selectUrlWithDate(string $id)
    {
        $sql = 'SELECT name, created_at FROM urls WHERE id=?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        [$url, $date] = $stmt->fetch();
        return [$url, $date];
    }

    public function selectId(string $url)
    {
        $sql = 'SELECT id FROM urls WHERE name=?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$url]);
        [$id] = $stmt->fetch();
        return [$id];
    }

    public function selectCheck(string $id)
    {
        $sql = "SELECT id, status_code, h1, title, description,
        created_at FROM url_checks WHERE url_id=? ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $checks = $stmt->fetchAll(\PDO::FETCH_CLASS, 'App\Checks');
        return $checks;
    }

    public function selectUrls()
    {
        $sql = "SELECT urls.id, urls.name FROM urls GROUP BY urls.id ORDER BY urls.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $urls = $stmt->fetchAll(\PDO::FETCH_CLASS, 'App\Urls');
        return $urls;
    }

    public function selectChecks()
    {
        $sql = "SELECT url_id, status_code, created_at FROM url_checks ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $checks = $stmt->fetchAll(\PDO::FETCH_CLASS, 'App\Checks');
        return $checks;
    }
}
