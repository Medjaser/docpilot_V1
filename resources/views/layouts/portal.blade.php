<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#F5F7FB] overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dépôt de documents sous-traitant - DocPilot' }}</title>

    <!-- Google Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-800 bg-[#F5F7FB] flex flex-col min-h-screen overflow-x-hidden">
    <!-- Header portail -->
    <header class="bg-white border-b border-slate-200 py-3 px-4 shadow-2xs">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-2">
            @php
                $portalCompany ??= null;
                $portalInvalidToken ??= false;
            @endphp
            @if (! $portalInvalidToken && $portalCompany)
                <div class="flex items-center gap-3 min-w-0">
                    @if ($portalLogoUrl = $portalCompany->logoUrl())
                        <img src="{{ $portalLogoUrl }}" alt="Logo {{ $portalCompany->name }}" class="w-8 h-8 rounded-lg object-contain border border-slate-200 bg-white shrink-0" />
                    @else
                        <div class="w-8 h-8 rounded-lg bg-blue-600 font-bold text-xs text-white flex items-center justify-center shrink-0">
                            {{ $portalCompany->initials() }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-900 text-xs sm:text-sm leading-tight truncate">{{ $portalCompany->name }}</h3>
                        <p class="text-[10px] text-slate-400 leading-tight truncate">Espace sécurisé de dépôt sous-traitant</p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-semibold text-slate-600 truncate">Portail sécurisé de dépôt documentaire</span>
                </div>
            @endif
            <x-ui.logo variant="dark" size="sm" />
        </div>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-3 sm:p-6 pb-32 space-y-5">
        {{ $slot }}
    </main>

    <!-- Footer minimal -->
    <footer class="py-4 px-4 text-center text-[11px] text-slate-400 border-t border-slate-200/60 bg-white">
        Propulsé par <strong>DocPilot</strong> — Solution de gestion documentaire BTP
    </footer>

    <script>
        window.portalUploadState = function (wire) {
            return {
                uploading: false,
                progress: 0,
                networkError: null,
                initCameraInput(el) {
                    if (/Android|HuaweiBrowser|HUAWEI|HONOR|wv/i.test(navigator.userAgent || '')) {
                        el.removeAttribute('capture');
                    }
                },
                async handlePortalFile(event, index) {
                    const input = event.target;
                    const file = input && input.files ? input.files[0] : null;
                    if (!file) return;

                    this.networkError = null;

                    if (file.size > 10 * 1024 * 1024) {
                        input.value = '';
                        await wire.reportClientError(
                            index,
                            'Le fichier dépasse 10 Mo. Prenez la photo en qualité normale ou envoyez un PDF.'
                        );
                        return;
                    }

                    this.uploading = true;
                    this.progress = 25;

                    try {
                        const isRasterImage = /^image\/(jpeg|jpg|png)$/i.test(file.type || '') || /\.(jpe?g|png)$/i.test(file.name || '');
                        let dataUrl = null;
                        let finalName = file.name || 'photo-document.jpg';

                        if (isRasterImage) {
                            dataUrl = await this.compressImageIfPossible(file);
                            if (dataUrl && dataUrl.indexOf('data:image/jpeg') === 0) {
                                finalName = finalName.replace(/\.[^.]+$/, '') + '.jpg';
                            }
                        }

                        if (!dataUrl) {
                            dataUrl = await this.readFileAsDataUrl(file);
                        }

                        this.progress = 65;
                        input.value = '';
                        await wire.uploadFromMobile(index, dataUrl, finalName);
                        this.progress = 100;
                    } catch (err) {
                        this.networkError = "L'envoi a échoué, vérifiez votre connexion et réessayez";
                    } finally {
                        this.uploading = false;
                        this.progress = 0;
                    }
                },
                readFileAsDataUrl(file) {
                    return new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.onerror = () => reject(new Error('read_error'));
                        reader.readAsDataURL(file);
                    });
                },
                compressImageIfPossible(file) {
                    return new Promise((resolve) => {
                        const url = URL.createObjectURL(file);
                        const img = new Image();
                        img.onload = () => {
                            try {
                                const maxSide = 2000;
                                let w = img.naturalWidth || img.width;
                                let h = img.naturalHeight || img.height;
                                if (!w || !h) {
                                    URL.revokeObjectURL(url);
                                    resolve(null);
                                    return;
                                }
                                if (w > maxSide || h > maxSide) {
                                    const ratio = Math.min(maxSide / w, maxSide / h);
                                    w = Math.max(1, Math.round(w * ratio));
                                    h = Math.max(1, Math.round(h * ratio));
                                }
                                const canvas = document.createElement('canvas');
                                canvas.width = w;
                                canvas.height = h;
                                const ctx = canvas.getContext('2d');
                                ctx.fillStyle = '#FFFFFF';
                                ctx.fillRect(0, 0, w, h);
                                ctx.drawImage(img, 0, 0, w, h);
                                const compressed = canvas.toDataURL('image/jpeg', 0.84);
                                URL.revokeObjectURL(url);
                                resolve(compressed);
                            } catch (e) {
                                URL.revokeObjectURL(url);
                                resolve(null);
                            }
                        };
                        img.onerror = () => {
                            URL.revokeObjectURL(url);
                            resolve(null);
                        };
                        img.src = url;
                    });
                }
            };
        };
    </script>

    @livewireScripts
</body>
</html>
