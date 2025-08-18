<?php

namespace App;

use Carbon\Carbon;

class UrlRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function selectUrl(int $id): Url
    {
        $sql = 'SELECT name, created_at FROM urls WHERE id=?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, 'App\Url');
        $url = $stmt->fetch();
        return $url;
    }

    /**
     * @return Url|bool
     */
    public function selectId(string $url)
    {
        $sql = 'SELECT id FROM urls WHERE name=?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$url]);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, 'App\Url');
        $url = $stmt->fetch();
        return $url;
    }

    public function selectUrls(): array
    {
        $sql = "SELECT urls.id, urls.name FROM urls GROUP BY urls.id ORDER BY urls.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $urls = $stmt->fetchAll(\PDO::FETCH_CLASS);
        return $urls;
    }

    public function insertUrl(string $url): int
    {
        $sql = 'INSERT INTO urls(name, created_at) VALUES(:name, :date)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $url);
        $stmt->bindValue(':date', Carbon::now());
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }
}
