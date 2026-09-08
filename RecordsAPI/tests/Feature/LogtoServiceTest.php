<?php

use App\Services\LogtoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto-m2m.test',
        'services.logto.m2m_app_id' => 'test-m2m',
        'services.logto.m2m_app_secret' => 'test-secret',
        'services.logto.management_api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    Http::fake([
        'https://logto-m2m.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
    ]);
});

test('getUser returns the full Logto profile', function () {
    Http::fake([
        'https://logto-m2m.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto-m2m.test/api/users/user-1' => Http::response([
            'id' => 'user-1',
            'username' => 'Vamp2o5',
            'primaryEmail' => 'bit-023-22@must.ac.mw',
            'name' => 'Santiago Vander',
            'avatar' => 'https://example.com/avatar.png',
        ], 200),
    ]);

    $profile = app(LogtoService::class)->getUser('user-1');

    expect($profile['name'])->toBe('Santiago Vander')
        ->and($profile['avatar'])->toBe('https://example.com/avatar.png')
        ->and($profile['username'])->toBe('Vamp2o5');
});

test('getUser returns an empty array when the management API rejects', function () {
    Http::fake([
        'https://logto-m2m.test/api/users/user-1' => Http::response([], 403),
    ]);

    expect(app(LogtoService::class)->getUser('user-1'))->toBe([]);
});
