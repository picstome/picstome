<?php

use Illuminate\Support\Facades\Route;

it('trusts forwarded headers from localhost in testing', function () {
    Route::get('/__trusted-proxy-test', fn () => response()->json([
        'scheme' => request()->getScheme(),
        'host' => request()->getHost(),
        'ip' => request()->ip(),
    ]));

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'http',
            'X-Forwarded-Host' => 'example.test',
            'X-Forwarded-Port' => '80',
            'X-Forwarded-For' => '203.0.113.10',
        ])
        ->getJson('/__trusted-proxy-test')
        ->assertJson([
            'scheme' => 'http',
            'host' => 'example.test',
            'ip' => '203.0.113.10',
        ]);
});

it('ignores forwarded headers from an untrusted address', function () {
    Route::get('/__untrusted-proxy-test', fn () => response()->json([
        'scheme' => request()->getScheme(),
        'host' => request()->getHost(),
        'ip' => request()->ip(),
    ]));

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'http',
            'X-Forwarded-Host' => 'untrusted.test',
            'X-Forwarded-Port' => '80',
            'X-Forwarded-For' => '203.0.113.10',
        ])
        ->getJson('https://app.test/__untrusted-proxy-test')
        ->assertJson([
            'scheme' => 'https',
            'host' => 'app.test',
            'ip' => '8.8.8.8',
        ]);
});
