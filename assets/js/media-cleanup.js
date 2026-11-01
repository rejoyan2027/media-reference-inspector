/**
 * Media Reference Inspector 2.7: explicit, two-step media removal.
 * Nothing is removed without a server-side review token, acknowledgement and typed confirmation.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-mri27-toolbar]');
    var dialogBackdrop = document.getElementById('mri27-dialog-backdrop');
    var opts = window.MediaRefInspectorCleanup || {};
    if (!root || !dialogBackdrop || !opts.ajaxUrl || !opts.nonce) { return; }

    var boxes = Array.prototype.slice.call(document.querySelectorAll('.mri26-explorer-grid .mri27-item-check'));
    var selectAll = document.getElementById('mri27-select-all');
    var count = document.getElementById('mri27-selected-count');
    var clearBtn = document.getElementById('mri27-clear-selection');
    var reviewBtn = document.getElementById('mri27-review-selection');
    var modal = dialogBackdrop.querySelector('.mri27-dialog');
    var closeBtn = document.getElementById('mri27-dialog-close');
    var cancelBtn = document.getElementById('mri27-dialog-cancel');
    var approveBtn = document.getElementById('mri27-dialog-approve');
    var ack = document.getElementById('mri27-ack');
    var word = document.getElementById('mri27-confirm-word');
    var mode = document.getElementById('mri27-dialog-mode');
    var items = document.getElementById('mri27-dialog-items');
    var feedback = document.getElementById('mri27-dialog-feedback');
    var confirmation = document.getElementById('mri27-dialog-confirmation');
    var strings = opts.strings || {};
    var token = '';
    var busy = false;
    var previousFocus = null;

    function selection() {
        return boxes.filter(function (checkbox) { return checkbox.checked; }).map(function (checkbox) { return checkbox.value; });
    }

    function updateSelection() {
        var selected = selection();
        var total = selected.length;
        boxes.forEach(function (checkbox) {
            var tile = checkbox.closest('.mri27-selectable-tile');
            if (tile) { tile.classList.toggle('is-selected', checkbox.checked); }
        });
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && total === boxes.length;
            selectAll.indeterminate = total > 0 && total < boxes.length;
        }
        if (count) { count.textContent = (strings.selectedCount || '%d selected').replace('%d', String(total)); }
        if (clearBtn) { clearBtn.disabled = total === 0; }
        if (reviewBtn) { reviewBtn.disabled = total === 0; }
    }

    function setFeedback(message) { feedback.textContent = message || ''; }

    function setBusy(value) {
        busy = value;
        closeBtn.disabled = value;
        cancelBtn.disabled = value;
        if (value) { approveBtn.disabled = true; }
        else { updateApprove(); }
    }

    function updateApprove() {
        approveBtn.disabled = busy || !token || !ack.checked || word.value.trim() !== (strings.confirmedWord || 'DELETE');
    }

    function openModal() {
        previousFocus = document.activeElement;
        token = '';
        ack.checked = false;
        word.value = '';
        confirmation.hidden = true;
        items.replaceChildren();
        mode.textContent = '';
        setFeedback(strings.loading || 'Preparing deletion review…');
        dialogBackdrop.hidden = false;
        document.body.classList.add('mri27-modal-open');
        closeBtn.focus();
        setBusy(true);
    }

    function closeModal() {
        if (busy) { return; }
        dialogBackdrop.hidden = true;
        document.body.classList.remove('mri27-modal-open');
        token = '';
        items.replaceChildren();
        setFeedback('');
        if (previousFocus && typeof previousFocus.focus === 'function') { previousFocus.focus(); }
    }

    function send(action, data) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', opts.nonce);
        Object.keys(data).forEach(function (key) {
            if (Array.isArray(data[key])) {
                data[key].forEach(function (value) { body.append(key + '[]', String(value)); });
            } else {
                body.set(key, String(data[key]));
            }
        });
        return fetch(opts.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : (strings.networkError || 'Request failed.'));
                }
                return json.data;
            });
        });
    }

    function listItem(item) {
        var line = document.createElement('div');
        line.className = 'mri27-review-item';
        line.setAttribute('role', 'listitem');

        var mark = document.createElement('span');
        mark.className = 'dashicons dashicons-admin-media mri27-review-media-icon';
        mark.setAttribute('aria-hidden', 'true');

        var details = document.createElement('div');
        details.className = 'mri27-review-file';
        var title = document.createElement('strong');
        title.textContent = item.title || ('Media #' + item.id);
        var meta = document.createElement('small');
        meta.textContent = '#' + item.id + ' · ' + (item.type || 'unknown type');
        details.appendChild(title);
        details.appendChild(meta);

        var state = document.createElement('span');
        state.className = 'mri27-review-state';
        if (item.status === 'referenced') {
            state.classList.add('is-danger');
            state.textContent = 'Referenced · ' + Number(item.knownReferences || 0) + ' cached references';
        } else if (item.status === 'unreferenced') {
            state.classList.add('is-warning');
            state.textContent = 'No supported references · not proven unused';
        } else {
            state.classList.add('is-warning');
            state.textContent = 'Not scanned · usage unknown';
        }
        line.appendChild(mark);
        line.appendChild(details);
        line.appendChild(state);
        return line;
    }

    function startReview(ids) {
        if (!ids || !ids.length) { return; }
        openModal();
        send('mediarefinspector_delete_preview', { ids: ids }).then(function (data) {
            token = data.token;
            var trash = Boolean(data.trash);
            mode.className = 'mri27-dialog-mode ' + (trash ? 'is-trash' : 'is-permanent');
            mode.textContent = trash
                ? 'Move to Media Trash — eligible files can be restored from the Media Library until the Trash is emptied.'
                : 'PERMANENT DELETION — Media Trash is disabled on this site. WordPress will remove media records and associated upload files. This cannot be undone here.';
            (data.items || []).forEach(function (item) { items.appendChild(listItem(item)); });
            confirmation.hidden = false;
            approveBtn.textContent = trash ? (strings.trash || 'Move to Trash') : (strings.permanent || 'Permanently delete');
            setFeedback('');
            setBusy(false);
        }).catch(function (error) {
            token = '';
            setFeedback(error.message || strings.networkError || 'Could not prepare review.');
            setBusy(false);
        });
    }

    function confirmReview() {
        if (busy || !token || !ack.checked || word.value.trim() !== (strings.confirmedWord || 'DELETE')) { return; }
        setBusy(true);
        setFeedback(strings.deleting || 'Processing selected media…');
        send('mediarefinspector_delete_confirm', { token: token, confirmation: word.value.trim(), acknowledge: '1' })
            .then(function () { window.location.reload(); })
            .catch(function (error) {
                // Failed confirmation consumes no client token. Server may have expired/consumed it.
                token = '';
                setFeedback(error.message || strings.networkError || 'Could not complete removal. Reload to verify before retrying.');
                setBusy(false);
            });
    }

    boxes.forEach(function (checkbox) { checkbox.addEventListener('change', updateSelection); });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes.forEach(function (checkbox) { if (!checkbox.disabled) { checkbox.checked = selectAll.checked; } });
            updateSelection();
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            boxes.forEach(function (checkbox) { checkbox.checked = false; });
            updateSelection();
        });
    }
    if (reviewBtn) { reviewBtn.addEventListener('click', function () { startReview(selection()); }); }
    document.querySelectorAll('.mri26-explorer-grid [data-mri27-single]').forEach(function (button) {
        button.addEventListener('click', function () { startReview([button.getAttribute('data-mri27-single')]); });
    });
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    approveBtn.addEventListener('click', confirmReview);
    ack.addEventListener('change', updateApprove);
    word.addEventListener('input', updateApprove);
    dialogBackdrop.addEventListener('mousedown', function (event) { if (event.target === dialogBackdrop) { closeModal(); } });
    document.addEventListener('keydown', function (event) {
        if (dialogBackdrop.hidden) { return; }
        if (event.key === 'Escape') { event.preventDefault(); closeModal(); }
        if (event.key === 'Tab') {
            var focusable = Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]),input:not([disabled])')).filter(function (element) { return element.offsetParent !== null; });
            if (!focusable.length) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    updateSelection();
}());
