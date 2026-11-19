<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

function limitFor(string $name, Request $request)
{
    return RateLimiter::limiter($name)($request);
}

function requestWithSession(array $input = [], array $session = []): Request
{
    $request = Request::create('/', 'POST', $input, [], [], ['REMOTE_ADDR' => '203.0.113.9']);
    $store = app('session')->driver();
    $store->put($session);
    $request->setLaravelSession($store);

    return $request;
}

it('limits two-factor attempts to five per minute keyed by login id', function () {
    $limit = limitFor('two-factor', requestWithSession([], ['login.id' => 42]));

    expect($limit->maxAttempts)->toBe(5)
        ->and($limit->key)->toBe(42);
});

it('limits login attempts to five per minute keyed by lowercased email and ip', function () {
    $limit = limitFor('login', requestWithSession(['email' => 'Jane@Example.COM']));

    expect($limit->maxAttempts)->toBe(5)
        ->and($limit->key)->toBe('jane@example.com|203.0.113.9');
});

it('limits passkey attempts to ten per minute keyed by credential id and ip', function () {
    $limit = limitFor('passkeys', requestWithSession(['credential' => ['id' => 'cred-abc']]));

    expect($limit->maxAttempts)->toBe(10)
        ->and($limit->key)->toBe('cred-abc|203.0.113.9');
});

it('falls back to the session id for passkey limiting without a credential id', function () {
    $request = requestWithSession();
    $limit = limitFor('passkeys', $request);

    expect($limit->key)->toBe($request->session()->getId().'|203.0.113.9');
});
