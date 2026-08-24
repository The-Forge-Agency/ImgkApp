<?php

namespace App\Services;

use App\Support\ImgkException;
use App\Support\ImgkParams;
use Imagick;
use ZipArchive;

/**
 * pack=favicon : toutes les tailles d'icône d'un site, d'un coup, dans un zip.
 */
class FaviconPackGenerator
{
    public function __construct(private readonly ImageTransformer $transformer) {}

    /**
     * @return array{bytes: string, mime: string, ext: string}
     */
    public function generate(ImgkParams $p, string $sourceBytes, string $sourceMime): array
    {
        $base = $this->transformer->decode($p, $sourceBytes, $sourceMime);

        // Carré centré une fois pour toutes.
        $w = $base->getImageWidth();
        $h = $base->getImageHeight();
        $side = min($w, $h);
        $base->cropImage($side, $side, intdiv($w - $side, 2), intdiv($h - $side, 2));
        $base->setImagePage(0, 0, 0, 0);
        $base->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);

        $zipPath = tempnam(sys_get_temp_dir(), 'imgk-favicon-');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new ImgkException('Impossible de générer le zip du pack favicon.', 500);
        }

        foreach (config('imgk.favicon_sizes') as $size) {
            $frame = $base->getImage();
            $frame->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            $frame->setImageFormat('png');

            $name = match ($size) {
                180 => 'apple-touch-icon.png',
                192 => 'icon-192.png',
                512 => 'icon-512.png',
                default => "favicon-{$size}.png",
            };

            $zip->addFromString($name, $frame->getImageBlob());
            $frame->clear();
        }

        $ico = new Imagick;

        foreach ([16, 32, 48] as $size) {
            $frame = $base->getImage();
            $frame->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            $frame->setImageFormat('png');
            $ico->addImage($frame);
        }

        $ico->setFormat('ico');
        $zip->addFromString('favicon.ico', $ico->getImagesBlob());
        $ico->clear();
        $base->clear();

        $zip->addFromString('site.webmanifest', json_encode([
            'icons' => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->addFromString('snippet.html', implode("\n", [
            '<link rel="icon" href="/favicon.ico" sizes="48x48">',
            '<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">',
            '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
            '<link rel="manifest" href="/site.webmanifest">',
        ])."\n");

        $zip->close();

        $bytes = file_get_contents($zipPath);
        @unlink($zipPath);

        return ['bytes' => $bytes, 'mime' => 'application/zip', 'ext' => 'zip'];
    }
}
