<?php

use App\Models\Document;
use Illuminate\Support\Str;

test('version max aggregate does not carry the relation order by', function () {
    $document = new Document;

    $sql = Str::lower($document->versions()
        ->reorder()
        ->selectRaw('MAX(CAST(version_number AS INTEGER)) as max')
        ->toSql());

    expect($sql)->not->toContain('order by "created_at"');
});
