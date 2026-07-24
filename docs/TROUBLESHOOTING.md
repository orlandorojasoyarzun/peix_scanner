# Troubleshooting

## PHP `post_max_size` — Herd keeps resetting to 2M

### Symptom

Uploading an image file larger than ~2MB returns:

```
Illuminate\Http\Exceptions\PostTooLargeException
POST Content-Length of X bytes exceeds the limit of 2097152 bytes
```

The file `/Users/orlandorojas/Library/Application Support/Herd/config/php/84/php.ini` shows `post_max_size=10M` but PHP-FPM still enforces 2M.

### Root cause

PHP-FPM (the FastCGI process manager that Herd uses) loads its configuration once at startup. Editing `php.ini` and running `herd restart` does restart the processes, but PHP's opcache can prevent the new settings from being read if the processes don't fully terminate and re-spawn. The result is that the old 2M limit persists.

### Confirmed working fix

1. **Edit the php.ini**:
   ```
   /Users/orlandorojas/Library/Application Support/Herd/config/php/84/php.ini
   ```
   Set:
   ```ini
   upload_max_filesize=10M
   post_max_size=10M
   ```

2. **Full restart of Herd services**:
   ```bash
   herd restart
   ```

3. **If that doesn't work**, check which PHP-FPM config is actually loaded:
   ```bash
   php -i | grep "Loaded Configuration File"
   ```
   If it shows `(none)`, the ini isn't being picked up. Check if Herd has a different PHP version configured.

4. **If still failing**, the PHP-FPM master process needs a full kill + start. Since `sudo` is required, either:
   - Restart the entire Mac
   - Or switch from Herd to plain `php artisan serve` for local dev (doesn't have this issue)

### Verification

After restart, confirm the values are loaded:
```bash
php -i | grep -E "post_max_size|upload_max_filesize"
```

Should return:
```
post_max_size => 10M => 10M
upload_max_filesize => 10M => 10M
```

### Impact on the app

- The `ScanImageRequest` validates with `max:8192` (8MB) which is well within the 10M server limit.
- Fish scan images are typically 1-4MB (mobile camera photos). Label images are usually < 1MB.
- If a user uploads a >10M file, they'll hit the server limit before validation.

---

## Label scan routes to fish scan instead of label scan

### Symptom

User selects "Escanear etiqueta", uploads a label photo, but gets the error:
```
Cannot read "..." (this model does not support image input)
```

This means the request hit `scan.store` (fish route) instead of `scan.storeLabel` (label route).

### Root cause

The scan form has a hidden input `scan_type` and JavaScript that changes `form.action` to the correct route before submit. If `scan_type` is not set correctly (e.g., JavaScript hasn't run, or the Alpine.js `mode` variable wasn't updated), the form defaults to `route('scan.store')`.

The fish scan model (`nvidia/nemotron-nano-12b-v2-vl:free`) is vision-only and doesn't support image input in the same way, causing the error.

### How the fix works

Current implementation (scan.blade.php):
- Each upload box is a `<div>` with an `onclick` that sets `scan_type` and `form.action`
- A single `<input type="file" name="photo">` is shared between both modes
- On submit, the `onclick` handler on the button checks `scan_type` and sets `form.action` accordingly

The key is that clicking a box (fish or label) sets `scan_type` BEFORE the file dialog opens, via:
```js
boxFish.addEventListener('click', () => {
    scanType.value = 'fish';
    form.action = '{{ route('scan.store') }}';
    // ...
    fileInput.click();
});
```

### Debugging

Add a `dd()` in `ScanController::store()` to log `$request->input('scan_type')` and confirm which route is being hit.

### If the issue persists

The fallback is to check `$request->input('scan_type')` in `store()` and delegate to `storeLabel()` if it's a label scan — making the controller self-routing and independent of JS:
```php
if ($request->input('scan_type') === 'label') {
    return $this->storeLabel($request, $adapter);
}
```

---

## OpenRouter API — model doesn't support image input

### Symptom

```
Cannot read "..." (this model does not support image input)
```

### Context

The model being used is `nvidia/nemotron-nano-12b-v2-vl:free`. It's a vision-language model that should accept images. However, OpenRouter's free tier models sometimes have restrictions or inconsistent image handling.

### Diagnosis

1. Check the model card at https://openrouter.ai/models/nvidia/nemotron-nano-12b-v2-vl
2. Check if the `vision` capability is listed
3. Try with a different vision model (e.g., `meta-llama/llama-4-scout-17b-16e-instruct` if available free)

### If the model fails

The `OpenRouterVisionAdapter` can be swapped by changing the model name in `.env`:
```
OPENROUTER_MODEL=nvidia/nemotron-nano-12b-v2-vl:free
```

To test a different model, change that line and run:
```bash
php artisan ai:test storage/app/test-images/atlantic-salmon.jpg
```

---

## Wikipedia API — 403 Forbidden on species images

### Symptom

Species reference images return 403 or no image.

### Root cause

Wikipedia's API requires a `User-Agent` header identifying the client. Without it, requests are rejected.

### Fix

`WikipediaService` sets the header automatically:
```php
'User-Agent' => 'PeixScanner/1.0 (peix-scanner@example.com)'
```

If images still fail, check:
1. The domain is not blocked by the server's firewall
2. The Wikipedia API URL is correct: `https://en.wikipedia.org/w/api.php`

---

## Tests passing but feature broken

### Symptom

All 12 tests pass but the actual app fails (e.g., label scan goes to wrong route).

### Why this happens

Tests use mocked AI responses and fake file uploads. They don't test:
- JavaScript-driven form submission
- The `scan_type` hidden field being set correctly
- The dynamic `form.action` change

### What to do

1. Test manually in the browser with DevTools open
2. Check the Network tab to see which route the form POSTs to
3. Add `dd($request->all())` in `store()` to inspect the incoming request
4. The `TROUBLESHOOTING.md` section on label scan routing covers the JS-dependent flow
