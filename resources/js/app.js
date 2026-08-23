// app.js is built by Vite and served from /build/assets/*.js. The strict
// CSP (script-src 'self' + Alpine hash, no 'unsafe-inline') blocks any
// <script> that lives inside a Blade template, so this file carries the
// DOM-level glue the scan page used to keep in an inline <script>.

// Scan page: click the upload boxes, route the file picker to the right
// controller endpoint (scan.store for fish, scan.storeLabel for labels),
// then preview the chosen file inside the clicked box. Logic matches the
// original inline <script> byte-for-byte; only the location has changed.
function wireScanPage() {
    const form = document.getElementById('scan-form');
    const boxFish = document.getElementById('box-fish');
    const boxLabel = document.getElementById('box-label');
    const fileInput = document.getElementById('photo');
    const scanType = document.getElementById('scan_type');

    if (! form || ! boxFish || ! boxLabel || ! fileInput || ! scanType) {
        return;
    }

    const fishRoute = form.dataset.scanRoute || form.action;
    const labelRoute = form.dataset.scanLabelRoute;

    boxFish.addEventListener('click', () => {
        scanType.value = 'fish';
        form.action = fishRoute;
        boxFish.classList.add('border-emerald-500', 'bg-emerald-50/30');
        boxLabel.classList.remove('border-emerald-500', 'bg-emerald-50/30');
        fileInput.click();
    });

    boxLabel.addEventListener('click', () => {
        scanType.value = 'label';
        form.action = labelRoute;
        boxLabel.classList.add('border-emerald-500', 'bg-emerald-50/30');
        boxFish.classList.remove('border-emerald-500', 'bg-emerald-50/30');
        fileInput.click();
    });

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files && fileInput.files[0];
        if (! file) {
            return;
        }

        let toUpload = file;

        // iPhones default to HEIC/HEIF for camera output. Our pipeline only
        // accepts JPEG/PNG/WebP (GD-based reencode), so we transcode HEIC
        // in the browser before submission. heic2any is dynamically imported
        // so users who never upload HEIC don't pay the ~1.3 MB bundle cost.
        if (isHeic(file)) {
            const targetBox = scanType.value === 'fish' ? boxFish : boxLabel;
            showConverting(targetBox);

            try {
                const heic2any = (await import('heic2any')).default;
                const converted = await heic2any({
                    blob: file,
                    toType: 'image/jpeg',
                    quality: 0.85,
                });
                const blob = Array.isArray(converted) ? converted[0] : converted;
                const originalName = file.name.replace(/\.hei[cf]$/i, '.jpg');
                toUpload = new File([blob], originalName, {type: 'image/jpeg'});
            } catch (err) {
                console.error('HEIC conversion failed', err);
                clearConverting(targetBox);
                alert('No pudimos convertir la foto HEIC. Toma una captura de pantalla o usa la opción "Más compatible" en Settings > Camera > Formats.');
                fileInput.value = '';
                return;
            }
        }

        replaceFileInInput(fileInput, toUpload);
        previewFile(toUpload, scanType.value === 'fish' ? boxFish : boxLabel);
    });

    // Swap the submit button into its busy state once the user actually
    // presses it, so a double submit does not produce two scans. We also
    // block submit while a HEIC conversion is mid-flight (see the
    // fileInput.dataset.busy flag set above).
    const submitBtn = document.getElementById('scan-submit');
    const submitLabel = document.getElementById('scan-submit-label');
    const submitBusy = document.getElementById('scan-submit-busy');
    const submitHint = document.getElementById('scan-hint');

    if (submitBtn && submitLabel && submitBusy) {
        form.addEventListener('submit', (e) => {
            if (fileInput.dataset.busy === 'true') {
                e.preventDefault();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            submitLabel.classList.add('hidden');
            // Flip data-busy so the CSS [id$="-submit-busy"]:not(...)
            // selector stops matching and the inline display:flex wins.
            submitBusy.dataset.busy = 'true';
            submitBusy.style.display = 'flex';
            if (submitHint) submitHint.classList.remove('hidden');
        });
    }
}

// True when the picked file looks like an HEIC/HEIF container. iOS Safari
// and Chrome on Android both report `image/heic` or `image/heif`; the
// extension check covers older iOS releases that reported `image/jpeg`
// while still wrapping HEIF bitstreams.
function isHeic(file) {
    const type = (file.type || '').toLowerCase();
    if (type === 'image/heic' || type === 'image/heif') {
        return true;
    }
    return /\.hei[cf]$/i.test(file.name || '');
}

function replaceFileInInput(input, file) {
    // The standard way to swap a file in an <input type=file> is via
    // DataTransfer. Anything else (mutating input.files, etc.) is a no-op
    // for security reasons.
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
}

function previewFile(file, box) {
    const reader = new FileReader();
    reader.onload = (e) => {
        const existing = box.querySelector('.preview-img');
        if (existing) existing.remove();
        const status = box.querySelector('.converting-status');
        if (status) status.remove();
        const icons = box.querySelector('.box-icons');
        if (icons) icons.classList.add('hidden');
        const img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'preview-img rounded-xl max-h-64 object-contain mt-2';
        box.appendChild(img);
    };
    reader.readAsDataURL(file);
}

function showConverting(box) {
    // Mark the input as busy so the form submit handler waits, and show
    // an inline status so the user knows something is happening (the
    // HEIC→JPEG transcode takes 1-3 s on a phone).
    const input = document.getElementById('photo');
    if (input) input.dataset.busy = 'true';

    clearConverting(box);

    const status = document.createElement('div');
    status.className = 'converting-status mt-2 flex items-center gap-2 text-xs text-slate-600';
    status.innerHTML = `
        <svg class="animate-spin h-4 w-4 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Convirtiendo HEIC a JPEG…
    `;
    box.appendChild(status);
}

function clearConverting(box) {
    const input = document.getElementById('photo');
    if (input) delete input.dataset.busy;
    const status = box && box.querySelector('.converting-status');
    if (status) status.remove();
}

// Generic "swap submit button into busy state on submit" wiring.
// Each form gets a label <span id="*-submit-label"> (the visible text by
// default) and a busy <span id="*-submit-busy"> (the spinner + busy text).
// The busy span is hidden at load by an ID-specific CSS rule
// ([id$="-submit-busy"] { display: none !important }) because Tailwind's
// `hidden` utility loses to .inline-flex in the cascade order. We set
// display directly so it overrides the !important rule once submit fires.
function wireBusyButton(formId, buttonId, labelId, busyId, busyDisplay) {
    const form = document.getElementById(formId);
    const button = document.getElementById(buttonId);
    const label = document.getElementById(labelId);
    const busy = document.getElementById(busyId);

    if (! form || ! button || ! label || ! busy) {
        return;
    }

    form.addEventListener('submit', () => {
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
        label.classList.add('hidden');
        // Flip data-busy so the CSS [id$="-submit-busy"]:not(...)
        // selector stops matching and the inline display takes over.
        busy.dataset.busy = 'true';
        busy.style.display = busyDisplay;
    });
}

function wireConfirmPage() {
    wireBusyButton('confirm-form', 'confirm-submit', 'confirm-submit-label', 'confirm-submit-busy', 'flex');
    wireBusyButton('rescan-form', 'rescan-submit', 'rescan-submit-label', 'rescan-submit-busy', 'inline-flex');
}

// Species page tabs: switch the visible panel when a tab button is
// clicked. We can't use Alpine for this because even the @alpinejs/csp
// build internally uses new AsyncFunction() to evaluate expressions
// like `tab === 'nutricion'`, which is blocked by our strict CSP (no
// 'unsafe-eval'). Vanilla JS keeps the same UX without the CSP conflict.
function wireSpeciesTabs() {
    const root = document.querySelector('[data-species-tabs]');
    if (! root) {
        return;
    }

    const buttons = root.querySelectorAll('[data-tab-button]');
    const panels = root.querySelectorAll('[data-tab-panel]');
    if (! buttons.length || ! panels.length) {
        return;
    }

    const setActive = (name) => {
        // Pre-split the active / inactive class strings into the individual
        // tailwind utility tokens, because each button declares them as a
        // single space-separated string in its data attribute. Comparing
        // the whole string to individual tokens (which is what the previous
        // version did) NEVER matched, so removed-buttons stayed "active"
        // and the tab row grew more pressed-looking with every click.
        const toTokenSet = (s) => new Set((s || '').split(/\s+/).filter(Boolean));
        const activeTokens = toTokenSet(buttons[0].dataset.tabActiveClass);
        const inactiveTokens = toTokenSet(buttons[0].dataset.tabInactiveClass);

        buttons.forEach((btn) => {
            const isActive = btn.dataset.tabButton === name;
            btn.className = btn.className
                .split(/\s+/)
                .filter((c) => c && ! activeTokens.has(c) && ! inactiveTokens.has(c))
                .join(' ');
            if (isActive) {
                activeTokens.forEach((t) => btn.classList.add(t));
            } else {
                inactiveTokens.forEach((t) => btn.classList.add(t));
            }
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.tabPanel === name;
            // The species page panels are mutually exclusive: hide
            // every panel that is not the active one. We do NOT rely on
            // [x-cloak] here (we removed the Alpine attribute) and we
            // do NOT add the x-cloak CSS rule for that reason either.
            panel.style.display = isActive ? '' : 'none';
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            setActive(btn.dataset.tabButton);
        });
    });

    // Pick the first tab as the default. The HTML may have another tab
    // marked with data-default-tab on the root element, in which case
    // we honour that. Otherwise we fall back to the first button in
    // DOM order.
    const defaultName = root.dataset.defaultTab || buttons[0].dataset.tabButton;
    setActive(defaultName);
}

// Species page explanation panel: same story as wireSpeciesTabs — we
// can't use Alpine because of strict CSP, so this is vanilla JS that
// POSTs to the species explain endpoint and renders the response.
function wireSpeciesExplanation() {
    const panel = document.querySelector('[data-explanation-panel]');
    if (! panel) {
        return;
    }

    const cta = panel.querySelector('[data-explanation-cta]');
    const result = panel.querySelector('[data-explanation-result]');
    const text = panel.querySelector('[data-explanation-text]');
    if (! cta || ! result || ! text) {
        return;
    }

    let config;
    try {
        config = JSON.parse(panel.dataset.explanationConfig || '{}');
    } catch (e) {
        config = {};
    }

    // If the cached explanation is already in the payload, render it
    // immediately so the user does not have to click to see what the
    // AI said last time.
    if (config.has_cached_explanation && config.cached_explanation) {
        text.textContent = config.cached_explanation;
        result.classList.remove('hidden');
        cta.classList.add('hidden');
    }

    cta.addEventListener('click', async () => {
        if (cta.disabled) {
            return;
        }
        cta.disabled = true;
        cta.classList.add('opacity-60', 'cursor-not-allowed');
        try {
            const r = await fetch(config.url_explain, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });
            const d = await r.json();
            text.textContent = d.explanation || 'No se pudo generar la explicación. Las recomendaciones automáticas siguen aplicando.';
        } catch (e) {
            text.textContent = 'No se pudo generar la explicación. Las recomendaciones automáticas siguen aplicando.';
        } finally {
            result.classList.remove('hidden');
            cta.classList.add('hidden');
        }
    });
}

// Each page registers the DOM glue it needs. Pages opt in by exposing a
// known id; if the id is missing, wireXxx() returns early and does nothing,
// so a single bundle can power every page without per-page conditionals.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        wireScanPage();
        wireConfirmPage();
        wireSpeciesTabs();
        wireSpeciesExplanation();
    });
} else {
    wireScanPage();
    wireConfirmPage();
    wireSpeciesTabs();
    wireSpeciesExplanation();
}
