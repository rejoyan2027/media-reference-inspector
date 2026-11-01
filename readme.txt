=== Media Reference Inspector ===
Contributors: codelith
Tags: media library, media usage, attachments, references, admin tools
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 2.7.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find where Media Library items are referenced before you replace or remove them.

== Description ==

Media Reference Inspector scans WordPress media references without modifying content. Administrators can also optionally select and remove files from Media Explorer, after reviewing a warning and explicitly confirming the action.

Scans never automatically delete files or modify content. Manual removal requires the administrator to review exactly which files will be removed, acknowledge the risk, and type DELETE. When Media Trash is disabled in WordPress, removal is permanent. No tables, tracking or analytics are added. Scan results are advisory: “No supported references found” does not prove that a file is unused.

= Scanner =

Inspect one media item at a time with a professional WordPress-native workflow. In 2.5, candidate content URLs and wp-image-ID classes are verified more strictly to reduce partial matches. Supported checks include:

* Post, page, custom post type, synced/reusable block, and other post content or excerpts containing the primary URL, generated image-size URLs, or the WordPress `wp-image-ID` class.
* Core Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks that store attachment IDs.
* Featured images stored in `_thumbnail_id` metadata.
* Navigation menu custom URLs.
* WordPress Site Icon and Site Logo.
* Active theme Custom Logo, Header Image, and Background Image settings.
* Core media widgets and block widgets.
* WooCommerce product gallery and product-category thumbnail attachment IDs.
* Elementor saved media-control references when the saved JSON confirms the attachment ID.
* Advanced Custom Fields (ACF) Image, File, and Gallery fields when ACF confirms the field type and saved attachment ID.
* Reference evidence details including confidence, source category, and context for supported results.

= Bulk Scan =

Bulk Scan processes media items one at a time through bounded AJAX requests. It supports media type, upload-age, search, maximum-item filters, selected attachment IDs, live progress, potential-unused review filtering, reference evidence and file-health filtering, result sorting, CSV/JSON export, and a printable HTML audit report. CSV cells beginning with spreadsheet formula characters are escaped.

= Broken URLs =

Run a bounded, read-only scan for local WordPress uploads URLs saved in post content or excerpts where the corresponding local file no longer exists. The scanner checks local uploads paths only and does not make external network requests.

= Page & Post Audit =

Audit a post or page to list supported media attachment IDs, flag broken attachment IDs, and review local file health without modifying content.

= Duplicate Finder =

Run an on-demand, bounded exact-file hash scan of recent local Media Library files. The tool reports exact matches only and never deletes files.

= Integration Coverage =

The admin interface reports supported coverage for WordPress Core, WooCommerce, Elementor, and ACF so administrators can see which integration-aware checks are active on the site.

= Dashboard =

The responsive admin overview uses read-only attachment metadata to display total attachments, images, uploads in the last 30 days, a media-family breakdown, and a scan-coverage summary restricted to the eight most recent attachments. Its thumbnail gallery can be filtered by title or cached scan status without server requests or scans. The coverage percentage is not a full-library usage or safety score. No automatic reference scan occurs on dashboard load.

= Media Explorer =

A separate paginated, searchable gallery of attachment records with media-type filtering, image thumbnails, cached-only reference states, and links to inspect or edit. In 2.7 administrators can check individual items or all available items on the current page (up to 18), or use a single-tile delete action. Before WordPress removes anything, the plugin obtains a one-time server-side review of the exact selected IDs with cached usage warnings, explains whether this site uses Media Trash or permanent deletion, and requires a risk acknowledgement plus a typed DELETE confirmation. Only the approved IDs can be acted upon. No automatic removal or background scans occur.

= Reference Footprint =

After an explicit one-item scan, a visual evidence summary groups supported references by source category, showing a count and brief labels for each category. This reflects only references returned by supported scanners; it is not a complete dependency graph or proof that a file is unused.

= Media Library Status =

Reference checks store a short-lived local scan-status cache for inspected attachments. Media Library workflows can show a recent reference count/status and offer an explicit re-scan without running a heavy scan on every page load.

= Media Impact Preview =

Single-item scans summarize supported reference categories that could be affected before a media item is replaced or removed. The preview is advisory and read-only.

= Extended Metadata and Builder Coverage =

2.4 adds bounded validation for media-like post meta, term meta, selected option values, known SEO/social image metadata, and supported Bricks, Divi, and Beaver Builder saved data. Candidate values are validated before a reference is reported.

= Site Audit Summary =

Run an explicit progressive audit of up to 100 recent Media Library items. Each item is processed in a separate nonce-protected AJAX request, with Pause and Resume (while keeping the page open), reference counts, file-health review, and per-item error reporting. For broken local URLs and duplicate groups, use the separate dedicated tabs. Results are not automatically saved to an audit history.

= Support =

The Help tab includes an explicit support form for bug reports, feature requests, and questions. Nothing is sent until an administrator submits the form. The message and reply email are sent through the site's configured WordPress mail system to plugin support.

= Privacy =

Media Reference Inspector does not send media or site data to analytics or telemetry services and stores no scan history. Short-lived scan-status cache data stays in WordPress. The Help support form sends a message only after an administrator explicitly submits it.

== Installation ==

1. Install Media Reference Inspector from the WordPress Plugin Directory, or upload the v2.7.1 test ZIP to a staging site first.
2. Activate the plugin.
3. Go to Media > Media References; the redesigned Dashboard opens first.
4. Use Media Explorer to search and, only if needed, manually remove selected media after two-step confirmation; use Scanner for one media item, Bulk Scan for a batch audit, Broken URLs for missing local uploads files referenced in content, Page Audit for post/page media review, Duplicates for exact-file checks, or Help for documentation and support.

== Screenshots ==

1. Scanner and integration coverage: inspect supported references across WordPress Core, WooCommerce, Elementor, and ACF.
2. Bulk reference scan: filter media, run bounded read-only scans, and export audit results.
3. Page & post media audit: review supported media references and local file health for posts and pages.
4. Help & support: review scanner guidance and submit an explicit administrator support request. The example email value is obscured for privacy.
5. Broken local media URLs: find supported content that references local uploads files that no longer exist.

== Frequently Asked Questions ==

= Does this plugin delete media files? =

Scans never delete anything. Since 2.7, authorized administrators can explicitly select attachments for deletion in Media Explorer. They must review the selected filenames and known scan statuses, accept the warning, and type DELETE. This calls the standard WordPress wp_delete_attachment() API. If MEDIA_TRASH and EMPTY_TRASH_DAYS are enabled in WordPress, files go to Media Trash; otherwise they are permanently removed, including associated uploaded files. Make a backup first. Selection and deletion are never automatic.

= Does “No supported references found” mean the file is safe to delete? =

No. Themes, builders, custom tables, external applications, caches, or custom code may store references outside the supported checks.

= Which WordPress blocks are detected by attachment ID? =

Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks are detected when their saved attributes reference the selected attachment.

= Does the plugin detect resized image URLs? =

Yes. It checks generated image-size URLs recorded in WordPress attachment metadata as well as the primary attachment URL.

= Does Broken URLs check external websites? =

No. It checks URLs under the site's own WordPress uploads base URL and verifies the corresponding local uploads file path. It does not request remote URLs.

= How does the support form work? =

An administrator can explicitly submit a bug report, feature request, or question from the Help tab. The form sends only the entered reply email and message plus the plugin and WordPress version through the site's configured WordPress mail system.

= Who can use the scanner? =

The scanner and support form require the `manage_options` capability because scan results may reveal references to non-public content.

== Changelog ==

= 2.7.1 =
* Added clear translator comments for dashboard, selection count, and untitled media ID placeholders.
* Sanitized and strictly validated selected Media Explorer IDs before review; rejected malformed and nested input instead of coercing it to attachment IDs.
* Excluded developer-only TESTING.md from the installable WordPress plugin ZIP (available in source package).
* Kept the existing two-step deletion review, capability checks, confirmation token, and Media Trash support unchanged.


= 2.7.0 =
* Added permission-checked, nonce-protected media removal to Media Explorer with per-tile actions and checkboxes for up to 18 items per page.
* Added page-level Select All, Clear, selected-item count and a responsive review dialog.
* Added a server-side, five-minute, current-user-scoped review token binding the exact selected IDs to a separate confirmation request.
* Requires risk acknowledgment plus typing DELETE; shows cached reference warnings and distinguishes Media Trash from permanent deletion.
* Uses WordPress wp_delete_attachment(), checks delete_post permissions again before each removal, and reports partial failures.
* No automatic deletion or background scans; all reference scan, audit and reporting tools remain read-only.

= 2.6.0 =
* Redesigned the Dashboard with a compact premium responsive layout, accessible navigation, attachment statistics, actual media-family breakdown, and quick workflow links.
* Replaced the long recent-media list with a compact thumbnail gallery of the eight most recent uploads, locally filterable by title and cached reference status.
* Added Media Explorer with paginated results, search, media-type filters, cached status indicators and explicit inspect/edit links.
* Added a read-only reference footprint visualizing supported sources only after a deliberate single-item scan; this is not a full dependency graph.
* Kept existing scanning, bulk, duplicate, broken URL, support and site-audit features; no automatic deletion or scanning is introduced.
* Added reduced-motion support and small-screen styles; updated release notices and version metadata.


= 2.5.0 =
* Added a responsive admin dashboard with attachment/image/30-day counts, links to existing workflows, and recent cached reference statuses without performing heavy automatic scans.
* Added labeled cached scan-state chips for Media Library list actions; unscanned attachments are explicitly marked "Not scanned".
* Replaced synchronous Site Audit with progressive, nonce-checked per-attachment requests, live summary and table, Pause and Resume, and visible errors. Dedicated Broken URLs and Duplicates tools remain separate.
* Reduced false positives by validating full URL boundaries and exact wp-image-ID class endings after content candidate searches.
* Limited Page & Post Audit block IDs to supported core media blocks, so unrelated block IDs are no longer misinterpreted as attachments.
* Restricted generic media metadata ID lists to exact integers or numeric comma/pipe-delimited lists, avoiding false references from arbitrary dates and dimensions.
* Escaped potentially dangerous leading formula characters in CSV report cells for spreadsheet safety.
* Updated the What's New notice, responsive layout, and installable ZIP folder to match the WordPress.org slug.
* Read-only inspection, existing scanner integrations, and other existing tabs are retained.


= 2.4.1 =
* Added five polished, privacy-safe WordPress.org screenshots using real plugin admin UI.
* Added matching screenshot captions for Scanner, Bulk Scan, Page Audit, Help, and Broken URLs.
* Preserved all 2.4.0 scanner behavior; this is a directory presentation maintenance release.

= 2.4.0 =
* Added Media Impact Preview for already-computed single-scan results.
* Added bounded generic media-like post meta, term meta, and selected option validation.
* Added known Yoast SEO and Rank Math social-image metadata checks.
* Added modular Bricks, Divi, and Beaver Builder saved-media checks with value validation.
* Improved Media Library cached reference counts and explicit re-scan wording.
* Added a bounded manual Site Audit Summary for up to 100 recent media items.
* Added JSON export alongside CSV and printable HTML reports.
* Added 2.4 NEW badges and updated What’s New guidance.
* Added a one-time WordPress admin What’s New notice for existing installs after updating to 2.4.0.
* Updated compatibility metadata and validation for WordPress 7.1.
* Removed corrupt legacy WordPress.org screenshot assets so broken screenshots are no longer served.
* Revalidated WordPress.org banner sources for stable asset re-sync.
* Preserved read-only behavior and added stricter bounds to new metadata queries.

= 2.3.0 =
* Added reference confidence, source-category, and context metadata for supported scan results.
* Added integration coverage status for WordPress Core, WooCommerce, Elementor, and ACF.
* Added a bounded Broken URLs scanner for missing local uploads files referenced in post content or excerpts.
* Added short-lived local scan-status caching for on-demand Media Library reference status.
* Added selected attachment-ID support to Bulk Scan.
* Added Bulk Scan evidence/source and file-health filtering.
* Added a printable HTML audit report alongside the existing CSV export.
* Preserved existing Scanner, Page Audit, Duplicate Finder, support form, responsive UI, and read-only safety behavior.

= 2.2.0 =
* Added per-user NEW badges and a What's New card that disappear after the new feature tabs are visited.
* Added Page & Post Media Audit with supported media listing, broken attachment-ID review, and file-health status.
* Added Media File Health to single-item scan results.
* Added a bounded exact Duplicate Finder for recent readable local files.
* Added confirmed ACF Image, File, and Gallery field reference detection when ACF is active.
* Added Bulk Scan result sorting and clearer Potential unused review wording.
* Removed the temporary Diagnostics tab now that normal WordPress.org updates ar verified.
* Rebuilt Bulk Scan controls for responsive desktop, tablet, and mobile layouts, including desktop-sized mobile viewports.
* Added an explicit Help-tab support email form for bug reports, feature requests, and general questions.
* Added nonce, capability, sanitization, rate-limit, and mail-delivery feedback protections to the support form.
* Preserved all 2.1.0 scanner, integration, CSV, and read-only behavior.

= 2.1.0 =
* Added Diagnostics with exact plugin-basename and WordPress core update-cache visibility plus a nonce-protected update-status refresh action.
* Added core media widget and block-widget reference detection.
* Added WooCommerce product-category thumbnail detection.
* Added a scanner coverage summary for core, widgets, WooCommerce, and Elementor checks.
* Added bulk upload-age filtering and increased bounded bulk batches up to 250 items.
* Expanded CSV exports with media URL, file size, and upload date.
* Added keyboard focus and reduced-motion accessibility polish.

= 2.0.0 =
* Redesigned the admin experience with a professional WordPress-native header and Scanner, Bulk Scan, and Help navigation.
* Added responsive media cards, grouped scan results, clearer status states, and follow-up Edit/View actions.
* Added bounded AJAX bulk scanning, live progress, result filtering, and CSV export.
* Added a Media Library “Check references” row action.
* Added WooCommerce product gallery detection.
* Added Elementor saved media-control detection with JSON validation.
* Preserved all 1.1.0 reference checks and read-only behavior.

= 1.1.0 =
* Added generated image-size URL detection and core media-block attachment ID detection.
* Extended menu, header, and background checks to generated URL variants.
* Added post IDs in the content-reference labels.

= 1.0.2 =
* Improved prepared SQL analysis and validated read-only navigation inputs.

= 1.0.1 =
* Improved responsive media-list layout and mobile usability.

= 1.0.0 =
* Initial release with read-only reference checks for standard WordPress content and settings.
