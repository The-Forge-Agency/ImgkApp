@extends('layouts.app')

@section('title', 'Studio imgk — règle à la souris, récupère le lien')
@section('description', 'Glisse-dépose une image, règle taille, format, poids et effets à la souris, et récupère l\'adresse imgk réutilisable.')

@section('body')
<div class="min-h-screen flex flex-col" style="background:var(--bg)"
     x-data='imgkStudio({ base: @json(rtrim(config("app.url"), "/")), csrf: @json(csrf_token()), presets: @json($presets) })'>

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b" style="border-color:var(--bd); background:color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(12px)">
        <nav class="mx-auto max-w-7xl px-4 h-16 flex items-center justify-between gap-3">
            @include('partials.brand')
            <div class="flex items-center gap-2">
                <a href="{{ route('docs') }}" class="hidden sm:inline-flex btn btn-ghost !py-2 !px-4 text-sm">Docs</a>
                <button type="button" class="btn btn-ghost !py-2 !px-4 text-sm" x-on:click="reset()" x-bind:disabled="!activeSource">Effacer</button>
                <button type="button" class="hidden sm:inline-flex btn btn-primary !py-2 !px-4 text-sm" x-on:click="copyAddress()" x-bind:disabled="!activeSource">
                    <span x-show="!copied">Copier le lien</span>
                    <span x-show="copied" x-cloak>Copié !</span>
                </button>
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 grid gap-6 lg:grid-cols-[400px_minmax(0,1fr)] items-start flex-1">

        {{-- Colonne réglages --}}
        <div class="space-y-5">

            {{-- Source --}}
            <section class="card p-5">
                <p class="field-label">Entrée</p>

                <div class="dropzone p-6 text-center cursor-pointer"
                     x-bind:class="{ 'is-over': dragOver }"
                     x-on:click="$refs.filePicker.click()"
                     x-on:dragover.prevent="dragOver = true"
                     x-on:dragleave.prevent="dragOver = false"
                     x-on:drop.prevent="onDrop($event)">
                    <img src="{{ asset('logo.svg') }}" alt="" class="w-10 h-10 mx-auto opacity-80">
                    <p class="mt-3 font-display font-semibold" style="color:var(--ink-alt)">
                        <span x-show="!uploading">Dépose ou colle tes images</span>
                        <span x-show="uploading" x-cloak>Envoi en cours…</span>
                    </p>
                    <p class="mt-1 text-xs" style="color:var(--ink-alt)">ou clique pour choisir un fichier</p>
                    <input type="file" class="hidden" x-ref="filePicker" multiple
                           accept="image/*,.heic,.heif,.pdf" x-on:change="onPick($event)">
                </div>

                <div class="mt-3 flex gap-2">
                    <input type="url" class="input font-address text-xs" placeholder="…ou colle l'adresse d'une image en ligne"
                           x-model="urlInput" x-on:keydown.enter="useUrl()">
                    <button type="button" class="btn btn-ghost !py-2 !px-3 text-sm shrink-0" x-on:click="useUrl()">OK</button>
                </div>

                <p class="mt-2 text-xs" style="color:var(--danger)" x-text="uploadError" x-show="uploadError" x-cloak></p>

                <button type="button" class="mt-2 text-xs underline decoration-dotted" style="color:var(--ink-alt)"
                        x-show="!sources.length" x-on:click="useDemo()">
                    Pas d'image sous la main ? Essaie avec la photo de démo →
                </button>

                {{-- Lot --}}
                <ul class="mt-3 space-y-1.5" x-show="sources.length" x-cloak>
                    <template x-for="(source, index) in sources" x-bind:key="source.url">
                        <li class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs cursor-pointer border transition-colors"
                            x-bind:style="index === activeIndex ? 'border-color:var(--accent); background:var(--accent-soft)' : 'border-color:var(--bd)'"
                            x-on:click="activeIndex = index">
                            <span class="truncate flex-1 font-address" x-text="source.name"></span>
                            <span style="color:var(--ink-alt)" x-text="source.bytes ? formatBytes(source.bytes) : ''"></span>
                            <button type="button" class="shrink-0 hover:text-[var(--danger)]" style="color:var(--ink-alt)"
                                    x-on:click.stop="removeSource(index)" aria-label="Retirer">✕</button>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Presets --}}
            <section class="card p-5">
                <p class="field-label">Presets</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['avatar' => 'Avatar', 'produit' => 'Photo produit', 'scan' => 'Scan document', 'social' => 'Réseaux sociaux', 'compress' => 'Sous 2 Mo', 'thumbnail' => 'Miniature'] as $key => $label)
                        <button type="button" class="chip !cursor-pointer transition-all"
                                x-bind:style="activePreset === '{{ $key }}' ? 'background:var(--accent); color:var(--accent-ink)' : ''"
                                x-on:click="applyPreset('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="mt-3 text-xs" style="color:var(--ink-alt)">Un preset se rappelle aussi par un mot : <code class="font-address" style="color:var(--accent)">preset=avatar</code></p>
            </section>

            {{-- Taille & cadrage --}}
            <details class="card control-group" open>
                <summary class="p-5 flex items-center justify-between">
                    <span class="font-display font-semibold">Taille & cadrage</span>
                    <span class="caret" style="color:var(--ink-alt)">›</span>
                </summary>
                <div class="px-5 pb-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="p-w">Largeur (w)</label>
                            <input id="p-w" type="number" min="1" max="8192" class="input" placeholder="auto" x-model="params.w">
                        </div>
                        <div>
                            <label class="field-label" for="p-h">Hauteur (h)</label>
                            <input id="p-h" type="number" min="1" max="8192" class="input" placeholder="auto" x-model="params.h">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="p-fit">Cadrage (fit)</label>
                            <select id="p-fit" class="input" x-model="params.fit" x-bind:disabled="!params.w || !params.h">
                                <option value="cover">Remplir (cover)</option>
                                <option value="contain">Contenir (contain)</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="p-dpr">Retina (dpr)</label>
                            <select id="p-dpr" class="input" x-model="params.dpr">
                                <option value="1">1×</option>
                                <option value="2">2×</option>
                                <option value="3">3×</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="p-max">Plafond (max)</label>
                            <input id="p-max" type="number" min="1" max="8192" class="input" placeholder="ex : 1600" x-model="params.max">
                        </div>
                        <div>
                            <label class="field-label" for="p-crop">Découpe (crop)</label>
                            <input id="p-crop" type="text" class="input font-address text-xs" placeholder="x,y,l,h" x-model="params.crop">
                        </div>
                    </div>
                </div>
            </details>

            {{-- Format & poids --}}
            <details class="card control-group">
                <summary class="p-5 flex items-center justify-between">
                    <span class="font-display font-semibold">Format & poids</span>
                    <span class="caret" style="color:var(--ink-alt)">›</span>
                </summary>
                <div class="px-5 pb-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="p-output">Format (output)</label>
                            <select id="p-output" class="input" x-model="params.output">
                                <option value="">Comme la source</option>
                                <option value="auto">auto (webp/avif)</option>
                                <option value="webp">webp</option>
                                <option value="avif">avif</option>
                                <option value="jpg">jpg</option>
                                <option value="png">png</option>
                                <option value="gif">gif</option>
                                <option value="ico">ico</option>
                                <option value="pdf">pdf</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="p-maxsize">Poids maxi (maxsize)</label>
                            <input id="p-maxsize" type="text" class="input font-address text-xs" placeholder="ex : 200kb" x-model="params.maxsize">
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="p-q">Qualité (q) <span x-text="params.q || 'auto'"></span></label>
                        <input id="p-q" type="range" min="1" max="100" x-model="params.q">
                    </div>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="params.optimized"> Compression intelligente</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="params.strip"> Retirer GPS & métadonnées</label>
                    </div>
                </div>
            </details>

            {{-- Couleurs & effets --}}
            <details class="card control-group">
                <summary class="p-5 flex items-center justify-between">
                    <span class="font-display font-semibold">Couleurs & effets</span>
                    <span class="caret" style="color:var(--ink-alt)">›</span>
                </summary>
                <div class="px-5 pb-5 space-y-4">
                    <div>
                        <label class="field-label" for="p-conversion">Conversion</label>
                        <select id="p-conversion" class="input" x-model="params.conversion">
                            <option value="">Aucune</option>
                            <option value="white-black">Noir et blanc</option>
                            <option value="sepia">Sépia</option>
                            <option value="invert">Négatif</option>
                            <option value="duotone">Duotone</option>
                            <option value="scan">Scan de document</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3" x-show="params.conversion === 'duotone'" x-cloak>
                        <label class="text-sm inline-flex items-center gap-2">Ombres
                            <input type="color" x-bind:value="'#' + params.duotoneDark" x-on:input="params.duotoneDark = $event.target.value.slice(1)">
                        </label>
                        <label class="text-sm inline-flex items-center gap-2">Lumières
                            <input type="color" x-bind:value="'#' + params.duotoneLight" x-on:input="params.duotoneLight = $event.target.value.slice(1)">
                        </label>
                    </div>
                    @foreach ([
                        ['key' => 'blur', 'label' => 'Flou (blur)', 'min' => 0, 'max' => 100],
                        ['key' => 'sharpen', 'label' => 'Netteté (sharpen)', 'min' => 0, 'max' => 100],
                        ['key' => 'brightness', 'label' => 'Luminosité', 'min' => -100, 'max' => 100],
                        ['key' => 'contrast', 'label' => 'Contraste', 'min' => -100, 'max' => 100],
                        ['key' => 'saturation', 'label' => 'Saturation', 'min' => -100, 'max' => 100],
                    ] as $slider)
                        <div>
                            <label class="field-label" for="p-{{ $slider['key'] }}">{{ $slider['label'] }} <span x-text="params.{{ $slider['key'] }}"></span></label>
                            <input id="p-{{ $slider['key'] }}" type="range" min="{{ $slider['min'] }}" max="{{ $slider['max'] }}" x-model="params.{{ $slider['key'] }}">
                        </div>
                    @endforeach
                </div>
            </details>

            {{-- Habillage --}}
            <details class="card control-group">
                <summary class="p-5 flex items-center justify-between">
                    <span class="font-display font-semibold">Habillage</span>
                    <span class="caret" style="color:var(--ink-alt)">›</span>
                </summary>
                <div class="px-5 pb-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="field-label" for="p-radius">Coins ronds (radius)</label>
                            <input id="p-radius" type="number" min="0" max="4096" class="input" placeholder="px" x-model="params.radius" x-bind:disabled="params.radiusMax">
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm pb-2"><input type="checkbox" x-model="params.radiusMax"> Cercle parfait</label>
                    </div>
                    <div class="grid grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="field-label" for="p-border">Contour (border)</label>
                            <input id="p-border" type="number" min="0" max="200" class="input" placeholder="épaisseur px" x-model="params.borderW">
                        </div>
                        <input type="color" x-bind:value="'#' + params.borderColor" x-on:input="params.borderColor = $event.target.value.slice(1)" aria-label="Couleur du contour">
                    </div>
                    <div class="grid grid-cols-2 gap-3 items-end">
                        <label class="inline-flex items-center gap-2 text-sm pb-2"><input type="checkbox" x-model="params.bgOn"> Fond (bg)</label>
                        <input type="color" x-bind:value="'#' + params.bg" x-on:input="params.bg = $event.target.value.slice(1); params.bgOn = true" aria-label="Couleur de fond">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="p-pad">Marge (pad)</label>
                            <input id="p-pad" type="number" min="0" max="500" class="input" placeholder="px" x-model="params.pad">
                        </div>
                        <div>
                            <label class="field-label" for="p-rotate">Rotation</label>
                            <select id="p-rotate" class="input" x-model="params.rotate">
                                <option value="0">0°</option>
                                <option value="90">90°</option>
                                <option value="180">180°</option>
                                <option value="270">270°</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-bind:checked="params.flip.includes('h')" x-on:change="params.flip = ($event.target.checked ? 'h' : '') + (params.flip.includes('v') ? 'v' : '')"> Miroir ↔</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-bind:checked="params.flip.includes('v')" x-on:change="params.flip = (params.flip.includes('h') ? 'h' : '') + ($event.target.checked ? 'v' : '')"> Miroir ↕</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="params.trim"> Rogner les bords vides (trim)</label>
                    </div>
                </div>
            </details>

            {{-- Incrustations --}}
            <details class="card control-group">
                <summary class="p-5 flex items-center justify-between">
                    <span class="font-display font-semibold">Filigrane & texte</span>
                    <span class="caret" style="color:var(--ink-alt)">›</span>
                </summary>
                <div class="px-5 pb-5 space-y-4">
                    <div>
                        <label class="field-label" for="p-wm">Filigrane (watermark, adresse d'un logo)</label>
                        <input id="p-wm" type="url" class="input font-address text-xs" placeholder="https://…/logo.png" x-model="params.wmUrl">
                    </div>
                    <div class="grid grid-cols-3 gap-3" x-show="params.wmUrl" x-cloak>
                        <div>
                            <label class="field-label" for="p-wmpos">Position</label>
                            <select id="p-wmpos" class="input" x-model="params.wmPos">
                                <option value="br">Bas droite</option>
                                <option value="bl">Bas gauche</option>
                                <option value="tr">Haut droite</option>
                                <option value="tl">Haut gauche</option>
                                <option value="center">Centre</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="p-wmop">Opacité <span x-text="params.wmOpacity + '%'"></span></label>
                            <input id="p-wmop" type="range" min="0" max="100" x-model="params.wmOpacity">
                        </div>
                        <div>
                            <label class="field-label" for="p-wmsize">Taille <span x-text="params.wmSize + '%'"></span></label>
                            <input id="p-wmsize" type="range" min="1" max="100" x-model="params.wmSize">
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="p-text">Texte incrusté (text)</label>
                        <input id="p-text" type="text" maxlength="200" class="input" placeholder="ex : © mon studio 2026" x-model="params.text">
                    </div>
                    <div class="grid grid-cols-3 gap-3 items-end" x-show="params.text" x-cloak>
                        <div>
                            <label class="field-label" for="p-textsize">Taille (px)</label>
                            <input id="p-textsize" type="number" min="6" max="400" class="input" x-model="params.textSize">
                        </div>
                        <div>
                            <label class="field-label" for="p-textpos">Position</label>
                            <select id="p-textpos" class="input" x-model="params.textPos">
                                <option value="br">Bas droite</option>
                                <option value="bl">Bas gauche</option>
                                <option value="tr">Haut droite</option>
                                <option value="tl">Haut gauche</option>
                                <option value="center">Centre</option>
                            </select>
                        </div>
                        <input type="color" x-bind:value="'#' + params.textColor" x-on:input="params.textColor = $event.target.value.slice(1)" aria-label="Couleur du texte">
                    </div>
                </div>
            </details>

            {{-- Pack favicon --}}
            <section class="card p-5 flex items-center justify-between gap-3">
                <div>
                    <p class="font-display font-semibold">Pack favicon</p>
                    <p class="text-xs mt-1" style="color:var(--ink-alt)">Toutes les tailles d'icône d'un site, dans un zip.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" x-model="params.packFavicon"> Activer</label>
            </section>
        </div>

        {{-- Colonne résultat --}}
        <div class="space-y-5 lg:sticky lg:top-20">

            {{-- Adresse --}}
            <section class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="field-label !mb-0">L'adresse — ta recette réutilisable</p>
                    <button type="button" class="text-xs font-semibold transition-colors" style="color:var(--accent)"
                            x-on:click="copyAddress()" x-bind:disabled="!activeSource">
                        <span x-show="!copied">Copier</span>
                        <span x-show="copied" x-cloak>Copié !</span>
                    </button>
                </div>
                <p class="address-box mt-3 p-4 min-h-[3.4rem]" x-show="activeSource" x-html="addressHtml"></p>
                <p class="address-box mt-3 p-4 text-center" style="color:var(--ink-alt)" x-show="!activeSource">
                    L'adresse s'écrira ici dès que tu donnes une image.
                </p>
            </section>

            {{-- Aperçu --}}
            <section class="card p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <p class="field-label !mb-0">Résultat</p>
                    <div class="text-xs font-address flex items-center gap-3" style="color:var(--ink-alt)" x-show="preview.url && !params.packFavicon" x-cloak>
                        <span x-show="activeSource?.bytes">avant <b x-text="formatBytes(activeSource?.bytes)"></b></span>
                        <span>après <b style="color:var(--accent)" x-text="formatBytes(preview.bytes)"></b></span>
                        <span x-text="preview.mime.replace('image/', '.')"></span>
                    </div>
                </div>

                {{-- État vide --}}
                <div class="mt-4 text-center py-16" x-show="!activeSource">
                    <img src="{{ asset('logo.svg') }}" alt="" class="w-14 h-14 mx-auto opacity-40">
                    <p class="mt-4 font-display font-semibold">Ici, ton image avant / après</p>
                    <p class="mt-1 text-sm" style="color:var(--ink-alt)">Dépose une image à gauche — ou pars de la photo de démo.</p>
                    <button type="button" class="btn btn-primary mt-5" x-on:click="useDemo()">Essayer avec la démo</button>
                </div>

                {{-- Pack favicon --}}
                <div class="mt-4 text-center py-14" x-show="activeSource && params.packFavicon" x-cloak>
                    <p class="text-4xl">🗂️</p>
                    <p class="mt-3 font-display font-semibold">Pack favicon prêt à générer</p>
                    <p class="mt-1 text-sm max-w-sm mx-auto" style="color:var(--ink-alt)">favicon.ico multi-tailles, png 16→512, apple-touch-icon, manifest et snippet HTML — d'un coup, dans un zip.</p>
                    <button type="button" class="btn btn-primary mt-5" x-on:click="downloadResult()">Télécharger le zip</button>
                </div>

                {{-- Comparateur --}}
                <div class="mt-4" x-show="activeSource && !params.packFavicon" x-cloak>
                    <div class="compare-wrap" x-ref="compare"
                         x-on:mousemove="$event.buttons === 1 && onCompareMove($event)"
                         x-on:mousedown="onCompareMove($event)"
                         x-on:touchmove.prevent="onCompareMove($event)">
                        <img x-bind:src="activeSource?.localUrl" alt="Image d'origine">
                        <div class="compare-after" x-bind:style="'clip-path: inset(0 0 0 ' + comparePos + '%)'">
                            <img x-bind:src="preview.url || activeSource?.localUrl" alt="Image transformée"
                                 x-bind:style="preview.loading ? 'opacity:.4; transition: opacity 200ms' : 'transition: opacity 200ms'">
                        </div>
                        <div class="compare-handle" x-bind:style="'left: calc(' + comparePos + '% - 1px)'"></div>
                    </div>
                    <div class="mt-2 flex justify-between text-xs font-address" style="color:var(--ink-alt)">
                        <span>← avant</span>
                        <span x-show="preview.loading">transformation…</span>
                        <span>après →</span>
                    </div>
                    <p class="mt-3 text-sm rounded-lg px-4 py-3" style="background:var(--danger-soft); color:var(--danger)"
                       x-show="preview.error" x-text="preview.error" x-cloak></p>
                </div>
            </section>

            {{-- Actions --}}
            <section class="card p-5 flex flex-wrap items-center gap-3" x-show="activeSource" x-cloak>
                <button type="button" class="btn btn-primary" x-on:click="downloadResult()" x-bind:disabled="preview.loading">Télécharger</button>
                <button type="button" class="btn btn-ghost" x-on:click="copyAddress()">Copier le lien</button>
                <button type="button" class="btn btn-ghost" x-show="sources.length > 1"
                        x-on:click="downloadBatchZip()" x-bind:disabled="batch.running">
                    <span x-show="!batch.running">Tout le lot en zip (<span x-text="sources.length"></span>)</span>
                    <span x-show="batch.running" x-cloak>Lot : <span x-text="batch.done"></span>/<span x-text="batch.total"></span>…</span>
                </button>
                <p class="w-full text-xs" style="color:var(--ink-alt)">
                    Le lien reste valable et mis en cache : colle-le dans un site, un mail ou envoie-le à ton dev.
                </p>
            </section>
        </div>
    </main>
</div>
@endsection
