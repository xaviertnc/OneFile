/* global F1 */

/* data-table.js */

(function(F1) {

  /**
   * F1 DataTable - 09 Jan 2026
   *
   * Paginated data table with search, sort, and optional state persistence.
   * Supports client-side or AJAX (server-side) modes.
   *
   * @author C. Moller <xavier.tnc@gmail.com>
   *
   * @version 2.2 - FT - 11 Jan 2026
   *   - Add footerTotals for AJAX mode, improve footer styling
   *
   * @version 2.1 - FIX - 11 Jan 2026
   *   - Fix addControlRight/Center logic, fix empty defaultState sort
   */

  function log(...args) { if (F1.DEBUG > 1) console.log(...args); }

  class DataTable {

    constructor( opts ) {
      this.container = typeof opts.container === 'string'
        ? document.querySelector( opts.container ) : opts.container;
      if ( !this.container ) throw new Error( 'DataTable: container not found' );

      this.columns = opts.columns || [];
      this.pageSize = opts.pageSize || 25;
      this.keyField = opts.keyField || 'id';
      this.onRowClick = opts.onRowClick || null;
      this.currencyColumns = opts.currencyColumns || [];
      this.footerTotals = opts.footerTotals || {}; // { field: 'R' } for AJAX mode
      this.serverTotals = {}; // Totals from server response
      this.pageSizes = opts.pageSizes || [ 10, 25, 50, 100, 250, 500 ];

      // AJAX mode
      this.ajaxUrl = opts.ajaxUrl || null;
      this.ajaxParams = opts.ajaxParams || ( () => ({}) );
      this.isAjax = !!this.ajaxUrl;

      // Data & state
      this.allData = [];
      this.filteredData = [];
      this.filteredIndices = [];
      this.sortCol = null;
      // Use explicit sortCol, else defaultState, else 'date' for AJAX
      const defSort = opts.defaultState?.sortColField;
      this.sortColField = opts.sortCol ?? ( defSort !== undefined ? defSort : ( this.isAjax ? 'date' : null ) );
      this.sortDir = opts.sortDir || opts.defaultState?.sortDir || 'desc';
      this.searchTerm = '';
      this.currentPage = 1;
      this.totalPages = 1;
      this.recordsTotal = 0;
      this.recordsFiltered = 0;
      this.currencyCache = {};
      this.isLoading = false;
      this.searchDebounceTimer = null;

      // Find initial sort column index
      if ( this.sortColField ) {
        this.sortCol = this.columns.findIndex( c => c.field === this.sortColField );
        if ( this.sortCol === -1 ) this.sortCol = null;
      }

      // State management
      this.stateKey = opts.stateKey || null;
      this.defaultState = opts.defaultState || null;
      this.customFilters = opts.customFilters || {};
      this.resetButton = null;

      this._init();
      if ( this.stateKey ) this._initState();
    } // constructor


    _init() {
      const c = this.container;
      c.innerHTML = '';
      c.classList.add( 'dt-wrap' );

      // Controls
      c.innerHTML = `<div class="dt-controls">
        <div class="dt-left"><label>Show <select class="dt-pagesize"></select> entries</label></div>
        <div class="dt-right"><label>Search: <input type="search" class="dt-search"></label></div>
      </div>`;
      this.controlsLeft = c.querySelector( '.dt-left' );
      this.controlsRight = c.querySelector( '.dt-right' );

      // Page size
      this.pageSizeSelect = c.querySelector( '.dt-pagesize' );
      this.pageSizes.forEach( s => {
        const o = document.createElement( 'option' );
        o.value = s; o.textContent = s;
        this.pageSizeSelect.appendChild( o );
      } );
      this.pageSizeSelect.value = this.pageSize;
      this.pageSizeSelect.onchange = () => this._onPageSizeChange();

      // Search
      this.searchInput = c.querySelector( '.dt-search' );
      this.searchInput.oninput = () => this._onSearch();

      // Table
      const scroll = document.createElement( 'div' );
      scroll.className = 'dt-scroll';
      const tbl = document.createElement( 'table' );
      tbl.className = 'dt-table';
      this.headerEl = document.createElement( 'thead' );
      this.tbody = document.createElement( 'tbody' );
      this.footerEl = document.createElement( 'tfoot' );
      tbl.append( this.headerEl, this.tbody, this.footerEl );
      scroll.appendChild( tbl );
      this.scrollContainer = scroll;

      // Loading
      this.loadingEl = document.createElement( 'div' );
      this.loadingEl.className = 'dt-loading hidden';
      this.loadingEl.innerHTML = '<div class="dt-spinner"></div>';
      scroll.appendChild( this.loadingEl );
      c.appendChild( scroll );

      // Bottom bar
      const bottom = document.createElement( 'div' );
      bottom.className = 'dt-bottom';
      this.infoEl = document.createElement( 'div' );
      this.infoEl.className = 'dt-info';
      this.paginationEl = document.createElement( 'div' );
      this.paginationEl.className = 'dt-pagination';
      bottom.append( this.infoEl, this.paginationEl );
      c.appendChild( bottom );

      this._renderHeader();
      this.tbody.onclick = e => this._onRowClick( e );
    } // _init


    _renderHeader() {
      let html = '<tr>';
      this.columns.forEach( ( col, i ) => {
        const sortable = col.sortable !== false;
        const cls = [ sortable ? 'sortable' : '', col.className || '' ].filter( Boolean ).join( ' ' );
        const arrows = sortable ? '<span class="sort-arrows"><span class="up">▲</span><span class="dn">▼</span></span>' : '';
        html += `<th class="${cls}" data-col="${i}">${col.title || ''}${arrows}</th>`;
      } );
      this.headerEl.innerHTML = html + '</tr>';
      this.headerEl.querySelectorAll( 'th.sortable' ).forEach( th => {
        th.onclick = () => this._onSort( +th.dataset.col );
      } );
      this._updateSortIndicators();
    } // _renderHeader


    // Client-side data
    setData( data ) {
      this.allData = data || [];
      this._buildCurrencyCache();
      this._applyFilter();
      this._applySort();
      this._render();
    } // setData


    _buildCurrencyCache() {
      this.currencyCache = {};
      this.currencyColumns.forEach( cc => {
        const field = this.columns[ cc.index ]?.field;
        if ( !field ) return;
        this.currencyCache[ cc.index ] = this.allData.map( r => {
          const n = parseFloat( String( r[ field ] ).replace( /[^0-9.\-]/g, '' ) );
          return isNaN( n ) ? 0 : n;
        } );
      } );
    } // _buildCurrencyCache


    _applyFilter() {
      const term = this.searchTerm.toLowerCase().trim();
      if ( !term ) {
        this.filteredData = [ ...this.allData ];
        this.filteredIndices = this.allData.map( ( _, i ) => i );
      } else {
        this.filteredData = [];
        this.filteredIndices = [];
        this.allData.forEach( ( row, idx ) => {
          if ( this.columns.some( c => String( c.field ? row[ c.field ] : '' ).toLowerCase().includes( term ) ) ) {
            this.filteredData.push( row );
            this.filteredIndices.push( idx );
          }
        } );
      }
      this.currentPage = 1;
      this._updatePagination();
    } // _applyFilter


    _applySort() {
      if ( this.sortCol === null ) return;
      const col = this.columns[ this.sortCol ];
      if ( !col?.field ) return;

      const field = col.field, dir = this.sortDir === 'asc' ? 1 : -1;
      const isCurr = col.type === 'currency', isDate = col.type === 'date';
      const indices = this.filteredData.map( ( _, i ) => i );

      indices.sort( ( ai, bi ) => {
        let a = this.filteredData[ ai ][ field ], b = this.filteredData[ bi ][ field ];
        if ( a == null ) return 1;
        if ( b == null ) return -1;
        if ( isCurr ) {
          a = parseFloat( String( a ).replace( /[^0-9.\-]/g, '' ) ) || 0;
          b = parseFloat( String( b ).replace( /[^0-9.\-]/g, '' ) ) || 0;
          return ( a - b ) * dir;
        }
        if ( isDate ) return ( ( new Date( a ).getTime() || 0 ) - ( new Date( b ).getTime() || 0 ) ) * dir;
        const na = parseFloat( a ), nb = parseFloat( b );
        if ( !isNaN( na ) && !isNaN( nb ) ) return ( na - nb ) * dir;
        a = String( a ).toLowerCase(); b = String( b ).toLowerCase();
        return a < b ? -dir : a > b ? dir : 0;
      } );

      this.filteredData = indices.map( i => this.filteredData[ i ] );
      this.filteredIndices = indices.map( i => this.filteredIndices[ i ] );
      this._updateSortIndicators();
    } // _applySort


    _updateSortIndicators() {
      this.headerEl.querySelectorAll( 'th' ).forEach( th => th.classList.remove( 'sort-asc', 'sort-desc' ) );
      if ( this.sortCol !== null ) {
        const th = this.headerEl.querySelector( `th[data-col="${this.sortCol}"]` );
        if ( th ) th.classList.add( this.sortDir === 'asc' ? 'sort-asc' : 'sort-desc' );
      }
    } // _updateSortIndicators


    _onSort( i ) {
      this.sortDir = this.sortCol === i ? ( this.sortDir === 'asc' ? 'desc' : 'asc' ) : 'asc';
      this.sortCol = i;
      this.sortColField = this.columns[ i ]?.field || null;
      if ( this.isAjax ) { this.currentPage = 1; this._fetchData(); }
      else { this._applySort(); this._render(); }
    } // _onSort


    _onSearch() {
      this.searchTerm = this.searchInput.value;
      if ( this.isAjax ) {
        clearTimeout( this.searchDebounceTimer );
        this.searchDebounceTimer = setTimeout( () => { this.currentPage = 1; this._fetchData(); }, 300 );
      } else {
        this._applyFilter(); this._applySort(); this._render();
      }
    } // _onSearch


    _onPageSizeChange() {
      this.pageSize = +this.pageSizeSelect.value;
      this.currentPage = 1;
      if ( this.isAjax ) this._fetchData();
      else { this._updatePagination(); this._render(); }
    } // _onPageSizeChange


    _updatePagination() {
      const count = this.isAjax ? this.recordsFiltered : this.filteredData.length;
      this.totalPages = Math.ceil( count / this.pageSize ) || 1;
      if ( this.currentPage > this.totalPages ) this.currentPage = this.totalPages;
    } // _updatePagination


    _goToPage( p ) {
      if ( p < 1 || p > this.totalPages ) return;
      this.currentPage = p;
      if ( this.isAjax ) this._fetchData(); else this._render();
      this.scrollContainer.scrollTop = 0;
    } // _goToPage


    async _fetchData() {
      if ( this.isLoading ) return;
      this.isLoading = true;
      this.loadingEl.classList.remove( 'hidden' );

      const data = {
        action: 'paginate',
        limit: this.pageSize,
        offset: ( this.currentPage - 1 ) * this.pageSize,
        search: this.searchTerm,
        sortCol: this.sortColField || 'date',
        sortDir: this.sortDir.toUpperCase(),
        ...this.ajaxParams()
      };

      try {
        const Ajax = F1.lib?.Ajax;
        let result;
        if ( Ajax ) result = await Ajax.post( this.ajaxUrl, data );
        else {
          const resp = await fetch( this.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams( data ).toString()
          } );
          if ( !resp.ok ) throw new Error( 'Network error' );
          result = await resp.json();
        }
        this.filteredData = result.data || [];
        this.recordsTotal = result.recordsTotal || 0;
        this.recordsFiltered = result.recordsFiltered || 0;
        this.serverTotals = result.totals || {};
        this._updatePagination();
        this._updateSortIndicators();
        this._renderRows();
        this._renderFooter();
        this._renderInfo();
        this._renderPagination();
      } catch ( e ) {
        console.error( 'DataTable AJAX error:', e );
        this.tbody.innerHTML = `<tr><td colspan="${this.columns.length}" class="center">Error loading data</td></tr>`;
      } finally {
        this.isLoading = false;
        this.loadingEl.classList.add( 'hidden' );
      }
    } // _fetchData


    load() { if ( this.isAjax ) this._fetchData(); }


    _render() {
      this._updatePagination();
      this._renderRows();
      this._renderFooter();
      this._renderInfo();
      this._renderPagination();
    } // _render


    _renderRows() {
      const start = this.isAjax ? 0 : ( this.currentPage - 1 ) * this.pageSize;
      const end = this.isAjax ? this.filteredData.length : Math.min( start + this.pageSize, this.filteredData.length );
      let html = '';
      for ( let i = start; i < end; i++ ) {
        const row = this.filteredData[ i ], key = row[ this.keyField ] || i;
        html += `<tr data-id="${key}" data-idx="${i}">`;
        this.columns.forEach( c => {
          const v = c.field ? row[ c.field ] : '';
          html += `<td class="${c.className || ''}">${c.render ? c.render( v, row ) : this._esc( v )}</td>`;
        } );
        html += '</tr>';
      }
      this.tbody.innerHTML = html;
    } // _renderRows


    _renderPagination() {
      const t = this.totalPages, c = this.currentPage;
      let html = `<button class="dt-btn${c <= 1 ? ' disabled' : ''}" data-p="prev">Previous</button>`;
      const max = 5;
      let s = Math.max( 1, c - Math.floor( max / 2 ) );
      let e = Math.min( t, s + max - 1 );
      if ( e - s < max - 1 ) s = Math.max( 1, e - max + 1 );

      if ( s > 1 ) { html += '<button class="dt-btn" data-p="1">1</button>'; if ( s > 2 ) html += '<span class="dt-dots">...</span>'; }
      for ( let p = s; p <= e; p++ ) html += `<button class="dt-btn${p === c ? ' active' : ''}" data-p="${p}">${p}</button>`;
      if ( e < t ) { if ( e < t - 1 ) html += '<span class="dt-dots">...</span>'; html += `<button class="dt-btn" data-p="${t}">${t}</button>`; }
      html += `<button class="dt-btn${c >= t ? ' disabled' : ''}" data-p="next">Next</button>`;

      this.paginationEl.innerHTML = html;
      this.paginationEl.querySelectorAll( '.dt-btn:not(.disabled)' ).forEach( b => {
        b.onclick = () => {
          const p = b.dataset.p;
          this._goToPage( p === 'prev' ? c - 1 : p === 'next' ? c + 1 : +p );
        };
      } );
    } // _renderPagination


    _renderFooter() {
      const hasFooterTotals = Object.keys( this.footerTotals ).length > 0;
      const hasCurrencyCols = this.currencyColumns.length > 0;
      if ( !hasFooterTotals && !hasCurrencyCols ) { this.footerEl.innerHTML = ''; return; }

      const fmt = n => n.toLocaleString( 'en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
      let html = '<tr>';

      this.columns.forEach( ( col, i ) => {
        // AJAX mode: use serverTotals with footerTotals config
        if ( this.isAjax && hasFooterTotals && col.field && this.footerTotals[ col.field ] !== undefined ) {
          const symbol = this.footerTotals[ col.field ];
          const total = this.serverTotals[ col.field ] || 0;
          html += `<th class="right nowrap">${symbol}${fmt( total )}</th>`;
        }
        // Client-side: use currencyColumns with currencyCache
        else if ( !this.isAjax && hasCurrencyCols ) {
          const cc = this.currencyColumns.find( x => x.index === i );
          if ( cc ) {
            const cache = this.currencyCache[ i ] || [];
            let sum = 0; this.filteredIndices.forEach( idx => sum += cache[ idx ] || 0 );
            html += `<th class="right nowrap">${cc.symbol || ''}${fmt( sum )}</th>`;
          } else html += '<th></th>';
        }
        else html += '<th></th>';
      } );

      this.footerEl.innerHTML = html + '</tr>';
    } // _renderFooter


    _renderInfo() {
      const total = this.isAjax ? this.recordsTotal : this.allData.length;
      const filtered = this.isAjax ? this.recordsFiltered : this.filteredData.length;
      const start = filtered === 0 ? 0 : ( this.currentPage - 1 ) * this.pageSize + 1;
      const end = Math.min( this.currentPage * this.pageSize, filtered );
      let txt = filtered === 0 ? 'No entries found'
        : `Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${filtered.toLocaleString()} entries`;
      if ( filtered !== total && total > 0 ) txt += ` (filtered from ${total.toLocaleString()} total)`;
      this.infoEl.textContent = txt;
    } // _renderInfo


    _onRowClick( e ) {
      if ( !this.onRowClick ) return;
      const row = e.target.closest( 'tr' );
      if ( row ) this.onRowClick( row.dataset.id, this.filteredData[ +row.dataset.idx ], e );
    } // _onRowClick


    _esc( s ) {
      return s == null ? '' : String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
    } // _esc


    // Public API
    getFilteredData() { return this.filteredData; }
    getAllData() { return this.allData; }
    getRowCount() { return this.filteredData.length; }
    addControlLeft( el ) { this.controlsLeft.appendChild( el ); }
    addControlRight( el ) { this.controlsRight.appendChild( el ); }


    // State Management
    _initState() {
      log( 'DataTable._initState()' );
      const url = new URL( location.href ), saved = this._loadState(), state = { ...saved };

      Object.keys( this.customFilters ).forEach( k => {
        const v = url.searchParams.get( k );
        if ( v !== null ) state[ k ] = v;
      } );

      if ( state.pageSize ) { this.pageSize = state.pageSize; this.pageSizeSelect.value = state.pageSize; }
      if ( state.currentPage ) this.currentPage = state.currentPage;
      if ( state.sortColField ) {
        const idx = this.columns.findIndex( c => c.field === state.sortColField );
        if ( idx !== -1 ) { this.sortCol = idx; this.sortColField = state.sortColField; this.sortDir = state.sortDir || 'desc'; }
      }
      if ( state.searchTerm ) { this.searchTerm = state.searchTerm; this.searchInput.value = state.searchTerm; }
      Object.keys( this.customFilters ).forEach( k => {
        const f = this.customFilters[ k ];
        if ( f.element && state[ k ] !== undefined ) f.element.value = state[ k ];
      } );

      setTimeout( () => this._updateSortIndicators(), 50 );

      // Hook methods for state saving
      const self = this, origFetch = this._fetchData;
      this._fetchData = async function() {
        await origFetch.call( this );
        this._updateSortIndicators();
        setTimeout( () => { self._saveState(); self._updateResetBtn(); }, 100 );
      };

      [ '_onPageSizeChange', '_onSort', '_onSearch', '_goToPage' ].forEach( m => {
        const orig = this[ m ];
        this[ m ] = function( ...a ) {
          orig.apply( this, a );
          if ( !this.isAjax ) setTimeout( () => { self._saveState(); self._updateResetBtn(); }, m === '_onSearch' ? 500 : 100 );
        };
      } );

      // Custom filter hooks
      Object.keys( this.customFilters ).forEach( k => {
        const f = this.customFilters[ k ];
        if ( f.element ) f.element.onchange = () => {
          if ( f.urlParam !== false ) { const u = new URL( location.href ); u.searchParams.set( k, f.element.value ); history.replaceState( null, '', u ); }
          this.currentPage = 1;
          if ( this.isAjax ) this.load();
          else { this._render(); setTimeout( () => { self._saveState(); self._updateResetBtn(); }, 100 ); }
        };
      } );

      // Reset button
      const Utils = F1.lib?.Utils;
      if ( Utils ) {
        this.resetButton = Utils.newEl( 'button', 'btn btn-sm btn-outline btn-clear-state hidden' );
        this.resetButton.dataset.tippyContent = 'Reset table filters/sort';
        this.resetButton.innerHTML = '<span class="fa fa-eraser"></span>';
        this.resetButton.onclick = () => this._resetState();
        this.addControlRight( this.resetButton );
        setTimeout( () => this._updateResetBtn(), 200 );
      }
    } // _initState


    _loadState() {
      try { const s = localStorage.getItem( this.stateKey ); return s ? JSON.parse( s ) : { ...this.defaultState }; }
      catch { return { ...this.defaultState }; }
    } // _loadState


    _saveState() {
      try {
        const s = { pageSize: this.pageSize, currentPage: this.currentPage,
          sortColField: this.sortColField, sortDir: this.sortDir, searchTerm: this.searchTerm || '' };
        Object.keys( this.customFilters ).forEach( k => {
          const f = this.customFilters[ k ]; s[ k ] = f.element ? f.element.value : f.default || '';
        } );
        localStorage.setItem( this.stateKey, JSON.stringify( s ) );
      } catch ( e ) { console.error( 'State save error:', e ); }
    } // _saveState


    _resetState() {
      localStorage.removeItem( this.stateKey );
      this.pageSize = this.defaultState.pageSize;
      this.currentPage = 1;
      this.sortColField = this.defaultState.sortColField;
      this.sortDir = this.defaultState.sortDir;
      this.searchTerm = '';
      this.pageSizeSelect.value = this.defaultState.pageSize;
      this.searchInput.value = '';
      const url = new URL( location.href );
      Object.keys( this.customFilters ).forEach( k => {
        const f = this.customFilters[ k ];
        if ( f.element ) f.element.value = f.default || '';
        if ( f.urlParam !== false ) url.searchParams.set( k, f.default || '' );
      } );
      history.replaceState( null, '', url );
      const idx = this.columns.findIndex( c => c.field === this.defaultState.sortColField );
      if ( idx !== -1 ) { this.sortCol = idx; this.sortColField = this.defaultState.sortColField; }
      else this.sortCol = null;
      this.sortDir = this.defaultState.sortDir || 'desc';
      this._updateSortIndicators();
      if ( this.isAjax ) { this.currentPage = 1; this.load(); }
      else this._render();
      this._updateResetBtn();
    } // _resetState


    _updateResetBtn() {
      if ( !this.resetButton ) return;
      const d = this.defaultState;
      const nonDefault = this.pageSize !== d.pageSize || this.currentPage !== d.currentPage ||
        ( this.sortColField || d.sortColField ) !== d.sortColField || this.sortDir !== d.sortDir ||
        this.searchTerm !== d.searchTerm ||
        Object.keys( this.customFilters ).some( k => {
          const f = this.customFilters[ k ];
          return ( f.element ? f.element.value : f.default || '' ) !== ( f.default || '' );
        } );
      this.resetButton.classList.toggle( 'hidden', !nonDefault );
    } // _updateResetBtn

  } // DataTable


  // Styles
  if ( !document.getElementById( 'dt-styles' ) ) {
    const s = document.createElement( 'style' );
    s.id = 'dt-styles';
    s.textContent = `
.dt-wrap{display:flex;flex-direction:column;height:100%;box-sizing:border-box}
.dt-wrap *,.dt-wrap *:before,.dt-wrap *:after{box-sizing:inherit}
.dt-controls{display:flex;justify-content:space-between;align-items:center;font-size:13px}
.dt-left,.dt-right{display:flex;gap:8px;align-items:center;margin:5px;white-space:nowrap}
.dt-left{gap:16px}
.dt-search,.dt-pagesize{padding:5px;border:1px solid #aaa;border-radius:3px;background:transparent}
.dt-scroll{overflow:auto;position:relative}
.dt-table{width:100%;border-collapse:collapse;}
.dt-table th,.dt-table td{padding:6px 2px 6px 12px;text-align:left;white-space:nowrap}
.dt-table th{font-size:13px}.dt-table td{font-size:12px;border-bottom:1px solid #ddd}
.dt-table thead{background:var(--heading-color,#2c3e50);color:#fff;position:sticky;top:0;z-index:2}
.dt-table tfoot tr{background:#f5f5f5;position:sticky;bottom:0;z-index:1;font-size:.9em;border-bottom:1px solid grey}
.dt-table tfoot th{padding:8px 2px 8px 12px;font-weight:600}
.dt-table th.sortable{cursor:pointer;user-select:none}
.dt-table th.sortable:hover{background:rgba(255,255,255,.1)}
.dt-table tbody tr:hover{background:#eee;cursor:pointer}
.dt-table .fa{width:1em;color:var(--primary-color);opacity:.67;vertical-align:middle;line-height:1}
.dt-bottom{display:flex;justify-content:space-between;align-items:flex-start;}
.dt-info{font-size:13px;color:#444;padding:10px 0 0;margin:5.5px}
.dt-pagination{display:flex;align-items:center;padding:3px 0 0;margin:5.5px}
.dt-btn{padding:6.5px 13px;border:1px solid transparent;background:transparent;cursor:pointer;margin-left:2px;color:#666;font-size:13px;border-radius:3px}
.dt-btn:first-child{margin-left:0}
.dt-btn:hover:not(.disabled):not(.active){background:rgba(0,0,0,.05)}
.dt-btn.active{border:1px solid rgba(0,0,0,.3);color:#444;background:linear-gradient(to bottom,#fff,#dcdcdc)}
.dt-btn.disabled{color:#999;cursor:not-allowed}
.dt-dots{padding:6.5px 8px;color:#666;font-size:13px}
.sort-arrows{display:inline-flex;flex-direction:column;vertical-align:middle;margin-left:8px;line-height:.7;font-size:9px}
.sort-arrows .up,.sort-arrows .dn{opacity:.2;transition:opacity .2s}
.dt-table th.sortable:hover .up,.dt-table th.sortable:hover .dn{opacity:.5}
.dt-table th.sort-asc .up,.dt-table th.sort-desc .dn{opacity:1;color:#fff}
.dt-table .left{text-align:left}.dt-table .right{text-align:right}.dt-table .center{text-align:center}
.dt-table .nowrap{white-space:nowrap}.dt-table .name{min-width:180px}.dt-table .mute{color:#888;font-size:.85em}
.dt-loading{position:absolute;inset:0;background:rgba(255,255,255,.7);display:flex;align-items:center;justify-content:center;z-index:10}
.dt-loading.hidden{display:none}
.dt-spinner{width:32px;height:32px;border:3px solid #ddd;border-top-color:var(--primary-color,#337ab7);border-radius:50%;animation:dt-spin .8s linear infinite}
@keyframes dt-spin{to{transform:rotate(360deg)}}
@media(max-width:640px){.dt-controls,.dt-bottom{flex-direction:column;align-items:stretch}.dt-left,.dt-right{justify-content:center}.dt-pagination{justify-content:center;flex-wrap:wrap}}
`;
    document.head.appendChild( s );
  }

  F1.lib = F1.lib || {};
  F1.lib.DataTable = DataTable;

})(window.F1 = window.F1 || {});
