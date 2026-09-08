<?php

use Illuminate\Support\Facades\Route;

test('public storage paths are not intercepted by a signed private-serving route', function () {
    expect(Route::has('storage.local'))->toBeFalse();

    $this->get('/storage/example.jpg')->assertStatus(404);
});
