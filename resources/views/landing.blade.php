@extends('layouts.app')

@php
    $demo = url('/demo/photo.jpg');
    $ex = fn (string $params) => url('/').'?url='.urlencode($demo).'&'.$params;
@endphp

@section('body')
<div class="min-h-screen flex flex-col" style="background:var(--bg)">

    {{-- Nav --}}
    <header class="sticky top-0 z-40 border-b" style="border-color:var(--bd); background:color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(12px)">
        <nav class="mx-auto max-w-6xl px-4 h-16 flex items-center justify-between gap-4">
            @include('partials.brand')
            <div class="hidden md:flex items-center gap-6 text-sm" style="color:var(--ink-alt)">
                <a href="#comment" class="hover:text-[var(--accent)] transition-colors">Comment ça marche</a>
                <a href="#exemples" class="hover:text-[var(--accent)] transition-colors">Exemples</a>
                <a href="{{ route('docs') }}" class="hover:text-[var(--accent)] transition-colors">Docs</a>
                <a href="https://github.com/The-Forge-Agency/ImgkApp" target="_blank" rel="noopener" class="hover:text-[var(--accent)] transition-colors">GitHub</a>
                <span class="opacity-60">#17/52</span>
            </div>
            <a href="{{ route('studio') }}" class="btn btn-primary !py-2 !px-4 text-sm">Commencer</a>
        </nav>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-4xl px-4 pt-16 pb-10 text-center rise">
        <span class="chip">App #17/52 · en ligne cette semaine</span>
        <h1 class="mt-6 text-4xl sm:text-5xl md:text-6xl font-bold leading-tight tracking-tight">
            Transforme une image juste<br class="hidden sm:block">
            en écrivant une <span style="color:var(--accent)">adresse</span>
        </h1>
        <p class="mt-5 text-lg max-w-2xl mx-auto" style="color:var(--ink-alt)">
            Redimensionner, convertir, alléger, recadrer : d'habitude c'est un logiciel, des clics et
            un réexport à chaque fois. Ici, tu écris ce que tu veux dans le lien — et l'image
            arrive déjà transformée, pour tout le monde, à chaque affichage.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('studio') }}" class="btn btn-primary">Essayer maintenant</a>
            <a href="#exemples" class="btn btn-ghost">Voir un exemple</a>
        </div>
        <p class="mt-4 text-sm" style="color:var(--ink-alt)">Zéro compte · gratuit · open source</p>
    </section>

    {{-- La recette --}}
    <section class="mx-auto w-full max-w-5xl px-4 py-10 rise rise-1">
        <div class="card overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b" style="border-color:var(--bd)">
                <span class="w-3 h-3 rounded-full" style="background:var(--danger)"></span>
                <span class="w-3 h-3 rounded-full" style="background:var(--ok)"></span>
                <span class="w-3 h-3 rounded-full" style="background:var(--accent)"></span>
                <span class="ml-3 text-xs font-address" style="color:var(--ink-alt)">une adresse imgk se lit comme une recette</span>
            </div>
            <div class="p-5 sm:p-8 grid md:grid-cols-2 gap-8 items-center">
                <div>
                    <p class="address-box p-4">
                        <span class="u-host">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</span><span class="u-sep">?</span><span class="u-key">url</span><span class="u-sep">=</span><span class="u-val">ta-photo.jpg</span><span class="u-sep">&</span><span class="u-key">w</span><span class="u-sep">=</span><span class="u-val">500</span><span class="u-sep">&</span><span class="u-key">h</span><span class="u-sep">=</span><span class="u-val">500</span><span class="u-sep">&</span><span class="u-key">conversion</span><span class="u-sep">=</span><span class="u-val">duotone</span><span class="u-sep">&</span><span class="u-key">output</span><span class="u-sep">=</span><span class="u-val">webp</span>
                    </p>
                    <ul class="mt-5 space-y-2.5 text-sm" style="color:var(--ink-alt)">
                        <li><code class="font-address" style="color:var(--accent)">?</code> ouvre les instructions, <code class="font-address" style="color:var(--accent)">&</code> sépare chaque réglage — l'ordre n'a pas d'importance</li>
                        <li><code class="font-address" style="color:var(--accent)">url=</code> l'image d'origine, où qu'elle soit sur internet</li>
                        <li><code class="font-address" style="color:var(--accent)">w=500&h=500</code> un carré de 500 pixels, recadré au centre</li>
                        <li><code class="font-address" style="color:var(--accent)">conversion=duotone</code> l'effet bichromie, celui de l'image ci-contre</li>
                        <li><code class="font-address" style="color:var(--accent)">output=webp</code> le format le plus léger pour le web</li>
                    </ul>
                </div>
                <figure class="text-center">
                    <img src="{{ $ex('w=500&h=500&fit=cover&conversion=duotone&duotone=0d1017,5b8cff&output=webp') }}"
                         alt="La photo de démo transformée par l'adresse ci-contre"
                         width="500" height="500" loading="lazy"
                         class="rounded-2xl w-full max-w-sm mx-auto border" style="border-color:var(--bd-strong)">
                    <figcaption class="mt-3 text-xs" style="color:var(--ink-alt)">Cette image est réellement servie par l'adresse ci-contre, en direct.</figcaption>
                </figure>
            </div>
        </div>
    </section>

    {{-- Exemples --}}
    <section id="exemples" class="mx-auto w-full max-w-6xl px-4 py-14">
        <h2 class="text-3xl font-bold text-center">La même photo, cinq adresses</h2>
        <p class="mt-3 text-center max-w-2xl mx-auto" style="color:var(--ink-alt)">
            Aucun fichier retouché : chaque vignette est la photo d'origine, transformée à la volée
            par les réglages écrits dessous.
        </p>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['label' => "L'originale", 'params' => null, 'src' => $demo, 'query' => 'demo/photo.jpg'],
                ['label' => 'Miniature allégée', 'params' => 'w=400&output=webp&optimized=true'],
                ['label' => 'Noir et blanc', 'params' => 'w=400&conversion=white-black'],
                ['label' => 'Avatar rond', 'params' => 'w=240&h=240&radius=max&border=6,5b8cff&output=png'],
                ['label' => 'Tamponnée', 'params' => 'w=400&text='.rawurlencode('© imgk').'&text_pos=br'],
                ['label' => 'Sépia vintage', 'params' => 'w=400&conversion=sepia&q=70'],
            ] as $example)
                <figure class="card p-4">
                    <div class="rounded-xl overflow-hidden grid place-items-center" style="background:var(--bg); min-height:180px">
                        <img src="{{ $example['src'] ?? $ex($example['params']) }}"
                             alt="{{ $example['label'] }}" loading="lazy" class="max-h-56 w-auto max-w-full">
                    </div>
                    <figcaption class="mt-3">
                        <p class="font-display font-semibold text-sm">{{ $example['label'] }}</p>
                        <code class="font-address text-xs break-all" style="color:var(--accent)">{{ $example['query'] ?? '?'.$example['params'] }}</code>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- Le twist --}}
    <section class="mx-auto w-full max-w-4xl px-4 py-10">
        <div class="card p-8 sm:p-10 text-center" style="border-color:var(--accent-border)">
            <h2 class="text-2xl sm:text-3xl font-bold">3 000 photos produit, une ligne à changer</h2>
            <p class="mt-4 max-w-2xl mx-auto" style="color:var(--ink-alt)">
                Une boutique dont les photos de 8 Mo font ramer le site n'a rien à réexporter :
                elle préfixe ses images avec une adresse imgk. Le jour où il faut du 800 pixels au lieu
                de 600, on change <code class="font-address" style="color:var(--accent)">w=600</code> en
                <code class="font-address" style="color:var(--accent)">w=800</code> — et les 3 000 photos
                changent, partout, sans toucher un seul fichier. Le lien n'est pas un raccourci :
                c'est la recette, réutilisable à l'infini et mise en cache pour tout le monde.
            </p>
        </div>
    </section>

    {{-- Comment ça marche --}}
    <section id="comment" class="mx-auto w-full max-w-6xl px-4 py-14">
        <h2 class="text-3xl font-bold text-center">Comment ça marche</h2>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['n' => '1', 'title' => "Donne l'image", 'body' => "Colle l'adresse d'une image en ligne, ou dépose un fichier dans le studio : il reçoit une adresse à lui."],
                ['n' => '2', 'title' => 'Écris la recette', 'body' => "Largeur, format, poids maxi, effets, habillage… chaque réglage est un mot dans l'adresse. À la main ou à la souris, comme tu préfères."],
                ['n' => '3', 'title' => 'Colle le lien partout', 'body' => "Site, mail, CMS, doc : l'image arrive déjà transformée, à chaque affichage, pour tout le monde. Change un chiffre, tout suit."],
            ] as $step)
                <div class="card p-6">
                    <span class="inline-grid place-items-center w-9 h-9 rounded-xl font-display font-bold" style="background:var(--accent); color:var(--accent-ink)">{{ $step['n'] }}</span>
                    <h3 class="mt-4 font-semibold text-lg">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed" style="color:var(--ink-alt)">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section class="mx-auto w-full max-w-6xl px-4 py-6 pb-14">
        <h2 class="text-3xl font-bold text-center">Ce que ça fait</h2>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['title' => 'Taille & cadrage', 'body' => 'Largeur, hauteur, remplir ou contenir, Retina (dpr=2), plafond de dimension, découpe précise d\'une zone.', 'code' => 'w=500&h=500&fit=cover'],
                ['title' => 'Format & poids', 'body' => 'png, jpg, webp, avif, gif, ico, pdf — ou auto selon le navigateur. Qualité, poids maximum imposé, nettoyage des métadonnées GPS.', 'code' => 'output=auto&maxsize=200kb'],
                ['title' => 'Couleurs & effets', 'body' => 'Noir et blanc, sépia, négatif, duotone, flou, netteté, luminosité, contraste, saturation, mode scan de document.', 'code' => 'conversion=scan'],
                ['title' => 'Habillage', 'body' => 'Coins arrondis jusqu\'au cercle parfait, contour, fond, marge, rotation, miroir, rognage des bords vides.', 'code' => 'radius=max&border=4,5b8cff'],
                ['title' => 'Filigrane & texte', 'body' => 'Un logo positionnable et réglable en transparence, ou un texte incrusté avec couleur et taille, pour signer une image publiée.', 'code' => 'text=©+moi&wm_opacity=40'],
                ['title' => 'Presets & pack favicon', 'body' => 'Des recettes toutes prêtes rappelées par un mot, et toutes les tailles d\'icône d\'un site générées d\'un coup dans un zip.', 'code' => 'preset=avatar · pack=favicon'],
            ] as $i => $feature)
                <div class="card p-6">
                    <span class="inline-grid place-items-center w-8 h-8 rounded-lg text-sm font-display font-bold" style="background:var(--accent-soft); color:var(--accent)">{{ $i + 1 }}</span>
                    <h3 class="mt-3 font-semibold">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed" style="color:var(--ink-alt)">{{ $feature['body'] }}</p>
                    <code class="mt-3 inline-block font-address text-xs" style="color:var(--accent)">{{ $feature['code'] }}</code>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA final --}}
    <section class="mx-auto w-full max-w-4xl px-4 pb-20 text-center">
        <div class="card p-10">
            <h2 class="text-2xl sm:text-3xl font-bold">Ta première image transformée dans 30 secondes</h2>
            <p class="mt-3" style="color:var(--ink-alt)">Dépose une image, règle à la souris, récupère l'adresse. Ou écris-la directement, si tu es du genre clavier.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('studio') }}" class="btn btn-primary">Ouvrir le studio</a>
                <a href="{{ route('docs') }}" class="btn btn-ghost">Lire la doc de l'adresse</a>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="mt-auto border-t" style="border-color:var(--bd)">
        <div class="mx-auto max-w-6xl px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm" style="color:var(--ink-alt)">
            <span>imgk · app #17/52 · TFA52</span>
            <span class="flex items-center gap-4">
                <a href="{{ route('docs') }}" class="hover:text-[var(--accent)] transition-colors">Docs</a>
                <a href="https://github.com/The-Forge-Agency/ImgkApp" target="_blank" rel="noopener" class="hover:text-[var(--accent)] transition-colors">Open source</a>
                <span>Gratuit · financé par des dons</span>
            </span>
        </div>
    </footer>
</div>
@endsection
