/**
 * Media Reference Inspector v2.6 dashboard filters.
 * Filters the eight already-rendered recent attachments; no AJAX, no scan.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-mri26-filter-root]');
    var gallery = document.getElementById('mri26-recent-gallery');
    if (!root || !gallery) {
        return;
    }

    var search = root.querySelector('[data-mri26-search]');
    var filters = Array.prototype.slice.call(root.querySelectorAll('[data-mri26-filter]'));
    var tiles = Array.prototype.slice.call(gallery.querySelectorAll('.mri26-media-tile'));
    var empty = document.getElementById('mri26-filter-empty');
    var count = document.getElementById('mri26-filter-count');
    var status = 'all';

    function update() {
        var needle = search ? search.value.trim().toLocaleLowerCase() : '';
        var visible = 0;
        tiles.forEach(function (tile) {
            var name = String(tile.getAttribute('data-mri26-name') || '').toLocaleLowerCase();
            var show = (status === 'all' || status === tile.dataset.mri26Status) && name.indexOf(needle) !== -1;
            tile.hidden = !show;
            if (show) { visible += 1; }
        });
        if (empty) { empty.hidden = visible !== 0; }
        if (count) {
            var template = (window.MediaRefInspectorDashboard || {}).countLabel || '%1$d / %2$d recent media shown';
            count.textContent = template.replace('%1$d', String(visible)).replace('%2$d', String(tiles.length));
        }
    }

    filters.forEach(function (button) {
        button.addEventListener('click', function () {
            status = button.getAttribute('data-mri26-filter') || 'all';
            filters.forEach(function (other) {
                var isActive = other === button;
                other.classList.toggle('is-active', isActive);
                other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            update();
        });
    });
    if (search) { search.addEventListener('input', update); }
    update();
}());
