<?php

use App\Jobs\AddToAcumbamailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('successfully adds subscriber to Acumbamail list', function () {
    config(['services.acumbamail.auth_token' => 'test_token']);
    config(['services.acumbamail.list_id' => '123']);

    Http::fake([
        'acumbamail.com/api/1/addSubscriber/' => Http::response(['subscriber_id' => 456], 200)
    ]);

    $job = new AddToAcumbamailList('john@example.com', 'John Doe', '123');
    $job->handle();

    Http::assertSent(function ($request) {
        $data = $request->data();
        return $request->url() === 'https://acumbamail.com/api/1/addSubscriber/' &&
               $request->method() === 'POST' &&
               $data['auth_token'] === 'test_token' &&
               $data['list_id'] === '123' &&
               $data['merge_fields']['EMAIL'] === 'john@example.com' &&
               $data['merge_fields']['NAME'] === 'John Doe' &&
               $data['double_optin'] === 0 &&
               $data['update_subscriber'] === 0 &&
               $data['complete_json'] === 0;
    });
});

it('uses provided list ID when specified', function () {
    config(['services.acumbamail.auth_token' => 'test_token']);
    config(['services.acumbamail.list_id' => 'default_list']);

    Http::fake([
        'acumbamail.com/api/1/addSubscriber/' => Http::response(['subscriber_id' => 789], 200)
    ]);

    $job = new AddToAcumbamailList('jane@example.com', 'Jane Smith', 'custom_list_123');
    $job->handle();

    Http::assertSent(function ($request) {
        $data = $request->data();
        return $data['list_id'] === 'custom_list_123'; // Should use provided list ID
    });
});

it('falls back to config list ID when none provided', function () {
    config(['services.acumbamail.auth_token' => 'test_token']);
    config(['services.acumbamail.list_id' => 'fallback_list']);

    Http::fake([
        'acumbamail.com/api/1/addSubscriber/' => Http::response(['subscriber_id' => 101], 200)
    ]);

    $job = new AddToAcumbamailList('bob@example.com', 'Bob Johnson'); // No listId provided
    $job->handle();

    Http::assertSent(function ($request) {
        $data = $request->data();
        return $data['list_id'] === 'fallback_list'; // Should use config fallback
    });
});

it('does not make HTTP request when auth token is missing', function () {
    config(['services.acumbamail.auth_token' => null]);
    config(['services.acumbamail.list_id' => '123']);

    Http::fake();

    $job = new AddToAcumbamailList('test@example.com', 'Test User');
    $job->handle();

    Http::assertNothingSent();
});

it('does not make HTTP request when list ID is missing', function () {
    config(['services.acumbamail.auth_token' => 'test_token']);
    config(['services.acumbamail.list_id' => null]);

    Http::fake();

    $job = new AddToAcumbamailList('test@example.com', 'Test User');
    $job->handle();

    Http::assertNothingSent();
});
