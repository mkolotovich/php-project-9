<?php

namespace App;

use Carbon\Carbon;
use DiDom\Document;

class Insert
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertUrl(string $url)
    {
        $sql = 'INSERT INTO urls(name, created_at) VALUES(:name, :date)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $url);
        $stmt->bindValue(':date', Carbon::now());
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    public function insertCheck(string $id, int $statusCode, string $name)
    {
        $html = new Document($name, true);
        $meta = $html->first('meta[name=description]');
        $sql = <<<EOT
        INSERT INTO url_checks(url_id, status_code, h1, title, description, created_at) 
        VALUES(:id, :status, :h1, :title, :description, :date)
        EOT;
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':status', $statusCode);
        $stmt->bindValue(':h1', $html->first('h1::text'));
        $stmt->bindValue(':title', $html->first('title::text'));
        if ($meta) {
            $stmt->bindValue(':description', $meta->getAttribute('content'));
        }
        $stmt->bindValue(':date', Carbon::now());
        $stmt->execute();
    }
}
