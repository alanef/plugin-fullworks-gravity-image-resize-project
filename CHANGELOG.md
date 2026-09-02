# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-02

### Added

- **Resizing of Gravity Forms upload-field images on submission.** Gravity Forms
  writes these files with `move_uploaded_file()` directly into
  `uploads/gravity_forms/` — they never pass through `wp_handle_upload()`, never
  become attachments, and never meet the image-size pipeline. That is why no
  media-library optimiser (Imsanity, Smush, ShortPixel, EWWW) can see them
  however it is configured, and why a 22MB phone photo is stored exactly as it
  arrived. Observed on a live site: 950 files averaging 4MB, 3.8GB in total, over
  half of the whole site.
- Configurable maximum dimension, default 2000px, clamped to 200–10000 so a
  mistyped setting degrades rather than disabling resizing or producing a 0px
  image. Filterable via `fwgir_max_dimension`.
- `wp fwgir scan` — what is on disk, what is orphaned, what is oversized.
- `wp fwgir resize` — downscale the existing backlog. Previews by default and
  needs `--execute` to act, because it is lossy and irreversible.
- `wp fwgir orphans` — files no entry *or saved draft* references. Drafts are
  checked deliberately: a part-completed submission has files on disk that no
  entry points at yet, and treating those as orphans would delete a customer's
  upload mid-form. Deletion needs both `--delete` and `--confirm`.

### Notes

- Post Image fields are deliberately untouched: those do create attachments, so
  WordPress's own sizes and any media optimiser already apply.
- `Uploads::url_to_path()` refuses anything resolving outside the uploads
  directory, so a tampered entry value cannot aim the resizer at another file.
- Resizing is idempotent — a second pass over an already-small image leaves it
  byte-identical rather than silently recompressing on every run.
