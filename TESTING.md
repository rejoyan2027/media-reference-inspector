# Media Reference Inspector v2.7.1 - Staging Checklist

This build is intended for a test or staging WordPress site **before** any WordPress.org release. It never automatically deletes media and does not run reference scans in Dashboard or Media Explorer during initial page load.

## Update (do not install as a second plugin)

1. Back up files and database; prefer staging WordPress 6.2+ with PHP 7.4+.
2. Go to **Plugins > Add New Plugin > Upload Plugin** and select the **installable ZIP**.
3. When prompted, **Replace current with uploaded**. Plugin slug and main PHP basename must remain `media-reference-inspector/` and `media-reference-inspector.php`.
4. Open **Media > Media References**. Dashboard should show `2.7.1` at the top. If it does not, clear any admin/browser cache and verify the installed plugin folder.

## Visual checks

- At desktop, laptop, tablet and phone widths, check the top header, navigation scrolling, dark hero, four counters, recent gallery, status pills, media mix, quick actions and safety notice.
- Open **Media Explorer**, enter a search and choose Image / Video / Audio / Application, then navigate between pages. Verify filters are retained in pagination links.
- Confirm media cards include real image thumbnails when available; SVGs and non-image attachments may use a generic file icon.
- On Dashboard, type into **Find recent media** and test **All**, **Referenced**, **Review**, and **Not scanned**. These filters apply *only to the last eight records*; they should not trigger network requests.
- Confirm the sample coverage percent counts only media with a recent cached status, not all attachments.
- Try an empty Media Library (or an empty test site), plus a site containing only a few files.
- Keyboard: tab through links and filters, check focus styling; browser text size 200% and reduced-motion setting.

## Functional regression

- Scanner: scan images with known usage in content and Elementor (where supported) and files with no supported references. Check **Reference footprint** displays only after manual scan and shows aggregate evidence by source. A missing usage MUST NOT be interpreted as safe to delete.
- Bulk Scan: run 3-5 items; verify progress, stop, filters and CSV/JSON/HTML export.
- Site Audit: 5 items, Pause/Resume, errors; duplicate finder and broken URL checks.
- Media Library: cached labels on list-table actions and "Not scanned" on items without a short-lived cache.
- Verify WooCommerce/Elementor/ACF integrations only where relevant plugins are installed.
- Do not promote to live or upload to the WordPress.org SVN repository until the test passes.

## Automated local checks

- `find . -name '*.php' -print0 | xargs -0 -n1 php -l`
- `php tests/smoke-test.php`
- `php tests/dashboard-smoke.php`
- `node --check assets/js/admin.js && node --check assets/js/site-audit.js && node --check assets/js/dashboard.js`

The standalone dashboard test uses WordPress stubs; it is **not** a substitute for a real WordPress installation or a Plugin Check run.

## v2.7 Media Explorer cleanup - staging-only tests

**Use throwaway attachments ONLY** (upload spare test images). Default WordPress sites have Media Trash disabled and confirmed deletion is permanent.

1. Open Media Explorer and confirm checkboxes and per-tile red delete icons appear only in Explorer (not Dashboard).
2. Select 2 test files; check count, Select this page, Clear, mobile layout, and accessible checkbox labels.
3. Click Review deletion. Confirm the exact IDs, titles, cached statuses and Trash/permanent warning are shown.
4. Confirm that Cancel/Escape leaves files untouched and that the confirm button is disabled until both the risk box and `DELETE` input are supplied.
5. Inspect a known referenced media item; confirm it shows a red cached warning and remains manual-only, never auto selected.
6. Confirm one **disposable** file. With default MEDIA_TRASH disabled the file and sizes are permanently deleted, and only selected files are affected.
7. Confirm a group of spare test files; check the outcome message and the updated Explorer list.
8. If using a test site with MEDIA_TRASH enabled, confirm files are placed in Media Trash and are restorable from the Media Library.
9. Verify non-admin users cannot access the admin page or deletion AJAX actions, and that invalid, expired or replayed confirmation tokens fail.
10. Regression-check Scanner, Bulk Scan, Page Audit, Duplicates, Broken URLs, Site Audit, and status cache.
