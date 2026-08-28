/* global F1 */

/**
 * vendors/F1/js/dialog.js
 *
 * F1 Dialog - 28 Aug 2026
 *
 * Purpose: Confirm, validate, auth, form, prompt. Drag + close X on every panel.
 *
 * @package F1
 * @author Senpai
 *
 * @version 1.0 - INIT - 28 Aug 2026 - Port S5 modal-dialogs; add form/prompt
 */

( function( F1 ) {

  const MODAL_Z = 12000;
  const CLOSE_X = '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"'
    + ' aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';


  function makeDraggable( panel, handle, opts ) {
    opts = opts || {};
    if ( ! panel || ! handle || panel.dataset.draggable === '1' ) return;
    panel.dataset.draggable = '1';
    handle.classList.add( 'modal-drag-handle' );
    const ignore = opts.ignore || '';
    let dragging = false, startX = 0, startY = 0, startLeft = 0, startTop = 0;

    const onMove = function( e ) {
      if ( ! dragging ) return;
      panel.style.left = ( startLeft + e.clientX - startX ) + 'px';
      panel.style.top = ( startTop + e.clientY - startY ) + 'px';
    };

    const onUp = function() {
      if ( ! dragging ) return;
      dragging = false;
      panel.classList.remove( 'dragging' );
      document.removeEventListener( 'mousemove', onMove );
      document.removeEventListener( 'mouseup', onUp );
    };

    handle.addEventListener( 'mousedown', function( e ) {
      if ( e.button !== 0 ) return;
      if ( ignore && e.target.closest( ignore ) ) return;
      if ( e.target.closest( 'button, a, input, select, textarea, label' ) ) return;
      const rect = panel.getBoundingClientRect();
      panel.style.position = 'fixed';
      panel.style.left = rect.left + 'px';
      panel.style.top = rect.top + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      panel.style.margin = '0';
      panel.style.transform = 'none';
      panel.style.transition = 'none';
      dragging = true;
      startX = e.clientX;
      startY = e.clientY;
      startLeft = rect.left;
      startTop = rect.top;
      panel.classList.add( 'dragging' );
      e.preventDefault();
      document.addEventListener( 'mousemove', onMove );
      document.addEventListener( 'mouseup', onUp );
    } );
  }


  function bindPopup( panel, handle, onClose ) {
    if ( ! panel || ! handle ) return;
    makeDraggable( panel, handle, {
      ignore: '.btn-close, .popup-close, button, a, input, select, textarea, label'
    } );
    if ( typeof onClose !== 'function' ) return;
    let btn = handle.querySelector( '.popup-close, .btn-close' );
    if ( ! btn ) {
      handle.insertAdjacentHTML( 'beforeend',
        '<button type="button" class="btn-close" aria-label="Close">' + CLOSE_X + '</button>' );
      btn = handle.querySelector( ':scope > .btn-close' );
    }
    if ( ! btn || btn.dataset.popupBound === '1' ) return;
    btn.dataset.popupBound = '1';
    btn.addEventListener( 'click', function( e ) {
      e.preventDefault();
      e.stopPropagation();
      onClose();
    } );
  }


  function dialogKind( type, needsAuth ) {
    if ( type === 'danger' ) return 'danger';
    if ( needsAuth ) return 'auth';
    if ( type === 'error' || type === 'warning' || type === 'success' || type === 'info' ) return type;
    return 'confirm';
  }


  function dialogOkClass( kind ) {
    if ( kind === 'danger' || kind === 'error' ) return 'btn-danger';
    if ( kind === 'auth' ) return 'btn-auth';
    return 'btn-primary';
  }


  function isOpts( x ) {
    return x && typeof x === 'object' && ! Array.isArray( x )
      && ( 'title' in x || 'okText' in x || 'fields' in x || 'danger' in x
        || 'theme' in x || 'okOnly' in x || 'cancelText' in x || 'warn' in x );
  }


  function optsKind( opts ) {
    if ( ! opts.type && ! opts.theme && ! opts.danger ) return '';
    let type = opts.type || opts.theme || ( opts.danger ? 'danger' : 'confirm' );
    if ( type === 'error' && ! opts.okOnly ) type = 'danger';
    return dialogKind( type, !! opts.auth );
  }


  function dialogHeader( title, who ) {
    const header = document.createElement( 'div' );
    header.className = 'popup-chrome';
    const heading = document.createElement( 'h3' );
    heading.textContent = title;
    header.appendChild( heading );
    if ( who ) {
      const sub = document.createElement( 'p' );
      sub.className = 'ui-dialog-who';
      sub.textContent = who;
      header.appendChild( sub );
    }
    return header;
  }


  function fillDialogMessage( content, message ) {
    if ( typeof message === 'string' || message == null ) {
      content.textContent = message || '';
      return;
    }
    content.classList.add( 'ui-dialog-message--rich' );
    if ( message.lead ) {
      const p = document.createElement( 'p' );
      p.textContent = message.lead;
      content.appendChild( p );
    }
    if ( message.facts && message.facts.length ) {
      const dl = document.createElement( 'dl' );
      dl.className = 'ui-dialog-facts';
      message.facts.forEach( function( pair ) {
        const dt = document.createElement( 'dt' );
        dt.textContent = pair[ 0 ];
        const dd = document.createElement( 'dd' );
        dd.textContent = pair[ 1 ];
        dl.appendChild( dt );
        dl.appendChild( dd );
      } );
      content.appendChild( dl );
    }
    if ( message.note ) {
      const note = document.createElement( 'p' );
      note.className = 'ui-dialog-note';
      note.textContent = message.note;
      content.appendChild( note );
    }
  }


  function mountPanel( id, kind, title, who ) {
    const existing = document.getElementById( id );
    if ( existing ) existing.remove();
    const overlay = document.createElement( 'div' );
    overlay.id = id;
    overlay.className = 'ui-dialog-overlay';
    overlay.style.zIndex = String( MODAL_Z );
    const box = document.createElement( 'div' );
    box.className = 'ui-dialog-panel' + ( kind ? ' ui-dialog-panel--' + kind : '' );
    const header = dialogHeader( title, who );
    box.appendChild( header );
    overlay.appendChild( box );
    document.body.appendChild( overlay );
    return { overlay: overlay, box: box, header: header };
  }


  function confirmDialog( message, title, type, needsAuth, extra ) {
    extra = extra || {};
    return new Promise( function( resolve ) {
      const kind = dialogKind( type, needsAuth );
      const who = ( message && typeof message === 'object' ) ? ( message.name || '' ) : '';
      const ui = mountPanel( 'f1-confirm-modal', kind, title, who );
      if ( extra.outline ) ui.box.classList.add( 'ui-dialog-panel--outline' );

      const content = document.createElement( 'div' );
      content.className = 'ui-dialog-message';
      fillDialogMessage( content, message );
      ui.box.appendChild( content );

      let authInput = null;
      let authError = null;
      if ( needsAuth ) {
        const field = document.createElement( 'div' );
        field.className = 'form-field ui-dialog-field';
        const label = document.createElement( 'label' );
        label.textContent = 'Authorization Code';
        authInput = document.createElement( 'input' );
        authInput.type = 'text';
        authInput.autocomplete = 'off';
        authInput.placeholder = 'Enter auth code';
        authError = document.createElement( 'p' );
        authError.className = 'ui-dialog-error';
        field.appendChild( label );
        field.appendChild( authInput );
        field.appendChild( authError );
        ui.box.appendChild( field );
      }

      const footer = document.createElement( 'div' );
      footer.className = 'ui-dialog-actions';
      const close = function( value ) { ui.overlay.remove(); resolve( value ); };
      const cancelled = needsAuth ? null : false;

      const cancelBtn = document.createElement( 'button' );
      cancelBtn.type = 'button';
      cancelBtn.className = 'btn-secondary';
      cancelBtn.textContent = extra.cancelText || 'Cancel';
      cancelBtn.onclick = function() { close( cancelled ); };

      const okBtn = document.createElement( 'button' );
      okBtn.type = 'button';
      okBtn.className = dialogOkClass( kind );
      okBtn.textContent = extra.okText
        || ( needsAuth ? 'Authorize' : ( kind === 'confirm' ? 'Confirm' : 'OK' ) );
      okBtn.onclick = function() {
        if ( ! needsAuth ) return close( true );
        const code = authInput.value.trim();
        if ( ! code ) {
          authError.textContent = 'An authorization code is required.';
          authInput.focus();
          return;
        }
        close( code );
      };

      footer.appendChild( cancelBtn );
      footer.appendChild( okBtn );
      ui.box.appendChild( footer );
      bindPopup( ui.box, ui.header, function() { close( cancelled ); } );
      if ( authInput ) {
        authInput.addEventListener( 'keydown', function( e ) {
          if ( e.key === 'Enter' ) okBtn.click();
        } );
        authInput.focus();
      }
    } );
  }


  function confirm( message, title, type ) {
    if ( isOpts( message ) ) {
      const o = message;
      return confirmDialog(
        o.message,
        o.title || 'Confirm',
        o.type || o.theme || ( o.danger ? 'danger' : 'confirm' ),
        !! o.auth,
        { okText: o.okText, cancelText: o.cancelText, outline: o.chrome === 'outline' }
      );
    }
    return confirmDialog( message, title || 'Confirm', type || 'confirm', false );
  }


  function auth( message, title, type ) {
    if ( isOpts( message ) ) {
      const o = message;
      return confirmDialog( o.message, o.title || 'Authorize Action',
        o.type || o.theme || 'confirm', true, { okText: o.okText } );
    }
    return confirmDialog( message, title || 'Authorize Action', type || 'confirm', true );
  }


  function validate( message, title, type ) {
    if ( isOpts( message ) ) {
      title = message.title || title;
      type = message.type || message.theme || type;
      message = message.message;
    }
    return new Promise( function( resolve ) {
      const kind = dialogKind( type || 'error', false );
      const ui = mountPanel( 'f1-validation-modal', kind, title || 'Validation Error' );
      const content = document.createElement( 'div' );
      content.className = 'ui-dialog-message';
      let errors = [];
      if ( Array.isArray( message ) ) errors = message;
      else if ( typeof message === 'string' ) {
        if ( message.includes( '\n- ' ) ) {
          errors = message.split( '\n- ' ).map( function( l ) {
            return l.replace( 'Validation Error:', '' ).trim();
          } ).filter( Boolean );
        } else errors = [ message ];
      }
      errors = errors.map( function( e ) { return e.replace( /^-\s*/, '' ).trim(); } );
      if ( errors.length === 1 ) {
        const p = document.createElement( 'p' );
        p.textContent = errors[ 0 ];
        p.style.margin = '0';
        content.appendChild( p );
      } else {
        const ul = document.createElement( 'ul' );
        ul.style.margin = '0';
        ul.style.paddingLeft = '1.2em';
        errors.forEach( function( err ) {
          const li = document.createElement( 'li' );
          li.textContent = err;
          ul.appendChild( li );
        } );
        content.appendChild( ul );
      }
      ui.box.appendChild( content );
      const footer = document.createElement( 'div' );
      footer.className = 'ui-dialog-actions';
      const btn = document.createElement( 'button' );
      btn.type = 'button';
      btn.className = dialogOkClass( kind );
      btn.textContent = 'OK';
      btn.onclick = function() { ui.overlay.remove(); resolve( true ); };
      footer.appendChild( btn );
      ui.box.appendChild( footer );
      bindPopup( ui.box, ui.header, function() { ui.overlay.remove(); resolve( true ); } );
    } );
  }


  function esc( s ) {
    const Utils = F1.lib && F1.lib.Utils;
    if ( Utils && Utils.escapeHtml ) return Utils.escapeHtml( String( s == null ? '' : s ) );
    return String( s == null ? '' : s )
      .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
  }


  function form( opts ) {
    opts = opts || {};
    return new Promise( function( resolve ) {
      const kind = optsKind( opts );
      const ui = mountPanel( 'f1-form-modal', kind || 'confirm', opts.title || 'Confirm' );
      if ( opts.chrome === 'outline' ) ui.box.classList.add( 'ui-dialog-panel--outline' );
      const fields = opts.fields || [];
      const formEl = document.createElement( 'form' );
      formEl.className = 'fu-create-form';
      if ( opts.message ) {
        const p = document.createElement( 'p' );
        p.className = 'fu-dialog-msg' + ( opts.warn ? ' fu-dialog-warn' : '' );
        p.textContent = opts.message;
        formEl.appendChild( p );
      }
      fields.forEach( function( f ) {
        const wrap = document.createElement( 'label' );
        wrap.className = 'fu-create-field';
        wrap.innerHTML = '<span class="fu-create-label">' + esc( f.label )
          + ( f.hint ? ' <span class="fu-create-hint">' + esc( f.hint ) + '</span>' : '' )
          + '</span>';
        const el = document.createElement( f.type === 'textarea' ? 'textarea' : 'input' );
        el.name = f.name;
        if ( f.type === 'textarea' ) el.rows = f.rows || 4;
        else { el.type = 'text'; el.autocomplete = 'off'; }
        if ( f.maxlength ) el.maxLength = f.maxlength;
        if ( f.placeholder ) el.placeholder = f.placeholder;
        el.value = f.value == null ? '' : f.value;
        el.addEventListener( 'input', function() { el.classList.remove( 'is-invalid' ); } );
        wrap.appendChild( el );
        formEl.appendChild( wrap );
      } );
      ui.box.appendChild( formEl );

      let settled = false;
      const finish = function( val ) {
        if ( settled ) return;
        settled = true;
        ui.overlay.remove();
        resolve( val );
      };

      const submit = function() {
        const values = {};
        for ( let i = 0; i < fields.length; i++ ) {
          const f = fields[ i ];
          const el = formEl.elements[ f.name ];
          values[ f.name ] = f.type === 'textarea' ? el.value : el.value.trim();
          if ( f.required && ! String( values[ f.name ] ).trim() ) {
            el.focus();
            el.classList.add( 'is-invalid' );
            return;
          }
        }
        finish( fields.length ? values : true );
      };

      const footer = document.createElement( 'div' );
      footer.className = 'ui-dialog-actions';
      if ( opts.altButton ) {
        const alt = document.createElement( 'button' );
        alt.type = 'button';
        alt.className = ( opts.altButton.danger ? 'btn-danger' : 'btn-secondary' ) + ' btn-alt';
        alt.textContent = opts.altButton.text;
        alt.onclick = function() { finish( opts.altButton.value ); };
        footer.appendChild( alt );
      }
      if ( ! opts.okOnly ) {
        const cancel = document.createElement( 'button' );
        cancel.type = 'button';
        cancel.className = 'btn-secondary';
        cancel.textContent = opts.cancelText || 'Cancel';
        cancel.onclick = function() { finish( null ); };
        footer.appendChild( cancel );
      }
      const ok = document.createElement( 'button' );
      ok.type = 'button';
      ok.className = opts.danger ? 'btn-danger' : 'btn-primary';
      ok.textContent = opts.okText || 'OK';
      ok.onclick = submit;
      footer.appendChild( ok );
      ui.box.appendChild( footer );
      formEl.addEventListener( 'submit', function( e ) { e.preventDefault(); submit(); } );
      bindPopup( ui.box, ui.header, function() { finish( null ); } );
      requestAnimationFrame( function() {
        const field = formEl.querySelector( 'input, textarea' );
        ( field || ok ).focus();
      } );
    } );
  }


  function prompt( opts ) {
    opts = opts || {};
    const field = {
      name: 'value',
      label: opts.label || 'Value',
      hint: opts.hint,
      type: opts.textarea ? 'textarea' : 'text',
      value: opts.value,
      placeholder: opts.placeholder,
      required: opts.required !== false,
      maxlength: opts.maxlength,
      rows: opts.rows
    };
    return form( Object.assign( {}, opts, { fields: [ field ] } ) )
      .then( function( v ) { return v ? v.value.trim() : null; } );
  }


  function alertDlg( opts ) {
    if ( typeof opts === 'string' ) opts = { message: opts };
    opts = opts || {};
    if ( opts.fields || opts.okText || opts.warn ) {
      return form( Object.assign( { okOnly: true, okText: 'OK' }, opts ) ).then( function( v ) {
        return !! v;
      } );
    }
    return validate( opts.message, opts.title, opts.type || opts.theme || 'error' );
  }


  F1.lib = F1.lib || {};
  F1.lib.Dialog = {
    confirm: confirm,
    auth: auth,
    validate: validate,
    form: form,
    prompt: prompt,
    alert: alertDlg,
    bindPopup: bindPopup,
    makeDraggable: makeDraggable
  };

} )( window.F1 = window.F1 || {} );
