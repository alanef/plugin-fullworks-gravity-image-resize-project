=== Fullworks Gravity Image Resize ===
Contributors: alanfuller
Tags: gravity forms, images, resize, uploads, storage
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Downscales images uploaded through Gravity Forms file-upload fields, which no media-library optimiser can see.

== Description ==

Gravity Forms writes file-upload fields straight to disk with `move_uploaded_file()`. They never pass through `wp_handle_upload()`, never become attachments, and never meet the WordPress image-size pipeline.

That means Imsanity, Smush, ShortPixel, EWWW and every other media-library optimiser **cannot see them**, however they are configured. A 22MB photo from a phone is stored exactly as it arrived, forever.

This plugin resizes those files as submissions arrive, and gives you WP-CLI tools for the backlog you already have.

= What it does =

* Downscales images on submission so the longest edge fits a limit you set (2000px by default)
* Keeps aspect ratio and format; nothing is cropped and a PNG stays a PNG
* Leaves non-images alone — PDFs and documents are untouched
* Ignores Post Image fields, which do become attachments and are already handled by WordPress

= Existing files =

Handled from WP-CLI, so every destructive step is deliberate and previewable:

`wp fwgir scan`
`wp fwgir resize`
`wp fwgir resize --execute`
`wp fwgir orphans`
`wp fwgir orphans --delete --confirm`

**Resizing and deleting are irreversible.** Back up your `uploads/gravity_forms` directory first; the plugin does not do it for you.

An orphan is a file no entry and no saved draft references. Drafts are checked deliberately: a part-completed submission has files on disk that no entry points at yet, and deleting those would take a customer's upload mid-form.

== Frequently Asked Questions ==

= Why will Imsanity not do this? =

Imsanity hooks `wp_handle_upload`, the media library path. Gravity Forms upload fields never go through it.

= Will it touch my existing images? =

Not unless you run `wp fwgir resize --execute`. On submission it only handles new uploads.

= Is it reversible? =

No. Resizing is lossy and overwrites the original. Back up first.

== Changelog ==

= 1.0.0 =
* Initial release
