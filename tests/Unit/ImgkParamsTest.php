<?php

use App\Support\ImgkException;
use App\Support\ImgkParams;

test('l\'ordre des paramètres ne change pas la clé de cache', function () {
    $a = ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'w' => '500', 'output' => 'webp', 'q' => '80']);
    $b = ImgkParams::fromQuery(['q' => '80', 'output' => 'webp', 'url' => 'https://x.test/a.png', 'w' => '500']);

    expect($a->cacheKey('webp'))->toBe($b->cacheKey('webp'));
});

test('les valeurs par défaut sont absentes de la forme canonique', function () {
    $p = ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'blur' => '0', 'dpr' => '1', 'rotate' => '360']);

    expect($p->canonical())->toBe(['url' => 'https://x.test/a.png']);
});

test('fit=cover est déduit quand largeur et hauteur sont données', function () {
    $p = ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'w' => '100', 'h' => '100']);

    expect($p->fit)->toBe('cover');
});

test('fit sans les deux dimensions est refusé', function () {
    ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'w' => '100', 'fit' => 'cover']);
})->throws(ImgkException::class);

test('url manquante est refusée', function () {
    ImgkParams::fromQuery(['w' => '100']);
})->throws(ImgkException::class, 'url=');

test('output inconnu est refusé, jpeg est normalisé en jpg', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'output' => 'JPEG'])->output)->toBe('jpg');

    expect(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'output' => 'tiff']))
        ->toThrow(ImgkException::class);
});

test('maxsize comprend kb, mb et les variantes françaises', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'maxsize' => '200kb'])->maxsizeBytes)->toBe(200 * 1024)
        ->and(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'maxsize' => '2mb'])->maxsizeBytes)->toBe(2 * 1024 * 1024)
        ->and(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'maxsize' => '2Mo'])->maxsizeBytes)->toBe(2 * 1024 * 1024);

    expect(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'maxsize' => 'grand']))
        ->toThrow(ImgkException::class);
});

test('rotate est normalisé entre 0 et 359', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'rotate' => '450'])->rotate)->toBe(90)
        ->and(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'rotate' => '-90'])->rotate)->toBe(270);
});

test('crop attend quatre entiers', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'crop' => '10,20,300,200'])->crop)->toBe([10, 20, 300, 200]);

    expect(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'crop' => '10,20']))
        ->toThrow(ImgkException::class);
});

test('les couleurs hex sont normalisées, formes courtes comprises', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'bg' => '#5B8CFF'])->bg)->toBe('5b8cff')
        ->and(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'bg' => 'f0a'])->bg)->toBe('ff00aa');

    expect(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'bg' => 'rouge']))
        ->toThrow(ImgkException::class);
});

test('conversion accepte les alias noir et blanc', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'conversion' => 'grayscale'])->conversion)->toBe('white-black');
});

test('un preset applique sa recette mais les paramètres explicites gagnent', function () {
    $p = ImgkParams::fromQuery(['url' => 'https://x.test/a.png', 'preset' => 'avatar', 'w' => '128']);

    expect($p->w)->toBe(128)
        ->and($p->h)->toBe(256)
        ->and($p->radius)->toBe('max')
        ->and($p->output)->toBe('webp');
});

test('un preset inconnu est refusé avec la liste des presets', function () {
    ImgkParams::fromQuery(['url' => 'https://x.test/a', 'preset' => 'licorne']);
})->throws(ImgkException::class, 'avatar');

test('radius accepte un rayon ou max', function () {
    expect(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'radius' => '24'])->radius)->toBe('24')
        ->and(ImgkParams::fromQuery(['url' => 'https://x.test/a', 'radius' => 'MAX'])->radius)->toBe('max');
});

test('les bornes numériques sont appliquées', function () {
    expect(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'w' => '0']))->toThrow(ImgkException::class)
        ->and(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'w' => '99999']))->toThrow(ImgkException::class)
        ->and(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'q' => '101']))->toThrow(ImgkException::class)
        ->and(fn () => ImgkParams::fromQuery(['url' => 'https://x.test/a', 'dpr' => '4']))->toThrow(ImgkException::class);
});
