<?php

namespace App\Services;

use App\Support\ImgkException;
use App\Support\ImgkParams;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;

/**
 * Pipeline de transformation déterministe : les mêmes paramètres produisent
 * toujours exactement la même sortie. L'ordre des étapes est fixe :
 * décodage → orientation → crop → trim → resize → effets → rotation/miroir
 * → habillage (radius, border, bg, pad) → incrustations → encodage.
 */
class ImageTransformer
{
    public function __construct(private readonly ImageFetcher $fetcher) {}

    /**
     * @return array{bytes: string, mime: string, ext: string}
     */
    public function transform(ImgkParams $p, string $sourceBytes, string $sourceMime, string $output): array
    {
        $im = $this->decode($p, $sourceBytes, $sourceMime);

        try {
            $this->autoOrient($im);
            $this->applyCrop($im, $p);
            $this->applyTrim($im, $p);
            $this->applyResize($im, $p);
            $this->applyEffects($im, $p);
            $this->applyRotateFlip($im, $p);
            $this->applyDressing($im, $p, $output);
            $this->applyWatermark($im, $p);
            $this->applyText($im, $p);

            return $this->encode($im, $p, $output);
        } finally {
            $im->clear();
        }
    }

    public function decode(ImgkParams $p, string $bytes, string $mime): Imagick
    {
        $im = new Imagick;

        try {
            // Ping d'abord : lit uniquement l'en-tête, et rejette les
            // décompressions-bombes avant tout décodage complet.
            if ($mime !== 'application/pdf') {
                $ping = new Imagick;
                $ping->pingImageBlob($bytes);

                $tooBig = $ping->getImageWidth() * $ping->getImageHeight() > (int) config('imgk.max_source_pixels');
                $ping->clear();

                if ($tooBig) {
                    throw new ImgkException('Image source trop grande en pixels (50 mégapixels max).', 413);
                }
            } else {
                // Résolution fixée avant décodage pour rasteriser proprement la page.
                $im->setResolution(150, 150);
            }

            $im->readImageBlob($bytes);
        } catch (ImagickException) {
            throw new ImgkException('Impossible de décoder cette image.', 422);
        }

        $frames = $im->getNumberImages();

        if ($mime === 'application/pdf') {
            $page = $p->page ?? 1;

            if ($page > $frames) {
                throw new ImgkException("Ce PDF n'a que {$frames} page(s), page={$page} n'existe pas.", 422);
            }

            $im->setIteratorIndex($page - 1);
        } elseif ($frames > 1) {
            $frame = min($p->frame ?? 0, $frames - 1);
            $im->setIteratorIndex($frame);
        }

        $single = $im->getImage();
        $im->clear();

        if ($single->getImageWidth() * $single->getImageHeight() > (int) config('imgk.max_source_pixels')) {
            $single->clear();

            throw new ImgkException('Image source trop grande en pixels (50 mégapixels max).', 413);
        }

        if ($mime === 'application/pdf') {
            // Fond blanc : une page PDF rasterisée arrive sur fond transparent.
            $single = $this->flattenOn($single, 'ffffff');
        }

        $single->setImageColorspace(Imagick::COLORSPACE_SRGB);

        return $single;
    }

    private function autoOrient(Imagick $im): void
    {
        try {
            $orientation = $im->getImageOrientation();
        } catch (ImagickException) {
            return;
        }

        match ($orientation) {
            Imagick::ORIENTATION_TOPRIGHT => $im->flopImage(),
            Imagick::ORIENTATION_BOTTOMRIGHT => $im->rotateImage(new ImagickPixel('none'), 180),
            Imagick::ORIENTATION_BOTTOMLEFT => $im->flipImage(),
            Imagick::ORIENTATION_LEFTTOP => [$im->flopImage(), $im->rotateImage(new ImagickPixel('none'), 270)],
            Imagick::ORIENTATION_RIGHTTOP => $im->rotateImage(new ImagickPixel('none'), 90),
            Imagick::ORIENTATION_RIGHTBOTTOM => [$im->flopImage(), $im->rotateImage(new ImagickPixel('none'), 90)],
            Imagick::ORIENTATION_LEFTBOTTOM => $im->rotateImage(new ImagickPixel('none'), 270),
            default => null,
        };

        $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    private function applyCrop(Imagick $im, ImgkParams $p): void
    {
        if ($p->crop === null) {
            return;
        }

        [$x, $y, $w, $h] = $p->crop;
        $iw = $im->getImageWidth();
        $ih = $im->getImageHeight();

        if ($x >= $iw || $y >= $ih) {
            throw new ImgkException("crop : le point ({$x},{$y}) est hors de l'image ({$iw}×{$ih}).");
        }

        $im->cropImage(min($w, $iw - $x), min($h, $ih - $y), $x, $y);
        $im->setImagePage(0, 0, 0, 0);
    }

    private function applyTrim(Imagick $im, ImgkParams $p): void
    {
        if (! $p->trim) {
            return;
        }

        $im->trimImage(0.05 * Imagick::getQuantum());
        $im->setImagePage(0, 0, 0, 0);
    }

    private function applyResize(Imagick $im, ImgkParams $p): void
    {
        $iw = $im->getImageWidth();
        $ih = $im->getImageHeight();
        $maxDim = (int) config('imgk.max_output_dimension');

        $targetW = $p->w !== null ? (int) round($p->w * $p->dpr) : null;
        $targetH = $p->h !== null ? (int) round($p->h * $p->dpr) : null;

        if ($targetW === null && $targetH === null) {
            $cap = min($p->max ?? $maxDim, $maxDim);

            if (max($iw, $ih) > $cap) {
                $ratio = $cap / max($iw, $ih);
                $im->resizeImage((int) round($iw * $ratio), (int) round($ih * $ratio), Imagick::FILTER_LANCZOS, 1);
            }

            return;
        }

        $targetW = $targetW !== null ? min($targetW, $maxDim) : null;
        $targetH = $targetH !== null ? min($targetH, $maxDim) : null;

        if ($targetW !== null && $targetH !== null) {
            if ($p->fit === 'contain') {
                $ratio = min($targetW / $iw, $targetH / $ih);
                $im->resizeImage(max(1, (int) round($iw * $ratio)), max(1, (int) round($ih * $ratio)), Imagick::FILTER_LANCZOS, 1);
                $this->extendCanvas($im, $targetW, $targetH, $p->bg);
            } else {
                // cover : remplir le cadre puis recadrer au centre.
                $ratio = max($targetW / $iw, $targetH / $ih);
                $im->resizeImage(max(1, (int) round($iw * $ratio)), max(1, (int) round($ih * $ratio)), Imagick::FILTER_LANCZOS, 1);
                $im->cropImage(
                    $targetW,
                    $targetH,
                    intdiv($im->getImageWidth() - $targetW, 2),
                    intdiv($im->getImageHeight() - $targetH, 2),
                );
                $im->setImagePage(0, 0, 0, 0);
            }
        } elseif ($targetW !== null) {
            $im->resizeImage($targetW, max(1, (int) round($ih * $targetW / $iw)), Imagick::FILTER_LANCZOS, 1);
        } else {
            $im->resizeImage(max(1, (int) round($iw * $targetH / $ih)), $targetH, Imagick::FILTER_LANCZOS, 1);
        }

        if ($p->max !== null && max($im->getImageWidth(), $im->getImageHeight()) > $p->max) {
            $ratio = $p->max / max($im->getImageWidth(), $im->getImageHeight());
            $im->resizeImage(
                max(1, (int) round($im->getImageWidth() * $ratio)),
                max(1, (int) round($im->getImageHeight() * $ratio)),
                Imagick::FILTER_LANCZOS,
                1,
            );
        }
    }

    private function extendCanvas(Imagick $im, int $w, int $h, ?string $bg): void
    {
        $canvas = new Imagick;
        $canvas->newImage($w, $h, new ImagickPixel($bg !== null ? "#{$bg}" : 'transparent'));
        $canvas->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $canvas->compositeImage(
            $im,
            Imagick::COMPOSITE_OVER,
            intdiv($w - $im->getImageWidth(), 2),
            intdiv($h - $im->getImageHeight(), 2),
        );
        $this->swap($im, $canvas);
    }

    private function applyEffects(Imagick $im, ImgkParams $p): void
    {
        match ($p->conversion) {
            'white-black' => $im->transformImageColorspace(Imagick::COLORSPACE_GRAY),
            'sepia' => $im->sepiaToneImage(0.8 * Imagick::getQuantum()),
            'invert' => $im->negateImage(false),
            'duotone' => $this->applyDuotone($im, $p->duotone ?? ['0d1017', '5b8cff']),
            'scan' => $this->applyScan($im),
            default => null,
        };

        if ($p->brightness !== 0 || $p->contrast !== 0) {
            $im->brightnessContrastImage($p->brightness, $p->contrast);
        }

        if ($p->saturation !== 0) {
            $im->modulateImage(100, 100 + $p->saturation, 100);
        }

        if ($p->blur > 0) {
            $im->blurImage(0, $p->blur / 4);
        }

        if ($p->sharpen > 0) {
            $im->unsharpMaskImage(0, 1, $p->sharpen / 20, 0.02);
        }
    }

    /**
     * @param  array{0:string,1:string}  $colors
     */
    private function applyDuotone(Imagick $im, array $colors): void
    {
        [$dark, $light] = $colors;

        $im->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);

        $gradient = new Imagick;
        $gradient->newPseudoImage(1, 256, "gradient:#{$light}-#{$dark}");
        $gradient->rotateImage(new ImagickPixel('transparent'), 90);

        $im->clutImage($gradient);
        $gradient->clear();
    }

    private function applyScan(Imagick $im): void
    {
        // Mode scan : redresse le document, passe en gris, nettoie le fond
        // et pousse le contraste pour un rendu imprimable.
        try {
            $im->deskewImage(0.4 * Imagick::getQuantum());
            $im->setImagePage(0, 0, 0, 0);
        } catch (ImagickException) {
            // Deskew impossible sur certaines sources : on continue sans.
        }

        $im->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $im->normalizeImage();
        $im->brightnessContrastImage(8, 35);
        $im->whiteThresholdImage(new ImagickPixel('#e8e8e8'));
        $im->unsharpMaskImage(0, 1, 1.2, 0.02);
    }

    private function applyRotateFlip(Imagick $im, ImgkParams $p): void
    {
        if (str_contains($p->flip ?? '', 'h')) {
            $im->flopImage();
        }

        if (str_contains($p->flip ?? '', 'v')) {
            $im->flipImage();
        }

        if ($p->rotate !== 0) {
            $bg = $p->bg !== null ? "#{$p->bg}" : 'transparent';
            $im->rotateImage(new ImagickPixel($bg), $p->rotate);
            $im->setImagePage(0, 0, 0, 0);
        }
    }

    private function applyDressing(Imagick $im, ImgkParams $p, string $output): void
    {
        if ($p->radius !== null) {
            $this->applyRadius($im, $p);
        }

        if ($p->border !== null) {
            $this->applyBorder($im, $p);
        }

        // bg remplace la transparence par une couleur pleine.
        if ($p->bg !== null && $p->fit !== 'contain' && $p->rotate === 0) {
            $this->swap($im, $this->flattenOn($im->getImage(), $p->bg));
        }

        if ($p->pad > 0) {
            $w = $im->getImageWidth() + 2 * $p->pad;
            $h = $im->getImageHeight() + 2 * $p->pad;
            $this->extendCanvas($im, $w, $h, $p->bg);
        }

        // Les formats sans alpha reçoivent un fond blanc implicite.
        if (in_array($output, ['jpg', 'pdf'], true) && $p->bg === null) {
            $this->swap($im, $this->flattenOn($im->getImage(), 'ffffff'));
        }
    }

    private function applyRadius(Imagick $im, ImgkParams $p): void
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $radius = $p->radius === 'max'
            ? min($w, $h) / 2
            : min((int) $p->radius, min($w, $h) / 2);

        if ($radius <= 0) {
            return;
        }

        if ($p->radius === 'max' && $w !== $h) {
            // Cercle parfait : recadrer d'abord au carré centré.
            $side = min($w, $h);
            $im->cropImage($side, $side, intdiv($w - $side, 2), intdiv($h - $side, 2));
            $im->setImagePage(0, 0, 0, 0);
            $w = $h = $side;
            $radius = $side / 2;
        }

        $mask = new Imagick;
        $mask->newImage($w, $h, new ImagickPixel('transparent'));

        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->roundRectangle(0, 0, $w - 1, $h - 1, $radius, $radius);
        $mask->drawImage($draw);

        $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $im->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $mask->clear();
    }

    private function applyBorder(Imagick $im, ImgkParams $p): void
    {
        [$width, $color] = $p->border;
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $radius = match (true) {
            $p->radius === 'max' => min($w, $h) / 2,
            $p->radius !== null => min((int) $p->radius, min($w, $h) / 2),
            default => 0,
        };

        // Contour dessiné DANS l'image, qui épouse les coins arrondis.
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('none'));
        $draw->setStrokeColor(new ImagickPixel("#{$color}"));
        $draw->setStrokeOpacity(1);
        $draw->setStrokeWidth($width);
        $inset = $width / 2;
        $strokeRadius = max(0, $radius - $inset);

        // roundRectangle avec un rayon nul ne dessine rien : rectangle simple dans ce cas.
        if ($strokeRadius > 0) {
            $draw->roundRectangle($inset, $inset, $w - 1 - $inset, $h - 1 - $inset, $strokeRadius, $strokeRadius);
        } else {
            $draw->rectangle($inset, $inset, $w - 1 - $inset, $h - 1 - $inset);
        }

        $im->drawImage($draw);
    }

    private function applyWatermark(Imagick $im, ImgkParams $p): void
    {
        if ($p->watermark === null) {
            return;
        }

        $fetched = $this->fetcher->fetch($p->watermark);

        $wm = new Imagick;

        try {
            $wm->readImageBlob($fetched['bytes']);
        } catch (ImagickException) {
            throw new ImgkException('Impossible de décoder l\'image de filigrane (watermark).', 422);
        }

        $wm = $wm->coalesceImages()->getImage();
        $wm->setImageColorspace(Imagick::COLORSPACE_SRGB);

        $targetW = max(1, (int) round($im->getImageWidth() * $p->wmSize / 100));
        $wm->resizeImage($targetW, max(1, (int) round($wm->getImageHeight() * $targetW / $wm->getImageWidth())), Imagick::FILTER_LANCZOS, 1);

        $wm->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $wm->evaluateImage(Imagick::EVALUATE_MULTIPLY, $p->wmOpacity / 100, Imagick::CHANNEL_ALPHA);

        [$x, $y] = $this->position($p->wmPos, $im, $wm->getImageWidth(), $wm->getImageHeight());
        $im->compositeImage($wm, Imagick::COMPOSITE_OVER, $x, $y);
        $wm->clear();
    }

    private function applyText(Imagick $im, ImgkParams $p): void
    {
        if ($p->text === null) {
            return;
        }

        $draw = new ImagickDraw;
        $draw->setFont(resource_path('fonts/SpaceGrotesk.ttf'));
        $draw->setFontSize($p->textSize);
        $draw->setFillColor(new ImagickPixel("#{$p->textColor}"));
        $draw->setStrokeColor(new ImagickPixel('rgba(0,0,0,0.35)'));
        $draw->setStrokeWidth(max(1, $p->textSize / 30));
        $draw->setTextAntialias(true);

        $metrics = $im->queryFontMetrics($draw, $p->text);
        [$x, $y] = $this->position($p->textPos, $im, (int) ceil($metrics['textWidth']), (int) ceil($metrics['textHeight']));

        $im->annotateImage($draw, $x, $y + $metrics['ascender'], 0, $p->text);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function position(string $pos, Imagick $im, int $w, int $h): array
    {
        $iw = $im->getImageWidth();
        $ih = $im->getImageHeight();
        $margin = max(8, (int) round($iw * 0.03));

        return match ($pos) {
            'tl' => [$margin, $margin],
            'tr' => [$iw - $w - $margin, $margin],
            'bl' => [$margin, $ih - $h - $margin],
            'center' => [intdiv($iw - $w, 2), intdiv($ih - $h, 2)],
            default => [$iw - $w - $margin, $ih - $h - $margin],
        };
    }

    /**
     * @return array{bytes: string, mime: string, ext: string}
     */
    private function encode(Imagick $im, ImgkParams $p, string $output): array
    {
        if ($output === 'ico') {
            return $this->encodeIco($im, $p);
        }

        $quality = $p->q ?? match ($output) {
            'avif' => 60,
            'webp' => 80,
            default => 82,
        };

        if ($p->strip || $p->optimized) {
            $im->stripImage();
        }

        $format = $output === 'jpg' ? 'jpeg' : $output;
        $im->setImageFormat($format);

        if ($output === 'jpg') {
            $im->setImageCompression(Imagick::COMPRESSION_JPEG);
            $im->setInterlaceScheme(Imagick::INTERLACE_PLANE);

            if ($p->optimized) {
                $im->setSamplingFactors(['2x2', '1x1', '1x1']);
            }
        }

        if ($output === 'webp' && $p->optimized) {
            $im->setOption('webp:method', '6');
        }

        if ($output === 'png' && $p->optimized) {
            $im->setOption('png:compression-level', '9');
        }

        if (in_array($output, ['jpg', 'webp', 'avif'], true)) {
            $im->setImageCompressionQuality($quality);
        }

        $bytes = $p->maxsizeBytes !== null
            ? $this->encodeUnderMaxsize($im, $p, $output, $quality)
            : $im->getImageBlob();

        return ['bytes' => $bytes, 'mime' => $this->mimeFor($output), 'ext' => $output];
    }

    private function encodeUnderMaxsize(Imagick $im, ImgkParams $p, string $output, int $startQuality): string
    {
        $target = $p->maxsizeBytes;

        // Les formats sans réglage de qualité passent par une réduction de taille.
        $qualityDriven = in_array($output, ['jpg', 'webp', 'avif'], true);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            if ($qualityDriven) {
                $lo = 5;
                $hi = $startQuality;
                $best = null;

                while ($lo <= $hi) {
                    $mid = intdiv($lo + $hi, 2);
                    $im->setImageCompressionQuality($mid);
                    $blob = $im->getImageBlob();

                    if (strlen($blob) <= $target) {
                        $best = $blob;
                        $lo = $mid + 1;
                    } else {
                        $hi = $mid - 1;
                    }
                }

                if ($best !== null) {
                    return $best;
                }
            } else {
                $blob = $im->getImageBlob();

                if (strlen($blob) <= $target) {
                    return $blob;
                }
            }

            // Toujours trop lourd même en qualité minimale : on réduit les dimensions.
            $im->resizeImage(
                max(1, (int) round($im->getImageWidth() * 0.75)),
                max(1, (int) round($im->getImageHeight() * 0.75)),
                Imagick::FILTER_LANCZOS,
                1,
            );
        }

        throw new ImgkException('Impossible de tenir sous ce poids : essaie un maxsize plus grand ou un autre format.', 422);
    }

    /**
     * @return array{bytes: string, mime: string, ext: string}
     */
    private function encodeIco(Imagick $im, ImgkParams $p): array
    {
        $sizes = $p->w !== null ? [min($p->w, 256)] : [16, 32, 48];

        $ico = new Imagick;

        foreach ($sizes as $size) {
            $frame = $im->getImage();
            $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);

            // Carré centré puis mise à l'échelle.
            $w = $frame->getImageWidth();
            $h = $frame->getImageHeight();
            $side = min($w, $h);
            $frame->cropImage($side, $side, intdiv($w - $side, 2), intdiv($h - $side, 2));
            $frame->setImagePage(0, 0, 0, 0);
            $frame->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            $frame->setImageFormat('png');

            $ico->addImage($frame);
        }

        $ico->setFormat('ico');
        $bytes = $ico->getImagesBlob();
        $ico->clear();

        return ['bytes' => $bytes, 'mime' => 'image/x-icon', 'ext' => 'ico'];
    }

    private function flattenOn(Imagick $im, string $hex): Imagick
    {
        $canvas = new Imagick;
        $canvas->newImage($im->getImageWidth(), $im->getImageHeight(), new ImagickPixel("#{$hex}"));
        $canvas->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $canvas->compositeImage($im, Imagick::COMPOSITE_OVER, 0, 0);
        $im->clear();

        return $canvas;
    }

    private function swap(Imagick $im, Imagick $replacement): void
    {
        $im->setImage($replacement);
    }

    private function mimeFor(string $output): string
    {
        return match ($output) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }
}
