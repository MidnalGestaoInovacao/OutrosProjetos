/* EB Crédito Rural — verificação em duas etapas (perfil): QR code no navegador (qrcode-generator, MIT), alternância de painéis e botões "Copiar". Sem dependências externas. */
( function () {
	'use strict';

	var i18n = window.ebcr2fa || {};

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function renderQr() {
		var nodes = document.querySelectorAll( '[data-ebcr-otpauth]' );
		if ( ! nodes.length || 'function' !== typeof window.qrcode ) {
			return;
		}
		Array.prototype.forEach.call( nodes, function ( el ) {
			var uri = el.getAttribute( 'data-ebcr-otpauth' );
			if ( ! uri ) {
				return;
			}
			try {
				var qr = window.qrcode( 0, 'M' );
				qr.addData( uri );
				qr.make();
				el.innerHTML = qr.createSvgTag( { cellSize: 4, margin: 2, scalable: true, alt: i18n.qrAlt || '' } );
			} catch ( e ) {
				el.textContent = uri;
			}
		} );
	}

	function syncMethodPanels() {
		var radios = document.querySelectorAll( 'input[name="ebcr_2fa[method]"]' );
		if ( ! radios.length ) {
			return;
		}
		var sync = function () {
			Array.prototype.forEach.call( radios, function ( r ) {
				var panel = document.getElementById( 'ebcr-2fa-panel-' + r.value );
				if ( panel ) {
					panel.hidden = ! r.checked;
				}
			} );
		};
		Array.prototype.forEach.call( radios, function ( r ) {
			r.addEventListener( 'change', sync );
		} );
		sync();
	}

	function fallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( ta );
		return ok;
	}

	function copyButtons() {
		var buttons = document.querySelectorAll( '[data-ebcr-copy]' );
		Array.prototype.forEach.call( buttons, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var target = document.getElementById( btn.getAttribute( 'data-ebcr-copy' ) );
				var text = btn.getAttribute( 'data-ebcr-copy-text' );
				if ( ! text && target ) {
					text = 'value' in target && target.value ? target.value : target.textContent;
				}
				if ( ! text ) {
					return;
				}
				var done = function () {
					var old = btn.textContent;
					btn.textContent = i18n.copied || 'Copiado!';
					btn.setAttribute( 'aria-live', 'polite' );
					window.setTimeout( function () {
						btn.textContent = old;
					}, 1800 );
				};
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( done, function () {
						if ( fallbackCopy( text ) ) {
							done();
						}
					} );
				} else if ( fallbackCopy( text ) ) {
					done();
				}
			} );
		} );
	}

	function confirmations() {
		var boxes = document.querySelectorAll( '[data-ebcr-needs-confirm]' );
		var confirmInput = document.getElementById( 'ebcr-2fa-confirm' );
		Array.prototype.forEach.call( boxes, function ( box ) {
			box.addEventListener( 'change', function () {
				if ( box.checked && 'ebcr_2fa[regenerate]' === box.name && i18n.confirm && ! window.confirm( i18n.confirm ) ) {
					box.checked = false;
					return;
				}
				if ( box.checked && confirmInput ) {
					confirmInput.focus();
				}
			} );
		} );
	}

	ready( function () {
		renderQr();
		syncMethodPanels();
		copyButtons();
		confirmations();
	} );
}() );
