(function () {
	'use strict';

	var app = document.getElementById('mediarefinspector-site-audit-app');
	var config = window.MediaRefInspectorAdmin || {};
	if (!app || !config.ajaxUrl || !config.nonce) { return; }

	var start = document.getElementById('mediarefinspector-site-audit-start');
	var pause = document.getElementById('mediarefinspector-site-audit-pause');
	var resume = document.getElementById('mediarefinspector-site-audit-resume');
	var limit = document.getElementById('mediarefinspector-site-audit-limit');
	var progress = document.getElementById('mediarefinspector-site-audit-progress');
	var progressBar = document.getElementById('mediarefinspector-site-audit-bar');
	var statusText = document.getElementById('mediarefinspector-site-audit-status');
	var progressCount = document.getElementById('mediarefinspector-site-audit-count');
	var summary = document.getElementById('mediarefinspector-site-audit-summary');
	var table = document.getElementById('mediarefinspector-site-audit-table');
	var rows = document.getElementById('mediarefinspector-site-audit-rows');
	var ids = [];
	var index = 0;
	var isPaused = false;
	var isRunning = false;
	var counts = {};

	function send(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', config.nonce);
		Object.keys(data || {}).forEach(function (key) { body.append(key, data[key]); });
		return fetch(config.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			if (!response.ok) { throw new Error('HTTP ' + response.status); }
			return response.json();
		}).then(function (result) {
			if (!result || !result.success || !result.data) {
				throw new Error(result && result.data && result.data.message ? result.data.message : 'Audit request failed');
			}
			return result.data;
		});
	}

	function updateCounters() {
		Object.keys(counts).forEach(function (key) {
			var el = summary.querySelector('[data-site-audit="' + key + '"]');
			if (el) { el.textContent = String(counts[key]); }
		});
		progressBar.value = index;
		progressBar.max = Math.max(ids.length, 1);
		progressCount.textContent = index + ' / ' + ids.length;
	}

	function addCell(row, value) {
		var cell = document.createElement('td');
		cell.textContent = String(value == null ? '' : value);
		row.appendChild(cell);
		return cell;
	}

	function addRow(item, errorText) {
		var row = document.createElement('tr');
		var media = addCell(row, item.title || 'Media #' + item.id);
		var filename = document.createElement('small');
		filename.textContent = item.filename || (errorText || '');
		media.appendChild(document.createElement('br'));
		media.appendChild(filename);
		addCell(row, errorText ? '—' : item.referenceCount);
		addCell(row, errorText ? 'Scan error' : (item.healthStatus === 'healthy' ? 'Healthy' : 'Needs review'));
		var actionCell = addCell(row, '');
		if (item.inspectUrl) {
			var link = document.createElement('a');
			link.href = item.inspectUrl;
			link.className = 'button button-small';
			link.textContent = 'Inspect';
			actionCell.appendChild(link);
		}
		rows.appendChild(row);
	}

	function reset() {
		ids = []; index = 0; isPaused = false; isRunning = false;
		counts = { scanned: 0, referenced: 0, unreferenced: 0, review: 0, errors: 0 };
		rows.replaceChildren();
		progress.hidden = false; summary.hidden = false; table.hidden = true;
		progressBar.value = 0; progressBar.max = 100;
		progressCount.textContent = '0 / 0';
		statusText.textContent = 'Preparing your site audit…';
		start.disabled = true; limit.disabled = true;
		pause.hidden = true; resume.hidden = true;
		updateCounters();
	}

	function finish(message) {
		isRunning = false;
		pause.hidden = true;
		resume.hidden = true;
		start.disabled = false;
		limit.disabled = false;
		statusText.textContent = message;
	}

	function next() {
		if (isPaused) {
			isRunning = false;
			statusText.textContent = 'Paused. You can resume this audit.';
			pause.hidden = true;
			resume.hidden = false;
			return;
		}
		if (index >= ids.length) {
			finish('Site audit complete. Review any flagged items before making changes.');
			return;
		}
		isRunning = true;
		statusText.textContent = 'Inspecting media references…';
		return send('mediarefinspector_bulk_scan_item', { attachment_id: ids[index] })
			.then(function (item) {
				counts.scanned++;
				if (item.status === 'referenced') { counts.referenced++; }
				else { counts.unreferenced++; }
				if (item.healthStatus !== 'healthy') { counts.review++; }
				addRow(item, '');
			}).catch(function (error) {
				counts.scanned++; counts.errors++;
				addRow({ id: ids[index], title: 'Media #' + ids[index] }, error.message);
			}).then(function () {
				index++;
				updateCounters();
				table.hidden = false;
				return next();
			});
	}

	start.addEventListener('click', function () {
		if (!window.confirm('Start a read-only, progressive site audit?')) { return; }
		reset();
		send('mediarefinspector_get_bulk_ids', {
			limit: limit.value, search: '', media_type: '', selected_ids: '', age: 0
		}).then(function (data) {
			ids = Array.isArray(data.ids) ? data.ids : [];
			updateCounters();
			if (!ids.length) { finish('No media items found.'); return; }
			pause.hidden = false;
			return next();
		}).catch(function (error) { finish('Could not start audit: ' + error.message); });
	});

	pause.addEventListener('click', function () {
		isPaused = true;
		pause.disabled = true;
		statusText.textContent = 'Pausing after the current item…';
	});
	resume.addEventListener('click', function () {
		if (isRunning) { return; }
		isPaused = false;
		pause.disabled = false;
		resume.hidden = true;
		pause.hidden = false;
		next();
	});
}());
