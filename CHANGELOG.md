
---

## `CHANGELOG.md`

```markdown
# Changelog

## 1.0.0

- First public release.
- Added Tools  
  Media Hygiene screen.
- Broken attachments:
  - Detect attachments whose `_wp_attached_file` meta points to a file that does not exist under uploads.
- Large media files:
  - List attachments above a configured file size threshold (default 5 MB).
- Orphaned files on disk:
  - Walk the uploads directory.
  - Flag files that are not referenced by any attachment meta.
  - Cap the visible list to avoid browser abuse.
- Published content with no featured image:
  - Sample of published public post types missing a `_thumbnail_id`.
- Read only audit:
  - No deletions.
  - No automated cleanups.
- Licensed under GPL-3.0-or-later.
