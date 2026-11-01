# Media Reference Inspector v2.7.1

WordPress media reference inspection with optional, explicitly confirmed Media Explorer deletion by **codelith**.

## Install / test

1. Back up your WordPress site and database, or preferably use a staging site.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, then upload the *installable* ZIP containing the `media-reference-inspector/` folder.
3. If WordPress asks to replace the current plugin version, confirm only on the test site first.
4. Activate the plugin and open **Media → Media References**. The redesigned Dashboard is the first tab; Media Explorer is its own tab.
5. Check **Media Explorer** search/pagination, recent-gallery filters, reference footprint after one scan, **Scanner**, **Bulk Scan**, **Page Audit**, **Site Audit**, **Duplicates**, **Broken URLs**, **Help**, and Media Library list actions.
6. Compare a few known `wp-image-ID` matches (including similarly numbered IDs), and test the Site Audit Pause/Resume controls.
7. Only after successful testing, deploy the release using your existing WordPress.org plugin SVN repository under **codelith**.

## Version 2.7.1 maintenance update

This release addresses the reported WordPress Plugin Check warnings: adds missing translator notes, sanitizes and validates selected Media Explorer IDs before deletion review, and leaves the developer-only `TESTING.md` out of the installable ZIP. Other features from v2.7.0 remain intact.

## Version 2.7.0 highlights

- Premium mobile-responsive dashboard, real attachment counts, media-family mix, and filterable eight-item thumbnail gallery (no automatic scans).
- New paginated Media Explorer with search/type filters and cached-only scan statuses.
- New post-scan reference footprint grouped by source category (advisory, not a full dependency map).
- Media Library cached reference badges and a clear not-scanned state.
- Progressive Site Audit with Pause/Resume and per-item errors.
- Improved exact URL / attachment ID validation and selected core media-block identification.
- Safer CSV report values against spreadsheet formula injection.
- Existing integration scanners retained; reference detection is advisory, not proof of unused files.

## Media removal safety (new in 2.7)

- Media Explorer lets administrators select individual files, select all **on the current page** (maximum 18), or choose a single file's trash icon.
- The Review dialog fetches a server-side summary and cached usage warnings. Reference scans are **not** triggered automatically and a "no references found" result is **not proof of safety**.
- Review and confirmation are separate, nonce-protected AJAX requests. The second request can only act on exactly the attachment IDs stored for the reviewing administrator in a short-lived, one-time token.
- The user must check a risk acknowledgement and type `DELETE`. WordPress permission `delete_post` is checked both when reviewing and when applying removal.
- WordPress `wp_delete_attachment( $id, false )` uses **Media Trash only if the site has enabled `MEDIA_TRASH` and `EMPTY_TRASH_DAYS`**; otherwise this removes files **permanently**. The dialog clearly identifies which mode applies. No auto-deletion is performed.
- Make a full backup and verify on staging before using removal on a real site. Removing media referenced by WooCommerce or a theme can break content.

## Compatibility and privacy

Minimum WordPress 6.2, PHP 7.4. Existing tested-to metadata is inherited from v2.4.1; WordPress runtime integration testing is still required before public release. No external telemetry or automatic removal. Confirmed removals use native WordPress attachment deletion. A support email is sent only after an administrator explicitly submits the form.

## Developer checks

- `find . -name '*.php' -print0 | xargs -0 -n1 php -l`
- `node --check assets/js/admin.js`
- `node --check assets/js/site-audit.js`
- See `readme.txt` for the release changelog and supported reference sources.
