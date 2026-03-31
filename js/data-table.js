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
   * @version 4.2 - FIX - 31 Mar 2026 - V-align dt-info with page-size; widen dt-bottom-left gap
   * @version 4.1 - FIX - 31 Mar 2026 - Hide empty dt-left; show on addControlLeft()
   * @version 4.0 - UPD - 31 Mar 2026 - Normalize controls spacing: gap 6px, margin 0 on dt-left/dt-right
   * @version 3.9 - UPD - 01 Apr 2026 - Move page-size control from top to bottom bar (all screen sizes)
 * @version 3.8 - FT - 29 Mar 2026 - Clear filters button in filter drawer header
   * @version 3.7 - FT - 29 Mar 2026 - Bottom page-size select for mobile
   * @version 3.6 - UPD - 29 Mar 2026 - Clearer info text + visible pagination button affordances
   *   - Auto min-width on table from column widths + minFlexWidth; prevents flex column collapse
   *
   * @version 2.7 - FT - 29 Mar 2026
   *   - Add widthLg column option: wider column widths on desktop+ (>compactBreakpoint)
   *
   * @version 2.6 - FT - 29 Mar 2026
   *   - Auto table-layout:fixed when columns have widths; trunc CSS overrides for fixed layout
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
      this.onLoad = opts.onLoad || null;
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

      // Compact headers
      this.compactBreakpoint = opts.compactBreakpoint || 1920;
      this._compact = false;

      // State management
      this.stateKey = opts.stateKey || null;
      this.defaultState = opts.defaultState || null;
      this.customFilters = opts.customFilters || {};
      this.resetButton = null;

      // Column config + responsive + export + filter panel
      this.filterPanel = opts.filterPanel || false;
      this.columnConfig = opts.columnConfig || false;
      this.responsive = opts.responsive || false;
      this.responsiveBreakpoints = opts.responsiveBreakpoints || { 2: 1200, 3: 900, 4: 640 };
      this.exportUrl = opts.exportUrl || null;
      this._colOrder = this.columns.map( ( _, i ) => i );
      this._colVisibility = new Map();
      this._responsiveHidden = new Set();
      this.minFlexWidth = opts.minFlexWidth || 120;

      this._init();
      if ( this.stateKey ) this._initState();
    } // constructor


    _init() {
      const c = this.container;
      c.innerHTML = '';
      c.classList.add( 'dt-wrap' );

      // Controls
      c.innerHTML = `<div class="dt-controls">
        <div class="dt-left"><label class="dt-pagesize-top">Show <select class="dt-pagesize"></select> entries</label></div>
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
      this.searchInput.placeholder = 'Search\u2026';
      this.searchInput.oninput = () => this._onSearch();

      // Table
      const scroll = document.createElement( 'div' );
      scroll.className = 'dt-scroll';
      const tbl = document.createElement( 'table' );
      tbl.className = 'dt-table';
      if ( this.columns.some( c => c.width ) ) { tbl.style.tableLayout = 'fixed'; tbl.classList.add( 'dt-fixed' ); }
      this._tbl = tbl;
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
      const bottomLeft = document.createElement( 'div' );
      bottomLeft.className = 'dt-bottom-left';
      this.infoEl = document.createElement( 'div' );
      this.infoEl.className = 'dt-info';
      const ps2Lbl = document.createElement( 'label' );
      ps2Lbl.className = 'dt-pagesize-bottom';
      ps2Lbl.textContent = 'Show: ';
      const ps2 = document.createElement( 'select' );
      ps2.className = 'dt-pagesize';
      this.pageSizes.forEach( s => { const o = document.createElement( 'option' ); o.value = s; o.textContent = s; ps2.appendChild( o ); } );
      ps2.value = this.pageSize;
      ps2.onchange = () => { this.pageSizeSelect.value = ps2.value; this._onPageSizeChange(); };
      this._pageSizeBottom = ps2;
      ps2Lbl.appendChild( ps2 );
      bottomLeft.append( this.infoEl, ps2Lbl );
      this.paginationEl = document.createElement( 'div' );
      this.paginationEl.className = 'dt-pagination';
      bottom.append( bottomLeft, this.paginationEl );
      c.appendChild( bottom );

      if ( this.stateKey ) this._loadColConfig();

      // Compact header titles at narrow widths
      if ( this.columns.some( c => c.titleShort || c.hideCompact ) ) {
        const mq = window.matchMedia( `(max-width: ${this.compactBreakpoint}px)` );
        this._compact = mq.matches;
        const needsRerender = this.columns.some( c => c.hideCompact || c.widthLg );
        mq.addEventListener( 'change', e => {
          this._compact = e.matches;
          needsRerender ? this._reRenderTable() : this._syncHeaderTitles();
        } );
      }

      this._renderHeader();
      this._updateMinWidth();
      this.tbody.onclick = e => this._onRowClick( e );
      if ( this.filterPanel ) this._initFilterPanel();
      if ( this.columnConfig ) this._initColumnConfig();
      if ( this.responsive ) this._initResponsive();
      if ( this.exportUrl ) this._initExport();
    } // _init


    _colW( col ) {
      const w = ( !this._compact && col.widthLg ) ? col.widthLg : col.width;
      return w ? ` style="width:${w};max-width:${w}"` : '';
    } // _colW


    _renderHeader() {
      let html = '<tr>';
      this._vis().forEach( ( { col, i } ) => {
        const sortable = col.sortable !== false;
        const cls = [ sortable ? 'sortable' : '', col.className || '' ].filter( Boolean ).join( ' ' );
        const arrows = sortable ? '<span class="sort-arrows"><span class="up">▲</span><span class="dn">▼</span></span>' : '';
        const label = ( this._compact && col.titleShort ) ? col.titleShort : ( col.title || '' );
        html += `<th class="${cls}" data-col="${i}" title="${col.title || ''}"${this._colW( col )}>${label}${arrows}</th>`;
      } );
      this.headerEl.innerHTML = html + '</tr>';
      this.headerEl.querySelectorAll( 'th.sortable' ).forEach( th => {
        th.onclick = () => this._onSort( +th.dataset.col );
      } );
      this._updateSortIndicators();
    } // _renderHeader


    _syncHeaderTitles() {
      const vis = this._vis();
      this.headerEl.querySelectorAll( 'th' ).forEach( ( th, idx ) => {
        const entry = vis[ idx ];
        if ( !entry ) return;
        const arrows = th.querySelector( '.sort-arrows' );
        const label = ( this._compact && entry.col.titleShort ) ? entry.col.titleShort : ( entry.col.title || '' );
        th.innerHTML = this._esc( label ) + ( arrows ? arrows.outerHTML : '' );
      } );
    } // _syncHeaderTitles


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
      if ( this._pageSizeBottom ) this._pageSizeBottom.value = this.pageSize;
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
        if ( this.onLoad ) this.onLoad( this );
      } catch ( e ) {
        console.error( 'DataTable AJAX error:', e );
        this.tbody.innerHTML = `<tr><td colspan="${this._vis().length}" class="center">Error loading data</td></tr>`;
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
        this._vis().forEach( ( { col: c } ) => {
          const v = c.field ? row[ c.field ] : '';
          html += `<td class="${c.className || ''}"${this._colW( c )}>${c.render ? c.render( v, row ) : this._esc( v )}</td>`;
        } );
        html += '</tr>';
      }
      this.tbody.innerHTML = html;
    } // _renderRows


    _renderPagination() {
      const t = this.totalPages, c = this.currentPage;
      let html = `<button class="dt-btn dt-prev${c <= 1 ? ' disabled' : ''}" data-p="prev"><span class="dt-pg-full">Previous</span><span class="dt-pg-short">&lsaquo;</span></button>`;
      const max = window.matchMedia( '(max-width:640px)' ).matches ? 3 : 5;
      let s = Math.max( 1, c - Math.floor( max / 2 ) );
      let e = Math.min( t, s + max - 1 );
      if ( e - s < max - 1 ) s = Math.max( 1, e - max + 1 );

      if ( s > 1 ) { html += '<button class="dt-btn" data-p="1">1</button>'; if ( s > 2 ) html += '<span class="dt-dots">...</span>'; }
      for ( let p = s; p <= e; p++ ) html += `<button class="dt-btn${p === c ? ' active' : ''}" data-p="${p}">${p}</button>`;
      if ( e < t ) { if ( e < t - 1 ) html += '<span class="dt-dots">...</span>'; html += `<button class="dt-btn" data-p="${t}">${t}</button>`; }
      html += `<button class="dt-btn dt-next${c >= t ? ' disabled' : ''}" data-p="next"><span class="dt-pg-full">Next</span><span class="dt-pg-short">&rsaquo;</span></button>`;

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

      // Format totals with comma thousands and dot decimals (e.g. 1,000,000.00) using shared Utils
      const fmt = n => ( F1.lib && F1.lib.Utils ) ? F1.lib.Utils.currency( n, '', ',', 2, '.' ).replace(/^\s+|\s+$/g, '') : n.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
      let html = '<tr>';

      this._vis().forEach( ( { col, i } ) => {
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
        : `Showing: <b>${start.toLocaleString()}</b> &ndash; <b>${end.toLocaleString()}</b> &nbsp;of&nbsp; <b>${filtered.toLocaleString()}</b>`;
      if ( filtered !== total && total > 0 ) txt += ` <span class="dt-info-filtered">(${total.toLocaleString()} total)</span>`;
      this.infoEl.innerHTML = txt;
      if ( this._exportBtn ) this._exportBtn.title = `Export ${filtered.toLocaleString()} entries to CSV`;
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
    addControlLeft( el ) { this.controlsLeft.style.display = 'flex'; this.controlsLeft.appendChild( el ); }
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
        this.resetButton.title = 'Reset table filters/sort';
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
      if ( this._pageSizeBottom ) this._pageSizeBottom.value = this.defaultState.pageSize;
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
      if ( !this.resetButton && !this._filterPanelWrap ) return;
      const d = this.defaultState;
      const filtersNonDefault = Object.keys( this.customFilters ).some( k => {
        const f = this.customFilters[ k ];
        return ( f.element ? f.element.value : f.default || '' ) !== ( f.default || '' );
      } );
      if ( this.resetButton ) {
        const nonDefault = this.pageSize !== d.pageSize || this.currentPage !== d.currentPage ||
          ( this.sortColField || d.sortColField ) !== d.sortColField || this.sortDir !== d.sortDir ||
          this.searchTerm !== d.searchTerm || filtersNonDefault;
        this.resetButton.classList.toggle( 'hidden', !nonDefault );
      }
      if ( this._filterPanelWrap ) {
        const badge = this._filterPanelWrap.querySelector( '.dt-filter-badge' );
        if ( badge ) badge.classList.toggle( 'active', filtersNonDefault );
      }
      if ( this._filterClearBtn ) this._filterClearBtn.classList.toggle( 'hidden', !filtersNonDefault );
    } // _updateResetBtn


    _resetFilters() {
      const url = new URL( location.href );
      Object.keys( this.customFilters ).forEach( k => {
        const f = this.customFilters[ k ];
        if ( f.element ) f.element.value = f.default || '';
        if ( f.urlParam !== false ) url.searchParams.set( k, f.default || '' );
      } );
      history.replaceState( null, '', url );
      this.currentPage = 1;
      if ( this.isAjax ) this.load();
      else this._render();
      this._saveState();
      this._updateResetBtn();
    } // _resetFilters

    // --- Column Config, Responsive & Export ---

    _vis() {
      const result = [];
      for ( const i of this._colOrder ) {
        const col = this.columns[ i ];
        if ( !col ) continue;
        const uv = this._colVisibility.get( i );
        if ( uv === false ) continue;
        if ( uv === undefined && ( this._responsiveHidden.has( i ) || ( this._compact && col.hideCompact ) ) ) continue;
        result.push( { col, i } );
      }
      return result;
    } // _vis


    _reRenderTable() {
      this._renderHeader();
      this._renderRows();
      this._renderFooter();
      this._updateMinWidth();
    } // _reRenderTable


    _updateMinWidth() {
      if ( !this._tbl ) return;
      let sum = 0, flex = 0;
      this._vis().forEach( ( { col } ) => {
        const w = ( !this._compact && col.widthLg ) ? col.widthLg : col.width;
        w ? sum += parseInt( w ) : flex++;
      } );
      this._tbl.style.minWidth = flex ? ( sum + flex * this.minFlexWidth ) + 'px' : '';
    } // _updateMinWidth


    _loadColConfig() {
      try {
        const raw = localStorage.getItem( this.stateKey + '-cols' );
        if ( !raw ) return;
        const cfg = JSON.parse( raw );
        if ( Array.isArray( cfg.order ) && cfg.order.length === this.columns.length ) this._colOrder = cfg.order;
        if ( cfg.vis ) Object.entries( cfg.vis ).forEach( ( [ k, v ] ) => this._colVisibility.set( +k, v ) );
      } catch { /* ignore */ }
    } // _loadColConfig


    _saveColConfig() {
      if ( !this.stateKey ) return;
      try {
        const vis = {};
        this._colVisibility.forEach( ( v, k ) => vis[ k ] = v );
        localStorage.setItem( this.stateKey + '-cols', JSON.stringify( { order: this._colOrder, vis } ) );
      } catch { /* ignore */ }
    } // _saveColConfig


    _initFilterPanel() {
      const Utils = F1.lib?.Utils;
      if ( !Utils ) return;
      const wrap = Utils.newEl( 'div', 'dt-filter-wrap' );
      const btn = Utils.newEl( 'button', 'btn btn-sm btn-outline dt-filter-btn', { type: 'button', title: 'Filters' } );
      btn.innerHTML = '<i class="fa fa-filter"></i><span class="dt-filter-badge"></span>';
      const panel = Utils.newEl( 'div', 'dt-filter-panel' );
      const header = Utils.newEl( 'div', 'dt-drawer-header' );
      header.innerHTML = '<span class="dt-drawer-title">Filters</span>';
      const clearBtn = Utils.newEl( 'button', 'dt-filter-clear hidden', { type: 'button' } );
      clearBtn.textContent = 'Reset All';
      clearBtn.onclick = () => this._resetFilters();
      this._filterClearBtn = clearBtn;
      const closeBtn = Utils.newEl( 'button', 'dt-drawer-close', { type: 'button', title: 'Close' } );
      closeBtn.innerHTML = '&times;';
      header.append( clearBtn, closeBtn );
      panel.appendChild( header );
      const backdrop = Utils.newEl( 'div', 'dt-filter-backdrop' );
      wrap.append( btn, panel );
      this._filterPanelWrap = wrap;
      this._filterPanel = panel;
      const mobile = () => window.matchMedia( '(max-width:640px)' ).matches;
      const close = () => {
        panel.classList.remove( 'open' ); backdrop.classList.remove( 'open' );
        if ( panel.parentElement === document.body ) wrap.appendChild( panel );
      };
      const open = () => {
        if ( mobile() ) document.body.appendChild( panel );
        panel.classList.add( 'open' ); backdrop.classList.add( 'open' );
      };
      btn.onclick = e => { e.stopPropagation(); panel.classList.contains( 'open' ) ? close() : open(); };
      closeBtn.onclick = close;
      backdrop.onclick = close;
      document.body.appendChild( backdrop );
    } // _initFilterPanel


    _initColumnConfig() {
      const Utils = F1.lib?.Utils;
      if ( !Utils ) return;
      const wrap = Utils.newEl( 'div', 'dt-col-config-wrap' );
      const btn = Utils.newEl( 'button', 'btn btn-sm btn-outline', { type: 'button', title: 'Configure columns' } );
      btn.innerHTML = '<i class="fa fa-columns"></i>';
      const panel = Utils.newEl( 'div', 'dt-col-config' );
      const cfgHeader = Utils.newEl( 'div', 'dt-drawer-header' );
      cfgHeader.innerHTML = '<span class="dt-drawer-title">Columns</span>';
      const cfgClose = Utils.newEl( 'button', 'dt-drawer-close', { type: 'button', title: 'Close' } );
      cfgClose.innerHTML = '&times;';
      cfgHeader.appendChild( cfgClose );
      panel.appendChild( cfgHeader );
      const backdrop = Utils.newEl( 'div', 'dt-col-config-backdrop' );
      wrap.append( btn, panel );
      this._colConfigWrap = wrap;
      const mobile = () => window.matchMedia( '(max-width:640px)' ).matches;
      const close = () => {
        panel.classList.remove( 'open' ); backdrop.classList.remove( 'open' );
        if ( panel.parentElement === document.body ) wrap.appendChild( panel );
      };
      const openCfg = () => {
        if ( mobile() ) document.body.appendChild( panel );
        panel.classList.add( 'open' ); backdrop.classList.add( 'open' );
      };
      btn.onclick = e => { e.stopPropagation(); panel.classList.contains( 'open' ) ? close() : openCfg(); };
      cfgClose.onclick = close;
      document.addEventListener( 'click', e => { if ( !wrap.contains( e.target ) && !panel.contains( e.target ) ) close(); } );
      backdrop.onclick = close;
      document.body.appendChild( backdrop );
      this._colConfigPanel = panel;
      this._renderColConfig();
    } // _initColumnConfig


    _renderColConfig() {
      const panel = this._colConfigPanel;
      if ( !panel ) return;
      const hdr = panel.querySelector( '.dt-drawer-header' );
      panel.innerHTML = '';
      if ( hdr ) panel.appendChild( hdr );
      this._colOrder.forEach( ( ci, pos ) => {
        const col = this.columns[ ci ];
        if ( !col || col.configurable === false || !col.title ) return;
        const uv = this._colVisibility.get( ci );
        const isVis = uv === false ? false : ( uv === true || !this._responsiveHidden.has( ci ) );
        const item = document.createElement( 'div' );
        item.className = 'dt-col-config-item';
        const lbl = document.createElement( 'label' );
        const cb = document.createElement( 'input' );
        cb.type = 'checkbox'; cb.checked = isVis;
        lbl.append( cb, document.createTextNode( ' ' + col.title ) );
        const mv = document.createElement( 'span' );
        mv.className = 'dt-col-config-move';
        const up = document.createElement( 'button' );
        up.type = 'button'; up.textContent = '▲'; up.title = 'Move up';
        up.onclick = e => { e.stopPropagation(); this._moveCol( pos, pos - 1 ); };
        const dn = document.createElement( 'button' );
        dn.type = 'button'; dn.textContent = '▼'; dn.title = 'Move down';
        dn.onclick = e => { e.stopPropagation(); this._moveCol( pos, pos + 1 ); };
        mv.append( up, dn );
        item.append( lbl, mv );
        cb.onchange = () => {
          this._colVisibility.set( ci, cb.checked );
          this._saveColConfig();
          this._reRenderTable();
        };
        panel.appendChild( item );
      } );
    } // _renderColConfig


    _moveCol( from, to ) {
      if ( to < 0 || to >= this._colOrder.length ) return;
      const item = this._colOrder.splice( from, 1 )[ 0 ];
      this._colOrder.splice( to, 0, item );
      this._saveColConfig();
      this._renderColConfig();
      this._reRenderTable();
    } // _moveCol


    _initResponsive() {
      const update = () => {
        const w = this.scrollContainer.clientWidth;
        const prev = new Set( this._responsiveHidden );
        this._responsiveHidden.clear();
        this.columns.forEach( ( col, i ) => {
          const p = col.priority;
          if ( p && p > 1 && this.responsiveBreakpoints[ p ] && w < this.responsiveBreakpoints[ p ] ) {
            this._responsiveHidden.add( i );
          }
        } );
        const changed = prev.size !== this._responsiveHidden.size || [ ...prev ].some( x => !this._responsiveHidden.has( x ) );
        if ( changed ) { this._reRenderTable(); if ( this._colConfigPanel ) this._renderColConfig(); }
      };
      if ( window.ResizeObserver ) new ResizeObserver( update ).observe( this.scrollContainer );
      update();
    } // _initResponsive


    _initExport() {
      const Utils = F1.lib?.Utils;
      if ( !Utils ) return;
      this._exportBtn = Utils.newEl( 'button', 'btn btn-sm btn-outline dt-export-btn', { type: 'button', title: 'Export to CSV' } );
      this._exportBtn.innerHTML = '<i class="fa fa-download"></i>';
      this._exportBtn.onclick = () => this._doExport();
      this.addControlRight( this._exportBtn );
    } // _initExport


    _doExport() {
      if ( !this.exportUrl ) return;
      let iframe = document.getElementById( 'dt-export-frame' );
      if ( !iframe ) {
        iframe = document.createElement( 'iframe' );
        iframe.id = 'dt-export-frame'; iframe.name = 'dt-export-frame';
        iframe.style.display = 'none';
        document.body.appendChild( iframe );
      }
      const form = document.createElement( 'form' );
      form.method = 'POST'; form.action = this.exportUrl;
      form.target = 'dt-export-frame'; form.style.display = 'none';
      const params = { action: 'export_csv', search: this.searchTerm || '',
        sortCol: this.sortColField || '', sortDir: ( this.sortDir || 'desc' ).toUpperCase(), ...this.ajaxParams() };
      Object.entries( params ).forEach( ( [ k, v ] ) => {
        const inp = document.createElement( 'input' );
        inp.type = 'hidden'; inp.name = k; inp.value = v;
        form.appendChild( inp );
      } );
      document.body.appendChild( form );
      form.submit();
      setTimeout( () => form.remove(), 2000 );
    } // _doExport

  } // DataTable


  // Styles
  if ( !document.getElementById( 'dt-styles' ) ) {
    const s = document.createElement( 'style' );
    s.id = 'dt-styles';
    s.textContent = `
.dt-wrap{display:flex;flex-direction:column;height:100%;box-sizing:border-box}
.dt-wrap *,.dt-wrap *:before,.dt-wrap *:after{box-sizing:inherit}
.dt-controls{display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:13px;padding:5px}
.dt-left,.dt-right{display:flex;gap:8px;align-items:center;margin:0;white-space:nowrap}
.dt-left{display:none;gap:16px}
.dt-search,.dt-pagesize{padding:5px;border:1px solid #aaa;border-radius:3px;background:transparent}
.dt-search{width:100px}
.dt-filter-sm{max-width:120px;padding:5px;border:1px solid #aaa;border-radius:3px;background:transparent;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.clients-filter-group{display:flex;align-items:center;gap:3px;flex-wrap:nowrap}
.dt-scroll{overflow:auto;position:relative}
.dt-table{width:100%;border-collapse:collapse}
.dt-table th,.dt-table td{padding:6px 6px;text-align:left;white-space:nowrap}
.dt-table th{font-size:13px}.dt-table td{font-size:12px;border-bottom:1px solid #ddd}
.dt-table thead{background:var(--heading-color,#2c3e50);color:#fff;position:sticky;top:0;z-index:2}
.dt-table tfoot tr{background:#f5f5f5;position:sticky;bottom:0;z-index:1;font-size:.9em;border-bottom:1px solid grey;box-shadow:0 4px 0 #f5f5f5}
.dt-table tfoot th{padding:8px 2px 8px 12px;font-weight:600}
.dt-table th.sortable{cursor:pointer;user-select:none}
.dt-table th.sortable:hover{background:rgba(255,255,255,.1)}
.dt-table tbody tr:hover{background:#eee;cursor:pointer}
.dt-table .fa{width:1em;color:var(--primary-color);opacity:.67;vertical-align:middle;line-height:1}
.dt-bottom{display:flex;justify-content:space-between;align-items:center;}
.dt-bottom-left{display:flex;align-items:center;gap:28px;margin:5.5px}
.dt-info{font-size:13px;color:#444;padding:0}
.dt-pagesize-top{display:none}
.dt-pagesize-bottom{display:flex;align-items:center;gap:4px;font-size:13px;color:#444;white-space:nowrap}
.dt-pagination{display:flex;align-items:center;padding:0;margin:5.5px}
.dt-btn{padding:6.5px 13px;border:1px solid #ddd;background:#fafafa;cursor:pointer;margin-left:2px;color:#555;font-size:13px;border-radius:3px}
.dt-pg-short{display:none}
.dt-btn:first-child{margin-left:0}
.dt-btn:hover:not(.disabled):not(.active){background:#e8e8e8;border-color:#ccc}
.dt-btn.active{border:1px solid rgba(0,0,0,.3);color:#fff;background:var(--primary-color,#337ab7)}
.dt-btn.disabled{color:#bbb;background:transparent;border-color:#eee;cursor:default}
.dt-btn.dt-prev,.dt-btn.dt-next{color:#555}
.dt-dots{padding:6.5px 4px;color:#999;font-size:13px}
.dt-info-filtered{color:#999;font-size:12px}
.sort-arrows{display:inline-flex;flex-direction:column;vertical-align:middle;margin-left:8px;line-height:.7;font-size:9px}
.sort-arrows .up,.sort-arrows .dn{opacity:.2;transition:opacity .2s}
.dt-table th.sortable:hover .up,.dt-table th.sortable:hover .dn{opacity:.5}
.dt-table th.sort-asc .up,.dt-table th.sort-desc .dn{opacity:1;color:#fff}
.dt-table .left{text-align:left}.dt-table .right{text-align:right}.dt-table .center{text-align:center}
.dt-table .nowrap{white-space:nowrap}.dt-table .name{min-width:140px}.dt-table .mute{color:#888;font-size:.85em}
.dt-table .trunc{width:1px;white-space:nowrap;overflow:hidden}.dt-table .trunc-text{display:inline-block;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle}
.dt-fixed .trunc{width:auto}.dt-fixed .trunc-text{max-width:calc(100% - 1.5em)}.dt-fixed td{overflow:hidden;text-overflow:ellipsis}
.dt-loading{position:absolute;inset:0;background:rgba(255,255,255,.7);display:flex;align-items:center;justify-content:center;z-index:10}
.dt-loading.hidden{display:none}
.dt-spinner{width:32px;height:32px;border:3px solid #ddd;border-top-color:var(--primary-color,#337ab7);border-radius:50%;animation:dt-spin .8s linear infinite}
@keyframes dt-spin{to{transform:rotate(360deg)}}
@media(max-width:640px){.dt-controls{flex-direction:column;align-items:stretch}.dt-left,.dt-right{justify-content:center}.dt-bottom{flex-direction:column;align-items:stretch;gap:2px}.dt-bottom-left{justify-content:space-between;width:100%;padding:0 0 4px}.dt-info{padding:0}.dt-pagesize-bottom{padding:0;margin-right:8px}.dt-pagination{justify-content:center;flex-wrap:nowrap;gap:0}.dt-pagination .dt-btn{padding:8px 10px;min-width:34px;font-size:13px;text-align:center}.dt-pagination .dt-dots{padding:8px 2px}.dt-pg-full{display:none}.dt-pg-short{display:inline;font-size:18px;font-weight:700;line-height:1}}
.dt-col-config-wrap{position:relative;display:inline-block}
.dt-col-config{position:absolute;right:0;top:100%;background:#fff;border:1px solid #ccc;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:100;min-width:220px;max-height:400px;overflow-y:auto;padding:4px 0;display:none}
.dt-col-config.open{display:block}
.dt-col-config-item{display:flex;align-items:center;padding:2px 8px}
.dt-col-config-item label{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;white-space:nowrap;flex:1}
.dt-col-config-move{display:flex;gap:1px}
.dt-col-config-move button{border:none;background:transparent;cursor:pointer;padding:1px 4px;font-size:9px;color:#888;line-height:1}
.dt-col-config-move button:hover{color:#333}
.dt-col-config-backdrop{display:none}
@media(max-width:640px){
.dt-col-config{position:fixed;bottom:0;left:0;right:0;top:auto;min-width:0;max-height:60vh;border-radius:12px 12px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.15);padding:12px 16px;z-index:1001}
.dt-col-config.open{display:block}
.dt-col-config-item{padding:6px 16px}
.dt-col-config-item label{font-size:14px;gap:10px}
.dt-col-config-move button{padding:4px 8px;font-size:12px}
.dt-col-config-backdrop.open{display:block;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1000}
}
.dt-export-btn{order:99;margin-left:12px}
.dt-drawer-header{display:none}
.dt-filter-clear{display:none}
.dt-filter-wrap{position:relative;display:inline-flex;align-items:center}
.dt-filter-btn{display:none;position:relative}
.dt-filter-panel{display:contents}
.dt-filter-badge{display:none}
.dt-filter-backdrop{display:none}
.dt-filter-panel label[data-label]::before{display:none}
@media(max-width:640px){
.dt-drawer-header{display:flex;justify-content:space-between;align-items:center;grid-column:1/-1;padding:0 0 4px}
.dt-drawer-title{font-size:15px;font-weight:600;color:#333}
.dt-drawer-close{margin-left:auto;border:none;background:transparent;font-size:22px;line-height:1;color:#888;cursor:pointer;padding:0 4px}
.dt-filter-clear{display:inline-block;margin-left:12px;border:none;background:transparent;font-size:12px;color:var(--primary-color,#337ab7);cursor:pointer;padding:2px 0}
.dt-filter-clear.hidden{display:none}
.dt-col-config-wrap{order:-1}
.dt-filter-btn{display:inline-flex}
.dt-filter-panel{display:none;position:fixed;bottom:0;left:0;right:0;z-index:1001;background:#fff;border-radius:12px 12px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.15);padding:16px;grid-template-columns:auto 1fr;gap:10px 12px;align-items:center}
.dt-filter-panel.open{display:grid}
.dt-filter-panel .clients-filter-group{display:contents}
.dt-filter-panel label{display:contents}
.dt-filter-panel label[data-label]::before{display:block;content:attr(data-label);font-size:13px;font-weight:600;color:#555;white-space:nowrap}
.dt-filter-panel select{width:100%;padding:8px;font-size:14px;border:1px solid #ccc;border-radius:4px;background:#fff}
.dt-filter-badge.active{display:block;position:absolute;top:-2px;right:-2px;width:8px;height:8px;border-radius:50%;background:var(--primary-color,#337ab7)}
.dt-filter-backdrop.open{display:block;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1000}
}
`;
    document.head.appendChild( s );
  }

  F1.lib = F1.lib || {};
  F1.lib.DataTable = DataTable;

})(window.F1 = window.F1 || {});
