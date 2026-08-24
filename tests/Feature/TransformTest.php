<?php

use Illuminate\Support\Facades\Storage;

const SRC = 'https://exemple.test/photo.png';

beforeEach(function () {
    Storage::fake('local');
});

function transformRequest(array $params, array $headers = [])
{
    return test()->get('/?'.http_build_query(['url' => SRC, ...$params]), $headers);
}

test('redimensionner par la largeur garde le ratio', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $response = transformRequest(['w' => 200])->assertOk();

    $im = imagickFrom($response->getContent());
    expect($im->getImageWidth())->toBe(200)->and($im->getImageHeight())->toBe(150);
});

test('fit=cover remplit exactement le cadre demandé', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['w' => 100, 'h' => 100])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(100)->and($im->getImageHeight())->toBe(100);
});

test('fit=contain garde l\'image entière sur le canevas demandé', function () {
    fakeFetcher([SRC => fixtureImage(400, 200)]);

    $im = imagickFrom(transformRequest(['w' => 100, 'h' => 100, 'fit' => 'contain', 'bg' => '00ff00'])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(100)->and($im->getImageHeight())->toBe(100);

    // Bande verte en haut : l'image 2:1 est contenue, le fond remplit le reste.
    $pixel = $im->getImagePixelColor(50, 2)->getColor();
    expect($pixel['g'])->toBeGreaterThan(200)->and($pixel['r'])->toBeLessThan(60);
});

test('dpr multiplie les dimensions pour le Retina', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['w' => 100, 'dpr' => 2])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(200);
});

test('max plafonne la plus grande dimension', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['max' => 120])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(120)->and($im->getImageHeight())->toBe(90);
});

test('crop découpe la zone exacte', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['crop' => '10,20,150,100'])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(150)->and($im->getImageHeight())->toBe(100);
});

test('crop hors de l\'image est une erreur claire', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    transformRequest(['crop' => '500,500,100,100'])->assertStatus(400)->assertJsonStructure(['error']);
});

test('output convertit le format', function (string $output, string $mime) {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    transformRequest(['w' => 50, 'output' => $output])
        ->assertOk()
        ->assertHeader('Content-Type', $mime);
})->with([
    ['webp', 'image/webp'],
    ['jpg', 'image/jpeg'],
    ['png', 'image/png'],
    ['avif', 'image/avif'],
    ['gif', 'image/gif'],
    ['pdf', 'application/pdf'],
]);

test('output=auto sert avif ou webp selon l\'en-tête Accept, avec Vary', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    transformRequest(['w' => 50, 'output' => 'auto'], ['Accept' => 'image/avif,image/webp,*/*'])
        ->assertOk()
        ->assertHeader('Content-Type', 'image/avif')
        ->assertHeader('Vary', 'Accept');

    transformRequest(['w' => 50, 'output' => 'auto'], ['Accept' => 'image/webp,*/*'])
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    transformRequest(['w' => 50, 'output' => 'auto'], ['Accept' => 'image/png'])
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('sans output le format de la source est conservé', function () {
    fakeFetcher([SRC => fixtureImage(400, 300, 'jpg')]);

    transformRequest(['w' => 50])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

test('un habillage transparent sur une source jpg bascule en png', function () {
    fakeFetcher([SRC => fixtureImage(400, 300, 'jpg')]);

    transformRequest(['w' => 50, 'radius' => '12'])->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('la qualité influe sur le poids', function () {
    fakeFetcher([SRC => fixtureImage(800, 600)]);

    $small = strlen(transformRequest(['output' => 'jpg', 'q' => 10])->assertOk()->getContent());
    $big = strlen(transformRequest(['output' => 'jpg', 'q' => 95])->assertOk()->getContent());

    expect($small)->toBeLessThan($big);
});

test('maxsize impose le poids maximum', function () {
    fakeFetcher([SRC => fixtureImage(1200, 900)]);

    $response = transformRequest(['output' => 'jpg', 'maxsize' => '10kb'])->assertOk();

    expect(strlen($response->getContent()))->toBeLessThanOrEqual(10 * 1024);
});

test('strip retire les métadonnées embarquées', function () {
    $im = new Imagick;
    $im->newPseudoImage(300, 200, 'gradient:#ffffff-#000000');
    $im->setImageFormat('jpeg');
    $im->setImageProfile('icc', str_repeat('x', 2048));
    $withProfile = $im->getImageBlob();

    fakeFetcher([SRC => $withProfile]);

    $out = imagickFrom(transformRequest(['strip' => 'true', 'output' => 'jpg'])->assertOk()->getContent());

    expect($out->getImageProfiles('icc'))->toBe([]);
});

test('rotate=90 inverse largeur et hauteur', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['rotate' => 90])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(300)->and($im->getImageHeight())->toBe(400);
});

test('flip=h passe le repère rouge à droite', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['flip' => 'h'])->assertOk()->getContent());

    // Le carré rouge du fixture est en haut à gauche ; après miroir il est à droite.
    $pixel = $im->getImagePixelColor(390, 10)->getColor();
    expect($pixel['r'])->toBeGreaterThan(200)->and($pixel['g'])->toBeLessThan(60);
});

test('conversion=white-black produit des pixels gris', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $im = imagickFrom(transformRequest(['conversion' => 'white-black', 'output' => 'png'])->assertOk()->getContent());

    $pixel = $im->getImagePixelColor(10, 10)->getColor();
    expect(abs($pixel['r'] - $pixel['g']))->toBeLessThan(6)
        ->and(abs($pixel['g'] - $pixel['b']))->toBeLessThan(6);
});

test('les effets modifient l\'image sans changer ses dimensions', function (array $params) {
    fakeFetcher([SRC => fixtureImage(300, 200)]);

    $plain = transformRequest(['output' => 'png'])->assertOk()->getContent();
    $styled = transformRequest([...$params, 'output' => 'png'])->assertOk()->getContent();

    $im = imagickFrom($styled);
    expect($im->getImageWidth())->toBe(300)
        ->and($styled)->not->toBe($plain);
})->with([
    'sepia' => [['conversion' => 'sepia']],
    'invert' => [['conversion' => 'invert']],
    'duotone' => [['conversion' => 'duotone', 'duotone' => '0d1017,5b8cff']],
    'blur' => [['blur' => '30']],
    'sharpen' => [['sharpen' => '40']],
    'brightness' => [['brightness' => '30']],
    'contrast' => [['contrast' => '30']],
    'saturation' => [['saturation' => '-80']],
]);

test('conversion=scan nettoie un document : sortie grise et contrastée', function () {
    fakeFetcher([SRC => fixtureImage(300, 200)]);

    $plain = transformRequest(['output' => 'png'])->assertOk()->getContent();
    $scanned = transformRequest(['conversion' => 'scan', 'output' => 'png'])->assertOk()->getContent();

    expect($scanned)->not->toBe($plain);

    // Le deskew peut ajuster légèrement les dimensions, mais la sortie reste une image valide grise.
    $im = imagickFrom($scanned);
    $pixel = $im->getImagePixelColor(intdiv($im->getImageWidth(), 2), intdiv($im->getImageHeight(), 2))->getColor();
    expect(abs($pixel['r'] - $pixel['b']))->toBeLessThan(6);
});

test('pad ajoute une marge autour', function () {
    fakeFetcher([SRC => fixtureImage(100, 100)]);

    $im = imagickFrom(transformRequest(['pad' => 20, 'bg' => 'ff0000', 'output' => 'png'])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(140)->and($im->getImageHeight())->toBe(140);
});

test('bg remplace la transparence par une couleur', function () {
    fakeFetcher([SRC => fixtureTransparentImage(200, 200)]);

    $im = imagickFrom(transformRequest(['bg' => 'ff0000', 'output' => 'png'])->assertOk()->getContent());

    $corner = $im->getImagePixelColor(2, 2)->getColor();
    expect($corner['r'])->toBeGreaterThan(200);
});

test('trim rogne les bords vides', function () {
    fakeFetcher([SRC => fixtureTransparentImage(200, 200)]);

    $im = imagickFrom(transformRequest(['trim' => 'true', 'output' => 'png'])->assertOk()->getContent());

    expect($im->getImageWidth())->toBeLessThan(120)->and($im->getImageHeight())->toBeLessThan(120);
});

test('radius=max produit un cercle : coins transparents, centre opaque', function () {
    fakeFetcher([SRC => fixtureImage(300, 200)]);

    $im = imagickFrom(transformRequest(['radius' => 'max', 'output' => 'png'])->assertOk()->getContent());

    expect($im->getImageWidth())->toBe(200)->and($im->getImageHeight())->toBe(200);

    expect($im->getImagePixelColor(2, 2)->getColor(1)['a'])->toBeLessThan(0.1)
        ->and($im->getImagePixelColor(100, 100)->getColor(1)['a'])->toBeGreaterThan(0.9);
});

test('border dessine un contour de la couleur demandée', function () {
    fakeFetcher([SRC => fixtureImage(200, 200)]);

    $im = imagickFrom(transformRequest(['border' => '10,00ff00', 'output' => 'png'])->assertOk()->getContent());

    $edge = $im->getImagePixelColor(100, 3)->getColor();
    expect($edge['g'])->toBeGreaterThan(200)->and($edge['r'])->toBeLessThan(60);
});

test('watermark incruste un logo, récupéré via le même garde-fou', function () {
    $wmUrl = 'https://exemple.test/logo.png';
    fakeFetcher([SRC => fixtureImage(400, 300), $wmUrl => fixtureImage(100, 100)]);

    $plain = transformRequest(['output' => 'png'])->assertOk()->getContent();
    $marked = transformRequest(['watermark' => $wmUrl, 'wm_opacity' => 80, 'output' => 'png'])->assertOk()->getContent();

    expect($marked)->not->toBe($plain);
});

test('text incruste le texte demandé', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $plain = transformRequest(['output' => 'png'])->assertOk()->getContent();
    $texted = transformRequest(['text' => '© imgk 2026', 'text_size' => 40, 'output' => 'png'])->assertOk()->getContent();

    expect($texted)->not->toBe($plain)
        ->and(imagickFrom($texted)->getImageWidth())->toBe(400);
});

test('output=ico produit un favicon multi-tailles', function () {
    fakeFetcher([SRC => fixtureImage(300, 300)]);

    $response = transformRequest(['output' => 'ico'])->assertOk()->assertHeader('Content-Type', 'image/x-icon');

    expect(substr($response->getContent(), 0, 4))->toBe("\x00\x00\x01\x00");
});

test('pack=favicon renvoie un zip complet', function () {
    fakeFetcher([SRC => fixtureImage(600, 600)]);

    $response = transformRequest(['pack' => 'favicon'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    $tmp = tempnam(sys_get_temp_dir(), 'imgk-test-');
    file_put_contents($tmp, $response->getContent());

    $zip = new ZipArchive;
    $zip->open($tmp);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));

    expect($names)->toContain('favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'icon-512.png', 'site.webmanifest', 'snippet.html');

    $zip->close();
    @unlink($tmp);
});

test('preset=avatar sort un carré webp aux coins ronds', function () {
    fakeFetcher([SRC => fixtureImage(600, 400)]);

    $response = transformRequest(['preset' => 'avatar'])->assertOk()->assertHeader('Content-Type', 'image/webp');

    $im = imagickFrom($response->getContent());
    expect($im->getImageWidth())->toBe(256)->and($im->getImageHeight())->toBe(256);
});

test('la même adresse est servie depuis le cache, quel que soit l\'ordre des paramètres', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    transformRequest(['w' => 100, 'output' => 'webp'])->assertOk()->assertHeader('X-Imgk-Cache', 'MISS');

    test()->get('/?output=webp&w=100&url='.urlencode(SRC))
        ->assertOk()
        ->assertHeader('X-Imgk-Cache', 'HIT');
});

test('la réponse porte un cache long, un ETag et répond 304', function () {
    fakeFetcher([SRC => fixtureImage(400, 300)]);

    $response = transformRequest(['w' => 100])->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $etag = $response->headers->get('ETag');

    transformRequest(['w' => 100], ['If-None-Match' => $etag])->assertStatus(304);
});

test('page= extrait une page d\'un PDF', function () {
    $pdf = new Imagick;
    $pdf->newImage(200, 280, new ImagickPixel('#ffffff'));
    $pdf->newImage(200, 280, new ImagickPixel('#ff0000'));
    $pdf->setFormat('pdf');
    fakeFetcher([SRC => $pdf->getImagesBlob()]);

    $im = imagickFrom(transformRequest(['page' => 2, 'output' => 'png'])->assertOk()->getContent());

    $pixel = $im->getImagePixelColor(100, 100)->getColor();
    expect($pixel['r'])->toBeGreaterThan(200)->and($pixel['g'])->toBeLessThan(60);
});

test('frame= extrait une image d\'un GIF animé', function () {
    $gif = new Imagick;

    foreach (['#0000ff', '#ff0000'] as $color) {
        $frame = new Imagick;
        $frame->newImage(100, 100, new ImagickPixel($color));
        $frame->setImageFormat('gif');
        $frame->setImageDelay(10);
        $gif->addImage($frame);
    }

    $gif->setFormat('gif');
    fakeFetcher([SRC => $gif->getImagesBlob()]);

    $im = imagickFrom(transformRequest(['frame' => 1, 'output' => 'png'])->assertOk()->getContent());

    $pixel = $im->getImagePixelColor(50, 50)->getColor();
    expect($pixel['r'])->toBeGreaterThan(200)->and($pixel['b'])->toBeLessThan(60);
});

test('sans url ni preset, la racine sert la landing', function () {
    test()->get('/')->assertOk()->assertSee('imgk');
});

test('une url invalide est une erreur JSON propre', function () {
    test()->get('/?url=pouet')->assertStatus(400)->assertJsonStructure(['error']);
});

test('une source interne est bloquée par la garde SSRF', function () {
    // Fetcher réel : la garde refuse avant toute requête réseau.
    test()->get('/?url='.urlencode('http://169.254.169.254/latest/meta-data/'))
        ->assertStatus(403)
        ->assertJsonStructure(['error']);
});

test('un contenu qui n\'est pas une image est refusé', function () {
    fakeFetcher([SRC => '<html>pas une image</html>']);

    transformRequest(['w' => 100])->assertStatus(415);
});
