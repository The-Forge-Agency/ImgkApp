<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('un fichier déposé reçoit une URL publique stable', function () {
    $file = UploadedFile::fake()->createWithContent('photo.png', fixtureImage(300, 200));

    $response = test()->post('/api/upload', ['file' => $file])
        ->assertCreated()
        ->assertJsonStructure(['url', 'bytes', 'mime']);

    $url = $response->json('url');
    expect($url)->toMatch('#/u/[0-9A-Z]{26}\.png$#');

    test()->get(parse_url($url, PHP_URL_PATH))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('un upload peut ensuite être transformé via son adresse imgk', function () {
    $file = UploadedFile::fake()->createWithContent('photo.png', fixtureImage(300, 200));
    $url = test()->post('/api/upload', ['file' => $file])->json('url');

    // Fetcher réel : l'upload local est lu sur disque, sans requête HTTP.
    $response = test()->get('/?'.http_build_query(['url' => $url, 'w' => 100]))->assertOk();

    expect(imagickFrom($response->getContent())->getImageWidth())->toBe(100);
});

test('un fichier qui n\'est pas une image est refusé', function () {
    $file = UploadedFile::fake()->createWithContent('script.txt', 'coucou');

    test()->post('/api/upload', ['file' => $file])->assertStatus(415);
});

test('un upload manquant est refusé', function () {
    test()->post('/api/upload', [], ['Accept' => 'application/json'])->assertStatus(422);
});
