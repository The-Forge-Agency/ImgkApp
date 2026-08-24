import { zipSync } from 'fflate';

/**
 * Le studio imgk : on règle tout à la souris, l'adresse s'écrit toute seule.
 * L'aperçu est la vraie image servie par l'API — pas une simulation.
 */
export default function imgkStudio(config) {
    return {
        base: config.base,
        csrf: config.csrf,
        presets: config.presets,

        sources: [],
        activeIndex: -1,
        urlInput: '',
        dragOver: false,
        uploading: false,
        uploadError: '',

        params: null,
        activePreset: '',

        preview: { loading: false, error: '', url: '', bytes: 0, mime: '' },
        comparePos: 50,
        copied: false,
        batch: { running: false, done: 0, total: 0 },

        debounceTimer: null,

        init() {
            this.params = this.defaultParams();

            this.$watch('params', () => {
                this.activePresetStillMatches();
                this.schedulePreview();
            });
            this.$watch('activeIndex', () => this.schedulePreview());

            window.addEventListener('paste', (e) => this.onPaste(e));
        },

        defaultParams() {
            return {
                w: '', h: '', fit: 'cover', dpr: '1', max: '', crop: '',
                output: '', q: '', optimized: false, maxsize: '', strip: false,
                conversion: '', duotoneDark: '0d1017', duotoneLight: '5b8cff',
                blur: 0, sharpen: 0, brightness: 0, contrast: 0, saturation: 0,
                radius: '', radiusMax: false, borderW: '', borderColor: '5b8cff',
                bgOn: false, bg: 'ffffff', pad: '', rotate: 0, flip: '', trim: false,
                wmUrl: '', wmPos: 'br', wmOpacity: 50, wmSize: 20,
                text: '', textColor: 'ffffff', textSize: 32, textPos: 'br',
                packFavicon: false,
            };
        },

        get activeSource() {
            return this.sources[this.activeIndex] ?? null;
        },

        // ————— Sources —————

        async onDrop(event) {
            this.dragOver = false;
            await this.addFiles(event.dataTransfer?.files ?? []);
        },

        async onPick(event) {
            await this.addFiles(event.target.files ?? []);
            event.target.value = '';
        },

        async onPaste(event) {
            const items = [...(event.clipboardData?.items ?? [])];
            const images = items.filter((i) => i.kind === 'file');

            if (images.length) {
                event.preventDefault();
                await this.addFiles(images.map((i) => i.getAsFile()).filter(Boolean));
            }
        },

        async addFiles(fileList) {
            this.uploadError = '';

            for (const file of [...fileList]) {
                await this.uploadFile(file);
            }
        },

        async uploadFile(file) {
            this.uploading = true;

            try {
                const body = new FormData();
                body.append('file', file);

                const response = await fetch(`${this.base}/api/upload`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body,
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? data.message ?? "L'upload a échoué.");
                }

                this.sources.push({
                    name: file.name || 'presse-papier',
                    url: data.url,
                    bytes: data.bytes,
                    localUrl: URL.createObjectURL(file),
                });
                this.activeIndex = this.sources.length - 1;
            } catch (error) {
                this.uploadError = error.message;
            } finally {
                this.uploading = false;
            }
        },

        useUrl() {
            const url = this.urlInput.trim();

            if (!/^https?:\/\//i.test(url)) {
                this.uploadError = "Il faut une adresse complète en http(s)://";

                return;
            }

            this.uploadError = '';
            this.sources.push({ name: url.split('/').pop() || url, url, bytes: 0, localUrl: url });
            this.activeIndex = this.sources.length - 1;
            this.urlInput = '';
        },

        useDemo() {
            this.uploadError = '';
            this.sources.push({
                name: 'photo-demo.jpg',
                url: `${this.base}/demo/photo.jpg`,
                bytes: 0,
                localUrl: `${this.base}/demo/photo.jpg`,
            });
            this.activeIndex = this.sources.length - 1;
        },

        removeSource(index) {
            this.sources.splice(index, 1);

            if (this.activeIndex >= this.sources.length) {
                this.activeIndex = this.sources.length - 1;
            }
        },

        // ————— Adresse —————

        queryParts(sourceUrl) {
            const p = this.params;
            const parts = [['url', sourceUrl]];
            const push = (key, value) => parts.push([key, String(value)]);

            if (p.w) push('w', p.w);
            if (p.h) push('h', p.h);
            if (p.w && p.h && p.fit !== 'cover') push('fit', p.fit);
            if (p.dpr !== '1') push('dpr', p.dpr);
            if (p.max) push('max', p.max);
            if (p.crop) push('crop', p.crop);
            if (p.output) push('output', p.output);
            if (p.q) push('q', p.q);
            if (p.optimized) push('optimized', 'true');
            if (p.maxsize) push('maxsize', p.maxsize);
            if (p.strip) push('strip', 'true');

            if (p.conversion) push('conversion', p.conversion);
            if (p.conversion === 'duotone') push('duotone', `${p.duotoneDark},${p.duotoneLight}`);
            if (Number(p.blur) > 0) push('blur', p.blur);
            if (Number(p.sharpen) > 0) push('sharpen', p.sharpen);
            if (Number(p.brightness) !== 0) push('brightness', p.brightness);
            if (Number(p.contrast) !== 0) push('contrast', p.contrast);
            if (Number(p.saturation) !== 0) push('saturation', p.saturation);

            if (p.radiusMax) push('radius', 'max');
            else if (p.radius) push('radius', p.radius);
            if (p.borderW) push('border', `${p.borderW},${p.borderColor}`);
            if (p.bgOn) push('bg', p.bg);
            if (p.pad) push('pad', p.pad);
            if (Number(p.rotate) !== 0) push('rotate', p.rotate);
            if (p.flip) push('flip', p.flip);
            if (p.trim) push('trim', 'true');

            if (p.wmUrl) {
                push('watermark', p.wmUrl);
                if (p.wmPos !== 'br') push('wm_pos', p.wmPos);
                if (Number(p.wmOpacity) !== 50) push('wm_opacity', p.wmOpacity);
                if (Number(p.wmSize) !== 20) push('wm_size', p.wmSize);
            }

            if (p.text) {
                push('text', p.text);
                if (p.textColor !== 'ffffff') push('text_color', p.textColor);
                if (Number(p.textSize) !== 32) push('text_size', p.textSize);
                if (p.textPos !== 'br') push('text_pos', p.textPos);
            }

            if (p.packFavicon) push('pack', 'favicon');

            return parts;
        },

        addressFor(sourceUrl) {
            const query = this.queryParts(sourceUrl)
                .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
                .join('&');

            return `${this.base}/?${query}`;
        },

        get address() {
            if (!this.activeSource) {
                return '';
            }

            return this.addressFor(this.activeSource.url);
        },

        get addressHtml() {
            if (!this.activeSource) {
                return '';
            }

            const host = this.base.replace(/^https?:\/\//, '');
            const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

            const query = this.queryParts(this.activeSource.url)
                .map(([k, v]) => `<span class="u-key">${esc(k)}</span><span class="u-sep">=</span><span class="u-val">${esc(encodeURIComponent(v))}</span>`)
                .join('<span class="u-sep">&amp;</span>');

            return `<span class="u-host">${esc(host)}/</span><span class="u-sep">?</span>${query}`;
        },

        // ————— Aperçu —————

        schedulePreview() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.refreshPreview(), 450);
        },

        async refreshPreview() {
            if (!this.activeSource || this.params.packFavicon) {
                return;
            }

            this.preview.loading = true;
            this.preview.error = '';

            const address = this.address;

            try {
                const response = await fetch(address, { headers: { Accept: 'image/avif,image/webp,image/*,*/*' } });

                if (address !== this.address) {
                    return; // un réglage a changé entre-temps, une autre requête suit
                }

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.error ?? `Erreur ${response.status}`);
                }

                const blob = await response.blob();

                if (this.preview.url) {
                    URL.revokeObjectURL(this.preview.url);
                }

                this.preview.url = URL.createObjectURL(blob);
                this.preview.bytes = blob.size;
                this.preview.mime = blob.type;
            } catch (error) {
                this.preview.error = error.message;
            } finally {
                this.preview.loading = false;
            }
        },

        // ————— Presets —————

        applyPreset(name) {
            const preset = this.presets[name];

            if (!preset) {
                return;
            }

            this.params = this.defaultParams();

            const map = {
                w: 'w', h: 'h', fit: 'fit', output: 'output', q: 'q', max: 'max',
                bg: 'bg', maxsize: 'maxsize', conversion: 'conversion',
            };

            for (const [key, value] of Object.entries(preset)) {
                if (key === 'radius' && value === 'max') this.params.radiusMax = true;
                else if (key === 'strip') this.params.strip = true;
                else if (key === 'optimized') this.params.optimized = true;
                else if (key === 'bg') { this.params.bgOn = true; this.params.bg = String(value); }
                else if (map[key]) this.params[map[key]] = String(value);
            }

            this.activePreset = name;
        },

        activePresetStillMatches() {
            // Dès qu'on retouche un réglage, le chip preset se désélectionne naturellement.
            this.activePreset = this.activePreset && this.presetMatches(this.activePreset) ? this.activePreset : '';
        },

        presetMatches(name) {
            const preset = this.presets[name];

            return Object.entries(preset).every(([key, value]) => {
                if (key === 'radius' && value === 'max') return this.params.radiusMax;
                if (key === 'strip') return this.params.strip;
                if (key === 'optimized') return this.params.optimized;
                if (key === 'bg') return this.params.bgOn && this.params.bg === String(value);

                return String(this.params[key] ?? '') === String(value);
            });
        },

        reset() {
            this.params = this.defaultParams();
            this.activePreset = '';
        },

        // ————— Actions —————

        async copyAddress() {
            await navigator.clipboard.writeText(this.address);
            this.copied = true;
            setTimeout(() => (this.copied = false), 1600);
        },

        extensionFor(mime) {
            return {
                'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp',
                'image/avif': 'avif', 'image/gif': 'gif', 'image/x-icon': 'ico',
                'application/pdf': 'pdf', 'application/zip': 'zip',
            }[mime] ?? 'bin';
        },

        async downloadResult() {
            const response = await fetch(this.address);

            if (!response.ok) {
                return;
            }

            const blob = await response.blob();
            const name = (this.activeSource.name.replace(/\.[a-z0-9]+$/i, '') || 'image');
            this.triggerDownload(blob, `imgk-${name}.${this.extensionFor(blob.type)}`);
        },

        async downloadBatchZip() {
            this.batch = { running: true, done: 0, total: this.sources.length };

            try {
                const entries = {};

                for (const source of this.sources) {
                    const response = await fetch(this.addressFor(source.url));

                    if (response.ok) {
                        const buffer = new Uint8Array(await response.arrayBuffer());
                        const stem = source.name.replace(/\.[a-z0-9]+$/i, '') || `image-${this.batch.done + 1}`;
                        const ext = this.extensionFor(response.headers.get('Content-Type') ?? '');
                        entries[`${stem}.${ext}`] = buffer;
                    }

                    this.batch.done++;
                }

                const zipped = zipSync(entries, { level: 0 });
                this.triggerDownload(new Blob([zipped], { type: 'application/zip' }), 'imgk-lot.zip');
            } finally {
                this.batch.running = false;
            }
        },

        triggerDownload(blob, filename) {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 4000);
        },

        // ————— Comparateur —————

        onCompareMove(event) {
            const rect = this.$refs.compare.getBoundingClientRect();
            const x = (event.touches?.[0]?.clientX ?? event.clientX) - rect.left;
            this.comparePos = Math.min(100, Math.max(0, (x / rect.width) * 100));
        },

        formatBytes(bytes) {
            if (!bytes) return '—';
            if (bytes < 1024) return `${bytes} o`;
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;

            return `${(bytes / 1024 / 1024).toFixed(2)} Mo`;
        },
    };
}
