@extends('layouts.app')

@section('title', 'Docs imgk — la référence de l\'adresse')
@section('description', 'Tous les réglages d\'une adresse imgk : taille, format, poids, effets, habillage, incrustations, presets et pack favicon.')

@php
    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $groups = [
        'Taille & cadrage' => [
            ['w', 'Largeur en pixels (1–8192)', 'w=500'],
            ['h', 'Hauteur en pixels (1–8192)', 'h=500'],
            ['fit', 'Avec w et h : cover remplit en recadrant au centre, contain contient l\'image entière (fond bg ou transparent). Défaut : cover', 'fit=contain'],
            ['dpr', 'Multiplicateur Retina, 1 à 3', 'dpr=2'],
            ['max', 'Plafond de la plus grande dimension — pratique sans connaître le ratio', 'max=1600'],
            ['crop', 'Découpe une zone précise : x,y,largeur,hauteur', 'crop=10,20,300,200'],
        ],
        'Format & poids' => [
            ['output', 'png · jpg · webp · avif · gif · ico · pdf · auto. auto lit l\'en-tête Accept et sert avif ou webp aux navigateurs qui savent. Sans output, le format de la source est conservé', 'output=auto'],
            ['q', 'Qualité 1–100 (jpg, webp, avif)', 'q=80'],
            ['optimized', 'Compression intelligente : meilleurs réglages d\'encodage + métadonnées retirées', 'optimized=true'],
            ['maxsize', 'Poids maximum imposé : imgk baisse la qualité (puis les dimensions) jusqu\'à tenir dessous', 'maxsize=200kb'],
            ['strip', 'Retire les métadonnées EXIF, GPS et profils', 'strip=true'],
        ],
        'Couleurs & effets' => [
            ['conversion', 'white-black · sepia · invert · duotone · scan. Le mode scan redresse, nettoie et contraste une photo de document', 'conversion=scan'],
            ['duotone', 'Les deux couleurs du duotone : ombres,lumières', 'duotone=0d1017,5b8cff'],
            ['blur', 'Flou 0–100', 'blur=20'],
            ['sharpen', 'Netteté 0–100', 'sharpen=40'],
            ['brightness', 'Luminosité −100 à 100', 'brightness=15'],
            ['contrast', 'Contraste −100 à 100', 'contrast=20'],
            ['saturation', 'Saturation −100 à 100', 'saturation=-100'],
        ],
        'Habillage' => [
            ['radius', 'Coins arrondis en pixels, ou radius=max pour un cercle parfait (recadré au carré)', 'radius=max'],
            ['border', 'Contour : épaisseur,couleur', 'border=4,5b8cff'],
            ['bg', 'Remplace la transparence (et remplit contain, pad, rotate)', 'bg=ffffff'],
            ['pad', 'Marge autour, en pixels', 'pad=40'],
            ['rotate', 'Rotation en degrés', 'rotate=90'],
            ['flip', 'Miroir : h (horizontal), v (vertical), hv', 'flip=h'],
            ['trim', 'Rogne automatiquement les bandes vides des bords', 'trim=true'],
        ],
        'Filigrane & texte' => [
            ['watermark', 'Adresse d\'une image de logo à incruster', 'watermark=https://…/logo.png'],
            ['wm_pos', 'Position : tl · tr · bl · br · center (défaut br)', 'wm_pos=br'],
            ['wm_opacity', 'Transparence du filigrane 0–100 (défaut 50)', 'wm_opacity=40'],
            ['wm_size', 'Largeur du filigrane en % de l\'image (défaut 20)', 'wm_size=25'],
            ['text', 'Texte incrusté (200 caractères max)', 'text=©+mon+studio'],
            ['text_color', 'Couleur hex du texte (défaut ffffff)', 'text_color=5b8cff'],
            ['text_size', 'Taille du texte en pixels (défaut 32)', 'text_size=48'],
            ['text_pos', 'Position du texte (défaut br)', 'text_pos=center'],
        ],
        'Presets & cas particuliers' => [
            ['preset', 'Une recette enregistrée, rappelée par un mot — voir la liste ci-dessous. Tes paramètres explicites gagnent toujours', 'preset=avatar'],
            ['pack', 'pack=favicon génère toutes les tailles d\'icône d\'un site dans un zip : ico multi-tailles, png 16→512, apple-touch-icon, manifest, snippet', 'pack=favicon'],
            ['page', 'La page d\'un PDF à rasteriser en image', 'page=3'],
            ['frame', 'L\'image d\'un GIF animé (0 = première)', 'frame=12'],
        ],
    ];
@endphp

@section('body')
<div class="min-h-screen flex flex-col" style="background:var(--bg)">
    <header class="sticky top-0 z-40 border-b" style="border-color:var(--bd); background:color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(12px)">
        <nav class="mx-auto max-w-5xl px-4 h-16 flex items-center justify-between gap-4">
            @include('partials.brand')
            <div class="flex items-center gap-2">
                <a href="{{ route('home') }}" class="hidden sm:inline-flex btn btn-ghost !py-2 !px-4 text-sm">Accueil</a>
                <a href="{{ route('studio') }}" class="btn btn-primary !py-2 !px-4 text-sm">Ouvrir le studio</a>
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-5xl px-4 py-10 flex-1">
        <span class="chip">Référence</span>
        <h1 class="mt-4 text-3xl sm:text-4xl font-bold">L'adresse imgk, réglage par réglage</h1>
        <p class="mt-4 max-w-2xl" style="color:var(--ink-alt)">
            Une adresse imgk se lit comme une recette : le <code class="font-address" style="color:var(--accent)">?</code>
            ouvre les instructions, le <code class="font-address" style="color:var(--accent)">&</code> sépare chaque réglage,
            et l'ordre n'a pas d'importance. La même adresse ressort toujours la même image, servie depuis le cache.
        </p>

        <div class="address-box mt-6 p-4">
            <span class="u-host">https://{{ $host }}/</span><span class="u-sep">?</span><span class="u-key">url</span><span class="u-sep">=</span><span class="u-val">https://exemple.com/photo.jpg</span><span class="u-sep">&</span><span class="u-key">w</span><span class="u-sep">=</span><span class="u-val">500</span><span class="u-sep">&</span><span class="u-key">output</span><span class="u-sep">=</span><span class="u-val">webp</span><span class="u-sep">&</span><span class="u-key">optimized</span><span class="u-sep">=</span><span class="u-val">true</span>
        </div>

        <p class="mt-4 text-sm" style="color:var(--ink-alt)">
            Le seul paramètre obligatoire est <code class="font-address" style="color:var(--accent)">url=</code> :
            l'adresse publique de l'image d'origine (jpg, png, webp, avif, gif, heic d'iPhone, bmp, tiff, ico ou pdf).
            Pense à encoder l'URL si elle contient elle-même des <code class="font-address">?</code> ou <code class="font-address">&</code>.
        </p>

        @foreach ($groups as $title => $rows)
            <h2 class="mt-12 text-2xl font-bold">{{ $title }}</h2>
            <div class="card mt-4 overflow-x-auto">
                <table class="docs-table">
                    <thead>
                        <tr><th class="w-36">Paramètre</th><th>Ce que ça fait</th><th class="w-52">Exemple</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as [$name, $description, $example])
                            <tr>
                                <td><code>{{ $name }}</code></td>
                                <td style="color:var(--ink-alt)">{{ $description }}</td>
                                <td><code style="color:var(--ink)">{{ $example }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        <h2 class="mt-12 text-2xl font-bold">Les presets embarqués</h2>
        <div class="card mt-4 overflow-x-auto">
            <table class="docs-table">
                <thead><tr><th class="w-36">Preset</th><th>Recette appliquée</th></tr></thead>
                <tbody>
                    @foreach ($presets as $name => $recipe)
                        <tr>
                            <td><code>preset={{ $name }}</code></td>
                            <td><code style="color:var(--ink)">{{ collect($recipe)->map(fn ($v, $k) => "{$k}={$v}")->implode('&') }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2 class="mt-12 text-2xl font-bold">Cache, limites & bonnes pratiques</h2>
        <div class="mt-4 space-y-4 text-sm leading-relaxed" style="color:var(--ink-alt)">
            <p><strong style="color:var(--ink)">Le lien est la recette.</strong> La même adresse produit exactement la même image : les résultats sont mis en cache longuement (<code class="font-address">Cache-Control: immutable</code>) et servis sans recalcul. Change un paramètre, l'image change partout où le lien est collé.</p>
            <p><strong style="color:var(--ink)">Fair use.</strong> L'offre ouverte est gratuite et sans compte, avec une limite de débit par IP et des bornes de sécurité : 30 Mo et 50 mégapixels maximum en entrée, sources http(s) publiques uniquement (les adresses internes sont bloquées). En-têtes utiles dans la réponse : <code class="font-address">X-Imgk-Cache</code> (HIT/MISS), <code class="font-address">X-Imgk-Bytes</code> et <code class="font-address">X-Imgk-Source-Bytes</code>.</p>
            <p><strong style="color:var(--ink)">Erreurs.</strong> Toute erreur sort en JSON clair avec le bon code HTTP : <code class="font-address">{"error": "maxsize attend un poids comme 200kb ou 2mb."}</code></p>
            <p><strong style="color:var(--ink)">Self-host.</strong> imgk est open source : déploie ta propre instance depuis <a href="https://github.com/The-Forge-Agency/ImgkApp" target="_blank" rel="noopener" class="underline decoration-dotted" style="color:var(--accent)">le repo GitHub</a> pour un contrôle total, une allowlist de domaines sources (<code class="font-address">IMGK_ALLOWED_HOSTS</code>) ou un usage intensif.</p>
        </div>

        <div class="card p-8 mt-12 text-center">
            <h2 class="text-xl font-bold">Plus simple à la souris ?</h2>
            <p class="mt-2 text-sm" style="color:var(--ink-alt)">Le studio règle tout en cliquant et affiche l'adresse correspondante en direct.</p>
            <a href="{{ route('studio') }}" class="btn btn-primary mt-5">Ouvrir le studio</a>
        </div>
    </main>

    <footer class="mt-auto border-t" style="border-color:var(--bd)">
        <div class="mx-auto max-w-5xl px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm" style="color:var(--ink-alt)">
            <span>imgk · app #17/52 · TFA52</span>
            <span>Gratuit et open source · financé par des dons</span>
        </div>
    </footer>
</div>
@endsection
