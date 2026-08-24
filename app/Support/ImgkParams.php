<?php

namespace App\Support;

/**
 * Parse, valide et canonise les réglages d'une adresse imgk.
 *
 * L'ordre des paramètres n'a pas d'importance : la forme canonique (clés triées,
 * valeurs normalisées, défauts retirés) sert de clé de cache, donc la même
 * recette produit toujours exactement le même résultat.
 */
class ImgkParams
{
    public const OUTPUTS = ['auto', 'png', 'jpg', 'webp', 'avif', 'gif', 'ico', 'pdf'];

    public const CONVERSIONS = ['white-black', 'sepia', 'invert', 'duotone', 'scan'];

    public const POSITIONS = ['tl', 'tr', 'bl', 'br', 'center'];

    public ?string $url = null;

    public ?int $w = null;

    public ?int $h = null;

    public ?string $fit = null;            // cover|contain

    public float $dpr = 1.0;

    public ?int $max = null;

    /** @var array{0:int,1:int,2:int,3:int}|null */
    public ?array $crop = null;

    public ?string $output = null;

    public ?int $q = null;

    public bool $optimized = false;

    public ?int $maxsizeBytes = null;

    public bool $strip = false;

    public ?string $conversion = null;

    /** @var array{0:string,1:string}|null */
    public ?array $duotone = null;

    public int $blur = 0;

    public int $sharpen = 0;

    public int $brightness = 0;

    public int $contrast = 0;

    public int $saturation = 0;

    public ?string $radius = null;         // px entier ou "max"

    /** @var array{0:int,1:string}|null */
    public ?array $border = null;

    public ?string $bg = null;

    public int $pad = 0;

    public int $rotate = 0;

    public ?string $flip = null;           // h|v|hv

    public bool $trim = false;

    public ?string $watermark = null;

    public string $wmPos = 'br';

    public int $wmOpacity = 50;

    public int $wmSize = 20;

    public ?string $text = null;

    public string $textColor = 'ffffff';

    public int $textSize = 32;

    public string $textPos = 'br';

    public ?string $preset = null;

    public ?string $pack = null;           // favicon

    public ?int $page = null;

    public ?int $frame = null;

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        $query = array_change_key_case(array_map(
            fn ($v) => is_array($v) ? '' : trim((string) $v),
            $query,
        ));

        if (($query['preset'] ?? '') !== '') {
            $presets = config('imgk.presets', []);
            $name = strtolower($query['preset']);

            if (! isset($presets[$name])) {
                throw new ImgkException("Preset inconnu : {$name}. Disponibles : ".implode(', ', array_keys($presets)));
            }

            // Les paramètres explicites gagnent toujours sur le preset.
            $query = array_merge(array_map(strval(...), $presets[$name]), $query);
            $query['preset'] = $name;
        }

        $p = new self;

        $p->url = self::str($query, 'url');
        $p->preset = self::str($query, 'preset');
        $p->w = self::intRange($query, 'w', 1, 8192);
        $p->h = self::intRange($query, 'h', 1, 8192);
        $p->fit = self::enum($query, 'fit', ['cover', 'contain']);
        $p->dpr = self::dpr($query);
        $p->max = self::intRange($query, 'max', 1, 8192);
        $p->crop = self::crop($query);
        $p->output = self::output($query);
        $p->q = self::intRange($query, 'q', 1, 100);
        $p->optimized = self::bool($query, 'optimized');
        $p->maxsizeBytes = self::maxsize($query);
        $p->strip = self::bool($query, 'strip');
        $p->conversion = self::conversion($query);
        $p->duotone = self::duotone($query);
        $p->blur = self::intRange($query, 'blur', 0, 100) ?? 0;
        $p->sharpen = self::intRange($query, 'sharpen', 0, 100) ?? 0;
        $p->brightness = self::intRange($query, 'brightness', -100, 100) ?? 0;
        $p->contrast = self::intRange($query, 'contrast', -100, 100) ?? 0;
        $p->saturation = self::intRange($query, 'saturation', -100, 100) ?? 0;
        $p->radius = self::radius($query);
        $p->border = self::border($query);
        $p->bg = self::hex($query, 'bg');
        $p->pad = self::intRange($query, 'pad', 0, 500) ?? 0;
        $p->rotate = self::rotate($query);
        $p->flip = self::enum($query, 'flip', ['h', 'v', 'hv']);
        $p->trim = self::bool($query, 'trim');
        $p->watermark = self::str($query, 'watermark');
        $p->wmPos = self::enum($query, 'wm_pos', self::POSITIONS) ?? 'br';
        $p->wmOpacity = self::intRange($query, 'wm_opacity', 0, 100) ?? 50;
        $p->wmSize = self::intRange($query, 'wm_size', 1, 100) ?? 20;
        $p->text = self::text($query);
        $p->textColor = self::hex($query, 'text_color') ?? 'ffffff';
        $p->textSize = self::intRange($query, 'text_size', 6, 400) ?? 32;
        $p->textPos = self::enum($query, 'text_pos', self::POSITIONS) ?? 'br';
        $p->pack = self::enum($query, 'pack', ['favicon']);
        $p->page = self::intRange($query, 'page', 1, 2000);
        $p->frame = self::intRange($query, 'frame', 0, 10000);

        if ($p->url === null) {
            throw new ImgkException('Il manque le paramètre url= : l\'adresse de l\'image à transformer.');
        }

        if ($p->fit !== null && ($p->w === null || $p->h === null)) {
            throw new ImgkException('fit= demande une largeur (w) ET une hauteur (h).');
        }

        if ($p->fit === null && $p->w !== null && $p->h !== null) {
            $p->fit = 'cover';
        }

        return $p;
    }

    /**
     * Forme canonique : clés triées, valeurs normalisées, défauts absents.
     *
     * @return array<string, string>
     */
    public function canonical(): array
    {
        $out = [];
        $put = function (string $key, mixed $value, mixed $default = null) use (&$out): void {
            if ($value !== null && $value !== $default) {
                $out[$key] = (string) $value;
            }
        };

        $put('url', $this->url);
        $put('w', $this->w);
        $put('h', $this->h);
        $put('fit', $this->fit);
        $put('dpr', $this->dpr === 1.0 ? null : rtrim(rtrim(number_format($this->dpr, 2, '.', ''), '0'), '.'));
        $put('max', $this->max);
        $put('crop', $this->crop ? implode(',', $this->crop) : null);
        $put('output', $this->output);
        $put('q', $this->q);
        $put('optimized', $this->optimized ? 'true' : null);
        $put('maxsize', $this->maxsizeBytes);
        $put('strip', $this->strip ? 'true' : null);
        $put('conversion', $this->conversion);
        $put('duotone', $this->duotone ? implode(',', $this->duotone) : null);
        $put('blur', $this->blur, 0);
        $put('sharpen', $this->sharpen, 0);
        $put('brightness', $this->brightness, 0);
        $put('contrast', $this->contrast, 0);
        $put('saturation', $this->saturation, 0);
        $put('radius', $this->radius);
        $put('border', $this->border ? implode(',', $this->border) : null);
        $put('bg', $this->bg);
        $put('pad', $this->pad, 0);
        $put('rotate', $this->rotate, 0);
        $put('flip', $this->flip);
        $put('trim', $this->trim ? 'true' : null);
        $put('watermark', $this->watermark);
        $put('text', $this->text);
        $put('pack', $this->pack);
        $put('page', $this->page);
        $put('frame', $this->frame);

        if ($this->watermark !== null) {
            $put('wm_pos', $this->wmPos, 'br');
            $put('wm_opacity', $this->wmOpacity, 50);
            $put('wm_size', $this->wmSize, 20);
        }

        if ($this->text !== null) {
            $put('text_color', $this->textColor, 'ffffff');
            $put('text_size', $this->textSize, 32);
            $put('text_pos', $this->textPos, 'br');
        }

        ksort($out);

        return $out;
    }

    public function cacheKey(string $resolvedOutput): string
    {
        $canonical = $this->canonical();
        $canonical['output'] = $resolvedOutput;
        ksort($canonical);

        return hash('sha256', http_build_query($canonical));
    }

    public function hasTransparencyDressing(): bool
    {
        return $this->radius !== null || $this->pad > 0 || ($this->rotate % 90) !== 0;
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function str(array $q, string $key): ?string
    {
        return ($q[$key] ?? '') === '' ? null : $q[$key];
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function intRange(array $q, string $key, int $min, int $max): ?int
    {
        $raw = self::str($q, $key);

        if ($raw === null) {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $raw)) {
            throw new ImgkException("{$key}={$raw} n'est pas un nombre entier.");
        }

        $value = (int) $raw;

        if ($value < $min || $value > $max) {
            throw new ImgkException("{$key} doit être entre {$min} et {$max}.");
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $q
     * @param  list<string>  $allowed
     */
    private static function enum(array $q, string $key, array $allowed): ?string
    {
        $raw = self::str($q, $key);

        if ($raw === null) {
            return null;
        }

        $value = strtolower($raw);

        if (! in_array($value, $allowed, true)) {
            throw new ImgkException("{$key}={$raw} : valeurs possibles → ".implode('|', $allowed));
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function bool(array $q, string $key): bool
    {
        $raw = self::str($q, $key);

        if ($raw === null) {
            return false;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function dpr(array $q): float
    {
        $raw = self::str($q, 'dpr');

        if ($raw === null) {
            return 1.0;
        }

        if (! is_numeric($raw)) {
            throw new ImgkException("dpr={$raw} n'est pas un nombre.");
        }

        $value = round((float) $raw, 2);

        if ($value < 1 || $value > 3) {
            throw new ImgkException('dpr doit être entre 1 et 3.');
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $q
     * @return array{0:int,1:int,2:int,3:int}|null
     */
    private static function crop(array $q): ?array
    {
        $raw = self::str($q, 'crop');

        if ($raw === null) {
            return null;
        }

        if (! preg_match('/^(\d+),(\d+),(\d+),(\d+)$/', $raw, $m)) {
            throw new ImgkException('crop attend x,y,largeur,hauteur (ex : crop=10,10,300,200).');
        }

        [, $x, $y, $w, $h] = array_map(intval(...), $m);

        if ($w < 1 || $h < 1) {
            throw new ImgkException('crop : la largeur et la hauteur doivent être supérieures à zéro.');
        }

        return [$x, $y, $w, $h];
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function output(array $q): ?string
    {
        $raw = self::str($q, 'output');

        if ($raw === null) {
            return null;
        }

        $value = strtolower($raw);

        if ($value === 'jpeg') {
            $value = 'jpg';
        }

        if (! in_array($value, self::OUTPUTS, true)) {
            throw new ImgkException("output={$raw} : formats possibles → ".implode('|', self::OUTPUTS));
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function maxsize(array $q): ?int
    {
        $raw = self::str($q, 'maxsize');

        if ($raw === null) {
            return null;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*(kb|mb|ko|mo|b)?$/i', $raw, $m)) {
            throw new ImgkException('maxsize attend un poids comme 200kb ou 2mb.');
        }

        $bytes = (float) $m[1] * match (strtolower($m[2] ?? 'b')) {
            'kb', 'ko' => 1024,
            'mb', 'mo' => 1024 * 1024,
            default => 1,
        };

        if ($bytes < 1024) {
            throw new ImgkException('maxsize : minimum 1kb.');
        }

        return (int) $bytes;
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function conversion(array $q): ?string
    {
        $raw = self::str($q, 'conversion');

        if ($raw === null) {
            return null;
        }

        $value = strtolower($raw);

        if (in_array($value, ['grayscale', 'greyscale', 'bw', 'nb'], true)) {
            $value = 'white-black';
        }

        if (! in_array($value, self::CONVERSIONS, true)) {
            throw new ImgkException("conversion={$raw} : valeurs possibles → ".implode('|', self::CONVERSIONS));
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $q
     * @return array{0:string,1:string}|null
     */
    private static function duotone(array $q): ?array
    {
        $raw = self::str($q, 'duotone');

        if ($raw === null) {
            return null;
        }

        $parts = explode(',', $raw);

        if (count($parts) !== 2) {
            throw new ImgkException('duotone attend deux couleurs hex : duotone=0d1017,5b8cff (ombre,lumière).');
        }

        return [self::normalizeHex($parts[0], 'duotone'), self::normalizeHex($parts[1], 'duotone')];
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function radius(array $q): ?string
    {
        $raw = self::str($q, 'radius');

        if ($raw === null) {
            return null;
        }

        $value = strtolower($raw);

        if ($value === 'max') {
            return 'max';
        }

        if (! preg_match('/^\d+$/', $value) || (int) $value > 4096) {
            throw new ImgkException('radius attend un rayon en pixels (radius=24) ou radius=max pour un cercle.');
        }

        return (string) (int) $value;
    }

    /**
     * @param  array<string, string>  $q
     * @return array{0:int,1:string}|null
     */
    private static function border(array $q): ?array
    {
        $raw = self::str($q, 'border');

        if ($raw === null) {
            return null;
        }

        $parts = explode(',', $raw, 2);
        $width = $parts[0];

        if (! preg_match('/^\d+$/', $width) || (int) $width < 1 || (int) $width > 200) {
            throw new ImgkException('border attend épaisseur,couleur (ex : border=4,5b8cff).');
        }

        return [(int) $width, self::normalizeHex($parts[1] ?? '000000', 'border')];
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function hex(array $q, string $key): ?string
    {
        $raw = self::str($q, $key);

        return $raw === null ? null : self::normalizeHex($raw, $key);
    }

    private static function normalizeHex(string $raw, string $key): string
    {
        $value = strtolower(ltrim(trim($raw), '#'));

        if (preg_match('/^[0-9a-f]{3}$/', $value)) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (! preg_match('/^[0-9a-f]{6}$/', $value)) {
            throw new ImgkException("{$key} attend une couleur hex comme 5b8cff.");
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function rotate(array $q): int
    {
        $raw = self::str($q, 'rotate');

        if ($raw === null) {
            return 0;
        }

        if (! preg_match('/^-?\d+$/', $raw)) {
            throw new ImgkException('rotate attend un angle en degrés (rotate=90).');
        }

        return (((int) $raw) % 360 + 360) % 360;
    }

    /**
     * @param  array<string, string>  $q
     */
    private static function text(array $q): ?string
    {
        $raw = self::str($q, 'text');

        if ($raw === null) {
            return null;
        }

        if (mb_strlen($raw) > 200) {
            throw new ImgkException('text : 200 caractères maximum.');
        }

        return $raw;
    }
}
