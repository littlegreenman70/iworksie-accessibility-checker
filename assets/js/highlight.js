/**
 * iWorks.ie Accessibility Checker: "Show on page" highlighter.
 *
 * Loaded only for a logged-in administrator with a valid nonce. Outlines the elements
 * from the last scan and offers a small keyboard-friendly panel to step through them.
 */
(function () {
	'use strict';

	var cfg = window.iwieacHighlight;
	if (!cfg) {
		return;
	}
	var S = cfg.strings || {};

	function format(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(str).replace(/%(\d+\$)?[sd]/g, function (match, pos) {
			var index = pos ? parseInt(pos, 10) - 1 : i++;
			return args[index] !== undefined ? String(args[index]) : match;
		});
	}

	function find(selector) {
		var parts = String(selector).split(' >>> ');
		var root = document;
		var el = null;
		try {
			for (var i = 0; i < parts.length; i++) {
				el = root.querySelector(parts[i]);
				if (!el) {
					return null;
				}
				root = el.shadowRoot || el;
			}
		} catch (e) {
			return null;
		}
		return el;
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text !== undefined) {
			node.textContent = text;
		}
		return node;
	}

	function init() {
		var entries = [];
		(cfg.items || []).forEach(function (item) {
			item.targets.forEach(function (target) {
				var node = find(target);
				entries.push({ item: item, target: target, node: node });
				if (node) {
					node.classList.add('iwieac-hl');
					node.classList.add(item.kind === 'violation' ? 'iwieac-hl--problem' : 'iwieac-hl--review');
				}
			});
		});

		var panel = el('div', 'iwieac-hl-panel');
		panel.id = 'iwieac-highlight-panel';
		panel.setAttribute('role', 'region');
		panel.setAttribute('aria-label', S.panelTitle);

		var title = el('p', 'iwieac-hl-panel__title', S.panelTitle);
		var position = el('p', 'iwieac-hl-panel__position');
		position.setAttribute('aria-live', 'polite');
		var issue = el('p', 'iwieac-hl-panel__issue');
		var code = el('code', 'iwieac-hl-panel__code');
		var note = el('p', 'iwieac-hl-panel__note');
		var nav = el('p', 'iwieac-hl-panel__nav');
		var prev = el('button', '', S.prev);
		var next = el('button', '', S.next);
		var close = el('button', '', S.close);
		var back = el('a', '', S.back);
		prev.type = next.type = close.type = 'button';
		back.href = cfg.backUrl;
		nav.appendChild(prev);
		nav.appendChild(next);
		nav.appendChild(close);
		nav.appendChild(back);

		panel.appendChild(title);
		panel.appendChild(position);
		panel.appendChild(issue);
		panel.appendChild(code);
		panel.appendChild(note);
		panel.appendChild(nav);
		document.body.appendChild(panel);

		var current = -1;

		function show(index) {
			if (!entries.length) {
				position.textContent = S.none;
				prev.disabled = next.disabled = true;
				return;
			}
			if (current >= 0 && entries[current].node) {
				entries[current].node.classList.remove('iwieac-hl--current');
			}
			current = (index + entries.length) % entries.length;
			var entry = entries[current];
			position.textContent = format(S.position, current + 1, entries.length);
			issue.textContent = (entry.item.kind === 'violation' ? S.problem : S.review) + ' · ' + entry.item.impact + ': ' + entry.item.title;
			code.textContent = entry.target;
			if (entry.node) {
				entry.node.classList.add('iwieac-hl--current');
				entry.node.scrollIntoView({ block: 'center', behavior: 'smooth' });
				note.textContent = S.changedNote;
			} else {
				note.textContent = S.notFound;
			}
		}

		prev.addEventListener('click', function () {
			show(current - 1);
		});
		next.addEventListener('click', function () {
			show(current + 1);
		});
		close.addEventListener('click', function () {
			entries.forEach(function (entry) {
				if (entry.node) {
					entry.node.classList.remove('iwieac-hl', 'iwieac-hl--problem', 'iwieac-hl--review', 'iwieac-hl--current');
				}
			});
			panel.parentNode.removeChild(panel);
		});

		show(0);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
