# Fullworks Gravity Image Resize

Downscales images uploaded through **Gravity Forms file-upload fields** — the ones
no media-library optimiser can reach.

## Why this exists

Gravity Forms writes upload-field files with `move_uploaded_file()` straight into
`uploads/gravity_forms/`:

```php
// gravityforms/includes/fields/class-gf-field-fileupload.php:1195
if ( move_uploaded_file( $file['tmp_name'], $target['path'] ) ) {
```

They never pass through `wp_handle_upload()`, never become attachments, and never
meet the WordPress image-size pipeline. Imsanity, Smush, ShortPixel and EWWW all
hook the media library, so **none of them can see these files** however they are
configured. A 22MB phone photo is stored exactly as it arrived.

Measured on one live site: **950 files, 4MB average, 3.8GB** — more than half of
the entire installation.

Post Image fields *do* create attachments and are deliberately left alone.

## Install

```bash
composer install && npm install
npm run env:start
```

## Use

Settings live at **Settings → Gravity Image Resize**: a maximum dimension
(2000px by default) and an on/off switch for resizing at submission time.

Existing files are handled from WP-CLI, so every destructive step is deliberate
and can be previewed:

```bash
wp fwgir scan                       # what is on disk, orphaned, oversized
wp fwgir resize                     # preview
wp fwgir resize --execute           # act
wp fwgir orphans                    # list unreferenced files
wp fwgir orphans --delete --confirm # delete them
```

**Resizing and deletion are irreversible.** Back up `uploads/gravity_forms`
yourself first — the plugin does not do it for you.

An *orphan* is a file that no entry **and no saved draft** references. Drafts are
checked on purpose: a part-completed submission has files on disk that no entry
points at yet, and treating those as orphans would delete a customer's upload
mid-form.

### Run as the web user, never `--allow-root`

`resize --execute` writes files. `WP_Image_Editor::save()` writes a temp file and
renames it, so running as root leaves **root-owned files in `wp-content/uploads`**
that php-fpm (running as `www-data`) cannot manage — which surfaces as 502s, on
customers' uploaded photos.

From the host:

```bash
docker exec -u www-data <container> wp fwgir scan --path=/var/www/html
```

Already inside the container as root? `www-data` usually has `nologin` as its
shell, so plain `su` refuses. Override it:

```bash
su -s /bin/sh www-data -c "wp fwgir resize --path=/var/www/html"
```

## Development

```bash
npm run lint:php          # PHPCS (WordPress standards)
npm run test              # full suite
npm run test:unit         # logic only
npm run test:integration  # real files through WP_Image_Editor
composer run phpcs-security
composer run phpcompat
```

Tests use genuine image files rather than mocks — the whole point of the plugin
is what `WP_Image_Editor` does to bytes on disk, and a mock would only test the
mock.

## Filters

| Filter | Purpose |
| --- | --- |
| `fwgir_max_dimension` | Override the configured longest edge |
| `fwgir_resize_on_upload` | Enable/disable resizing at submission |
| `fwgir_resized_upload` | Fires after each file is considered (path, result, entry) |
