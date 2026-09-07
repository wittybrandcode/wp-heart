/* WP-HEART admin application — production bundle 1.6.0.
 * Dependency-free SPA consuming wp-heart/v1. Read-only UI: the server
 * enforces policy. Strings go through wp.i18n when available. */
(function () {
	'use strict';

	var cfg = window.WPHeartData || {};
	var REST = (cfg.restUrl || '/wp-json/wp-heart/v1/').replace(/\/?$/, '/');
	var NONCE = cfg.nonce || '';
	var i18n = (window.wp && window.wp.i18n) ? window.wp.i18n : null;

	function __(s) {
		return (i18n && i18n.__) ? i18n.__(s, 'wp-heart') : s;
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	function api(path, opts) {
		opts = opts || {};
		return fetch(REST + path.replace(/^\//, ''), {
			method: opts.method || 'GET',
			headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
			body: opts.body ? JSON.stringify(opts.body) : undefined
		}).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok) throw new Error((j && j.message) || ('Request failed (' + r.status + ')'));
				return j;
			});
		});
	}

	/* ---------- shared bits ---------- */
	function badge(text, kind) {
		return '<span class="wh-badge wh-' + esc(kind) + '">' + esc(text) + '</span>';
	}
	function clsBadge(c) {
		if (!c) return badge('UNKNOWN', 'unknown');
		return badge(c.type + ' · ' + c.confidence, c.type.toLowerCase().replace(/[^a-z]/g, ''));
	}
	function sevBadge(s) {
		return badge(s, 'sev-' + String(s).toLowerCase());
	}
	function fmtBytes(n) {
		if (n == null) return '—';
		var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
		while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
		return (Math.round(n * 10) / 10) + ' ' + u[i];
	}
	function fmtNum(n) {
		if (n == null) return '—';
		return Number(n).toLocaleString();
	}
	function modeLabel(m) {
		var labels = { EXACT: __('exact'), ESTIMATED: __('estimated'), CACHED: __('cached'), UNKNOWN: __('unknown'), UNAVAILABLE: __('unavailable') };
		return m ? ' <span class="wh-mode">(' + esc(labels[m] || String(m).toLowerCase()) + ')</span>' : '';
	}
	function loading(msg) {
		return '<p class="wh-state" role="status">' + esc(msg || __('Loading…')) + '</p>';
	}
	function emptyState(msg) {
		return '<p class="wh-state wh-empty">' + esc(msg) + '</p>';
	}
	function errorState(msg) {
		return '<div class="wh-state wh-error" role="alert">' + esc(msg) + '</div>';
	}
	function okState(msg) {
		return '<div class="wh-state wh-ok" role="status">' + esc(msg) + '</div>';
	}
	function pager(meta, hash) {
		if (!meta) return '';
		var p = meta.page || 1, sep = hash.indexOf('?') >= 0 ? '&' : '?';
		var h = '<nav class="wh-pager" aria-label="' + esc(__('Pagination')) + '">';
		if (p > 1) h += '<a class="button" href="#' + hash + sep + 'page=' + (p - 1) + '"><span class="dashicons dashicons-arrow-left-alt2"></span> ' + esc(__('Prev')) + '</a> ';
		h += '<span class="wh-page">' + esc(__('Page')) + ' ' + p;
		if (meta.total != null) h += ' · ' + fmtNum(meta.total) + ' ' + esc(__('total')) + ' (' + esc(meta.total_mode || '') + ')';
		h += '</span> ';
		if (meta.has_more) h += '<a class="button" href="#' + hash + sep + 'page=' + (p + 1) + '">' + esc(__('Next')) + ' <span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		return h + '</nav>';
	}
	function parseHash() {
		var h = (location.hash || '#overview').slice(1), params = {}, q = h.indexOf('?');
		if (q >= 0) {
			h.split('?')[1].split('&').forEach(function (kv) {
				var k = kv.split('=');
				params[decodeURIComponent(k[0])] = decodeURIComponent(k[1] || '');
			});
			h = h.slice(0, q);
		}
		return { route: h.split('/'), params: params };
	}
	function renderValue(v) {
		if (v == null) return '<span class="wh-null">NULL</span>';
		var s = String(v);
		if (/[\x00-\x08\x0B\x0C\x0E-\x1F]/.test(s)) return '<span class="wh-binary">' + esc(__('[binary data]')) + '</span>';
		if (/^(a:\d+:\{|s:\d+:|O:\d+:|b:[01];|i:-?\d+;|N;)/.test(s)) {
			return '<details><summary>' + esc(__('Serialized value — expand')) + '</summary><pre>' + esc(s.length > 4000 ? s.slice(0, 4000) + '…' : s) + '</pre></details>';
		}
		if (s.length > 300) return '<details><summary>' + esc(s.slice(0, 120)) + '…</summary><pre>' + esc(s.length > 4000 ? s.slice(0, 4000) + '…' : s) + '</pre></details>';
		return esc(s);
	}
	function focusHeading(el) {
		var h = el.querySelector('h2');
		if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
	}

	var app = document.getElementById('wp-heart-app');
	if (!app) return;

	var NAV = [['overview', 'Overview'], ['tables', 'Tables'], ['map', 'Map'], ['snapshots', 'Snapshots'], ['search', 'Search'], ['health', 'Health'], ['query', 'Query'], ['audit', 'Audit'], ['settings', 'Settings']];
	var NAV_ICONS = { overview: 'dashicons-dashboard', tables: 'dashicons-editor-table', map: 'dashicons-networking', snapshots: 'dashicons-camera', search: 'dashicons-search', health: 'dashicons-heart', query: 'dashicons-editor-code', audit: 'dashicons-list-view', settings: 'dashicons-admin-generic' };
	function navLabels() {
		return { overview: __('Overview'), tables: __('Tables'), map: __('Map'), snapshots: __('Snapshots'), search: __('Search'), health: __('Health'), query: __('Query'), audit: __('Audit'), settings: __('Settings') };
	}
	function nav() {
		var r = parseHash().route[0] || 'overview', labels = navLabels(), h = '<nav class="wh-nav" aria-label="' + esc(__('WP-HEART sections')) + '"><ul>';
		NAV.forEach(function (it) {
			h += '<li><a href="#' + it[0] + '"' + (r === it[0] ? ' aria-current="page" class="current"' : '') + '><span class="dashicons ' + NAV_ICONS[it[0]] + '"></span> ' + esc(labels[it[0]]) + '</a></li>';
		});
		return h + '</ul><button class="button wh-refresh" id="wh-refresh" type="button"><span class="dashicons dashicons-update-alt"></span> ' + esc(__('Refresh metadata')) + '</button></nav><div id="wh-view" aria-live="polite"></div>';
	}

	/* ---------- overview ---------- */
	function viewOverview(el) {
		el.innerHTML = loading();
		Promise.all([api('database'), api('cron').catch(function () { return { data: null }; })]).then(function (all) {
			var res = all[0], cronRes = all[1];
			var d = res.data, m = res.meta || {}, dist = d.classification_distribution || {};
			var total = Object.keys(dist).reduce(function (a, k) { return a + dist[k]; }, 0) || 1;
			var h = '<section class="wh-grid">';
			h += '<div class="wh-card"><h3>' + esc(__('Database')) + '</h3><p class="wh-stat">' + fmtNum(d.table_count) + '</p><p class="wh-stat-sub">' + esc(__('tables')) + '</p><dl class="wh-kv">'
				+ '<dt>' + esc(__('Name')) + '</dt><dd>' + esc(d.name) + '</dd>'
				+ '<dt>' + esc(__('Engine')) + '</dt><dd>' + esc(d.engine) + ' ' + esc(d.server_version || '') + '</dd>'
				+ '<dt>' + esc(__('Charset')) + '</dt><dd>' + esc(d.charset || '—') + '</dd>'
				+ '<dt>' + esc(__('Est. size')) + '</dt><dd>' + fmtBytes(d.estimated_size) + modeLabel(d.estimated_size_mode) + '</dd>'
				+ '<dt>' + esc(__('Freshness')) + '</dt><dd>' + esc(m.freshness || d.freshness || '') + '</dd></dl></div>';
			if (d.autoload) {
				h += '<div class="wh-card"><h3>' + esc(__('Autoload')) + '</h3><p class="wh-stat">' + fmtBytes(d.autoload.bytes) + '</p><p class="wh-stat-sub">' + fmtNum(d.autoload.count) + ' ' + esc(__('autoloaded options')) + ' (' + esc((d.autoload.accuracy || '').toLowerCase()) + ')</p><ul class="wh-list">';
				(d.autoload.top || []).slice(0, 3).forEach(function (o) {
					h += '<li><code>' + esc(o.name) + '</code> — ' + fmtBytes(o.bytes) + '</li>';
				});
				h += '</ul><p><a class="button" href="#health"><span class="dashicons dashicons-heart"></span> ' + esc(__('Open diagnostics')) + '</a></p></div>';
				var cron = cronRes.data;
				if (cron) {
					var asLine = esc(__('Action Scheduler')) + ': ' + (cron.action_scheduler.available ? Object.keys(cron.action_scheduler.by_status).map(function (k) { return fmtNum(cron.action_scheduler.by_status[k]) + ' ' + k; }).join(' · ') : esc(__('not detected')));
					h += '<div class="wh-card"><h3>' + esc(__('Scheduled')) + '</h3><p class="wh-stat">' + fmtNum(cron.wp_cron.overdue) + '</p><p class="wh-stat-sub">' + esc(__('overdue events')) + '</p><ul class="wh-list">'
						+ '<li>wp-cron: ' + fmtNum(cron.wp_cron.total) + ' ' + esc(__('events')) + '</li><li>' + asLine + '</li></ul>'
						+ '<p><a class="button" href="#health"><span class="dashicons dashicons-heart"></span> ' + esc(__('Open diagnostics')) + '</a></p></div>';
				}
			}
			h += '<div class="wh-card"><h3>' + esc(__('Classification')) + '</h3><ul class="wh-bars">';
			Object.keys(dist).sort().forEach(function (k) {
				var pct = Math.round((dist[k] / total) * 100);
				h += '<li><a href="#tables?cls=' + encodeURIComponent(k) + '">' + badge(k, k.toLowerCase()) + '</a><span class="wh-bar-track" aria-hidden="true"><span class="wh-bar-fill" style="inline-size:' + pct + '%"></span></span><span class="wh-count">' + fmtNum(dist[k]) + ' (' + pct + '%)</span></li>';
			});
			h += '</ul></div>';
			var sev = d.health_summary.by_severity || {};
			h += '<div class="wh-card"><h3>' + esc(__('Health')) + '</h3><p class="wh-stat">' + fmtNum(d.health_summary.issue_count) + '</p><p class="wh-stat-sub">' + esc(__('issues found')) + '</p><ul class="wh-list">';
			Object.keys(sev).sort().forEach(function (k) {
				h += '<li>' + sevBadge(k) + ' <span class="wh-count">' + fmtNum(sev[k]) + '</span></li>';
			});
			h += '</ul><p><a class="button" href="#health"><span class="dashicons dashicons-heart"></span> ' + esc(__('Open diagnostics')) + '</a></p></div>';
			h += '<div class="wh-card wh-wide"><h3>' + esc(__('Largest tables')) + '</h3><div class="wh-scroll"><table class="widefat striped"><thead><tr><th scope="col">' + esc(__('Table')) + '</th><th scope="col">' + esc(__('Size')) + '</th><th scope="col">' + esc(__('Rows')) + '</th></tr></thead><tbody>';
			(d.largest_tables || []).forEach(function (t) {
				h += '<tr><td><a href="#tables/' + esc(t.name) + '">' + esc(t.name) + '</a></td><td>' + fmtBytes(t.size_bytes) + modeLabel(t.size_mode) + '</td><td>' + fmtNum(t.rows) + modeLabel(t.rows_mode) + '</td></tr>';
			});
			el.innerHTML = h + '</tbody></table></div></div></section>';
			focusHeading(el);
		}).catch(function (e) { el.innerHTML = errorState(e.message); });
	}

	/* ---------- tables ---------- */
	var CLS_OPTS = ['CORE', 'PLUGIN', 'THEME_CUSTOM', 'UNKNOWN', 'ORPHAN_CANDIDATE'];
	var SORTS = [['name', 'Name'], ['size', 'Size'], ['rows', 'Rows'], ['classification', 'Classification']];
	var whOwners = null;
	var whFocusName = null;
	function getOwners(cb) {
		if (whOwners) { cb(whOwners); return; }
		api('owners').then(function (r) { whOwners = r.data || []; cb(whOwners); }).catch(function () { cb([]); });
	}
	function viewTables(el, params) {
		el.innerHTML = loading();
		getOwners(function (owners) {
			var q = 'tables?page=' + encodeURIComponent(params.page || 1) + '&per_page=20'
				+ (params.search ? '&search=' + encodeURIComponent(params.search) : '')
				+ (params.cls ? '&classification=' + encodeURIComponent(params.cls) : '')
				+ (params.eng ? '&engine=' + encodeURIComponent(params.eng) : '')
				+ (params.owner ? '&owner=' + encodeURIComponent(params.owner) : '')
				+ (params.orderby ? '&orderby=' + encodeURIComponent(params.orderby) : '')
				+ (params.order ? '&order=' + encodeURIComponent(params.order) : '');
			var baseHash = 'tables?search=' + encodeURIComponent(params.search || '') + '&cls=' + encodeURIComponent(params.cls || '') + '&eng=' + encodeURIComponent(params.eng || '') + '&owner=' + encodeURIComponent(params.owner || '') + '&orderby=' + encodeURIComponent(params.orderby || 'name');
			var filtered = !!(params.search || params.cls || params.eng || params.owner);
			api(q).then(function (res) {
				var ownerMap = {};
				owners.forEach(function (o) { ownerMap[o.slug] = o; });
				function ownerCell(cls) {
					var slug = cls && cls.owner ? cls.owner : null;
					if (!slug) return '—';
					var o = ownerMap[slug] || { name: slug, active: true };
					return '<a href="#tables?owner=' + encodeURIComponent(slug) + '" title="' + esc(slug) + '">' + esc(o.name) + '</a>' + (o.active ? '' : ' <span class="wh-mode">(' + esc(__('inactive')) + ')</span>');
				}
				var h = '<form class="wh-filters" id="wh-tfilter"><label>' + esc(__('Search')) + ' <input type="search" name="s" value="' + esc(params.search || '') + '"></label> '
					+ '<label>' + esc(__('Classification')) + ' <select name="c"><option value="">' + esc(__('All')) + '</option>'
					+ CLS_OPTS.map(function (c) { return '<option value="' + c + '"' + (params.cls === c ? ' selected' : '') + '>' + c + '</option>'; }).join('') + '</select></label> '
					+ '<label>' + esc(__('Owner')) + ' <select name="w"><option value="">' + esc(__('All owners')) + '</option>'
					+ owners.map(function (o) { return '<option value="' + esc(o.slug) + '"' + ((params.owner || '') === o.slug ? ' selected' : '') + '>' + esc(o.name) + ' (' + o.count + (o.active ? '' : ', ' + esc(__('inactive')) + '') + ')</option>'; }).join('') + '</select></label> '
					+ '<label>' + esc(__('Engine')) + ' <select name="e"><option value="">' + esc(__('All')) + '</option>'
					+ ['INNODB', 'MYISAM', 'MEMORY', 'CSV', 'ARCHIVE'].map(function (e) { return '<option value="' + e + '"' + ((params.eng || '') === e ? ' selected' : '') + '>' + e + '</option>'; }).join('') + '</select></label> '
					+ '<label>' + esc(__('Sort by')) + ' <select name="o">'
					+ SORTS.map(function (s) { return '<option value="' + s[0] + '"' + ((params.orderby || 'name') === s[0] ? ' selected' : '') + '>' + esc(__(s[1])) + '</option>'; }).join('') + '</select></label> '
					+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-filter"></span> ' + esc(__('Filter')) + '</button></form>';
				if (filtered) {
					h += '<p class="wh-result-meta" role="status">' + esc(__('Filtered view')) + ' — ' + fmtNum(res.meta.pagination.total) + ' ' + esc(__('matching tables')) + ' (<a href="#tables">' + esc(__('Clear filters')) + '</a>)</p>';
				}
				if (!res.data.length) {
					el.innerHTML = h + emptyState(__('No tables match. Unknown tables are never hidden — try clearing the filter.'));
					bindFilter(el);
					restoreFocus(el);
					return;
				}
				if (!filtered) {
					h += '<p class="wh-result-meta">' + esc(__('Showing')) + ' ' + res.data.length + ' ' + esc(__('of')) + ' ' + fmtNum(res.meta.pagination.total) + ' ' + esc(__('tables')) + '</p>';
				}
				h += '<div class="wh-scroll"><table class="widefat striped wh-table"><thead><tr><th scope="col">' + esc(__('Table')) + '</th><th scope="col">' + esc(__('Classification')) + '</th><th scope="col">' + esc(__('Owner')) + '</th><th scope="col">' + esc(__('Rows')) + '</th><th scope="col">' + esc(__('Size')) + '</th><th scope="col">' + esc(__('Engine')) + '</th></tr></thead><tbody>';
				res.data.forEach(function (t) {
					h += '<tr><td><a href="#tables/' + esc(t.name) + '">' + esc(t.name) + '</a></td>'
						+ '<td>' + clsBadge(t.classification) + '</td>'
						+ '<td>' + ownerCell(t.classification) + '</td>'
						+ '<td>' + fmtNum(t.metadata.estimated_rows) + modeLabel(t.metadata.rows_mode) + '</td>'
						+ '<td>' + fmtBytes(t.metadata.size_bytes) + modeLabel(t.metadata.size_mode) + '</td>'
						+ '<td>' + esc(t.metadata.engine || '—') + '</td></tr>';
				});
				el.innerHTML = h + '</tbody></table></div>' + pager(res.meta.pagination, baseHash);
				focusHeading(el);
				bindFilter(el);
				restoreFocus(el);
			}).catch(function (e) { el.innerHTML = errorState(e.message); });
		});
	}
	function restoreFocus(el) {
		if (!whFocusName) return;
		var c = el.querySelector('#wh-tfilter [name="' + whFocusName + '"]');
		whFocusName = null;
		if (c && c.focus) c.focus({ preventScroll: true });
	}
	function bindFilter(el) {
		var f = el.querySelector('#wh-tfilter');
		if (!f) return;
		function apply() {
			var active = document.activeElement;
			whFocusName = (active && active.name) || null;
			location.hash = '#tables?search=' + encodeURIComponent(f.s.value) + '&cls=' + encodeURIComponent(f.c.value) + '&owner=' + encodeURIComponent(f.w.value) + '&eng=' + encodeURIComponent(f.e.value) + '&orderby=' + encodeURIComponent(f.o.value);
		}
		f.addEventListener('submit', function (ev) {
			ev.preventDefault();
			apply();
		});
		// Dropdowns apply instantly; the search box stays submit-driven so
		// typing never loses focus or spams the server per keystroke.
		['c', 'w', 'e', 'o'].forEach(function (n) {
			if (f[n]) f[n].addEventListener('change', apply);
		});
	}

	/* ---------- table detail + row inspector ---------- */
	function viewTableDetail(el, name, params) {
		el.innerHTML = loading();
		api('tables/' + encodeURIComponent(name)).then(function (res) {
			var d = res.data, cls = d.classification, tab = params.tab || 'struct';
			var h = '<p><a href="#tables">← ' + esc(__('Tables')) + '</a></p><h2 class="wh-title">' + esc(d.name) + ' ' + clsBadge(cls) + '</h2>';
			if (cls && cls.evidence) {
				h += '<details class="wh-evidence"><summary>' + esc(__('Why this classification?')) + ' (' + esc(cls.confidence) + ' ' + esc(__('confidence')) + ')</summary><ul class="wh-list">';
				cls.evidence.forEach(function (e) { h += '<li><strong>' + esc(e.source) + '</strong> (' + esc(e.strength) + '): ' + esc(e.description) + '</li>'; });
				h += '</ul></details>';
			}
			var base = '#tables/' + encodeURIComponent(d.name);
			h += '<div class="wh-tabs" role="tablist" aria-label="' + esc(__('Table sections')) + '">'
				+ '<a class="button" role="tab" aria-selected="' + (tab === 'struct') + '" href="' + base + '"><span class="dashicons dashicons-editor-table"></span> ' + esc(__('Structure')) + '</a> '
				+ '<a class="button" role="tab" aria-selected="' + (tab === 'rows') + '" href="' + base + '?tab=rows"><span class="dashicons dashicons-database"></span> ' + esc(__('Data')) + '</a> '
				+ '<a class="button" role="tab" aria-selected="' + (tab === 'rels') + '" href="' + base + '?tab=rels"><span class="dashicons dashicons-networking"></span> ' + esc(__('Relationships')) + '</a></div><div id="wh-tabbody"></div>';
			el.innerHTML = h;
			focusHeading(el);
			var body = el.querySelector('#wh-tabbody');
			if (tab === 'rows') renderDataTab(body, d, params);
			else if (tab === 'rels') renderRelsTab(body, d);
			else renderStructTab(body, d);
		}).catch(function (e) { el.innerHTML = errorState(e.message); });
	}
	function renderStructTab(body, d) {
		var h = '<h3>' + esc(__('Columns')) + ' (' + d.columns.length + ')</h3><div class="wh-scroll"><table class="widefat striped"><thead><tr><th scope="col">' + esc(__('Name')) + '</th><th scope="col">' + esc(__('Type')) + '</th><th scope="col">' + esc(__('Null')) + '</th><th scope="col">' + esc(__('Default')) + '</th><th scope="col">' + esc(__('Extra')) + '</th></tr></thead><tbody>'
			+ d.columns.map(function (c) {
				return '<tr><td><code>' + esc(c.name) + '</code></td><td>' + esc(c.native_type || c.data_type || '—') + '</td><td>' + (c.nullable == null ? '—' : (c.nullable ? 'YES' : 'NO')) + '</td><td>' + esc(c.default == null ? '—' : c.default) + '</td><td>' + esc(c.extra || '—') + '</td></tr>';
			}).join('') + '</tbody></table></div>';
		h += '<h3>' + esc(__('Indexes')) + ' (' + d.indexes.length + ')</h3><ul class="wh-list">' + (d.indexes.length ? d.indexes.map(function (i) {
			return '<li><code>' + esc(i.name) + '</code>' + (i.primary ? ' ' + badge(__('PRIMARY'), 'primary') : '') + (i.unique ? ' ' + badge(__('UNIQUE'), 'unique') : '') + ' (' + i.columns.map(esc).join(', ') + ')</li>';
		}).join('') : '<li>' + esc(__('No indexes reported.')) + '</li>') + '</ul>';
		body.innerHTML = h;
	}
	function renderRelsTab(body, d) {
		var h = '<h3>' + esc(__('Relationships')) + '</h3>';
		if (!d.relationships.length) h += emptyState(__('No relationships detected (NONE known).'));
		h += '<ul class="wh-list">' + d.relationships.map(function (r) {
			return '<li>' + badge(r.origin, 'rel-' + r.origin.toLowerCase()) + ' <code>' + esc(r.source_column) + '</code>'
				+ (r.target_table ? ' → <a href="#tables/' + esc(r.target_table) + '">' + esc(r.target_table) + '</a>.<code>' + esc(r.target_column || '') + '</code>' : '')
				+ ' <span class="wh-mode">(' + esc(r.confidence) + ')</span></li>';
		}).join('') + '</ul><h3>' + esc(__('Constraints')) + '</h3><ul class="wh-list">'
			+ (d.constraints.length ? d.constraints.map(function (c) { return '<li><code>' + esc(c.name) + '</code> ' + esc(c.type) + '</li>'; }).join('') : '<li>' + esc(__('None detected — absence of foreign keys is normal in WordPress.')) + '</li>') + '</ul>';
		body.innerHTML = h;
	}
	function renderDataTab(body, d, params) {
		body.innerHTML = loading();
		var url = 'tables/' + encodeURIComponent(d.name) + '/rows?page=' + encodeURIComponent(params.page || 1) + '&per_page=20' + (params.s ? '&search=' + encodeURIComponent(params.s) : '');
		api(url).then(function (r2) {
			var cols = d.columns.map(function (c) { return c.name; });
			var addr = (r2.meta && r2.meta.row_addressing) || { mode: 'none', columns: [] };
			var canInspect = addr.mode === 'single';
			var h = '<form class="wh-filters" id="wh-dsearch"><label>' + esc(__('Search rows')) + ' <input type="search" name="q" value="' + esc(params.s || '') + '" minlength="2" maxlength="100"></label> '
				+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-search"></span> ' + esc(__('Search')) + '</button> '
				+ (r2.data.length ? '<button class="button" type="button" id="wh-csv"><span class="dashicons dashicons-media-spreadsheet"></span> ' + esc(__('Export CSV')) + '</button>' : '') + '</form>';
			if (!cols.length) { body.innerHTML = h + emptyState(__('No columns reported.')); return; }
			if (!r2.data.length) {
				body.innerHTML = h + emptyState(params.s ? __('No matches in this table.') : __('No rows in this table.'));
				bindDataSearch(body, d);
				return;
			}
			h += '<div class="wh-scroll"><table class="widefat striped"><thead><tr>' + cols.map(function (c) { return '<th scope="col">' + esc(c) + '</th>'; }).join('') + (canInspect ? '<th scope="col"><span class="screen-reader-text">' + esc(__('Actions')) + '</span></th>' : '') + '</tr></thead><tbody>'
				+ r2.data.map(function (row) {
					var cells = cols.map(function (c) { return '<td>' + renderValue(row[c]) + '</td>'; }).join('');
					if (canInspect) cells += '<td><button type="button" class="button button-small wh-rowbtn" data-id="' + esc(row[addr.columns[0]]) + '"><span class="dashicons dashicons-visibility"></span> ' + esc(__('View')) + '</button></td>';
					return '<tr>' + cells + '</tr>';
				}).join('') + '</tbody></table></div>' + pager(r2.meta.pagination, 'tables/' + d.name + '?tab=rows' + (params.s ? '&s=' + encodeURIComponent(params.s) : ''));
			if (addr.mode === 'composite') h += '<p class="wh-result-meta">' + esc(__('Composite primary key — rows list safely but cannot be deep-linked individually.')) + '</p>';
			if (addr.mode === 'none') h += '<p class="wh-result-meta">' + esc(__('No primary key — rows list safely but cannot be addressed individually.')) + '</p>';
			body.innerHTML = h;
			bindDataSearch(body, d);
			body.querySelectorAll('.wh-rowbtn').forEach(function (btn) {
				btn.addEventListener('click', function () { openRowModal(d.name, addr.columns[0], btn.getAttribute('data-id')); });
			});
			var csvBtn = body.querySelector('#wh-csv');
			if (csvBtn) csvBtn.addEventListener('click', function () { exportCsv(d.name, cols, r2.data, params.page || 1); });
		}).catch(function (e) { body.innerHTML = errorState(e.message); });
	}
	function bindDataSearch(body, d) {
		var f = body.querySelector('#wh-dsearch');
		if (f) f.addEventListener('submit', function (ev) {
			ev.preventDefault();
			location.hash = '#tables/' + encodeURIComponent(d.name) + '?tab=rows' + (f.q.value.trim() ? '&s=' + encodeURIComponent(f.q.value.trim()) : '');
		});
	}
	function exportCsv(table, cols, rows, page) {
		var q = function (v) {
			var s = v == null ? '' : String(v);
			return '"' + s.replace(/"/g, '""') + '"';
		};
		var csv = '﻿' + cols.map(q).join(',') + '\r\n'
			+ rows.map(function (row) { return cols.map(function (c) { return q(row[c]); }).join(','); }).join('\r\n');
		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = 'wp-heart-' + table + '-p' + page + '.csv';
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
	}
	var lastFocus = null;
	function openRowModal(table, pkCol, id) {
		lastFocus = document.activeElement;
		var wrap = document.createElement('div');
		wrap.innerHTML = '<div class="wh-modal-backdrop"><div class="wh-modal" role="dialog" aria-modal="true" aria-labelledby="wh-rowtitle">' + loading(__('Loading row…')) + '</div></div>';
		document.body.appendChild(wrap);
		var modal = wrap.firstChild.firstChild;
		function close() { wrap.remove(); if (lastFocus && lastFocus.focus) lastFocus.focus(); document.removeEventListener('keydown', onKey); }
		function onKey(e) { if (e.key === 'Escape') close(); }
		document.addEventListener('keydown', onKey);
		wrap.firstChild.addEventListener('click', function (e) { if (e.target.className === 'wh-modal-backdrop') close(); });
		api('tables/' + encodeURIComponent(table) + '/rows/' + encodeURIComponent(id)).then(function (res) {
			var row = res.data.row, cols = Object.keys(row);
			var h = '<h2 id="wh-rowtitle">' + esc(table) + ' <span class="wh-mode">(' + esc(pkCol) + ' = ' + esc(id) + ')</span></h2><dl>'
				+ cols.map(function (c) { return '<dt><code>' + esc(c) + '</code></dt><dd>' + renderValue(row[c]) + '</dd>'; }).join('')
				+ '</dl><p><button type="button" class="button" id="wh-modalclose"><span class="dashicons dashicons-no"></span> ' + esc(__('Close')) + '</button></p>';
			modal.innerHTML = h;
			modal.querySelector('#wh-modalclose').addEventListener('click', close);
			modal.querySelector('#wh-modalclose').focus();
		}).catch(function (e) { modal.innerHTML = errorState(e.message) + '<p><button type="button" class="button" id="wh-modalclose"><span class="dashicons dashicons-no"></span> ' + esc(__('Close')) + '</button></p>'; modal.querySelector('#wh-modalclose').addEventListener('click', close); });
	}

	/* ---------- search ---------- */
	function viewSearch(el, params) {
		el.innerHTML = '<form class="wh-filters" id="wh-sform"><label>' + esc(__('Search database')) + ' <input type="search" name="q" value="' + esc(params.q || '') + '" minlength="2" maxlength="100" required></label> '
			+ '<label>' + esc(__('Limit to tables (comma-separated, optional)')) + ' <input type="text" name="t" value="' + esc(params.t || '') + '" placeholder="wp_posts, wp_postmeta"></label> '
			+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-search"></span> ' + esc(__('Search')) + '</button></form><div id="wh-sres"></div>';
		focusHeading(el);
		var res = el.querySelector('#wh-sres');
		el.querySelector('#wh-sform').addEventListener('submit', function (ev) {
			ev.preventDefault();
			var q = ev.target.q.value.trim(), t = ev.target.t.value.trim();
			if (q.length < 2) { res.innerHTML = errorState(__('Type at least 2 characters.')); return; }
			res.innerHTML = loading(__('Searching (bounded, schema-aware)…'));
			var url = 'search?q=' + encodeURIComponent(q) + '&page=' + encodeURIComponent(params.page || 1) + (t ? '&tables=' + encodeURIComponent(t) : '');
			api(url).then(function (r) {
				if (!r.data.length) { res.innerHTML = emptyState(__('No matches. Totals are estimated; narrow the scope from a table page.')); return; }
				res.innerHTML = '<div class="wh-scroll"><table class="widefat striped"><thead><tr><th scope="col">' + esc(__('Table')) + '</th><th scope="col">' + esc(__('Column')) + '</th><th scope="col">' + esc(__('Row')) + '</th><th scope="col">' + esc(__('Preview')) + '</th></tr></thead><tbody>'
					+ r.data.map(function (m) {
						return '<tr><td><a href="#tables/' + esc(m.table) + '">' + esc(m.table) + '</a></td><td>' + esc(m.column) + '</td><td>' + esc(m.row_id == null ? '—' : m.row_id) + '</td><td>' + esc(m.preview) + '</td></tr>';
					}).join('') + '</tbody></table></div>' + pager(r.meta.pagination, 'search?q=' + encodeURIComponent(q) + (t ? '&t=' + encodeURIComponent(t) : ''))
					+ '<p class="wh-result-meta">' + esc(__('Searched')) + ' ' + esc(r.meta.tables_searched) + ' ' + esc(__('table(s)')) + (r.meta.truncated ? ' — ' + esc(__('results truncated at the safety cap')) : '') + '.</p>';
			}).catch(function (e) { res.innerHTML = errorState(e.message); });
		});
		if (params.q) el.querySelector('#wh-sform').dispatchEvent(new Event('submit'));
	}

	/* ---------- health ---------- */
	var SEVS = ['INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'];
	function viewHealth(el, params) {
		el.innerHTML = '<div id="wh-hbody">' + loading() + '</div>';
		focusHeading(el);
		var body = el.querySelector('#wh-hbody');
		api('issues').then(function (res) {
			var sev = params.sev || '';
			var h = '<form class="wh-filters" id="wh-hfilter"><label>' + esc(__('Severity')) + ' <select name="s"><option value="">' + esc(__('All')) + '</option>'
				+ SEVS.map(function (s) { return '<option value="' + s + '"' + (sev === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select></label> '
				+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-filter"></span> ' + esc(__('Filter')) + '</button></form>';
			var items = res.data.filter(function (i) { return !sev || i.severity === sev; });
			if (!items.length) { body.innerHTML = h + (sev ? emptyState(__('No issues at this severity.')) : '<div class="wh-state wh-ok" role="status">' + esc(__('No issues detected by the registered diagnostics.')) + '</div>'); bindHealth(body); return; }
			body.innerHTML = h + items.map(function (i) {
				return '<article class="wh-issue sev-' + esc(i.severity) + '"><h3>' + sevBadge(i.severity) + ' ' + esc(i.id) + '</h3>'
					+ '<p>' + esc(i.explanation) + '</p>'
					+ '<p><strong>' + esc(__('Affected:')) + '</strong> ' + i.affected.map(function (a) {
						if (a.indexOf('option:') === 0) return '<code>' + esc(a.slice(7)) + '</code>';
						return '<a href="#tables/' + esc(a) + '">' + esc(a) + '</a>';
					}).join(', ') + '</p>'
					+ '<details><summary>' + esc(__('Evidence & recommendation')) + '</summary><ul class="wh-list">' + (i.evidence || []).map(function (e) { return '<li>' + esc(e.description || JSON.stringify(e)) + '</li>'; }).join('') + '</ul><p><strong>' + esc(__('Recommendation:')) + '</strong> ' + esc(i.recommendation) + '</p></details></article>';
			}).join('');
			bindHealth(body);
		}).catch(function (e) { body.innerHTML = errorState(e.message); });
	}
	function bindHealth(body) {
		var f = body.querySelector('#wh-hfilter');
		if (f) f.addEventListener('submit', function (ev) { ev.preventDefault(); location.hash = '#health' + (f.s.value ? '?sev=' + f.s.value : ''); });
	}

	/* ---------- query console ---------- */
	function viewQuery(el) {
		el.innerHTML = '<div class="wh-notice" role="note">' + esc(__('Read-only console. Only SELECT, SHOW, DESCRIBE and EXPLAIN run — the server rejects everything else.')) + '</div>'
			+ '<form id="wh-qform"><label for="wh-sql">SQL</label><textarea id="wh-sql" rows="6" spellcheck="false" placeholder="SELECT * FROM …">SELECT 1</textarea>'
			+ '<p><button class="button button-primary" type="submit"><span class="dashicons dashicons-controls-play"></span> ' + esc(__('Run')) + '</button> <button class="button" type="button" id="wh-explain"><span class="dashicons dashicons-visibility"></span> ' + esc(__('Explain')) + '</button></p></form><div id="wh-qres"></div><div class="wh-history" id="wh-qhist"></div>';
		focusHeading(el);
		var res = el.querySelector('#wh-qres');
		function history() {
			var items = [];
			try { items = JSON.parse(localStorage.getItem('wh_query_history') || '[]'); } catch (e) { items = []; }
			return items;
		}
		function pushHistory(sql) {
			var items = history().filter(function (s) { return s !== sql; });
			items.unshift(sql);
			try { localStorage.setItem('wh_query_history', JSON.stringify(items.slice(0, 10))); } catch (e) { }
			renderHistory();
		}
		function renderHistory() {
			var box = el.querySelector('#wh-qhist'), items = history();
			if (!items.length) { box.innerHTML = ''; return; }
			box.innerHTML = '<h3>' + esc(__('Recent queries (this browser only)')) + '</h3><ul>' + items.map(function (s) {
				return '<li><button type="button" data-sql="' + esc(s) + '"><span class="dashicons dashicons-backup"></span> ' + esc(s.length > 90 ? s.slice(0, 90) + '…' : s) + '</button></li>';
			}).join('') + '</ul>';
			box.querySelectorAll('button').forEach(function (b) {
				b.addEventListener('click', function () { el.querySelector('#wh-sql').value = b.getAttribute('data-sql'); });
			});
		}
		function run(explain) {
			var sql = el.querySelector('#wh-sql').value;
			res.innerHTML = loading(__('Running…'));
			api(explain ? 'query/explain' : 'query', { method: 'POST', body: { sql: sql } }).then(function (r) {
				pushHistory(sql);
				var rows = explain ? r.data.plan : r.data.rows;
				var cols = rows.length ? Object.keys(rows[0]) : [];
				var meta = r.meta || {};
				var info = explain ? fmtNum(rows.length) + ' ' + __('plan row(s).')
					: fmtNum(meta.row_count) + ' ' + __('row(s)') + (meta.truncated ? ' — ' + __('truncated at the safety cap') : '') + ' ' + __('in') + ' ' + esc(meta.elapsed_ms) + ' ms.';
				res.innerHTML = '<p class="wh-result-meta">' + esc(info) + '</p>'
					+ (rows.length ? '<div class="wh-scroll"><table class="widefat striped"><thead><tr>' + cols.map(function (c) { return '<th scope="col">' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>'
						+ rows.slice(0, 200).map(function (row) { return '<tr>' + cols.map(function (c) { return '<td>' + renderValue(row[c]) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>'
						: emptyState(__('No rows returned.')));
			}).catch(function (e) { res.innerHTML = errorState(e.message); });
		}
		el.querySelector('#wh-qform').addEventListener('submit', function (ev) { ev.preventDefault(); run(false); });
		el.querySelector('#wh-explain').addEventListener('click', function () { run(true); });
		renderHistory();
	}

	/* ---------- map ---------- */
	function viewMap(el) {
		el.innerHTML = '<div class="wh-notice" role="note">' + esc(__('Loading interactive map engine...')) + '</div>';
		focusHeading(el);
		api('map').then(function (res) {
			var g = res.data;
			if (!window.cytoscape) {
				var script = document.createElement('script');
				script.src = 'https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.23.0/cytoscape.min.js';
				script.onload = function () { renderCyto(el, g); };
				document.head.appendChild(script);
			} else {
				renderCyto(el, g);
			}
		}).catch(function (e) { el.innerHTML = errorState(e.message); });
	}

	function renderCyto(el, g) {
		var h = '<div class="wh-cy-tools">'
			+ '<strong>' + esc(__('Map')) + '</strong> — '
			+ g.nodes.length + ' ' + esc(__('tables')) + ', ' + g.edges.length + ' ' + esc(__('relationships'))
			+ (g.truncated ? ' (' + esc(__('truncated at safety cap')) + ')' : '')
			+ ' <button type="button" class="button button-small" id="wh-cy-fit" style="margin-inline-start: auto;"><span class="dashicons dashicons-editor-expand"></span> ' + esc(__('Fit to screen')) + '</button>'
			+ '</div><div id="wh-cy" class="wh-cy-container"></div>';
		el.innerHTML = h;

		var rootStyle = getComputedStyle(document.body);
		function getVar(name, def) { return rootStyle.getPropertyValue(name).trim() || def; }
		var colorPrimary = getVar('--wh-primary', '#007cba');
		var colorWarning = getVar('--wh-warning', '#f56e28');
		var colorText = getVar('--wh-text', '#3c434a');
		var colorMuted = getVar('--wh-text-muted', '#646970');
		var colorBgCard = getVar('--wh-bg-card', '#ffffff');
		var fontFam = getVar('--wh-font', 'sans-serif');

		var cyNodes = g.nodes.map(function (n) {
			var color = colorText;
			if (n.classification === 'CORE') color = colorPrimary;
			else if (n.classification === 'WOOCOMMERCE') color = '#96588a';
			else if (n.classification === 'PLUGIN') color = colorWarning;
			else if (n.classification === 'LOG') color = colorMuted;

			var size = 15;
			if (n.metadata && n.metadata.rows != null) {
				size = Math.max(10, Math.min(45, 10 + Math.sqrt(n.metadata.rows || 0) * 0.5));
			}

			return { data: { id: n.id, label: n.id, cls: n.classification, color: color, size: size } };
		});

		var cyEdges = g.edges.map(function (e) {
			var color = e.origin === 'PHYSICAL' ? colorPrimary : colorWarning;
			return { data: { source: e.source_table, target: e.target_table, label: e.source_column, color: color, origin: e.origin } };
		});

		var cy = cytoscape({
			container: document.getElementById('wh-cy'),
			elements: { nodes: cyNodes, edges: cyEdges },
			style: [
				{
					selector: 'node',
					style: {
						'label': 'data(label)',
						'width': 'data(size)',
						'height': 'data(size)',
						'background-color': 'data(color)',
						'color': colorText,
						'font-size': '12px',
						'font-family': fontFam,
						'text-valign': 'bottom',
						'text-margin-y': 6,
						'text-outline-color': colorBgCard,
						'text-outline-width': 3,
						'transition-property': 'background-color, line-color, target-arrow-color',
						'transition-duration': '0.2s'
					}
				},
				{
					selector: 'edge',
					style: {
						'width': 1,
						'line-color': 'data(color)',
						'target-arrow-color': 'data(color)',
						'target-arrow-shape': 'triangle',
						'curve-style': 'bezier',
						'opacity': 0.35,
						'transition-property': 'opacity, width',
						'transition-duration': '0.2s'
					}
				},
				{
					selector: '.dimmed',
					style: { 'opacity': 0.1 }
				},
				{
					selector: 'node.highlighted',
					style: { 'font-weight': 'bold', 'z-index': 99, 'text-outline-width': 4 }
				},
				{
					selector: 'edge.highlighted',
					style: { 'width': 2.5, 'opacity': 0.9, 'label': 'data(label)', 'font-size': '10px', 'color': colorMuted, 'text-rotation': 'autorotate', 'text-background-opacity': 1, 'text-background-color': colorBgCard }
				}
			],
			layout: {
				name: 'cose',
				padding: 50,
				nodeRepulsion: 400000,
				idealEdgeLength: 100,
				edgeElasticity: 100,
				gravity: 250,
				numIter: 1000,
				fit: true
			},
			wheelSensitivity: 0.15,
			minZoom: 0.2,
			maxZoom: 3
		});

		cy.on('mouseover', 'node', function (e) {
			var node = e.target;
			var neighborhood = node.neighborhood().add(node);
			cy.elements().addClass('dimmed');
			neighborhood.removeClass('dimmed').addClass('highlighted');
			node.style('text-outline-width', 5);
		});

		cy.on('mouseout', 'node', function (e) {
			cy.elements().removeClass('dimmed').removeClass('highlighted');
			e.target.style('text-outline-width', 3);
		});

		cy.on('tap', 'node', function (e) {
			window.location.hash = '#tables/' + encodeURIComponent(e.target.id());
		});

		el.querySelector('#wh-cy-fit').addEventListener('click', function () { cy.fit(cy.elements(), 50); });
	}

	function viewAudit(el, params) {
			el.innerHTML = loading();
			focusHeading(el);
			api('audit?page=' + encodeURIComponent(params.page || 1)).then(function (res) {
				if (!res.data.length) { el.innerHTML = emptyState(__('No audit events recorded yet.')); return; }
				el.innerHTML = '<div class="wh-scroll"><table class="widefat striped"><thead><tr><th scope="col">' + esc(__('Time')) + '</th><th scope="col">' + esc(__('Event')) + '</th><th scope="col">' + esc(__('Actor')) + '</th><th scope="col">' + esc(__('Target')) + '</th><th scope="col">' + esc(__('Outcome')) + '</th></tr></thead><tbody>'
					+ res.data.map(function (e) {
						return '<tr><td>' + esc(new Date(e.timestamp * 1000).toLocaleString()) + '</td><td>' + badge(e.type, 'audit') + '</td><td>' + esc(e.actor) + '</td><td>' + esc(e.target) + '</td><td>' + esc(e.outcome) + '</td></tr>';
					}).join('') + '</tbody></table></div>' + pager(res.meta.pagination, 'audit');
			}).catch(function (e) { el.innerHTML = errorState(e.message); });
		}

		/* ---------- settings + context ---------- */
		function viewSettings(el) {
			el.innerHTML = loading();
			focusHeading(el);
			Promise.all([api('settings'), api('context')]).then(function (all) {
				var s = all[0].data, ctx = all[1].data;

				/* Row 1: Tuning — inline form with all inputs in one row */
				var h = '<form id="wh-setform" class="wh-filters">'
					+ '<label>' + esc(__('Cache TTL')) + ' <input type="number" name="cache_ttl" min="60" max="3600" value="' + esc(s.cache_ttl) + '" style="min-inline-size:100px"></label> '
					+ '<label>' + esc(__('Page size')) + ' <input type="number" name="default_per_page" min="5" max="100" value="' + esc(s.default_per_page) + '" style="min-inline-size:80px"></label> '
					+ '<label>' + esc(__('Max page')) + ' <input type="number" name="max_per_page" min="10" max="200" value="' + esc(s.max_per_page) + '" style="min-inline-size:80px"></label> '
					+ '<label>' + esc(__('Max rows')) + ' <input type="number" name="max_query_rows" min="50" max="2000" value="' + esc(s.max_query_rows) + '" style="min-inline-size:80px"></label> '
					+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-saved"></span> ' + esc(__('Save')) + '</button>'
					+ '</form><div id="wh-setmsg"></div>';

				/* Row 2: Environment — inline info row */
				h += '<div class="wh-filters wh-env-strip">'
					+ '<span class="wh-env-item"><span class="dashicons dashicons-admin-multisite"></span> <strong>' + esc(__('Multisite')) + ':</strong> ' + (ctx.is_multisite ? esc(__('Yes')) : esc(__('No'))) + '</span>'
					+ '<span class="wh-env-item"><span class="dashicons dashicons-admin-site"></span> <strong>' + esc(__('Site ID')) + ':</strong> ' + esc(ctx.blog_id) + '</span>'
					+ '<span class="wh-env-item"><span class="dashicons dashicons-database"></span> <strong>' + esc(__('Prefix')) + ':</strong> <code>' + esc(ctx.prefix) + '</code></span>'
					+ '<span class="wh-env-item"><span class="dashicons dashicons-info"></span> <strong>' + esc(__('Version')) + ':</strong> ' + esc(s.version) + '</span>'
					+ '</div>';

				/* Row 3: Capabilities — compact inline list */
				var caps = Object.keys(s.capabilities || {});
				if (caps.length) {
					h += '<details class="wh-settings-caps"><summary><span class="dashicons dashicons-shield"></span> ' + esc(__('Capabilities')) + ' (' + caps.length + ')</summary>'
						+ '<div class="wh-caps-grid">' + caps.map(function (k) { return '<span class="wh-cap-item"><code>' + esc(k) + '</code> — ' + esc(s.capabilities[k]) + '</span>'; }).join('') + '</div></details>';
				}

				el.innerHTML = h;
				var setForm = el.querySelector('#wh-setform');
				if (setForm) {
					setForm.addEventListener('submit', function (ev) {
						ev.preventDefault();
						var f = ev.target, body = { cache_ttl: +f.cache_ttl.value, default_per_page: +f.default_per_page.value, max_per_page: +f.max_per_page.value, max_query_rows: +f.max_query_rows.value };
						api('settings', { method: 'POST', body: body }).then(function () {
							el.querySelector('#wh-setmsg').innerHTML = okState(__('Settings saved.'));
						}).catch(function (e) { el.querySelector('#wh-setmsg').innerHTML = errorState(e.message); });
					});
				}
			}).catch(function (e) { el.innerHTML = errorState(e.message); });
		}

		/* ---------- snapshots ---------- */
		function viewSnapshots(el) {
			el.innerHTML = '<div class="wh-notice" role="note">' + esc(__('Snapshots freeze database structure (never row content) so two points in time can be compared.')) + '</div>'
				+ '<form class="wh-filters" id="wh-snapform"><label>' + esc(__('Label (optional)')) + ' <input type="text" name="label" maxlength="191"></label> '
				+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-camera"></span> ' + esc(__('Create snapshot')) + '</button></form>'
				+ '<form class="wh-filters" id="wh-impform"><label>' + esc(__('Import snapshot file')) + ' <input type="file" name="f" accept="application/json,.json"></label> '
				+ '<button class="button" type="submit"><span class="dashicons dashicons-upload"></span> ' + esc(__('Import')) + '</button></form>'
				+ '<div id="wh-snapmsg"></div><div id="wh-snaplist">' + loading() + '</div><div id="wh-snapdiff"></div>';
			focusHeading(el);
			var msg = el.querySelector('#wh-snapmsg');
			function reload() {
				var box = el.querySelector('#wh-snaplist');
				api('snapshots').then(function (res) {
					if (!res.data.length) { box.innerHTML = emptyState(__('No snapshots yet. Capture the first one above.')); return; }
					var opts = res.data.map(function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.label) + ' (' + esc(s.created_at) + ')</option>'; }).join('');
					var h = '<div class="wh-scroll"><table class="widefat striped"><thead><tr><th scope="col">' + esc(__('Label')) + '</th><th scope="col">' + esc(__('Created')) + '</th><th scope="col">' + esc(__('Tables')) + '</th><th scope="col">' + esc(__('Actions')) + '</th></tr></thead><tbody>'
						+ res.data.map(function (s) {
							return '<tr><td>' + esc(s.label) + '</td><td>' + esc(s.created_at) + '</td><td>' + fmtNum(s.tables) + '</td>'
								+ '<td><button type="button" class="button button-small" data-dl="' + esc(s.id) + '"><span class="dashicons dashicons-download"></span> ' + esc(__('Download')) + '</button> '
								+ '<button type="button" class="button button-small" data-del="' + esc(s.id) + '"><span class="dashicons dashicons-trash"></span> ' + esc(__('Delete')) + '</button></td></tr>';
						}).join('') + '</tbody></table></div>';
					h += '<form class="wh-filters" id="wh-difform"><label>A <select name="a">' + opts + '</select></label> '
						+ '<label>B <select name="b">' + opts + '</select></label> '
						+ '<button class="button button-primary" type="submit"><span class="dashicons dashicons-leftright"></span> ' + esc(__('Compare')) + '</button></form>';
					box.innerHTML = h;
					box.querySelectorAll('[data-dl]').forEach(function (b) {
						b.addEventListener('click', function () {
							api('snapshots/' + encodeURIComponent(b.getAttribute('data-dl')) + '/download').then(function (r) {
								var blob = new Blob([r.data.content], { type: 'application/json' });
								var a = document.createElement('a');
								a.href = URL.createObjectURL(blob);
								a.download = r.data.filename;
								document.body.appendChild(a);
								a.click();
								setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
							}).catch(function (e) { msg.innerHTML = errorState(e.message); });
						});
					});
					box.querySelectorAll('[data-del]').forEach(function (b) {
						b.addEventListener('click', function () {
							if (!window.confirm(__('Delete this snapshot?'))) return;
							api('snapshots/' + encodeURIComponent(b.getAttribute('data-del')), { method: 'DELETE' }).then(function () {
								msg.innerHTML = okState(__('Snapshot deleted.'));
								reload();
							}).catch(function (e) { msg.innerHTML = errorState(e.message); });
						});
					});
					box.querySelector('#wh-difform').addEventListener('submit', function (ev) {
						ev.preventDefault();
						var f = ev.target, out = el.querySelector('#wh-snapdiff');
						if (f.a.value === f.b.value) { out.innerHTML = errorState(__('Select two different snapshots.')); return; }
						out.innerHTML = loading();
						api('snapshots/compare?a=' + encodeURIComponent(f.a.value) + '&b=' + encodeURIComponent(f.b.value)).then(function (r) {
							out.innerHTML = renderDiff(r.data, r.meta);
						}).catch(function (e) { out.innerHTML = errorState(e.message); });
					});
				}).catch(function (e) { box.innerHTML = errorState(e.message); });
			}
			el.querySelector('#wh-snapform').addEventListener('submit', function (ev) {
				ev.preventDefault();
				msg.innerHTML = loading(__('Capturing snapshot…'));
				api('snapshots', { method: 'POST', body: { label: ev.target.label.value } }).then(function () {
					msg.innerHTML = okState(__('Snapshot captured.'));
					reload();
				}).catch(function (e) { msg.innerHTML = errorState(e.message); });
			});
			el.querySelector('#wh-impform').addEventListener('submit', function (ev) {
				ev.preventDefault();
				var file = ev.target.f.files[0];
				if (!file) return;
				var reader = new FileReader();
				reader.onload = function () {
					var doc;
					try { doc = JSON.parse(reader.result); } catch (e) { msg.innerHTML = errorState(__('Invalid snapshot file.')); return; }
					api('snapshots/import', { method: 'POST', body: { snapshot: doc } }).then(function () {
						msg.innerHTML = okState(__('Snapshot imported.'));
						reload();
					}).catch(function (e) { msg.innerHTML = errorState(e.message); });
				};
				reader.readAsText(file);
			});
			reload();
		}
		function renderDiff(diff, meta) {
			var s = diff.summary, h = '<h3>' + esc((meta.a ? meta.a.label : '')) + ' → ' + esc((meta.b ? meta.b.label : '')) + '</h3>';
			h += '<p class="wh-result-meta">' + fmtNum(s.tables_added) + ' ' + esc(__('added')) + ' · ' + fmtNum(s.tables_removed) + ' ' + esc(__('removed')) + ' · ' + fmtNum(s.tables_changed) + ' ' + esc(__('changed')) + '</p>';
			function names(list) {
				return list.length ? '<ul class="wh-list">' + list.map(function (t) { return '<li><a href="#tables/' + esc(t) + '">' + esc(t) + '</a></li>'; }).join('') + '</ul>' : '<p class="wh-result-meta">—</p>';
			}
			h += '<div class="wh-grid"><div class="wh-card"><h3>' + esc(__('Added tables')) + '</h3>' + names(diff.tables.added) + '</div>';
			h += '<div class="wh-card"><h3>' + esc(__('Removed tables')) + '</h3>' + names(diff.tables.removed) + '</div></div>';
			h += '<h3>' + esc(__('Changed tables')) + '</h3>';
			var changed = diff.tables.changed, keys = Object.keys(changed);
			if (!keys.length) h += emptyState(__('No structural changes between these snapshots.'));
			keys.forEach(function (t) {
				var d = changed[t];
				h += '<article class="wh-issue"><h3><a href="#tables/' + esc(t) + '">' + esc(t) + '</a></h3>';
				Object.keys(d).forEach(function (section) {
					h += '<details open><summary>' + esc(section) + '</summary>';
					if (d[section].added || d[section].removed) {
						h += '<p>+' + (d[section].added || []).map(esc).join(', ') + '</p><p>−' + (d[section].removed || []).map(esc).join(', ') + '</p>';
					}
					Object.keys(d[section].changed || {}).forEach(function (n) {
						h += '<p><code>' + esc(n) + '</code></p><ul class="wh-list">' + Object.keys(d[section].changed[n]).map(function (f) {
							var c = d[section].changed[n][f];
							return '<li><code>' + esc(f) + '</code>: ' + esc(JSON.stringify(c.from)) + ' → ' + esc(JSON.stringify(c.to)) + '</li>';
						}).join('') + '</ul>';
					});
					Object.keys(d[section]).forEach(function (k) {
						if (d[section][k] && d[section][k].from !== undefined) {
							h += '<p><code>' + esc(k) + '</code>: ' + esc(JSON.stringify(d[section][k].from)) + ' → ' + esc(JSON.stringify(d[section][k].to)) + '</p>';
						}
					});
					h += '</details>';
				});
				h += '</article>';
			});
			return h;
		}

		/* ---------- router ---------- */
		function render() {
			app.innerHTML = nav();
			var view = document.getElementById('wh-view');
			var p = parseHash(), r = p.route[0] || 'overview';
			document.getElementById('wh-refresh').addEventListener('click', function (btn) {
				var b = btn.target;
				b.disabled = true;
				view.innerHTML = loading(__('Refreshing…'));
				api('refresh', { method: 'POST' }).then(function () {
					view.innerHTML = okState(__('Metadata refreshed.'));
					setTimeout(render, 600);
				}).catch(function (e) { view.innerHTML = errorState(e.message); b.disabled = false; });
			});
			if (r === 'overview') viewOverview(view);
			else if (r === 'tables' && p.route[1]) viewTableDetail(view, decodeURIComponent(p.route[1]), p.params);
			else if (r === 'tables') viewTables(view, p.params);
			else if (r === 'map') viewMap(view);
			else if (r === 'snapshots') viewSnapshots(view);
			else if (r === 'search') viewSearch(view, p.params);
			else if (r === 'health') viewHealth(view, p.params);
			else if (r === 'query') viewQuery(view);
			else if (r === 'audit') viewAudit(view, p.params);
			else if (r === 'settings') viewSettings(view);
			else viewOverview(view);
		}

		window.addEventListener('hashchange', render);
		render();
	})();
