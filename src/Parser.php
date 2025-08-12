<?php

namespace App\Parser;

use DiDom\Document;

function parse(string $name)
{
    $html = new Document($name, true);
    $meta = $html->first('meta[name=description]');
    $result = ['h1' => $html->first('h1::text'), 'title' => $html->first('title::text')];
    if ($meta) {
        $result['description'] = $meta->getAttribute('content');
    }
    return $result;
}
