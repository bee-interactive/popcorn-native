<?php

use App\Http\Middleware\LocaleMiddleware;
use Illuminate\Http\Request;

test('sets locale to English when en cookie is present', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'en');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('en');
});

test('sets locale to French when fr cookie is present', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'fr');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('fr');
});

test('uses default locale when no cookie is present', function () {
    $middleware = new LocaleMiddleware;

    $defaultLocale = config('app.locale');

    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe($defaultLocale);
});

test('does not change locale when cookie is empty string', function () {
    $middleware = new LocaleMiddleware;

    app()->setLocale('en');
    $originalLocale = app()->getLocale();

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', '');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe($originalLocale);
});

test('always passes request to next middleware', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'fr');

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getContent())->toBe('Success');
    expect($response->getStatusCode())->toBe(200);
});

test('works with POST requests', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'POST');
    $request->cookies->set('locale', 'fr');

    $response = $middleware->handle($request, function ($req) {
        return response('Posted');
    });

    expect($response->getContent())->toBe('Posted');
    expect(app()->getLocale())->toBe('fr');
});

test('works with PUT requests', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'PUT');
    $request->cookies->set('locale', 'en');

    $response = $middleware->handle($request, function ($req) {
        return response('Updated');
    });

    expect($response->getStatusCode())->toBe(200);
    expect(app()->getLocale())->toBe('en');
});

test('works with PATCH requests', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'PATCH');
    $request->cookies->set('locale', 'fr');

    $middleware->handle($request, function ($req) {
        return response('Patched');
    });

    expect(app()->getLocale())->toBe('fr');
});

test('works with DELETE requests', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'DELETE');
    $request->cookies->set('locale', 'en');

    $middleware->handle($request, function ($req) {
        return response('Deleted');
    });

    expect(app()->getLocale())->toBe('en');
});

test('passes original request to next middleware', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'fr');

    $passedRequest = null;

    $middleware->handle($request, function ($req) use (&$passedRequest) {
        $passedRequest = $req;

        return response('Success');
    });

    expect($passedRequest)->toBe($request);
});

test('locale persists after middleware execution', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'fr');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('fr');
});

test('locale switches from en to fr', function () {
    app()->setLocale('en');
    expect(app()->getLocale())->toBe('en');

    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'fr');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('fr');
});

test('locale switches from fr to en', function () {
    app()->setLocale('fr');
    expect(app()->getLocale())->toBe('fr');

    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'en');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('en');
});

test('handles multiple requests with different locales', function () {
    $middleware = new LocaleMiddleware;

    $request1 = Request::create('/test1', 'GET');
    $request1->cookies->set('locale', 'en');

    $middleware->handle($request1, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('en');

    $request2 = Request::create('/test2', 'GET');
    $request2->cookies->set('locale', 'fr');

    $middleware->handle($request2, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('fr');
});

test('handles unsupported locale gracefully', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'de');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('de');
});

test('does not throw error when cookie is null', function () {
    $middleware = new LocaleMiddleware;

    $originalLocale = app()->getLocale();

    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect($response->getStatusCode())->toBe(200);
    expect(app()->getLocale())->toBe($originalLocale);
});

test('cookie value is case sensitive', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'FR');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('FR');
});

test('numeric locale value is accepted', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', '123');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('123');
});

test('special characters in locale are handled', function () {
    $middleware = new LocaleMiddleware;

    $request = Request::create('/test', 'GET');
    $request->cookies->set('locale', 'en-US');

    $middleware->handle($request, function ($req) {
        return response('Success');
    });

    expect(app()->getLocale())->toBe('en-US');
});
