<?php

use App\Http\Middleware\RedirectIfAuthenticatedToDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

test('allows access when token is in session', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'test-session-token']);

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getContent())->toBe('Success');
    expect($response->getStatusCode())->toBe(200);
});

test('allows access when token is in cookie', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    $request->cookies->set('app-access-token', 'test-cookie-token');

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getContent())->toBe('Success');
    expect($response->getStatusCode())->toBe(200);
});

test('redirects to login when no token present', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('login');
});

test('redirects to login when session token is empty string', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => '']);

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('redirects to login when cookie token is empty string', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    $request->cookies->set('app-access-token', '');

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('session token works without cookie', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'session-token-only']);

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('cookie token works without session token', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    $request->cookies->set('app-access-token', 'cookie-token-only');

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('allows access when both session and cookie tokens present', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'session-token']);
    $request->cookies->set('app-access-token', 'cookie-token');

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('handles null session token correctly', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => null]);

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('handles missing session correctly', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('works with POST requests', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'POST');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'test-token']);

    $response = $middleware->handle($request, function ($req) {
        return response('Posted');
    });

    expect($response->getContent())->toBe('Posted');
});

test('redirects POST requests without token', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'POST');
    $request->setLaravelSession(session()->driver());

    $response = $middleware->handle($request, function ($req) {
        return response('Posted');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('works with PUT requests', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'PUT');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'test-token']);

    $response = $middleware->handle($request, function ($req) {
        return response('Updated');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('works with PATCH requests', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'PATCH');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'test-token']);

    $response = $middleware->handle($request, function ($req) {
        return response('Patched');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('works with DELETE requests', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'DELETE');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'test-token']);

    $response = $middleware->handle($request, function ($req) {
        return response('Deleted');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('next closure is called with original request', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());
    session(['app-access-token' => 'test-token']);

    $passedRequest = null;

    $middleware->handle($request, function ($req) use (&$passedRequest) {
        $passedRequest = $req;

        return response('Success');
    });

    expect($passedRequest)->toBe($request);
});

test('returns redirect response with correct status code', function () {
    $middleware = new RedirectIfAuthenticatedToDashboard;

    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(session()->driver());

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getStatusCode())->toBe(302);
});
