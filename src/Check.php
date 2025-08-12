<?php

namespace App;

use Carbon\Carbon;

use function App\Parser\parse;

class Check
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function selectCheck(string $id): array
    {
        $sql = "SELECT id, status_code, h1, title, description,
        created_at FROM url_checks WHERE url_id=? ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $checks = $stmt->fetchAll(\PDO::FETCH_CLASS);
        return $checks;
    }

    public function selectChecks(): array
    {
        $sql = "SELECT url_id, status_code, created_at FROM url_checks ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $checks = $stmt->fetchAll(\PDO::FETCH_CLASS);
        return $checks;
    }

    public function insertCheck(string $id, int $statusCode, string $name): void
    {
        ['h1' => $h1, 'title' => $title, 'description' => $meta] = parse($name);
        $sql = <<<EOT
        INSERT INTO url_checks(url_id, status_code, h1, title, description, created_at) 
        VALUES(:id, :status, :h1, :title, :description, :date)
        EOT;
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':status', $statusCode);
        $stmt->bindValue(':h1', $h1);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $meta);
        $stmt->bindValue(':date', Carbon::now());
        $stmt->execute();
    }
}
