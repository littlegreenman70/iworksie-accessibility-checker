/**
 * iWorks.ie Accessibility Checker: in-browser scanner and admin interactions.
 *
 * Each page is loaded in a same-origin iframe, axe-core is injected into it, and a
 * trimmed copy of the results is posted back to WordPress. Nothing leaves the site.
 */
(function () {
	'use strict';

	var cfg = window.iwieacAdmin;
	if (!cfg) {
		return;
	}
	var S = cfg.strings || {};
	var running = false;
	var stopRequested = false;

	function format(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(str).replace(/%(\d+\$)?[sd]/g, function (match, pos) {
			var index = pos ? parseInt(pos, 10) - 1 : i++;
			return args[index] !== undefined ? String(args[index]) : match;
		});
	}

	function plural(singular, pluralForm, count) {
		if (window.wp && wp.i18n && typeof wp.i18n._n === 'function') {
			return wp.i18n.sprintf(wp.i18n._n(singular, pluralForm, count, 'iworksie-accessibility-checker'), count);
		}
		return format(count === 1 ? singular : pluralForm, count);
	}

	function post(action, data) {
		var body = new FormData();
		body.append('action', 'iwieac_' + action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (key) {
			var value = data[key];
			if (Array.isArray(value)) {
				value.forEach(function (v) {
					body.append(key + '[]', v);
				});
			} else {
				body.append(key, value);
			}
		});
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (res) {
			return res.json().catch(function () {
				return { success: false, data: { message: S.failed } };
			});
		});
	}

	function addArg(url, key, value) {
		var hashIndex = url.indexOf('#');
		if (hashIndex !== -1) {
			url = url.slice(0, hashIndex);
		}
		return url + (url.indexOf('?') === -1 ? '?' : '&') + encodeURIComponent(key) + '=' + encodeURIComponent(value);
	}

	/* ---------------------------------------------------------------------
	 * Scanner
	 * ------------------------------------------------------------------- */

	function targetString(target) {
		if (!Array.isArray(target) || !target.length) {
			return '';
		}
		var last = target[target.length - 1];
		if (Array.isArray(last)) {
			// Shadow DOM path: host selector first, then inner selectors.
			return last.join(' >>> ');
		}
		return String(last);
	}

	function trimRules(list) {
		return (list || [])
			.filter(function (rule) {
				return rule.nodes && rule.nodes.length;
			})
			.map(function (rule) {
				var impact = rule.impact || '';
				if (!impact) {
					rule.nodes.some(function (node) {
						impact = node.impact || '';
						return !!impact;
					});
				}
				return {
					id: rule.id,
					impact: impact,
					help: rule.help || '',
					description: rule.description || '',
					helpUrl: rule.helpUrl || '',
					tags: rule.tags || [],
					n: rule.nodes.length,
					nodes: rule.nodes.slice(0, 30).map(function (node) {
						return {
							t: targetString(node.target),
							h: String(node.html || '').slice(0, 400),
							s: String(node.failureSummary || '').slice(0, 1000)
						};
					})
				};
			});
	}

	function trimResults(results) {
		return {
			engine: results.testEngine && results.testEngine.version ? results.testEngine.version : '',
			passes: (results.passes || []).length,
			violations: trimRules(results.violations),
			incomplete: trimRules(results.incomplete)
		};
	}

	/**
	 * Distinguish "redirected elsewhere" from "framing blocked" when the frame is unreadable.
	 */
	function classifyBlocked(url) {
		return fetch(url, { method: 'GET', credentials: 'same-origin', redirect: 'manual' })
			.then(function (res) {
				return res.type === 'opaqueredirect' ? 'other_site' : 'frame_blocked';
			})
			.catch(function () {
				return 'frame_blocked';
			});
	}

	function scanItem(item, bestPractice) {
		return new Promise(function (resolve) {
			var stage = document.querySelector('[data-iwieac-stage]');
			var frame = document.createElement('iframe');
			var finished = false;
			var started = false;
			var timer;

			function finish(result) {
				if (finished) {
					return;
				}
				finished = true;
				window.clearTimeout(timer);
				window.setTimeout(function () {
					if (frame.parentNode) {
						frame.parentNode.removeChild(frame);
					}
				}, 50);
				resolve(result);
			}

			function run(win, doc) {
				var tags = (cfg.tags || []).slice();
				if (bestPractice) {
					tags.push('best-practice');
				}
				try {
					win.axe
						.run(
							{ exclude: [['#wpadminbar'], ['#iwieac-highlight-panel']] },
							{
								runOnly: { type: 'tag', values: tags },
								resultTypes: ['violations', 'incomplete'],
								iframes: false,
								elementRef: false
							}
						)
						.then(function (results) {
							finish({ payload: trimResults(results) });
						})
						.catch(function () {
							finish({ error: 'axe_error' });
						});
				} catch (e) {
					finish({ error: 'axe_error' });
				}
				return doc;
			}

			frame.className = 'iwieac-frame';
			frame.setAttribute('title', item.label || item.url);
			frame.setAttribute('tabindex', '-1');
			frame.setAttribute('aria-hidden', 'true');

			frame.addEventListener('load', function () {
				var win;
				var doc;
				try {
					win = frame.contentWindow;
					doc = frame.contentDocument;
					if (!doc || !win || win.location.href === 'about:blank') {
						if (!started) {
							return;
						}
						throw new Error('blank');
					}
				} catch (e) {
					classifyBlocked(item.url).then(function (code) {
						finish({ error: code });
					});
					return;
				}
				// Give sliders, lazy content and consent banners a moment to render.
				window.setTimeout(function () {
					if (finished) {
						return;
					}
					try {
						if (win.axe && typeof win.axe.run === 'function') {
							run(win, doc);
							return;
						}
						var script = doc.createElement('script');
						script.src = cfg.axeUrl;
						script.onload = function () {
							if (win.axe) {
								run(win, doc);
							} else {
								finish({ error: 'script_blocked' });
							}
						};
						script.onerror = function () {
							finish({ error: 'script_blocked' });
						};
						(doc.head || doc.documentElement).appendChild(script);
					} catch (e2) {
						finish({ error: 'script_blocked' });
					}
				}, 1200);
			});

			timer = window.setTimeout(function () {
				finish({ error: 'timeout' });
			}, cfg.pageTimeout || 45000);

			if (stage) {
				stage.innerHTML = '';
				stage.appendChild(frame);
			} else {
				frame.className += ' iwieac-frame--offscreen';
				document.body.appendChild(frame);
			}
			started = true;
			frame.src = addArg(item.url, 'iwieac_scan', cfg.scanNonce);
		});
	}

	function saveResult(item, index, result) {
		var data = {
			url: item.url,
			label: item.label || '',
			object_type: item.type || '',
			object_id: item.id || 0,
			index: index
		};
		if (result.error) {
			data.error = result.error;
		} else {
			data.payload = JSON.stringify(result.payload);
		}
		return post('save_result', data);
	}

	/* ---------------------------------------------------------------------
	 * Run UI
	 * ------------------------------------------------------------------- */

	function runEl() {
		return document.querySelector('[data-iwieac-run]');
	}

	function setProgress(done, total, message) {
		var run = runEl();
		if (!run) {
			return;
		}
		var pct = total > 0 ? Math.round((done / total) * 100) : 0;
		var track = run.querySelector('[role="progressbar"]');
		var bar = run.querySelector('.iwt-progress__bar');
		var pctEl = run.querySelector('.iwt-progress__pct');
		var msg = run.querySelector('[data-iwieac-message]');
		if (track) {
			track.setAttribute('aria-valuenow', String(pct));
		}
		if (bar) {
			bar.style.width = pct + '%';
		}
		if (pctEl) {
			pctEl.textContent = pct + '%';
		}
		if (msg && message !== undefined) {
			msg.textContent = message;
		}
	}

	function announce(text) {
		var el = document.querySelector('[data-iwieac-announce]');
		if (el) {
			el.textContent = '';
			window.setTimeout(function () {
				el.textContent = text;
			}, 50);
		}
	}

	function logResult(item, json) {
		var log = document.querySelector('[data-iwieac-log]');
		if (!log) {
			return;
		}
		var li = document.createElement('li');
		var title = document.createElement(json && json.success ? 'a' : 'span');
		title.textContent = item.label || item.url;
		if (json && json.success) {
			title.href = json.data.detailUrl;
		}
		li.appendChild(title);
		var meta = document.createElement('span');
		meta.className = 'iwieac-log__meta';
		if (json && json.success && json.data.status === 'ok') {
			meta.textContent = ' ' + format(S.resultLine, json.data.problems, json.data.review);
			li.className = json.data.problems > 0 ? 'has-problems' : 'is-clean';
		} else {
			meta.textContent = ' ' + (json && json.success ? S.resultError : (json && json.data && json.data.message) || S.failed);
			li.className = 'has-error';
		}
		li.appendChild(meta);
		log.insertBefore(li, log.firstChild);
		while (log.children.length > 50) {
			log.removeChild(log.lastChild);
		}
	}

	function setRunning(state) {
		running = state;
		document.querySelectorAll('[data-iwieac-start],[data-iwieac-one],[data-iwieac-resume],[data-iwieac-rescan]').forEach(function (btn) {
			btn.disabled = state;
		});
		var stop = document.querySelector('[data-iwieac-stop]');
		if (stop) {
			stop.hidden = !state;
			stop.disabled = false;
		}
		var run = runEl();
		if (run && state) {
			run.hidden = false;
		}
		var stage = document.querySelector('[data-iwieac-stage]');
		if (stage) {
			stage.hidden = !state;
		}
	}

	window.addEventListener('beforeunload', function (event) {
		if (running) {
			event.preventDefault();
			event.returnValue = S.leaveWarning;
			return S.leaveWarning;
		}
		return undefined;
	});

	function runQueue(view) {
		var items = view.items || [];
		var index = view.position || 0;
		var total = items.length;
		stopRequested = false;
		setRunning(true);
		setProgress(index, total, '');

		function next() {
			if (stopRequested) {
				setRunning(false);
				setProgress(index, total, S.cancelled);
				announce(S.cancelled);
				showResume(index, total);
				return;
			}
			if (index >= total) {
				setRunning(false);
				var doneMsg = plural(S.doneSingular || 'Finished. %d page checked.', S.donePlural || 'Finished. %d pages checked.', total);
				setProgress(total, total, doneMsg);
				announce(doneMsg);
				var resume = document.querySelector('[data-iwieac-resume]');
				if (resume) {
					resume.hidden = true;
				}
				return;
			}
			var item = items[index];
			setProgress(index, total, format(S.scanning, index + 1, total, item.label || item.url));
			scanItem(item, !!view.bestPractice)
				.then(function (result) {
					return saveResult(item, index, result);
				})
				.then(function (json) {
					logResult(item, json);
					index++;
					if (index % 10 === 0) {
						announce(format(S.scanning, index, total, item.label || item.url));
					}
					next();
				})
				.catch(function () {
					logResult(item, null);
					index++;
					next();
				});
		}
		next();
	}

	function showResume(done, total) {
		var resume = document.querySelector('[data-iwieac-resume]');
		if (resume && done < total) {
			resume.hidden = false;
		}
	}

	function scanSingle(url, after) {
		setRunning(true);
		setProgress(0, 1, S.working);
		return post('check_url', { url: url }).then(function (json) {
			if (!json || !json.success) {
				setRunning(false);
				setProgress(0, 1, (json && json.data && json.data.message) || S.failed);
				announce((json && json.data && json.data.message) || S.failed);
				return null;
			}
			var item = json.data;
			setProgress(0, 1, format(S.scanningOne, item.label || item.url));
			var bestPractice = !!cfg.bestPractice;
			var box = document.querySelector('[data-iwieac-full-form] input[name="best_practice"]');
			if (box) {
				bestPractice = box.checked;
			}
			return scanItem(item, bestPractice)
				.then(function (result) {
					return saveResult(item, -1, result);
				})
				.then(function (saved) {
					logResult(item, saved);
					setRunning(false);
					setProgress(1, 1, S.doneOne);
					announce(S.doneOne);
					if (after) {
						after(saved);
					}
					return saved;
				});
		}).catch(function () {
			setRunning(false);
			setProgress(0, 1, S.failed);
		});
	}

	/* ---------------------------------------------------------------------
	 * Bindings
	 * ------------------------------------------------------------------- */

	function bindScan() {
		var full = document.querySelector('[data-iwieac-full-form]');
		if (full) {
			full.addEventListener('submit', function (event) {
				event.preventDefault();
				if (running) {
					return;
				}
				var sources = Array.prototype.map.call(full.querySelectorAll('input[name="sources[]"]:checked'), function (el) {
					return el.value;
				});
				var max = full.querySelector('input[name="max_pages"]');
				var bp = full.querySelector('input[name="best_practice"]');
				setRunning(true);
				setProgress(0, 1, S.building);
				post('build_queue', {
					sources: sources,
					max_pages: max ? max.value : '',
					best_practice: bp && bp.checked ? '1' : '0'
				}).then(function (json) {
					if (!json || !json.success) {
						setRunning(false);
						setProgress(0, 1, (json && json.data && json.data.message) || S.failed);
						announce((json && json.data && json.data.message) || S.failed);
						return;
					}
					var log = document.querySelector('[data-iwieac-log]');
					if (log) {
						log.innerHTML = '';
					}
					runQueue(json.data);
				});
			});
		}

		var resume = document.querySelector('[data-iwieac-resume]');
		if (resume) {
			resume.addEventListener('click', function () {
				if (running) {
					return;
				}
				post('queue_status', {}).then(function (json) {
					if (json && json.success && json.data) {
						runQueue(json.data);
					} else {
						resume.hidden = true;
					}
				});
			});
		}

		var stop = document.querySelector('[data-iwieac-stop]');
		if (stop) {
			stop.addEventListener('click', function () {
				stopRequested = true;
				stop.disabled = true;
			});
		}

		var one = document.querySelector('[data-iwieac-one-form]');
		if (one) {
			one.addEventListener('submit', function (event) {
				event.preventDefault();
				if (running) {
					return;
				}
				var input = one.querySelector('input[name="url"]');
				if (input && input.value) {
					scanSingle(input.value.trim());
				}
			});
		}

		document.querySelectorAll('[data-iwieac-rescan]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (running) {
					return;
				}
				scanSingle(btn.getAttribute('data-iwieac-rescan'), function (saved) {
					if (saved && saved.success) {
						window.location.reload();
					}
				});
			});
		});

		if (cfg.autoScan && cfg.autoScan.url && one) {
			scanSingle(cfg.autoScan.url);
		}

		var clear = document.querySelector('[data-iwieac-clear]');
		if (clear) {
			clear.addEventListener('submit', function (event) {
				// eslint-disable-next-line no-alert
				if (!window.confirm(S.confirmClear)) {
					event.preventDefault();
				}
			});
		}
	}

	function bindDismiss() {
		document.querySelectorAll('[data-iwieac-dismiss]').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				var status = form.querySelector('.iwieac-dismiss__status');
				var data = {};
				new FormData(form).forEach(function (value, key) {
					data[key] = value;
				});
				if (!data.reason) {
					if (status) {
						status.textContent = S.reasonMissing;
					}
					return;
				}
				var button = form.querySelector('button[type="submit"]');
				if (button) {
					button.disabled = true;
				}
				if (status) {
					status.textContent = S.working;
				}
				post('dismiss', data).then(function (json) {
					if (json && json.success) {
						window.location.reload();
						return;
					}
					if (button) {
						button.disabled = false;
					}
					if (status) {
						status.textContent = (json && json.data && json.data.message) || S.failed;
					}
				});
			});
		});

		document.querySelectorAll('[data-iwieac-restore]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				btn.disabled = true;
				post('restore', { id: btn.getAttribute('data-iwieac-restore') }).then(function () {
					window.location.reload();
				});
			});
		});
	}

	function openFromHash() {
		if (!window.location.hash) {
			return;
		}
		var id = window.location.hash.slice(1);
		if (!/^[a-z0-9-]+$/i.test(id)) {
			return;
		}
		var card = document.getElementById(id);
		if (!card) {
			return;
		}
		var toggle = card.querySelector('.iwt-result__toggle');
		if (toggle && toggle.getAttribute('aria-expanded') !== 'true') {
			toggle.click();
		}
		card.scrollIntoView({ block: 'start' });
		if (toggle) {
			toggle.focus({ preventScroll: true });
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		bindScan();
		bindDismiss();
		// Core binds the accordions on DOMContentLoaded too; wait a tick so its handlers exist.
		window.setTimeout(openFromHash, 0);
	});
})();
