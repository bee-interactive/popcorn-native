<?php

use App\Helpers\Popcorn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.api.url' => 'https://api.test/']);
    session(['app-access-token' => 'test-token']);

    Storage::fake('local');
});

test('uploads valid image file successfully', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('avatar.jpg', 100, 100)->size(500); // 500KB

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});

test('rejects file larger than 5MB', function () {
    $file = UploadedFile::fake()->image('huge.jpg')->size(6000); // 6MB

    expect(fn () => Popcorn::postWithFile('/users/avatar', 'avatar', $file))
        ->toThrow(\InvalidArgumentException::class, 'exceeds maximum allowed size');
});

test('rejects non-image file types', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100);

    expect(fn () => Popcorn::postWithFile('/users/avatar', 'avatar', $file))
        ->toThrow(\InvalidArgumentException::class, 'is not allowed');
});

test('accepts jpeg images', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('photo.jpeg')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});

test('accepts png images', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('photo.png')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});

test('accepts gif images', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('animation.gif')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});

test('accepts webp images', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('photo.webp')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});

test('rejects executable files', function () {
    $file = UploadedFile::fake()->create('malware.exe', 100);

    expect(fn () => Popcorn::postWithFile('/users/avatar', 'avatar', $file))
        ->toThrow(\InvalidArgumentException::class);
});

test('rejects files with wrong extension', function () {
    $file = UploadedFile::fake()->create('photo.txt', 100, 'text/plain');

    expect(fn () => Popcorn::postWithFile('/users/avatar', 'avatar', $file))
        ->toThrow(\InvalidArgumentException::class);
});

test('file size exactly at 5MB limit is allowed', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('large.jpg')->size(5120); // Exactly 5MB

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});

test('file size just over 5MB limit is rejected', function () {
    $file = UploadedFile::fake()->image('toolarge.jpg')->size(5121); // 5MB + 1KB

    expect(fn () => Popcorn::postWithFile('/users/avatar', 'avatar', $file))
        ->toThrow(\InvalidArgumentException::class, 'exceeds maximum');
});

test('validates file path string correctly', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    // Créer un fichier temporaire valide
    $tempPath = tempnam(sys_get_temp_dir(), 'test');
    $newPath = $tempPath.'.png';

    // Écrire une petite image (1x1 pixel PNG)
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    file_put_contents($newPath, $pngData);

    try {
        $result = Popcorn::postWithFile('/users/avatar', 'avatar', $newPath);

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($result->get('success'))->toBeTrue();
    } finally {
        if (file_exists($newPath)) {
            unlink($newPath);
        }
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
});

test('rejects string path to non-existent file', function () {
    expect(fn () => Popcorn::postWithFile('/users/avatar', 'avatar', '/non/existent/file.jpg'))
        ->toThrow(\Exception::class);
});

test('sends file with correct field name', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('avatar.jpg')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'profile_picture', $file);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'users/avatar');
    });
});

test('includes extra parameters in upload request', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('avatar.jpg')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file, null, [
        'crop' => 'square',
        'quality' => 80,
    ]);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'users/avatar');
    });
});

test('uses custom filename when provided', function () {
    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $file = UploadedFile::fake()->image('original.jpg')->size(500);

    $result = Popcorn::postWithFile('/users/avatar', 'avatar', $file, 'custom-name.jpg');

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->get('success'))->toBeTrue();
});
