/* Level Up embed, parent side.
 *
 * Two jobs:
 *  1. Resize the iframe to its content. An iframe has a fixed height and cannot
 *     size itself, so the embedded page measures itself and posts its height
 *     here. Without this the embed is a short box with its own scrollbar.
 *  2. Submit the signup form to the WordPress REST endpoint, which stores the
 *     address. The embedded page is static and has nowhere to put it.
 */
(function () {
	'use strict';

	var cfg = window.LevelUpEmbed || {};
	var S = cfg.strings || {};
	var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

	document.querySelectorAll('.lu-embed').forEach(function (root) {
		var frame  = root.querySelector('.lu-frame');
		var signup = root.querySelector('.lu-signup');
		var form   = root.querySelector('.lu-form');
		var input  = root.querySelector('.lu-input');
		var trap   = root.querySelector('.lu-trap');
		var btn    = root.querySelector('.lu-btn');
		var out    = root.querySelector('.lu-status');

		/* --- messages from the embedded page --------------------------- */
		window.addEventListener('message', function (e) {
			// Never skip this: without an origin check any site could resize the
			// frame or drive scrolling on this page. An empty configured origin
			// means the URL was unusable, so nothing is trusted.
			if (!cfg.origin || e.origin !== cfg.origin) return;
			if (!frame || e.source !== frame.contentWindow) return;

			var d = e.data;
			if (!d || d.source !== 'level-up') return;

			if (d.type === 'height' && typeof d.height === 'number' && d.height > 0) {
				frame.style.height = Math.ceil(d.height) + 'px';
				sendViewport();
			}

			// A link in the embedded page pointed at a section that now lives out
			// here (the signup form), so scroll to it on its behalf.
			if (d.type === 'navigate' && signup) {
				signup.scrollIntoView({ behavior: 'smooth', block: 'start' });
				if (input) setTimeout(function () { input.focus(); }, 400);
			}
		});

		/* --- telling the frame what is on screen ------------------------ */

		/* The frame is sized to its full content, so from inside it there is no
		 * such thing as "the visible area" — position:fixed would pin the crew
		 * modal to the middle of the whole page instead of the reader's screen.
		 * Send the slice of the frame currently in view so it can place the
		 * modal over it. */
		function sendViewport() {
			if (!frame || !frame.contentWindow || !cfg.origin) return;
			var r = frame.getBoundingClientRect();
			var offset = Math.max(0, -r.top);
			var height = Math.max(0, Math.min(r.bottom, window.innerHeight) - Math.max(r.top, 0));
			frame.contentWindow.postMessage(
				{ source: 'level-up-parent', type: 'viewport', offset: Math.round(offset), height: Math.round(height) },
				cfg.origin // never '*': this goes to a specific known frame
			);
		}

		window.addEventListener('scroll', sendViewport, { passive: true });
		window.addEventListener('resize', sendViewport);
		if (frame) frame.addEventListener('load', sendViewport);

		/* --- signup form ------------------------------------------------ */
		if (!form) return;

		var done = false;

		function say(message, isError) {
			out.textContent = message;
			root.classList.toggle('is-error', !!isError);
		}

		input.addEventListener('input', function () {
			if (!done) say(S.idle || '', false);
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (done || btn.disabled) return;

			var email = (input.value || '').trim();
			if (!EMAIL.test(email)) {
				say(S.bad || 'Invalid email', true);
				input.focus();
				return;
			}

			btn.disabled = true;
			btn.textContent = '...';
			say(S.sending || '', false);

			// The honeypot is sent rather than judged here, so a bot posting
			// straight at the endpoint meets the same check instead of skipping it.
			fetch(cfg.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: email, company: trap ? trap.value : '' })
			}).then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			}).then(function () {
				done = true;
				btn.textContent = S.done || 'DONE';
				say(S.ok || '', false);
			}).catch(function () {
				btn.disabled = false;
				btn.textContent = S.join || 'JOIN';
				say(S.fail || 'Try again', true);
			});
		});
	});
})();
