/*
 * Hingenia Insignias — admin JS.
 *
 * Fase 2: editor visual de plantillas.
 *  - Filtro/búsqueda de tarjetas de curso.
 *  - Selector de imagen (wp.media).
 *  - Cajas arrastrables/redimensionables (nombre + QR) sobre el lienzo.
 *  - Coordenadas guardadas en px de la imagen original (no del display).
 *  - Guardado/borrado vía AJAX.
 */
( function ( $ ) {
	'use strict';

	var A = window.HI_ADMIN || {};

	function ajax( action, data ) {
		return $.post( A.ajax_url, $.extend( { action: action, nonce: A.nonce }, data || {} ) );
	}

	/* ============================================================
	   Filtro + búsqueda de tarjetas
	   ============================================================ */
	function initFilter() {
		var grid = document.getElementById( 'hi-tpl-grid' );
		if ( ! grid ) { return; }
		var search   = document.getElementById( 'hi-tpl-search' );
		var chips    = document.getElementById( 'hi-tpl-filter' );
		var noresult = document.getElementById( 'hi-tpl-noresults' );
		var state    = 'all';

		function apply() {
			var q = ( search.value || '' ).trim().toLowerCase();
			var shown = 0;
			grid.querySelectorAll( '.hi-tpl-card' ).forEach( function ( card ) {
				var okState = ( state === 'all' ) || ( card.getAttribute( 'data-state' ) === state );
				var okText  = ! q || ( card.getAttribute( 'data-title' ) || '' ).indexOf( q ) !== -1;
				var show    = okState && okText;
				card.style.display = show ? '' : 'none';
				if ( show ) { shown++; }
			} );
			if ( noresult ) { noresult.hidden = shown !== 0; }
		}

		search.addEventListener( 'input', apply );
		chips.querySelectorAll( '.hi-chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				chips.querySelectorAll( '.hi-chip' ).forEach( function ( c ) { c.classList.remove( 'is-active' ); } );
				chip.classList.add( 'is-active' );
				state = chip.getAttribute( 'data-filter' );
				apply();
			} );
		} );
	}

	/* ============================================================
	   Editor
	   ============================================================ */
	var EL = {};
	var ed = {
		courseId: 0, courseTitle: '', tplId: 0,
		pngId: 0, pngUrl: '',
		natW: 0, natH: 0, scale: 1,
		layout: null,
		mediaFrame: null
	};

	function defaultLayout() {
		var tpl = document.getElementById( 'hi-default-layout' );
		try { return JSON.parse( tpl.textContent ); } catch ( e ) { return null; }
	}

	function cacheEls() {
		EL.modal     = document.getElementById( 'hi-editor' );
		EL.course    = document.getElementById( 'hi-ed-course' );
		EL.stage     = document.getElementById( 'hi-ed-stage' );
		EL.drop      = document.getElementById( 'hi-ed-drop' );
		EL.img       = document.getElementById( 'hi-ed-img' );
		EL.boxName   = document.getElementById( 'hi-box-name' );
		EL.boxNameTx = document.getElementById( 'hi-box-name-text' );
		EL.boxQr     = document.getElementById( 'hi-box-qr' );
		EL.boxQrGrid = document.getElementById( 'hi-box-qr-grid' );
		EL.imgMeta   = document.getElementById( 'hi-ed-imgmeta' );
		EL.ctrlName  = document.getElementById( 'hi-ctrl-name' );
		EL.ctrlQr    = document.getElementById( 'hi-ctrl-qr' );
		EL.upload    = document.getElementById( 'hi-ed-upload' );
		EL.change    = document.getElementById( 'hi-ed-change' );
		EL.save      = document.getElementById( 'hi-ed-save' );
		EL.status    = document.getElementById( 'hi-ed-status' );
		// controls
		EL.nSize   = document.getElementById( 'hi-name-size' );
		EL.nSizeO  = document.getElementById( 'hi-name-size-out' );
		EL.nColor  = document.getElementById( 'hi-name-color' );
		EL.nAlign  = document.getElementById( 'hi-name-align' );
		EL.nWeight = document.getElementById( 'hi-name-weight' );
		EL.nFont   = document.getElementById( 'hi-name-font' );
		EL.nUpper  = document.getElementById( 'hi-name-upper' );
		EL.qFg     = document.getElementById( 'hi-qr-fg' );
		EL.qBg     = document.getElementById( 'hi-qr-bg' );
		EL.qEnab   = document.getElementById( 'hi-qr-enabled' );
	}

	function fakeQrSvg( fg, bg ) {
		var n = 11, cell = 100 / n, r = '';
		// fondo
		r += '<rect width="100" height="100" fill="' + bg + '"/>';
		// patrón pseudo-aleatorio determinista
		var seed = 7;
		function rnd() { seed = ( seed * 9301 + 49297 ) % 233280; return seed / 233280; }
		for ( var y = 0; y < n; y++ ) {
			for ( var x = 0; x < n; x++ ) {
				if ( rnd() > 0.5 ) {
					r += '<rect x="' + ( x * cell ) + '" y="' + ( y * cell ) + '" width="' + cell + '" height="' + cell + '" fill="' + fg + '"/>';
				}
			}
		}
		// finder patterns (esquinas)
		function finder( ox, oy ) {
			var s = cell * 3;
			return '<rect x="' + ox + '" y="' + oy + '" width="' + s + '" height="' + s + '" fill="' + fg + '"/>'
				+ '<rect x="' + ( ox + cell * 0.6 ) + '" y="' + ( oy + cell * 0.6 ) + '" width="' + ( s - cell * 1.2 ) + '" height="' + ( s - cell * 1.2 ) + '" fill="' + bg + '"/>'
				+ '<rect x="' + ( ox + cell ) + '" y="' + ( oy + cell ) + '" width="' + cell + '" height="' + cell + '" fill="' + fg + '"/>';
		}
		r += finder( 0, 0 ) + finder( 100 - cell * 3, 0 ) + finder( 0, 100 - cell * 3 );
		return '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width:100%;height:100%;display:block">' + r + '</svg>';
	}

	function computeScale() {
		var wrap = EL.stage.parentElement;
		var maxW = Math.min( wrap.clientWidth - 4, 640 );
		var maxH = Math.max( 280, Math.round( window.innerHeight * 0.56 ) );
		var s = Math.min( maxW / ed.natW, maxH / ed.natH );
		if ( ! isFinite( s ) || s <= 0 ) { s = 1; }
		ed.scale = s;
		EL.stage.style.width  = ( ed.natW * s ) + 'px';
		EL.stage.style.height = ( ed.natH * s ) + 'px';
	}

	function placeBox( box, b ) {
		box.style.left   = ( b.x * ed.scale ) + 'px';
		box.style.top    = ( b.y * ed.scale ) + 'px';
		box.style.width  = ( b.w * ed.scale ) + 'px';
		box.style.height = ( b.h * ed.scale ) + 'px';
	}

	function renderBoxes() {
		if ( ! ed.natW ) { return; }
		var L = ed.layout;
		placeBox( EL.boxName, L.name );
		placeBox( EL.boxQr, L.qr );
		EL.boxName.hidden = false;
		EL.boxQr.hidden   = ! L.qr.enabled;
		syncNamePreview();
		EL.boxQrGrid.innerHTML = fakeQrSvg( L.qr.fg, L.qr.bg );
	}

	function syncNamePreview() {
		var L = ed.layout.name;
		var t = EL.boxNameTx;
		t.style.fontSize   = Math.max( 8, L.size * ed.scale ) + 'px';
		t.style.color      = L.color;
		t.style.fontWeight = L.weight;
		t.style.textTransform = L.uppercase ? 'uppercase' : 'none';
		t.style.fontFamily = ( L.font === 'serif' ) ? 'Georgia, "Times New Roman", serif' : 'Inter, system-ui, sans-serif';
		t.style.textAlign  = L.align;
		t.style.justifyContent = ( L.align === 'left' ) ? 'flex-start' : ( L.align === 'right' ? 'flex-end' : 'center' );
	}

	function syncControls() {
		var L = ed.layout;
		EL.nSize.value  = L.name.size;  EL.nSizeO.textContent = L.name.size;
		EL.nColor.value = toHex( L.name.color, '#1e293b' );
		EL.nAlign.value = L.name.align;
		EL.nWeight.value = String( L.name.weight );
		EL.nFont.value  = L.name.font;
		EL.nUpper.checked = !! L.name.uppercase;
		EL.qFg.value    = toHex( L.qr.fg, '#000000' );
		EL.qBg.value    = toHex( L.qr.bg, '#ffffff' );
		EL.qEnab.checked = !! L.qr.enabled;
	}

	function toHex( v, fb ) {
		return /^#[0-9a-fA-F]{6}$/.test( v || '' ) ? v : fb;
	}

	function bindControls() {
		EL.nSize.addEventListener( 'input', function () {
			ed.layout.name.size = parseInt( this.value, 10 ); EL.nSizeO.textContent = this.value; syncNamePreview();
		} );
		EL.nColor.addEventListener( 'input', function () { ed.layout.name.color = this.value; syncNamePreview(); } );
		EL.nAlign.addEventListener( 'change', function () { ed.layout.name.align = this.value; syncNamePreview(); } );
		EL.nWeight.addEventListener( 'change', function () { ed.layout.name.weight = parseInt( this.value, 10 ); syncNamePreview(); } );
		EL.nFont.addEventListener( 'change', function () { ed.layout.name.font = this.value; syncNamePreview(); } );
		EL.nUpper.addEventListener( 'change', function () { ed.layout.name.uppercase = this.checked; syncNamePreview(); } );
		EL.qFg.addEventListener( 'input', function () { ed.layout.qr.fg = this.value; EL.boxQrGrid.innerHTML = fakeQrSvg( ed.layout.qr.fg, ed.layout.qr.bg ); } );
		EL.qBg.addEventListener( 'input', function () { ed.layout.qr.bg = this.value; EL.boxQrGrid.innerHTML = fakeQrSvg( ed.layout.qr.fg, ed.layout.qr.bg ); } );
		EL.qEnab.addEventListener( 'change', function () { ed.layout.qr.enabled = this.checked; EL.boxQr.hidden = ! this.checked; } );
	}

	/* ---------- Drag & resize ---------- */
	function makeInteractive( box, key, square ) {
		var handle = box.querySelector( '[data-resize]' );

		box.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === handle ) { return; }
			e.preventDefault();
			var sx = e.clientX, sy = e.clientY;
			var ox = parseFloat( box.style.left ), oy = parseFloat( box.style.top );
			var sw = EL.stage.clientWidth, sh = EL.stage.clientHeight;
			var bw = box.offsetWidth, bh = box.offsetHeight;
			function mv( ev ) {
				var nx = Math.min( Math.max( 0, ox + ( ev.clientX - sx ) ), sw - bw );
				var ny = Math.min( Math.max( 0, oy + ( ev.clientY - sy ) ), sh - bh );
				box.style.left = nx + 'px'; box.style.top = ny + 'px';
				ed.layout[ key ].x = Math.round( nx / ed.scale );
				ed.layout[ key ].y = Math.round( ny / ed.scale );
			}
			function up() { document.removeEventListener( 'mousemove', mv ); document.removeEventListener( 'mouseup', up ); }
			document.addEventListener( 'mousemove', mv );
			document.addEventListener( 'mouseup', up );
		} );

		handle.addEventListener( 'mousedown', function ( e ) {
			e.preventDefault(); e.stopPropagation();
			var sx = e.clientX, sy = e.clientY;
			var ow = box.offsetWidth, oh = box.offsetHeight;
			var ox = parseFloat( box.style.left ), oy = parseFloat( box.style.top );
			var sw = EL.stage.clientWidth, sh = EL.stage.clientHeight;
			function mv( ev ) {
				var nw = Math.max( 24, ow + ( ev.clientX - sx ) );
				var nh = Math.max( 18, oh + ( ev.clientY - sy ) );
				if ( square ) { nw = nh = Math.max( nw, nh ); }
				nw = Math.min( nw, sw - ox );
				nh = Math.min( nh, sh - oy );
				if ( square ) { var m = Math.min( nw, nh ); nw = nh = m; }
				box.style.width = nw + 'px'; box.style.height = nh + 'px';
				ed.layout[ key ].w = Math.round( nw / ed.scale );
				ed.layout[ key ].h = Math.round( nh / ed.scale );
				if ( key === 'name' ) { /* tamaño de texto independiente */ }
			}
			function up() { document.removeEventListener( 'mousemove', mv ); document.removeEventListener( 'mouseup', up ); }
			document.addEventListener( 'mousemove', mv );
			document.addEventListener( 'mouseup', up );
		} );
	}

	/* ---------- Imagen ---------- */
	function loadImage( url, cb ) {
		var im = new Image();
		im.onload = function () {
			ed.natW = im.naturalWidth; ed.natH = im.naturalHeight;
			EL.img.src = url; EL.img.hidden = false;
			EL.drop.hidden = true;
			EL.change.hidden = false;
			EL.imgMeta.textContent = ed.natW + ' × ' + ed.natH + ' px';
			EL.ctrlName.hidden = false; EL.ctrlQr.hidden = false;
			EL.save.disabled = false;
			computeScale();
			renderBoxes();
			if ( cb ) { cb(); }
		};
		im.onerror = function () { setStatus( 'No se pudo cargar la imagen.', true ); };
		im.src = url;
	}

	function openMedia() {
		if ( ed.mediaFrame ) { ed.mediaFrame.open(); return; }
		ed.mediaFrame = wp.media( {
			title: 'Selecciona la imagen base de la insignia',
			button: { text: 'Usar esta imagen' },
			library: { type: 'image' },
			multiple: false
		} );
		ed.mediaFrame.on( 'select', function () {
			var att = ed.mediaFrame.state().get( 'selection' ).first().toJSON();
			ed.pngId  = att.id;
			ed.pngUrl = att.url;
			loadImage( att.url );
		} );
		ed.mediaFrame.open();
	}

	/* ---------- Abrir / cerrar ---------- */
	function openEditor( data ) {
		ed.courseId    = parseInt( data.course, 10 );
		ed.courseTitle = data.courseTitle || '';
		ed.tplId       = parseInt( data.tplId, 10 ) || 0;
		ed.pngId       = parseInt( data.pngId, 10 ) || 0;
		ed.pngUrl      = data.pngUrl || '';
		ed.natW = ed.natH = 0;
		try { ed.layout = JSON.parse( data.layout ); } catch ( e ) { ed.layout = defaultLayout(); }
		if ( ! ed.layout ) { ed.layout = defaultLayout(); }

		EL.course.textContent = ed.courseTitle;
		setStatus( '' );
		EL.save.disabled = true;
		EL.change.hidden = true;
		EL.img.hidden = true;
		EL.boxName.hidden = true;
		EL.boxQr.hidden = true;
		EL.drop.hidden = false;
		EL.ctrlName.hidden = true;
		EL.ctrlQr.hidden = true;
		EL.imgMeta.textContent = 'Ninguna imagen aún.';

		EL.modal.hidden = false;
		EL.modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'hi-modal-open' );

		syncControls();
		if ( ed.pngUrl ) { loadImage( ed.pngUrl ); }
	}

	function closeEditor() {
		EL.modal.hidden = true;
		EL.modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'hi-modal-open' );
	}

	function setStatus( msg, err ) {
		EL.status.textContent = msg || '';
		EL.status.className = 'hi-editor__status' + ( err ? ' is-error' : ( msg ? ' is-ok' : '' ) );
	}

	function save() {
		if ( ! ed.pngUrl && ! ed.pngId ) { setStatus( 'Sube una imagen primero.', true ); return; }
		ed.layout.canvas_w = ed.natW;
		ed.layout.canvas_h = ed.natH;
		EL.save.disabled = true;
		setStatus( 'Guardando…' );
		ajax( 'hi_save_template', {
			course_id: ed.courseId,
			nombre: ed.courseTitle,
			png_attachment_id: ed.pngId,
			png_url: ed.pngUrl,
			layout_json: JSON.stringify( ed.layout )
		} ).done( function ( res ) {
			if ( res && res.success ) {
				setStatus( 'Guardado ✓' );
				setTimeout( function () { window.location.reload(); }, 600 );
			} else {
				setStatus( ( res && res.data && res.data.msg ) || 'Error al guardar.', true );
				EL.save.disabled = false;
			}
		} ).fail( function () {
			setStatus( 'Error de red.', true );
			EL.save.disabled = false;
		} );
	}

	function initEditor() {
		if ( ! document.getElementById( 'hi-editor' ) ) { return; }
		cacheEls();
		bindControls();
		makeInteractive( EL.boxName, 'name', false );
		makeInteractive( EL.boxQr, 'qr', true );

		EL.upload.addEventListener( 'click', openMedia );
		EL.change.addEventListener( 'click', openMedia );
		EL.save.addEventListener( 'click', save );

		EL.modal.querySelectorAll( '[data-close]' ).forEach( function ( b ) {
			b.addEventListener( 'click', closeEditor );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! EL.modal.hidden ) { closeEditor(); }
		} );

		// abrir editor desde tarjetas
		document.querySelectorAll( '.hi-tpl-edit' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				openEditor( {
					course: btn.getAttribute( 'data-course' ),
					courseTitle: btn.getAttribute( 'data-course-title' ),
					tplId: btn.getAttribute( 'data-tpl-id' ),
					pngId: btn.getAttribute( 'data-png-id' ),
					pngUrl: btn.getAttribute( 'data-png-url' ),
					layout: btn.getAttribute( 'data-layout' )
				} );
			} );
		} );

		// borrar plantilla
		document.querySelectorAll( '.hi-tpl-delete' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! window.confirm( '¿Eliminar esta plantilla? Las insignias ya emitidas no se borran.' ) ) { return; }
				ajax( 'hi_delete_template', { id: btn.getAttribute( 'data-tpl-id' ) } ).done( function () {
					window.location.reload();
				} );
			} );
		} );

		// reflow del lienzo al cambiar tamaño de ventana
		window.addEventListener( 'resize', function () {
			if ( EL.modal.hidden || ! ed.natW ) { return; }
			computeScale(); renderBoxes();
		} );

		// preselección desde dashboard (?curso=ID)
		if ( window.HI_PRESELECT_COURSE ) {
			var pre = document.querySelector( '.hi-tpl-edit[data-course="' + window.HI_PRESELECT_COURSE + '"]' );
			if ( pre ) { pre.click(); }
		}
	}

	/* ============================================================
	   Emisiones: filtros + emitir individual + revocar
	   ============================================================ */
	function initEmisiones() {
		var search = document.getElementById( 'hi-em-search' );
		var courseSel = document.getElementById( 'hi-em-course' );
		var table  = document.getElementById( 'hi-em-table' );
		var nores  = document.getElementById( 'hi-em-noresults' );

		if ( table ) {
			function apply() {
				var q = ( search.value || '' ).trim().toLowerCase();
				var cf = courseSel.value;
				var shown = 0;
				table.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
					var okText = ! q || ( tr.getAttribute( 'data-search' ) || '' ).indexOf( q ) !== -1;
					var okCourse = ! cf || tr.getAttribute( 'data-course' ) === cf;
					var show = okText && okCourse;
					tr.style.display = show ? '' : 'none';
					if ( show ) { shown++; }
				} );
				if ( nores ) { nores.hidden = shown !== 0; }
			}
			search.addEventListener( 'input', apply );
			courseSel.addEventListener( 'change', apply );

			table.querySelectorAll( '.hi-em-revoke' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					if ( ! window.confirm( '¿Revocar esta insignia? Se eliminará su imagen y dejará de ser válida.' ) ) { return; }
					ajax( 'hi_revoke_cert', { id: btn.getAttribute( 'data-id' ) } ).done( function () {
						var tr = btn.closest( 'tr' );
						if ( tr ) { tr.parentNode.removeChild( tr ); }
					} );
				} );
			} );
		}

		// Modal emitir: lista de matriculados
		var modal = document.getElementById( 'hi-emit-modal' );
		if ( ! modal ) { return; }
		var openBtn   = document.getElementById( 'hi-emit-open' );
		var courseSel2 = document.getElementById( 'hi-emit-course' );
		var listEl    = document.getElementById( 'hi-emit-list' );
		var searchEl  = document.getElementById( 'hi-emit-search' );
		var allChk    = document.getElementById( 'hi-emit-all' );
		var reissueChk = document.getElementById( 'hi-emit-reissue' );
		var countEl   = document.getElementById( 'hi-emit-count' );
		var go        = document.getElementById( 'hi-emit-go' );
		var status    = document.getElementById( 'hi-emit-status' );
		var progress  = document.getElementById( 'hi-emit-progress' );
		var bar       = document.getElementById( 'hi-emit-bar' );
		var progressTxt = document.getElementById( 'hi-emit-progress-txt' );
		var manualToggle = document.getElementById( 'hi-emit-manual-toggle' );
		var manualForm   = document.getElementById( 'hi-emit-manual-form' );
		var mName  = document.getElementById( 'hi-emit-mname' );
		var mEmail = document.getElementById( 'hi-emit-memail' );
		var mAdd   = document.getElementById( 'hi-emit-madd' );
		var students = [];   // {user_id,name,email,has,manual?}

		function open() {
			modal.hidden = false; document.body.classList.add( 'hi-modal-open' );
			status.textContent = ''; progress.hidden = true; bar.style.width = '0%';
			loadStudents();
		}
		function close() { modal.hidden = true; document.body.classList.remove( 'hi-modal-open' ); }
		if ( openBtn ) { openBtn.addEventListener( 'click', open ); }
		modal.querySelectorAll( '[data-close]' ).forEach( function ( b ) { b.addEventListener( 'click', close ); } );

		function esc( s ) { return ( s || '' ).replace( /[&<>"']/g, function ( m ) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]; } ); }

		function loadStudents() {
			listEl.innerHTML = '<div class="hi-emit-loading">Cargando estudiantes…</div>';
			go.disabled = true; countEl.textContent = '0';
			ajax( 'hi_course_students', { course_id: courseSel2.value } ).done( function ( res ) {
				if ( res && res.success ) {
					students = res.data.students || [];
					render();
				} else {
					listEl.innerHTML = '<div class="hi-emit-loading">No se pudieron cargar.</div>';
				}
			} ).fail( function () { listEl.innerHTML = '<div class="hi-emit-loading">Error de red.</div>'; } );
		}

		function render() {
			if ( ! students.length ) {
				listEl.innerHTML = '<div class="hi-emit-loading">Este curso no tiene estudiantes matriculados.</div>';
				updateCount(); return;
			}
			var html = '';
			students.forEach( function ( s, i ) {
				var tag = s.has ? '<span class="hi-tag hi-tag--skip">ya tiene</span>' : '<span class="hi-tag hi-tag--ok">pendiente</span>';
				var checked = s.has ? '' : 'checked';
				var dis = ( s.has && ! reissueChk.checked ) ? 'disabled' : '';
				html += '<label class="hi-emit-row" data-i="' + i + '" data-search="' + esc( ( s.name + ' ' + ( s.email || '' ) ).toLowerCase() ) + '">'
					+ '<input type="checkbox" class="hi-emit-cb" ' + checked + ' ' + dis + '>'
					+ '<span class="hi-emit-row__ava">' + esc( ( s.name || '·' ).charAt(0).toUpperCase() ) + '</span>'
					+ '<span class="hi-emit-row__info"><span class="hi-emit-row__name">' + esc( s.name ) + ( s.manual ? ' <em>(manual)</em>' : '' ) + '</span>'
					+ '<span class="hi-emit-row__mail">' + esc( s.email || 'sin correo' ) + '</span></span>'
					+ tag + '</label>';
			} );
			listEl.innerHTML = html;
			listEl.querySelectorAll( '.hi-emit-cb' ).forEach( function ( cb ) {
				cb.addEventListener( 'change', updateCount );
			} );
			applySearch();
			updateCount();
		}

		function applySearch() {
			var q = ( searchEl.value || '' ).trim().toLowerCase();
			listEl.querySelectorAll( '.hi-emit-row' ).forEach( function ( row ) {
				row.style.display = ( ! q || ( row.getAttribute( 'data-search' ) || '' ).indexOf( q ) !== -1 ) ? '' : 'none';
			} );
		}

		function selected() {
			var out = [];
			listEl.querySelectorAll( '.hi-emit-row' ).forEach( function ( row ) {
				var cb = row.querySelector( '.hi-emit-cb' );
				if ( cb && cb.checked ) { out.push( students[ parseInt( row.getAttribute( 'data-i' ), 10 ) ] ); }
			} );
			return out;
		}

		function updateCount() {
			var n = selected().length;
			countEl.textContent = n;
			go.disabled = n === 0;
		}

		courseSel2.addEventListener( 'change', loadStudents );
		searchEl.addEventListener( 'input', applySearch );
		allChk.addEventListener( 'change', function () {
			listEl.querySelectorAll( '.hi-emit-row' ).forEach( function ( row ) {
				if ( row.style.display === 'none' ) { return; }
				var cb = row.querySelector( '.hi-emit-cb' );
				if ( cb && ! cb.disabled ) { cb.checked = allChk.checked; }
			} );
			updateCount();
		} );
		reissueChk.addEventListener( 'change', render );

		manualToggle.addEventListener( 'click', function () { manualForm.hidden = ! manualForm.hidden; if ( ! manualForm.hidden ) { mName.focus(); } } );
		mAdd.addEventListener( 'click', function () {
			var nm = mName.value.trim(); if ( ! nm ) { mName.focus(); return; }
			students.unshift( { user_id: 0, name: nm, email: mEmail.value.trim(), has: false, manual: true } );
			mName.value = ''; mEmail.value = '';
			render();
		} );

		go.addEventListener( 'click', function () {
			var sel = selected();
			if ( ! sel.length ) { return; }
			var rows = sel.map( function ( s ) { return { name: s.name, email: s.email || '' }; } );
			go.disabled = true; progress.hidden = false; status.textContent = '';
			var course = courseSel2.value, reissue = reissueChk.checked ? 1 : 0;
			var CHUNK = 20, done = 0, tot = rows.length, agg = { ok:0, skip:0, err:0 };

			function chunk( start ) {
				var part = rows.slice( start, start + CHUNK );
				if ( ! part.length ) { finish(); return; }
				ajax( 'hi_emit_batch', { course_id: course, reissue: reissue, rows: JSON.stringify( part ) } ).done( function ( res ) {
					if ( res && res.success ) { agg.ok += res.data.ok; agg.skip += res.data.skip; agg.err += res.data.err; }
					done += part.length;
					bar.style.width = Math.round( done / tot * 100 ) + '%';
					progressTxt.textContent = 'Emitiendo… ' + done + ' / ' + tot;
					chunk( start + CHUNK );
				} ).fail( function () { progressTxt.textContent = 'Error de red.'; go.disabled = false; } );
			}
			function finish() {
				progressTxt.textContent = 'Completado: ' + agg.ok + ' emitidas, ' + agg.skip + ' omitidas, ' + agg.err + ' con error.';
				status.textContent = '✓ Listo'; status.className = 'hi-editor__status is-ok';
				setTimeout( function () { window.location.reload(); }, 1200 );
			}
			chunk( 0 );
		} );
	}

	/* ============================================================
	   Importar CSV
	   ============================================================ */
	function parseCSV( text ) {
		var rows = [], row = [], field = '', i = 0, inQ = false, c;
		text = text.replace( /\r\n/g, '\n' ).replace( /\r/g, '\n' );
		for ( ; i < text.length; i++ ) {
			c = text[ i ];
			if ( inQ ) {
				if ( c === '"' ) {
					if ( text[ i + 1 ] === '"' ) { field += '"'; i++; }
					else { inQ = false; }
				} else { field += c; }
			} else {
				if ( c === '"' ) { inQ = true; }
				else if ( c === ',' || c === ';' || c === '\t' ) { row.push( field ); field = ''; }
				else if ( c === '\n' ) { row.push( field ); rows.push( row ); row = []; field = ''; }
				else { field += c; }
			}
		}
		if ( field !== '' || row.length ) { row.push( field ); rows.push( row ); }
		return rows.filter( function ( r ) { return r.some( function ( x ) { return ( x || '' ).trim() !== ''; } ); } );
	}

	function initImportar() {
		var courseSel = document.getElementById( 'hi-imp-course' );
		if ( ! courseSel ) { return; }
		var fileInput = document.getElementById( 'hi-imp-file' );
		var browse    = document.getElementById( 'hi-imp-browse' );
		var drop      = document.getElementById( 'hi-imp-drop' );
		var paste     = document.getElementById( 'hi-imp-paste' );
		var previewBtn = document.getElementById( 'hi-imp-preview' );
		var headerChk = document.getElementById( 'hi-imp-header' );
		var reissueChk = document.getElementById( 'hi-imp-reissue' );
		var step3     = document.getElementById( 'hi-imp-step3' );
		var tbody     = document.getElementById( 'hi-imp-tbody' );
		var countPill = document.getElementById( 'hi-imp-count' );
		var emitBtn   = document.getElementById( 'hi-imp-emit' );
		var progress  = document.getElementById( 'hi-imp-progress' );
		var bar       = document.getElementById( 'hi-imp-bar' );
		var progressTxt = document.getElementById( 'hi-imp-progress-txt' );
		var summary   = document.getElementById( 'hi-imp-summary' );
		var parsed    = [];

		function readFile( file ) {
			var fr = new FileReader();
			fr.onload = function () { paste.value = fr.result; buildPreview(); };
			fr.readAsText( file, 'UTF-8' );
		}

		browse.addEventListener( 'click', function () { fileInput.click(); } );
		fileInput.addEventListener( 'change', function () { if ( fileInput.files[0] ) { readFile( fileInput.files[0] ); } } );
		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.add( 'is-over' ); } );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.remove( 'is-over' ); } );
		} );
		drop.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer.files[0] ) { readFile( e.dataTransfer.files[0] ); }
		} );
		previewBtn.addEventListener( 'click', buildPreview );

		function looksEmail( s ) { return /@/.test( s || '' ); }

		function buildPreview() {
			var rows = parseCSV( paste.value || '' );
			if ( headerChk.checked && rows.length ) { rows = rows.slice( 1 ); }
			parsed = rows.map( function ( r ) {
				var name = ( r[0] || '' ).trim();
				var email = ( r[1] || '' ).trim();
				// si la primera columna es un email y no hay segunda, ajusta
				if ( ! email && looksEmail( name ) ) { email = name; name = ''; }
				return { name: name, email: email };
			} ).filter( function ( x ) { return x.name || x.email; } );

			tbody.innerHTML = '';
			parsed.forEach( function ( p, idx ) {
				var tr = document.createElement( 'tr' );
				tr.innerHTML = '<td class="hi-muted">' + ( idx + 1 ) + '</td>'
					+ '<td>' + escapeHtml( p.name || '—' ) + '</td>'
					+ '<td class="hi-muted">' + escapeHtml( p.email || '—' ) + '</td>'
					+ '<td id="hi-imp-st-' + idx + '"><span class="hi-muted">pendiente</span></td>';
				tbody.appendChild( tr );
			} );
			countPill.textContent = parsed.length + ' filas';
			step3.hidden = parsed.length === 0;
			summary.hidden = true;
			progress.hidden = true;
			bar.style.width = '0%';
			emitBtn.disabled = parsed.length === 0;
			if ( parsed.length === 0 ) { window.alert( 'No se encontraron filas válidas.' ); }
		}

		function escapeHtml( s ) {
			return ( s || '' ).replace( /[&<>"']/g, function ( m ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ m ];
			} );
		}

		emitBtn.addEventListener( 'click', function () {
			if ( ! parsed.length ) { return; }
			emitBtn.disabled = true;
			progress.hidden = false;
			summary.hidden = true;
			var course = courseSel.value;
			var reissue = reissueChk.checked ? 1 : 0;
			var CHUNK = 20, done = 0, tot = parsed.length;
			var agg = { ok: 0, skip: 0, err: 0 };

			function sendChunk( start ) {
				var chunk = parsed.slice( start, start + CHUNK );
				if ( ! chunk.length ) { finish(); return; }
				ajax( 'hi_emit_batch', { course_id: course, reissue: reissue, rows: JSON.stringify( chunk ) } ).done( function ( res ) {
					if ( res && res.success ) {
						agg.ok += res.data.ok; agg.skip += res.data.skip; agg.err += res.data.err;
						( res.data.items || [] ).forEach( function ( it, i ) {
							var cell = document.getElementById( 'hi-imp-st-' + ( start + i ) );
							if ( cell ) {
								if ( it.status === 'ok' ) { cell.innerHTML = '<span class="hi-tag hi-tag--ok">emitida</span>'; }
								else if ( it.status === 'skip' ) { cell.innerHTML = '<span class="hi-tag hi-tag--skip">ya tenía</span>'; }
								else { cell.innerHTML = '<span class="hi-tag hi-tag--err" title="' + escapeHtml( it.msg || '' ) + '">error</span>'; }
							}
						} );
					}
					done += chunk.length;
					var pct = Math.round( done / tot * 100 );
					bar.style.width = pct + '%';
					progressTxt.textContent = 'Emitiendo… ' + done + ' / ' + tot;
					sendChunk( start + CHUNK );
				} ).fail( function () {
					progressTxt.textContent = 'Error de red durante la emisión.';
					emitBtn.disabled = false;
				} );
			}
			function finish() {
				progressTxt.textContent = 'Completado.';
				summary.hidden = false;
				summary.innerHTML = '<span class="hi-tag hi-tag--ok">' + agg.ok + ' emitidas</span> '
					+ '<span class="hi-tag hi-tag--skip">' + agg.skip + ' omitidas</span> '
					+ '<span class="hi-tag hi-tag--err">' + agg.err + ' con error</span> '
					+ '<a href="' + ( A.base ? A.base + '-emisiones' : '#' ) + '" class="hi-link">Ver emisiones →</a>';
			}
			sendChunk( 0 );
		} );
	}

	$( function () {
		initFilter();
		initEditor();
		initEmisiones();
		initImportar();
	} );

} )( jQuery );
