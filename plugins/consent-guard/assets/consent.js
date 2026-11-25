/**
 * Consent Guard — lógica del banner en el navegador.
 *
 * - Lee y guarda la elección en la cookie propia (JSON: v, id, r, c, t).
 * - Activa los scripts que el servidor dejó en type="text/plain" según las categorías aceptadas.
 * - Envía la señal a Google Consent Mode v2 y a Microsoft Clarity.
 * - Registra la elección en el servidor (si el registro está activo).
 * - Nunca usa innerHTML con datos: todo el HTML lo genera PHP ya escapado.
 */
(function () {
	'use strict';

	var cfg = window.consentGuardConfig;
	if (!cfg || typeof cfg !== 'object') {
		return;
	}

	var root = document.getElementById('consent-guard');
	if (!root) {
		return;
	}

	var banner = root.querySelector('[data-cg-banner]');
	var prefs = root.querySelector('[data-cg-prefs]');
	var reopen = root.querySelector('.cg-reopen');
	var optional = Array.isArray(cfg.categories) ? cfg.categories : [];
	var lastFocus = null;

	// ---------------------------------------------------------------------
	// Cookie de consentimiento
	// ---------------------------------------------------------------------

	function readConsent() {
		var match = document.cookie.match(new RegExp('(?:^|; )' + cfg.cookie + '=([^;]*)'));
		if (!match) {
			return null;
		}
		try {
			var data = JSON.parse(decodeURIComponent(match[1]));
			if (!data || data.v !== 1 || data.r !== cfg.revision || !Array.isArray(data.c) || !/^[a-f0-9]{32}$/.test(String(data.id))) {
				return null;
			}
			data.c = data.c.filter(function (c) { return optional.indexOf(c) !== -1; });
			return data;
		} catch (e) {
			return null;
		}
	}

	function newId() {
		var bytes = new Uint8Array(16);
		window.crypto.getRandomValues(bytes);
		return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
	}

	function writeConsent(categories, previous) {
		var data = {
			v: 1,
			id: previous ? previous.id : newId(),
			r: cfg.revision,
			c: categories,
			t: Math.floor(Date.now() / 1000)
		};
		var parts = [
			cfg.cookie + '=' + encodeURIComponent(JSON.stringify(data)),
			'Max-Age=' + (cfg.days * 86400),
			'Path=' + cfg.path,
			'SameSite=Lax'
		];
		if (cfg.secure) {
			parts.push('Secure');
		}
		document.cookie = parts.join('; ');
		return data;
	}

	// Borra las cookies de las categorías que el visitante retiró (en el dominio actual y el principal).
	function deleteCookies(categories) {
		var names = [];
		categories.forEach(function (c) {
			(cfg.cookies && cfg.cookies[c] || []).forEach(function (n) { names.push(n); });
		});
		if (!names.length) {
			return;
		}

		var host = window.location.hostname;
		var domains = ['', host, '.' + host];
		var pieces = host.split('.');
		if (pieces.length > 2) {
			domains.push('.' + pieces.slice(-2).join('.'));
		}

		document.cookie.split('; ').forEach(function (pair) {
			var name = pair.split('=')[0];
			var hit = names.some(function (n) { return name === n || (n.slice(-1) === '_' && name.indexOf(n) === 0); });
			if (!hit) {
				return;
			}
			domains.forEach(function (d) {
				['/', cfg.path].forEach(function (p) {
					document.cookie = name + '=; Max-Age=0; Path=' + p + (d ? '; Domain=' + d : '');
				});
			});
		});
	}

	// ---------------------------------------------------------------------
	// Activación de scripts bloqueados
	// ---------------------------------------------------------------------

	function activateScripts(categories) {
		var blocked = Array.prototype.slice.call(document.querySelectorAll('script[type="text/plain"][data-cg-category]'));
		var queue = blocked.filter(function (s) { return categories.indexOf(s.getAttribute('data-cg-category')) !== -1; });

		// En orden: cada script externo espera a cargar antes del siguiente, como en la página original.
		queue.reduce(function (chain, old) {
			return chain.then(function () { return runScript(old); });
		}, Promise.resolve());
	}

	function runScript(old) {
		return new Promise(function (resolve) {
			var script = document.createElement('script');
			Array.prototype.forEach.call(old.attributes, function (attr) {
				if (attr.name !== 'type' && attr.name.indexOf('data-cg-') !== 0) {
					script.setAttribute(attr.name, attr.value);
				}
			});
			var type = old.getAttribute('data-cg-type');
			if (type) {
				script.type = type;
			}
			if (old.src) {
				script.async = false;
				script.onload = script.onerror = function () { resolve(); };
			} else {
				script.text = old.text;
			}
			old.parentNode.replaceChild(script, old);
			if (!old.src) {
				resolve();
			}
		});
	}

	// ---------------------------------------------------------------------
	// Señales a terceros
	// ---------------------------------------------------------------------

	function signal(categories) {
		var has = function (c) { return categories.indexOf(c) !== -1 ? 'granted' : 'denied'; };

		if (cfg.gcm && typeof window.gtag === 'function') {
			window.gtag('consent', 'update', {
				ad_storage: has('marketing'),
				ad_user_data: has('marketing'),
				ad_personalization: has('marketing'),
				analytics_storage: has('analytics'),
				functionality_storage: has('functional'),
				personalization_storage: has('functional')
			});
		}

		if (typeof window.clarity === 'function') {
			window.clarity('consentv2', { ad_Storage: has('marketing'), analytics_Storage: has('analytics') });
		}

		document.dispatchEvent(new CustomEvent('consentguard:change', { detail: { categories: categories.slice() } }));
	}

	function log(data, action) {
		if (!cfg.log || !window.fetch) {
			return;
		}
		window.fetch(cfg.endpoint, {
			method: 'POST',
			credentials: 'omit',
			keepalive: true,
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ id: data.id, action: action, categories: data.c, revision: data.r })
		}).catch(function () { /* Sin conexión: la elección ya quedó en la cookie. */ });
	}

	// ---------------------------------------------------------------------
	// Interfaz
	// ---------------------------------------------------------------------

	function isDialog(el) {
		return el && typeof el.showModal === 'function';
	}

	function showBanner() {
		if (!banner) {
			return;
		}
		if (isDialog(banner)) {
			if (!banner.open) {
				banner.showModal();
			}
		} else {
			banner.hidden = false;
		}
		if (reopen) {
			reopen.hidden = true;
		}
	}

	function hideBanner() {
		if (!banner) {
			return;
		}
		if (isDialog(banner)) {
			if (banner.open) {
				banner.close();
			}
		} else {
			banner.hidden = true;
		}
		if (reopen) {
			reopen.hidden = false;
		}
	}

	function openPrefs() {
		var current = readConsent();
		var gpcOn = cfg.gpc && navigator.globalPrivacyControl === true;
		root.querySelectorAll('[data-cg-toggle]').forEach(function (input) {
			var cat = input.getAttribute('data-cg-toggle');
			input.checked = current ? current.c.indexOf(cat) !== -1 : false;
			// Con GPC activo, publicidad arranca desmarcada aunque antes se hubiera aceptado.
			if (gpcOn && !current && cat === 'marketing') {
				input.checked = false;
			}
		});
		lastFocus = document.activeElement;
		if (banner && isDialog(banner) && banner.open) {
			banner.close();
		}
		if (isDialog(prefs)) {
			prefs.showModal();
		}
	}

	function closePrefs() {
		if (isDialog(prefs) && prefs.open) {
			prefs.close();
		}
	}

	function decide(action) {
		var previous = readConsent();
		var categories;

		if (action === 'accept_all') {
			categories = optional.slice();
		} else if (action === 'reject_all') {
			categories = [];
		} else {
			categories = [];
			root.querySelectorAll('[data-cg-toggle]').forEach(function (input) {
				if (input.checked) {
					categories.push(input.getAttribute('data-cg-toggle'));
				}
			});
			action = 'custom';
		}

		// Con GPC, «aceptar todo» no incluye publicidad (la señal del navegador manda).
		if (cfg.gpc && navigator.globalPrivacyControl === true && action === 'accept_all') {
			categories = categories.filter(function (c) { return c !== 'marketing'; });
		}

		var data = writeConsent(categories, previous);
		var revoked = previous ? previous.c.filter(function (c) { return categories.indexOf(c) === -1; }) : [];

		closePrefs();
		hideBanner();
		signal(categories);
		log(data, action);

		if (revoked.length) {
			// Los scripts que ya corrieron no se pueden descargar: se borran sus cookies y se recarga.
			deleteCookies(revoked);
			window.location.reload();
			return;
		}

		activateScripts(categories);

		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	document.addEventListener('click', function (event) {
		var target = event.target instanceof Element ? event.target : null;
		if (!target) {
			return;
		}

		var opener = target.closest('[data-cg-open]');
		if (opener) {
			event.preventDefault();
			openPrefs();
			return;
		}

		if (!root.contains(target)) {
			return;
		}

		var button = target.closest('[data-cg-action]');
		if (button) {
			var action = button.getAttribute('data-cg-action');
			if (action === 'customize') {
				openPrefs();
			} else {
				decide(action);
			}
			return;
		}

		if (target.closest('[data-cg-close]')) {
			closePrefs();
			if (!readConsent()) {
				showBanner();
			}
		}
	});

	// El banner tipo ventana exige una elección: Escape no lo cierra sin decidir.
	if (isDialog(banner)) {
		banner.addEventListener('cancel', function (event) { event.preventDefault(); });
	}

	if (prefs) {
		prefs.addEventListener('close', function () {
			if (!readConsent()) {
				showBanner();
			}
		});
	}

	// API pública mínima para integraciones.
	window.ConsentGuard = {
		open: openPrefs,
		has: function (category) {
			if (category === 'necessary') {
				return true;
			}
			var current = readConsent();
			return !!current && current.c.indexOf(category) !== -1;
		}
	};

	// ---------------------------------------------------------------------
	// Arranque
	// ---------------------------------------------------------------------

	var stored = readConsent();
	if (stored) {
		activateScripts(stored.c);
		signal(stored.c);
		if (reopen) {
			reopen.hidden = false;
		}
	} else {
		showBanner();
	}
})();
