<?php

use App\Services\ImageFetcher;
use Tests\Support\FakeFetcher;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Génère une image de test en mémoire (dégradé + coins colorés, repérable).
 */
function fixtureImage(int $width = 400, int $height = 300, string $format = 'png'): string
{
    $im = new Imagick;
    $im->newPseudoImage($width, $height, 'gradient:#5b8cff-#0d1017');
    $im->setImageColorspace(Imagick::COLORSPACE_SRGB);

    $draw = new ImagickDraw;
    $draw->setFillColor(new ImagickPixel('#ff0000'));
    $draw->rectangle(0, 0, (int) ($width / 4), (int) ($height / 4));
    $im->drawImage($draw);

    $im->setImageFormat($format === 'jpg' ? 'jpeg' : $format);

    return $im->getImageBlob();
}

/**
 * Image transparente avec un carré opaque au centre (tests bg/trim).
 */
function fixtureTransparentImage(int $width = 200, int $height = 200): string
{
    $im = new Imagick;
    $im->newImage($width, $height, new ImagickPixel('transparent'));

    $draw = new ImagickDraw;
    $draw->setFillColor(new ImagickPixel('#5b8cff'));
    $draw->rectangle((int) ($width / 4), (int) ($height / 4), (int) ($width * 3 / 4), (int) ($height * 3 / 4));
    $im->drawImage($draw);

    $im->setImageFormat('png');

    return $im->getImageBlob();
}

function fakeFetcher(array $urls): FakeFetcher
{
    $fake = new FakeFetcher($urls);
    app()->instance(ImageFetcher::class, $fake);

    return $fake;
}

function imagickFrom(string $bytes): Imagick
{
    $im = new Imagick;
    $im->readImageBlob($bytes);

    return $im;
}
