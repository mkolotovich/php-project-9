<?php

namespace App;

class UrlsWithChecks
{
    public int $id;
    public string $name;
    public mixed $status_code;
    public mixed $last_check_date;

    public function __construct(int $id, string $name, mixed $statusCode = null, mixed $createdAt = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->status_code = $statusCode;
        $this->last_check_date = $createdAt;
    }
}
