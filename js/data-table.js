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
 * Last version commits:
 * @version 5.48 - UPD - 28 Jul 2026 - AF filter btn tip: Custom Filters
 * @version 5.47 - UPD - 28 Jul 2026 - Col config badge dot 10→9px
 * @version 5.46 - UPD - 28 Jul 2026 - AF/col badges sit on outer corner; col dot +2px
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
      this.sortColField = null;
      this.sortDir = 'asc';
      this.sortStack = [];
      this.searchTerm = '';
      this.currentPage = 1;
      this.totalPages = 1;
      this.recordsTotal = 0;
      this.recordsFiltered = 0;
      this.currencyCache = {};
      this.isLoading = false;
      this.searchDebounceTimer = null;

      this._initSortStack( opts );

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
      this.exportEnabled = opts.export !== false; // false disables; else button always
      this.advancedFilters = !!opts.advancedFilters;
      this._af = {};
      this._afTimer = null;
      this._colOrder = this.columns.map( ( _, i ) => i );
      this._colVisibility = new Map();
      this._responsiveHidden = new Set();
      this.minFlexWidth = opts.minFlexWidth || 120;
      // Row density (not breakpoint `_compact` / titleShort)
      this.density = opts.density === 'compact' ? 'compact' : 'comfortable';
      this._densityInputs = [];

      if ( this.advancedFilters ) {
        this.filterPanel = true;
        const userParams = this.ajaxParams;
        this.ajaxParams = () => Object.assign( {}, userParams(), this._afFlatParams() );
      }

      this._init();
      if ( this.stateKey ) this._initState();
    } // constructor


    _init() {
      const c = this.container;
      c.innerHTML = '';
      c.classList.add( 'dt-wrap' );
      if ( this.stateKey ) this._loadDensity();
      this._applyDensity();

      // Controls
      c.innerHTML = `<div class="dt-controls">
        <div class="dt-left"><label class="dt-pagesize-top">Show <select class="dt-pagesize"></select> entries</label></div>
        <div class="dt-right">
          <div class="dt-search-wrap dt-search-label">
            <span class="dt-search-icon" aria-hidden="true">
              <svg viewBox="0 0 16 16" width="14" height="14" focusable="false">
                <circle cx="6.5" cy="6.5" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                <path d="M10 10l3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </span>
            <input type="search" class="dt-search" placeholder="Search\u2026" aria-label="Search">
            <button type="button" class="dt-search-clear hidden" aria-label="Clear search" title="Clear search">&times;</button>
          </div>
        </div>
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
      this.searchClearBtn = c.querySelector( '.dt-search-clear' );
      this.searchInput.oninput = () => { this._onSearch(); this._syncSearchClear(); };
      this.searchClearBtn.onclick = e => {
        e.preventDefault();
        this.searchInput.value = '';
        this._onSearch();
        this._syncSearchClear();
        this.searchInput.focus();
      };

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

      // Empty state (below thead; no h-scroll spacer when tbody is empty)
      this.emptyEl = document.createElement( 'div' );
      this.emptyEl.className = 'dt-empty-msg hidden';
      this.emptyEl.innerHTML = '<svg class="dt-empty-ico" viewBox="0 0 64 64" aria-hidden="true">'
        + '<path d="M8 24l6-12h36l6 12v28a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4V24z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>'
        + '<path d="M8 24h48M22 36h20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
        + '</svg><span class="dt-empty-lbl">No entries found</span>';
      scroll.appendChild( this.emptyEl );
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
      if ( this.advancedFilters ) this._initAdvancedFilters();
      if ( this.columnConfig ) this._initColumnConfig();
      if ( this.responsive ) this._initResponsive();
      if ( this.exportEnabled ) this._initExport();
    } // _init


    _initSortStack( opts ) {
      const ds = opts.defaultState;
      if ( ds?.sortStack?.length ) this.sortStack = ds.sortStack.map( s => ({ field: s.field, dir: s.dir || 'asc' }) );
      else {
        const field = opts.sortCol ?? ds?.sortColField ?? ( this.isAjax ? 'date' : null );
        const dir = opts.sortDir || ds?.sortDir || 'desc';
        this.sortStack = field ? [{ field, dir }] : [];
      }
      this._syncLegacySort();
    } // _initSortStack


    _defaultSortStack() {
      const d = this.defaultState || {};
      if ( d.sortStack?.length ) return d.sortStack.map( s => ({ field: s.field, dir: s.dir || 'asc' }) );
      if ( d.sortColField ) return [{ field: d.sortColField, dir: d.sortDir || 'asc' }];
      return [];
    } // _defaultSortStack


    _syncLegacySort() {
      const primary = this.sortStack[ 0 ];
      if ( primary ) {
        this.sortColField = primary.field;
        this.sortDir = primary.dir || 'asc';
        this.sortCol = this.columns.findIndex( c => c.field === primary.field );
        if ( this.sortCol === -1 ) this.sortCol = null;
      } else {
        this.sortColField = null;
        this.sortDir = 'asc';
        this.sortCol = null;
      }
    } // _syncLegacySort


    _applyStateSort( state ) {
      if ( state.sortStack ) this.sortStack = state.sortStack.map( s => ({ field: s.field, dir: s.dir || 'asc' }) );
      else if ( state.sortColField ) this.sortStack = [{ field: state.sortColField, dir: state.sortDir || 'asc' }];
      else this.sortStack = [];
      this._syncLegacySort();
    } // _applyStateSort


    _soleGrowVisible() {
      return !this._vis().some( v => {
        if ( v.col.grow ) return false;
        const w = ( !this._compact && v.col.widthLg ) ? v.col.widthLg : v.col.width;
        return !w;
      } );
    } // _soleGrowVisible


    _colW( col ) {
      const w = ( !this._compact && col.widthLg ) ? col.widthLg : col.width;
      if ( col.grow ) {
        const min = w ? `min-width:${w};` : '';
        return this._soleGrowVisible()
          ? ` style="${min}width:99%"` : ( min ? ` style="${min}"` : '' );
      }
      if ( w ) return ` style="width:${w};max-width:${w}"`;
      return '';
    } // _colW


    _renderHeader() {
      const sortTip = 'Click: asc/desc/none · Shift/Ctrl+click: multi-sort';
      let html = '<tr>';
      this._vis().forEach( ( { col, i } ) => {
        const sortable = col.sortable !== false;
        const cls = [ sortable ? 'sortable' : '', col.className || '' ].filter( Boolean ).join( ' ' );
        const showShort = this._compact && col.titleShort;
        const label = showShort ? col.titleShort : ( col.title || '' );
        const tip = col.titleTip || col.title || '';
        const labelTip = tip ? ` title="${this._esc( tip )}"` : '';
        const labelHtml = `<span class="th-label"${labelTip}>${this._esc( label )}</span>`;
        const arrows = sortable
          ? `<span class="sort-arrows" title="${sortTip}"><span class="up">▲</span><span class="dn">▼</span></span>`
          : '';
        html += `<th class="${cls}" data-col="${i}"${this._colW( col )}>${labelHtml}${arrows}</th>`;
      } );
      this.headerEl.innerHTML = html + '</tr>';
      this.headerEl.querySelectorAll( 'th.sortable' ).forEach( th => {
        th.onclick = ( e ) => this._onSort( +th.dataset.col, e );
      } );
      this._updateSortIndicators();
    } // _renderHeader


    _syncHeaderTitles() {
      const vis = this._vis();
      this.headerEl.querySelectorAll( 'th' ).forEach( ( th, idx ) => {
        const entry = vis[ idx ];
        if ( !entry ) return;
        const col = entry.col;
        const showShort = this._compact && col.titleShort;
        const labelEl = th.querySelector( '.th-label' );
        if ( !labelEl ) return;
        labelEl.textContent = showShort ? col.titleShort : ( col.title || '' );
        const tip = col.titleTip || col.title || '';
        if ( tip ) labelEl.title = tip;
        else labelEl.removeAttribute( 'title' );
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
      this.filteredData = [];
      this.filteredIndices = [];
      this.allData.forEach( ( row, i ) => {
        if ( term ) {
          const hay = Object.values( row ).join( ' ' ).toLowerCase();
          if ( !hay.includes( term ) ) return;
        }
        if ( this.advancedFilters && !this._rowMatchesAf( row ) ) return;
        this.filteredData.push( row );
        this.filteredIndices.push( i );
      } );
    } // _applyFilter


    _applySort() {
      if ( !this.sortStack.length ) return;

      const indices = this.filteredData.map( ( _, i ) => i );
      indices.sort( ( ai, bi ) => {
        const aRow = this.filteredData[ ai ], bRow = this.filteredData[ bi ];
        for ( const { field, dir } of this.sortStack ) {
          const cmp = this._compareField( aRow, bRow, field );
          if ( cmp !== 0 ) return cmp * ( dir === 'asc' ? 1 : -1 );
        }
        return 0;
      } );

      this.filteredData = indices.map( i => this.filteredData[ i ] );
      this.filteredIndices = indices.map( i => this.filteredIndices[ i ] );
      this._updateSortIndicators();
    } // _applySort


    _compareField( aRow, bRow, field ) {
      const col = this.columns.find( c => c.field === field );
      if ( !col?.field ) return 0;
      let a = aRow[ field ], b = bRow[ field ];
      if ( a == null ) return 1;
      if ( b == null ) return -1;
      if ( col.type === 'currency' ) {
        a = parseFloat( String( a ).replace( /[^0-9.\-]/g, '' ) ) || 0;
        b = parseFloat( String( b ).replace( /[^0-9.\-]/g, '' ) ) || 0;
        return a - b;
      }
      if ( col.type === 'date' ) return ( new Date( a ).getTime() || 0 ) - ( new Date( b ).getTime() || 0 );
      const na = parseFloat( a ), nb = parseFloat( b );
      if ( !isNaN( na ) && !isNaN( nb ) ) return na - nb;
      a = String( a ).toLowerCase(); b = String( b ).toLowerCase();
      return a < b ? -1 : a > b ? 1 : 0;
    } // _compareField


    _updateSortIndicators() {
      this.headerEl.querySelectorAll( 'th' ).forEach( th => {
        th.classList.remove( 'sort-asc', 'sort-desc' );
        th.querySelector( '.sort-pri' )?.remove();
      } );
      this.sortStack.forEach( ( s, n ) => {
        const i = this.columns.findIndex( c => c.field === s.field );
        if ( i === -1 ) return;
        const th = this.headerEl.querySelector( `th[data-col="${i}"]` );
        if ( !th ) return;
        th.classList.add( s.dir === 'asc' ? 'sort-asc' : 'sort-desc' );
        if ( this.sortStack.length > 1 ) {
          const badge = document.createElement( 'span' );
          badge.className = 'sort-pri';
          badge.textContent = n + 1;
          th.appendChild( badge );
        }
      } );
    } // _updateSortIndicators


    _onSort( i, e ) {
      const col = this.columns[ i ];
      if ( !col?.field || col.sortable === false ) return;
      const field = col.field;
      const multi = e && ( e.ctrlKey || e.metaKey || e.shiftKey );
      const idx = this.sortStack.findIndex( s => s.field === field );

      if ( multi ) {
        if ( idx === -1 ) this.sortStack.push( { field, dir: 'asc' } );
        else if ( this.sortStack[ idx ].dir === 'asc' ) this.sortStack[ idx ].dir = 'desc';
        else this.sortStack.splice( idx, 1 );
      } else if ( idx === -1 || this.sortStack.length !== 1 ) {
        this.sortStack = [{ field, dir: 'asc' }];
      } else if ( this.sortStack[ 0 ].dir === 'asc' ) {
        this.sortStack[ 0 ].dir = 'desc';
      } else {
        this.sortStack = [];
      }
      this._syncLegacySort();
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


    _syncSearchClear() {
      if ( !this.searchClearBtn ) return;
      this.searchClearBtn.classList.toggle( 'hidden', !( this.searchInput?.value ) );
    } // _syncSearchClear


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
        sortCol: this.sortColField || '',
        sortDir: this.sortColField ? ( this.sortDir || 'asc' ).toUpperCase() : '',
        sortStack: JSON.stringify( this.sortStack ),
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
      const empty = end <= start;
      this.container.classList.toggle( 'dt-empty', empty );
      if ( this.emptyEl ) this.emptyEl.classList.toggle( 'hidden', !empty );
      if ( empty ) {
        this.tbody.innerHTML = '';
        return;
      }
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
      const filtered = this.isAjax ? this.recordsFiltered : this.filteredData.length;
      // No totals bar when empty — same look as lists without footerTotals
      if ( filtered === 0 || ( !hasFooterTotals && !hasCurrencyCols ) ) {
        this.footerEl.innerHTML = '';
        return;
      }

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
      let txt = '<span class="dt-info-label">Showing:</span><span class="dt-info-vals">'
        + `<b>${start.toLocaleString()}</b> &ndash; `
        + `<b>${end.toLocaleString()}</b> of <b>${filtered.toLocaleString()}</b>`
        + ( filtered !== total && total > 0
          ? ` <span class="dt-info-filtered">(${total.toLocaleString()} total)</span>` : '' )
        + '</span>';
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

      if ( state.pageSize ) {
        this.pageSize = state.pageSize;
        this.pageSizeSelect.value = state.pageSize;
        if ( this._pageSizeBottom ) this._pageSizeBottom.value = state.pageSize;
      }
      if ( state.currentPage ) this.currentPage = state.currentPage;
      this._applyStateSort( state );
      if ( state.searchTerm ) { this.searchTerm = state.searchTerm; this.searchInput.value = state.searchTerm; }
      this._syncSearchClear();
      Object.keys( this.customFilters ).forEach( k => {
        const f = this.customFilters[ k ];
        if ( f.element && state[ k ] !== undefined ) f.element.value = state[ k ];
      } );
      if ( this.advancedFilters && state.af && typeof state.af === 'object' ) {
        this._af = state.af;
        this._afFillUi();
      }

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
          sortStack: this.sortStack, sortColField: this.sortColField, sortDir: this.sortDir, searchTerm: this.searchTerm || '' };
        Object.keys( this.customFilters ).forEach( k => {
          const f = this.customFilters[ k ]; s[ k ] = f.element ? f.element.value : f.default || '';
        } );
        if ( this.advancedFilters ) s.af = this._af;
        localStorage.setItem( this.stateKey, JSON.stringify( s ) );
      } catch ( e ) { console.error( 'State save error:', e ); }
    } // _saveState


    _resetState() {
      localStorage.removeItem( this.stateKey );
      this.pageSize = this.defaultState.pageSize;
      this.currentPage = 1;
      this.searchTerm = '';
      this.pageSizeSelect.value = this.defaultState.pageSize;
      if ( this._pageSizeBottom ) this._pageSizeBottom.value = this.defaultState.pageSize;
      this.searchInput.value = '';
      this._syncSearchClear();
      const url = new URL( location.href );
      Object.keys( this.customFilters ).forEach( k => {
        const f = this.customFilters[ k ];
        if ( f.element ) f.element.value = f.default || '';
        if ( f.urlParam !== false ) url.searchParams.set( k, f.default || '' );
      } );
      history.replaceState( null, '', url );
      if ( this.advancedFilters ) {
        this._af = {};
        this._afFillUi();
      }
      this.sortStack = this._defaultSortStack().map( s => ({ ...s }) );
      this._syncLegacySort();
      this._updateSortIndicators();
      if ( this.isAjax ) { this.currentPage = 1; this.load(); }
      else this._render();
      this._updateResetBtn();
    } // _resetState


    _effectiveSortDir( dir ) {
      return ( dir !== undefined && dir !== null && dir !== '' ) ? dir : 'desc';
    } // _effectiveSortDir


    _sortStackEq( a, b ) {
      return JSON.stringify( a || [] ) === JSON.stringify( b || [] );
    } // _sortStackEq


    _afActive() {
      return !!( this.advancedFilters && Object.keys( this._af || {} ).some( k => this._af[ k ]?.op ) );
    } // _afActive


    _afSummaryItems() {
      if ( !this.advancedFilters ) return [];
      const items = [];
      this.columns.forEach( col => {
        if ( !col.filter || !col.field ) return;
        const spec = this._af[ col.field ];
        if ( !spec || !spec.op ) return;
        const title = col.title || col.field;
        const op = this._afOpLabel( spec.op );
        let val = '';
        if ( spec.op === 'EMPTY' || spec.op === 'NOT_EMPTY' ) {
          val = '';
        } else if ( spec.op === 'BETWEEN' ) {
          val = ( spec.v || '' ) + ' – ' + ( spec.v2 || '' );
        } else if ( spec.op === 'IN' || spec.op === 'NOT_IN' ) {
          const raw = Array.isArray( spec.set ) ? spec.set
            : String( spec.set ?? '' ).split( /[\n,]+/ );
          const opts = this._resolveFilterOptions( col );
          val = raw.map( v => {
            const s = String( v ).trim();
            if ( !s ) return '';
            const hit = opts.find( o => o.value === s );
            return hit ? hit.label : s;
          } ).filter( Boolean ).join( ', ' );
        } else if ( ( col.filter.type || 'text' ) === 'boolean' ) {
          const s = String( spec.v ?? '' );
          val = s === '1' ? 'Yes' : s === '0' ? 'No' : s;
        } else {
          val = spec.v != null ? String( spec.v ) : '';
        }
        const text = val ? ( title + ' ' + op + ' ' + val ) : ( title + ' ' + op );
        items.push( { field: col.field, title, text } );
      } );
      return items;
    } // _afSummaryItems


    _initAfSummary() {
      const Utils = F1.lib?.Utils;
      if ( !Utils || this._afSummaryBar ) return;
      const bar = Utils.newEl( 'div', 'dt-af-summary hidden' );
      bar.setAttribute( 'aria-live', 'polite' );
      this._afSummaryBar = bar;
    } // _initAfSummary


    _renderAfSummary() {
      if ( !this._afSummaryBar ) return;
      if ( !this._afSummaryBar.isConnected ) {
        const controls = this.container.querySelector( '.dt-controls' );
        if ( controls ) controls.appendChild( this._afSummaryBar );
      }
      const items = this._afSummaryItems();
      this._afSummaryBar.innerHTML = '';
      this._afSummaryBar.classList.toggle( 'hidden', !items.length );
      items.forEach( item => {
        const chip = document.createElement( 'span' );
        chip.className = 'dt-af-chip';
        chip.title = item.text;
        const lbl = document.createElement( 'span' );
        lbl.className = 'dt-af-chip-lbl';
        lbl.textContent = item.text;
        const x = document.createElement( 'button' );
        x.type = 'button';
        x.className = 'dt-af-chip-x';
        x.setAttribute( 'aria-label', 'Clear ' + item.title );
        x.innerHTML = '&times;';
        x.onclick = e => {
          e.preventDefault();
          e.stopPropagation();
          this._afClearField( item.field );
        };
        chip.append( lbl, x );
        this._afSummaryBar.appendChild( chip );
      } );
      const btn = this._filterPanelWrap?.querySelector( '.dt-filter-btn' );
      if ( btn ) {
        btn.title = items.length
          ? ( 'Filters: ' + items.map( i => i.text ).join( ' · ' ) )
          : 'Filters';
      }
    } // _renderAfSummary


    _afClearField( field ) {
      if ( !field || !this._af ) return;
      delete this._af[ field ];
      this._afFillUi();
      this.currentPage = 1;
      // Sync quick-filter overrides (Days↔Custom) before ajaxParams are read.
      this._updateResetBtn();
      if ( this.isAjax ) this.load();
      else { this._applyFilter(); this._applySort(); this._render(); }
      if ( this.stateKey ) this._saveState();
    } // _afClearField


    _quickFiltersActive() {
      return Object.keys( this.customFilters ).some( k => {
        const f = this.customFilters[ k ];
        return ( f.element ? f.element.value : f.default || '' ) !== ( f.default || '' );
      } );
    } // _quickFiltersActive


    _updateResetBtn() {
      if ( !this.resetButton && !this._filterPanelWrap ) return;
      const d = this.defaultState || {};
      const afActive = this._afActive();
      const filtersNonDefault = this._quickFiltersActive() || afActive;
      const sortNonDefault = !this._sortStackEq( this.sortStack, this._defaultSortStack() );
      if ( this.resetButton ) {
        const nonDefault = this.pageSize !== d.pageSize || this.currentPage !== d.currentPage ||
          sortNonDefault || this.searchTerm !== d.searchTerm || filtersNonDefault;
        this.resetButton.classList.toggle( 'hidden', !nonDefault );
      }
      // Filter-btn badge + tray Reset track advanced filters only (quick filters have their own UI).
      if ( this._filterPanelWrap ) {
        const badge = this._filterPanelWrap.querySelector( '.dt-filter-badge' );
        if ( badge ) {
          const n = this._afSummaryItems().length;
          badge.classList.toggle( 'active', n > 0 );
          badge.textContent = n > 0 ? String( n ) : '';
        }
      }
      if ( this._filterClearBtn ) {
        this._filterClearBtn.classList.toggle( 'hidden', !afActive );
        this._filterPanel?.querySelector( '.dt-drawer-sep' )?.classList.toggle( 'hidden', !afActive );
      }
      this._renderAfSummary();
    } // _updateResetBtn


    _resetFilters() {
      if ( this.advancedFilters ) {
        this._af = {};
        this._afFillUi();
      }
      this.currentPage = 1;
      this._updateResetBtn();
      if ( this.isAjax ) this.load();
      else { this._applyFilter(); this._applySort(); this._render(); }
      this._saveState();
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
      if ( this.columnConfig ) this._updateColConfigBtn();
    } // _reRenderTable


    _updateMinWidth() {
      if ( !this._tbl ) return;
      let sum = 0, flex = 0;
      this._vis().forEach( ( { col } ) => {
        const w = ( !this._compact && col.widthLg ) ? col.widthLg : col.width;
        if ( col.grow ) { if ( w ) sum += parseInt( w, 10 ) || 0; return; }
        w ? sum += parseInt( w, 10 ) || 0 : flex++;
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


    _loadDensity() {
      if ( !this.stateKey ) return;
      try {
        const d = localStorage.getItem( this.stateKey + '-density' );
        if ( d === 'compact' || d === 'comfortable' ) this.density = d;
      } catch { /* ignore */ }
    } // _loadDensity


    _saveDensity() {
      if ( !this.stateKey ) return;
      try { localStorage.setItem( this.stateKey + '-density', this.density ); } catch { /* ignore */ }
    } // _saveDensity


    _applyDensity() {
      const c = this.container;
      if ( !c ) return;
      c.classList.toggle( 'dt-density-compact', this.density === 'compact' );
      c.classList.toggle( 'dt-density-comfortable', this.density === 'comfortable' );
      ( this._densityInputs || [] ).forEach( inp => { inp.checked = inp.value === this.density; } );
    } // _applyDensity


    _setDensity( d ) {
      this.density = d === 'compact' ? 'compact' : 'comfortable';
      this._applyDensity();
      this._saveDensity();
    } // _setDensity


    _afOps( type ) {
      const map = {
        number: [ 'EQ', 'NE', 'GT', 'GE', 'LT', 'LE', 'BETWEEN', 'IN', 'NOT_IN', 'EMPTY', 'NOT_EMPTY' ],
        date: [ 'EQ', 'NE', 'GT', 'GE', 'LT', 'LE', 'BETWEEN', 'EMPTY', 'NOT_EMPTY' ],
        enum: [ 'IN', 'NOT_IN', 'EQ', 'NE', 'EMPTY', 'NOT_EMPTY' ],
        text: [ 'CONTAINS', 'STARTS', 'EQ', 'NE', 'IN', 'NOT_IN', 'EMPTY', 'NOT_EMPTY' ],
        boolean: [ 'EQ', 'NE', 'EMPTY', 'NOT_EMPTY' ]
      };
      return map[ type ] || map.text;
    } // _afOps


    _afOpLabel( op ) {
      return ( {
        EQ: '=', NE: '≠', GT: '>', GE: '≥', LT: '<', LE: '≤',
        BETWEEN: 'between', IN: 'in', NOT_IN: 'not in',
        CONTAINS: 'contains', STARTS: 'starts with',
        EMPTY: 'is empty', NOT_EMPTY: 'not empty'
      } )[ op ] || op;
    } // _afOpLabel


    /**
     * Normalize enum/filter options → [{ value, label }].
     * Accepts: array | CSS selector | HTMLSelectElement | Element | strings | {value,label}.
     * Public so app chrome can reuse without duplicating (do not fork in app helpers).
     */
    static resolveFilterOptions( raw ) {
      if ( raw == null || raw === '' ) return [];
      const skip = v => v === '' || v === 'All';
      const fromNodes = nodes => {
        const out = [];
        Array.from( nodes || [] ).forEach( o => {
          const value = o.hasAttribute( 'value' ) ? o.value : ( o.textContent || '' ).trim();
          const label = ( o.dataset?.label || o.textContent || '' ).trim() || value;
          if ( skip( value ) || skip( label ) ) return;
          out.push( { value: String( value ), label } );
        } );
        return out;
      };
      const optionNodes = el => {
        if ( !el ) return [];
        // <template> children live in .content, not the light DOM
        const root = el.content || el;
        return root.querySelectorAll ? root.querySelectorAll( 'option' ) : [];
      };
      if ( typeof raw === 'string' ) {
        return fromNodes( optionNodes( document.querySelector( raw ) ) );
      }
      if ( typeof HTMLSelectElement !== 'undefined' && raw instanceof HTMLSelectElement ) {
        return fromNodes( raw.options );
      }
      if ( raw && typeof raw === 'object' && raw.nodeType === 1 ) {
        return fromNodes( optionNodes( raw ) );
      }
      if ( !Array.isArray( raw ) ) return [];
      return raw.map( o => {
        if ( o && typeof o === 'object' ) {
          const value = o.value != null ? String( o.value ) : '';
          const label = o.label != null ? String( o.label ) : value;
          return { value, label };
        }
        const s = String( o ?? '' );
        return { value: s, label: s };
      } ).filter( o => !skip( o.value ) && !skip( o.label ) );
    } // resolveFilterOptions


    _resolveFilterOptions( col ) {
      return DataTable.resolveFilterOptions( col?.filter?.options );
    } // _resolveFilterOptions


    _afFlatParams() {
      const out = {};
      Object.entries( this._af || {} ).forEach( ( [ field, spec ] ) => {
        if ( !spec || !spec.op ) return;
        out[ 'af[' + field + '][op]' ] = spec.op;
        if ( spec.v !== undefined && spec.v !== '' ) out[ 'af[' + field + '][v]' ] = spec.v;
        if ( spec.v2 !== undefined && spec.v2 !== '' ) out[ 'af[' + field + '][v2]' ] = spec.v2;
        if ( spec.set !== undefined && spec.set !== '' ) {
          out[ 'af[' + field + '][set]' ] = Array.isArray( spec.set ) ? spec.set.join( ',' ) : spec.set;
        }
      } );
      return out;
    } // _afFlatParams


    _initAdvancedFilters() {
      const Utils = F1.lib?.Utils;
      if ( !Utils || !this._filterPanelWrap ) return;
      this._filterPanelWrap.classList.add( 'dt-af' );
      this.addControlRight( this._filterPanelWrap );
      this._initAfSummary();
      const body = Utils.newEl( 'div', 'dt-af-body' );
      this.columns.forEach( col => {
        if ( !col.filter || !col.field ) return;
        body.appendChild( this._afBuildRow( col ) );
      } );
      this._filterPanel.appendChild( body );
      this._afBody = body;
    } // _initAdvancedFilters


    _afBuildRow( col ) {
      const Utils = F1.lib.Utils;
      const type = col.filter.type || 'text';
      const ops = col.filter.ops || this._afOps( type );
      const enumOpts = type === 'enum' ? this._resolveFilterOptions( col ) : [];
      const row = Utils.newEl( 'div', 'dt-af-row' );
      row.dataset.field = col.field;
      row.dataset.type = type;

      const title = col.title || col.field;
      const meta = Utils.newEl( 'div', 'dt-af-meta' );
      const label = Utils.newEl( 'div', 'dt-af-label' );
      label.textContent = title;
      label.title = title;
      const typeEl = Utils.newEl( 'span', 'dt-af-type' );
      typeEl.textContent = type;
      typeEl.title = 'Filter type: ' + type;
      meta.append( label, typeEl );

      const opSel = Utils.newEl( 'select', 'form-control dt-filter-sm dt-af-op' );
      opSel.innerHTML = '<option value="">—</option>' + ops.map( o =>
        '<option value="' + o + '">' + this._afOpLabel( o ) + '</option>' ).join( '' );

      const vals = Utils.newEl( 'div', 'dt-af-vals' );
      const clearBtn = Utils.newEl( 'button', 'dt-af-clear hidden', {
        type: 'button', title: 'Clear this filter'
      } );
      clearBtn.innerHTML = '<svg viewBox="0 0 12 12" width="10" height="10" focusable="false">'
        + '<path d="M1.5 1.5l9 9M10.5 1.5l-9 9" fill="none" stroke="currentColor" '
        + 'stroke-width="1.6" stroke-linecap="round"/></svg>';
      row.append( meta, opSel, vals, clearBtn );

      const fillEnumSelect = ( sel, multi ) => {
        sel.multiple = !!multi;
        if ( multi ) sel.size = Math.min( 6, Math.max( 3, enumOpts.length || 3 ) );
        enumOpts.forEach( o => {
          const opt = document.createElement( 'option' );
          opt.value = o.value;
          opt.textContent = o.label;
          sel.appendChild( opt );
        } );
      };

      const syncClear = () => clearBtn.classList.toggle( 'hidden', !opSel.value );

      const syncVals = () => {
        const op = opSel.value;
        vals.innerHTML = '';
        if ( !op || op === 'EMPTY' || op === 'NOT_EMPTY' ) return;
        if ( op === 'BETWEEN' ) {
          const a = Utils.newEl( 'input', 'form-control dt-filter-sm dt-af-v' );
          const b = Utils.newEl( 'input', 'form-control dt-filter-sm dt-af-v2' );
          a.type = b.type = type === 'date' ? 'date' : ( type === 'number' ? 'number' : 'text' );
          a.placeholder = 'From'; b.placeholder = 'To';
          a.oninput = b.oninput = () => this._afSchedule();
          vals.append( a, b );
          return;
        }
        if ( op === 'IN' || op === 'NOT_IN' ) {
          if ( type === 'enum' && enumOpts.length ) {
            const sel = Utils.newEl( 'select', 'form-control dt-filter-sm dt-af-set' );
            fillEnumSelect( sel, true );
            sel.onchange = () => this._afSchedule();
            vals.appendChild( sel );
          } else {
            const ta = Utils.newEl( 'textarea', 'form-control dt-af-set' );
            ta.rows = 2; ta.placeholder = 'Values (comma / newline)';
            ta.oninput = () => this._afSchedule();
            vals.appendChild( ta );
          }
          return;
        }
        if ( type === 'boolean' ) {
          const sel = Utils.newEl( 'select', 'form-control dt-filter-sm dt-af-v' );
          sel.innerHTML = '<option value="1">Yes</option><option value="0">No</option>';
          sel.onchange = () => this._afSchedule();
          vals.appendChild( sel );
          return;
        }
        if ( type === 'enum' && enumOpts.length && ( op === 'EQ' || op === 'NE' ) ) {
          const sel = Utils.newEl( 'select', 'form-control dt-filter-sm dt-af-v' );
          fillEnumSelect( sel, false );
          sel.onchange = () => this._afSchedule();
          vals.appendChild( sel );
          return;
        }
        const inp = Utils.newEl( 'input', 'form-control dt-filter-sm dt-af-v' );
        inp.type = type === 'date' ? 'date' : ( type === 'number' ? 'number' : 'text' );
        if ( col.filter.unit ) inp.placeholder = col.filter.unit;
        inp.oninput = () => this._afSchedule();
        vals.appendChild( inp );
      };

      clearBtn.onclick = e => {
        e.stopPropagation();
        opSel.value = '';
        syncVals();
        syncClear();
        this._afSchedule();
      };
      opSel.onchange = () => { syncVals(); syncClear(); this._afSchedule(); };
      row._afSyncVals = () => { syncVals(); syncClear(); };
      return row;
    } // _afBuildRow


    _afSchedule() {
      clearTimeout( this._afTimer );
      this._afTimer = setTimeout( () => this._afApply(), 300 );
    } // _afSchedule


    _afCollect() {
      const next = {};
      if ( !this._afBody ) { this._af = next; return; }
      this._afBody.querySelectorAll( '.dt-af-row' ).forEach( row => {
        const field = row.dataset.field;
        const op = row.querySelector( '.dt-af-op' )?.value || '';
        if ( !op ) return;
        const spec = { op };
        if ( op === 'EMPTY' || op === 'NOT_EMPTY' ) { next[ field ] = spec; return; }
        if ( op === 'BETWEEN' ) {
          const v = row.querySelector( '.dt-af-v' )?.value ?? '';
          const v2 = row.querySelector( '.dt-af-v2' )?.value ?? '';
          if ( v === '' || v2 === '' ) return;
          spec.v = v; spec.v2 = v2; next[ field ] = spec; return;
        }
        if ( op === 'IN' || op === 'NOT_IN' ) {
          const sel = row.querySelector( 'select.dt-af-set' );
          const ta = row.querySelector( 'textarea.dt-af-set' );
          let set = '';
          if ( sel ) set = Array.from( sel.selectedOptions ).map( o => o.value ).join( ',' );
          else if ( ta ) set = ta.value;
          if ( !set || !String( set ).trim() ) return;
          spec.set = set; next[ field ] = spec; return;
        }
        const v = row.querySelector( '.dt-af-v' )?.value ?? '';
        if ( v === '' ) return;
        spec.v = v; next[ field ] = spec;
      } );
      this._af = next;
    } // _afCollect


    _afFillUi() {
      if ( !this._afBody ) return;
      this._afBody.querySelectorAll( '.dt-af-row' ).forEach( row => {
        const field = row.dataset.field;
        const spec = this._af[ field ] || {};
        const opSel = row.querySelector( '.dt-af-op' );
        if ( !opSel ) return;
        opSel.value = spec.op || '';
        if ( row._afSyncVals ) row._afSyncVals();
        if ( !spec.op ) return;
        if ( spec.op === 'BETWEEN' ) {
          const a = row.querySelector( '.dt-af-v' ), b = row.querySelector( '.dt-af-v2' );
          if ( a ) a.value = spec.v || '';
          if ( b ) b.value = spec.v2 || '';
          return;
        }
        if ( spec.op === 'IN' || spec.op === 'NOT_IN' ) {
          const sel = row.querySelector( 'select.dt-af-set' );
          const ta = row.querySelector( 'textarea.dt-af-set' );
          const parts = String( spec.set || '' ).split( /[\n,]+/ ).map( s => s.trim() ).filter( Boolean );
          if ( sel ) Array.from( sel.options ).forEach( o => { o.selected = parts.includes( o.value ); } );
          if ( ta ) ta.value = parts.join( ', ' );
          return;
        }
        const inp = row.querySelector( '.dt-af-v' );
        if ( inp ) inp.value = spec.v || '';
      } );
    } // _afFillUi


    _afApply() {
      this._afCollect();
      this.currentPage = 1;
      this._updateResetBtn();
      if ( this.isAjax ) this.load();
      else { this._applyFilter(); this._applySort(); this._render(); }
      if ( this.stateKey ) this._saveState();
    } // _afApply


    _rowMatchesAf( row ) {
      for ( const col of this.columns ) {
        if ( !col.filter || !col.field ) continue;
        const spec = this._af[ col.field ];
        if ( !spec || !spec.op ) continue;
        if ( !this._matchAf( row[ col.field ], col.filter.type || 'text', spec ) ) return false;
      }
      return true;
    } // _rowMatchesAf


    _matchAf( raw, type, spec ) {
      const op = spec.op;
      const empty = raw == null || raw === '';
      if ( op === 'EMPTY' ) return empty;
      if ( op === 'NOT_EMPTY' ) return !empty;

      if ( type === 'boolean' ) {
        const truthy = !( raw == null || raw === '' || raw === 0 || raw === '0' || raw === false );
        const want = spec.v === '1' || spec.v === 1 || spec.v === true || spec.v === 'true'
          || String( spec.v ).toLowerCase() === 'yes';
        if ( op === 'EQ' ) return truthy === want;
        if ( op === 'NE' ) return truthy !== want;
        return true;
      }

      const parseSet = s => String( s || '' ).split( /[\n,]+/ ).map( x => x.trim() ).filter( Boolean );

      if ( op === 'IN' || op === 'NOT_IN' ) {
        const set = parseSet( spec.set );
        if ( !set.length ) return true;
        const hit = set.some( v => String( raw ) === v || ( type === 'number' && Number( raw ) === Number( v ) ) );
        return op === 'IN' ? hit : !hit;
      }

      if ( type === 'number' ) {
        const n = Number( raw ), a = Number( spec.v ), b = Number( spec.v2 );
        if ( op === 'BETWEEN' ) return !isNaN( n ) && n >= a && n <= b;
        if ( isNaN( n ) || isNaN( a ) ) return false;
        if ( op === 'EQ' ) return n === a;
        if ( op === 'NE' ) return n !== a;
        if ( op === 'GT' ) return n > a;
        if ( op === 'GE' ) return n >= a;
        if ( op === 'LT' ) return n < a;
        if ( op === 'LE' ) return n <= a;
        return true;
      }

      if ( type === 'date' ) {
        const t = Date.parse( raw ), a = Date.parse( spec.v ), b = Date.parse( spec.v2 );
        if ( op === 'BETWEEN' ) return !isNaN( t ) && t >= a && t <= b;
        if ( isNaN( t ) || isNaN( a ) ) return false;
        if ( op === 'EQ' ) return String( raw ).slice( 0, 10 ) === String( spec.v ).slice( 0, 10 );
        if ( op === 'NE' ) return String( raw ).slice( 0, 10 ) !== String( spec.v ).slice( 0, 10 );
        if ( op === 'GT' ) return t > a;
        if ( op === 'GE' ) return t >= a;
        if ( op === 'LT' ) return t < a;
        if ( op === 'LE' ) return t <= a;
        return true;
      }

      const s = String( raw ?? '' ), v = String( spec.v ?? '' );
      if ( op === 'CONTAINS' ) return s.toLowerCase().includes( v.toLowerCase() );
      if ( op === 'STARTS' ) return s.toLowerCase().startsWith( v.toLowerCase() );
      if ( op === 'EQ' ) return s === v;
      if ( op === 'NE' ) return s !== v;
      return true;
    } // _matchAf


    _closeDtTrays( except ) {
      if ( except !== 'filter' && this._filterPanel ) {
        this._filterPanel.classList.remove( 'open' );
        this._filterBackdrop?.classList.remove( 'open' );
      }
      if ( except !== 'cols' && this._colConfigPanel ) {
        this._colConfigPanel.classList.remove( 'open' );
        this._colConfigBackdrop?.classList.remove( 'open' );
      }
    } // _closeDtTrays


    _trayCloseBtn() {
      const btn = F1.lib.Utils.newEl( 'button', 'dt-drawer-close', { type: 'button', title: 'Close' } );
      btn.innerHTML = '<span class="dt-drawer-close-lbl">Close</span>'
        + '<span class="dt-drawer-close-x" aria-hidden="true">'
        + '<svg viewBox="0 0 12 12" width="12" height="12" focusable="false">'
        + '<path d="M1.5 1.5l9 9M10.5 1.5l-9 9" fill="none" stroke="currentColor" '
        + 'stroke-width="1.6" stroke-linecap="round"/></svg></span>';
      return btn;
    } // _trayCloseBtn


    _initFilterPanel() {
      const Utils = F1.lib?.Utils;
      if ( !Utils ) return;
      const wrap = Utils.newEl( 'div', 'dt-filter-wrap' );
      const btn = Utils.newEl( 'button', 'btn btn-sm btn-outline dt-filter-btn', { type: 'button', title: 'Custom Filters' } );
      btn.innerHTML = '<i class="fa fa-filter"></i><span class="dt-filter-badge"></span>';
      const tray = !!this.advancedFilters;
      const panel = Utils.newEl( 'div', tray ? 'dt-filter-panel dt-tray' : 'dt-filter-panel' );
      const header = Utils.newEl( 'div', 'dt-drawer-header' );
      header.innerHTML = '<span class="dt-drawer-title">Filters</span>';
      const sep = Utils.newEl( 'span', 'dt-drawer-sep hidden' );
      sep.setAttribute( 'aria-hidden', 'true' );
      const clearBtn = Utils.newEl( 'button', 'dt-filter-clear hidden', { type: 'button' } );
      clearBtn.textContent = 'Reset';
      clearBtn.onclick = () => this._resetFilters();
      this._filterClearBtn = clearBtn;
      const closeBtn = this._trayCloseBtn();
      header.append( sep, clearBtn, closeBtn );
      panel.appendChild( header );
      const backdrop = Utils.newEl( 'div', tray ? 'dt-tray-backdrop dt-filter-backdrop' : 'dt-filter-backdrop' );
      this._filterPanelWrap = wrap;
      this._filterPanel = panel;
      this._filterBackdrop = backdrop;
      if ( tray ) {
        wrap.appendChild( btn );
        document.body.append( panel, backdrop );
        const close = () => { panel.classList.remove( 'open' ); backdrop.classList.remove( 'open' ); };
        const open = () => {
          this._closeDtTrays( 'filter' );
          panel.classList.add( 'open' ); backdrop.classList.add( 'open' );
        };
        btn.onclick = e => { e.stopPropagation(); panel.classList.contains( 'open' ) ? close() : open(); };
        closeBtn.onclick = close;
        backdrop.onclick = close;
        return;
      }
      wrap.append( btn, panel );
      document.body.appendChild( backdrop );
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
    } // _initFilterPanel


    _intrinsicColVisible( ci ) {
      const col = this.columns[ ci ];
      if ( !col ) return false;
      if ( this._responsiveHidden.has( ci ) ) return false;
      if ( this._compact && col.hideCompact ) return false;
      return true;
    } // _intrinsicColVisible


    _isColConfigCustom() {
      const n = this.columns.length;
      if ( this._colOrder.length !== n ) return true;
      for ( let i = 0; i < n; i++ ) if ( this._colOrder[ i ] !== i ) return true;
      for ( const [ ci, v ] of this._colVisibility ) {
        if ( v !== this._intrinsicColVisible( ci ) ) return true;
      }
      return false;
    } // _isColConfigCustom


    _configurableColIndices() {
      return this._colOrder.filter( ci => {
        const col = this.columns[ ci ];
        return col && col.configurable !== false && col.title;
      } );
    } // _configurableColIndices


    _colConfigVisible( ci ) {
      const uv = this._colVisibility.get( ci );
      return uv === false ? false : ( uv === true || !this._responsiveHidden.has( ci ) );
    } // _colConfigVisible


    _updateColConfigActions() {
      if ( !this._colConfigAllCb ) return;
      const indices = this._configurableColIndices();
      const vis = indices.filter( ci => this._colConfigVisible( ci ) );
      this._colConfigAllCb.checked = vis.length === indices.length && indices.length > 0;
      this._colConfigAllCb.indeterminate = vis.length > 0 && vis.length < indices.length;
      if ( this._colConfigResetBtn ) this._colConfigResetBtn.classList.toggle( 'hidden', !this._isColConfigCustom() );
    } // _updateColConfigActions


    _updateColConfigBtn() {
      if ( !this._colConfigBtn ) return;
      const custom = this._isColConfigCustom();
      this._colConfigBtn.title = custom ? 'Configure columns (custom layout)' : 'Configure columns';
      const badge = this._colConfigBtn.querySelector( '.dt-col-config-badge' );
      if ( badge ) badge.classList.toggle( 'active', custom );
      this._updateColConfigActions();
    } // _updateColConfigBtn


    _initColumnConfig() {
      const Utils = F1.lib?.Utils;
      if ( !Utils ) return;
      const wrap = Utils.newEl( 'div', 'dt-col-config-wrap' );
      const btn = Utils.newEl( 'button', 'btn btn-sm btn-outline', { type: 'button', title: 'Configure columns' } );
      btn.innerHTML = '<i class="fa fa-columns"></i><span class="dt-col-config-badge"></span>';
      this._colConfigBtn = btn;
      const panel = Utils.newEl( 'div', 'dt-col-config dt-tray' );
      const cfgHeader = Utils.newEl( 'div', 'dt-drawer-header' );
      cfgHeader.innerHTML = '<span class="dt-drawer-title">Columns</span>';
      const cfgClose = this._trayCloseBtn();
      cfgHeader.appendChild( cfgClose );
      const cfgActions = Utils.newEl( 'div', 'dt-col-config-actions' );
      const allLbl = document.createElement( 'label' );
      allLbl.className = 'dt-col-config-all';
      const allCb = document.createElement( 'input' );
      allCb.type = 'checkbox';
      allLbl.append( allCb, document.createTextNode( ' All' ) );
      this._colConfigAllCb = allCb;
      allCb.onchange = () => {
        this._configurableColIndices().forEach( ci => this._colVisibility.set( ci, allCb.checked ) );
        this._saveColConfig();
        this._renderColConfig();
        this._reRenderTable();
      };
      const resetBtn = Utils.newEl( 'button', 'dt-col-config-reset hidden', { type: 'button' } );
      resetBtn.textContent = 'Reset';
      resetBtn.onclick = () => this._resetColConfig();
      this._colConfigResetBtn = resetBtn;
      cfgActions.append( allLbl, resetBtn );
      const rowsSec = Utils.newEl( 'div', 'dt-col-config-rows' );
      rowsSec.innerHTML = '<div class="dt-col-config-sec-title">Rows</div>';
      const dens = Utils.newEl( 'div', 'dt-col-config-density' );
      dens.innerHTML = '<span class="dt-col-config-density-lbl">Density</span>';
      const densOpts = Utils.newEl( 'div', 'dt-col-config-density-opts' );
      const densName = 'dt-density-' + ( this.stateKey || 'default' ).replace( /[^\w-]/g, '_' );
      this._densityInputs = [];
      [ [ 'comfortable', 'Comfortable' ], [ 'compact', 'Compact' ] ].forEach( ( [ val, label ] ) => {
        const lbl = document.createElement( 'label' );
        const inp = document.createElement( 'input' );
        inp.type = 'radio';
        inp.name = densName;
        inp.value = val;
        inp.checked = this.density === val;
        inp.onchange = () => { if ( inp.checked ) this._setDensity( val ); };
        lbl.append( inp, document.createTextNode( ' ' + label ) );
        densOpts.appendChild( lbl );
        this._densityInputs.push( inp );
      } );
      dens.appendChild( densOpts );
      rowsSec.appendChild( dens );
      panel.append( cfgHeader, cfgActions );
      this._colConfigRows = rowsSec;
      const backdrop = Utils.newEl( 'div', 'dt-tray-backdrop dt-col-config-backdrop' );
      wrap.appendChild( btn );
      document.body.append( panel, backdrop );
      this._colConfigWrap = wrap;
      this._colConfigBackdrop = backdrop;
      const close = () => {
        panel.classList.remove( 'open' ); backdrop.classList.remove( 'open' );
      };
      const openCfg = () => {
        this._closeDtTrays( 'cols' );
        panel.classList.add( 'open' ); backdrop.classList.add( 'open' );
      };
      btn.onclick = e => { e.stopPropagation(); panel.classList.contains( 'open' ) ? close() : openCfg(); };
      cfgClose.onclick = close;
      backdrop.onclick = close;
      this._colConfigPanel = panel;
      this._renderColConfig();
      this._updateColConfigBtn();
    } // _initColumnConfig


    _renderColConfig() {
      const panel = this._colConfigPanel;
      if ( !panel ) return;
      const hdr = panel.querySelector( '.dt-drawer-header' );
      const actions = panel.querySelector( '.dt-col-config-actions' );
      const rowsSec = this._colConfigRows || panel.querySelector( '.dt-col-config-rows' );
      let body = panel.querySelector( '.dt-col-config-body' );
      panel.innerHTML = '';
      if ( hdr ) panel.appendChild( hdr );
      if ( actions ) panel.appendChild( actions );
      if ( !body ) body = document.createElement( 'div' );
      body.className = 'dt-col-config-body';
      body.innerHTML = '';
      panel.appendChild( body );
      if ( rowsSec ) panel.appendChild( rowsSec );
      this._colOrder.forEach( ( ci, pos ) => {
        const col = this.columns[ ci ];
        if ( !col || col.configurable === false || !col.title ) return;
        const isVis = this._colConfigVisible( ci );
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
        item.dataset.ci = String( ci );
        const grip = document.createElement( 'span' );
        grip.className = 'dt-col-config-grip';
        grip.textContent = '⋮⋮⋮⋮';
        grip.title = 'Drag to reorder';
        grip.setAttribute( 'role', 'button' );
        grip.setAttribute( 'aria-label', 'Drag to reorder' );
        grip.onpointerdown = e => this._colConfigGripDown( e, item, body );
        item.append( lbl, mv, grip );
        cb.onchange = () => {
          this._colVisibility.set( ci, cb.checked );
          this._saveColConfig();
          this._reRenderTable();
          this._updateColConfigActions();
        };
        body.appendChild( item );
      } );
      this._updateColConfigActions();
    } // _renderColConfig


    _resetColConfig() {
      this._colOrder = this.columns.map( ( _, i ) => i );
      this._colVisibility.clear();
      if ( this.stateKey ) {
        try { localStorage.removeItem( this.stateKey + '-cols' ); } catch { /* ignore */ }
      }
      this._renderColConfig();
      this._reRenderTable();
    } // _resetColConfig


    _moveCol( from, to ) {
      if ( to < 0 || to >= this._colOrder.length ) return;
      const item = this._colOrder.splice( from, 1 )[ 0 ];
      this._colOrder.splice( to, 0, item );
      this._saveColConfig();
      this._renderColConfig();
      this._reRenderTable();
    } // _moveCol


    /** Place fromCi before/after toCi (after = drop on lower half). */
    _moveColTo( fromCi, toCi, after ) {
      const from = this._colOrder.indexOf( fromCi );
      const to = this._colOrder.indexOf( toCi );
      if ( from < 0 || to < 0 ) return;
      let target = after ? to + 1 : to;
      if ( from < target ) target -= 1;
      if ( from === target ) return;
      const item = this._colOrder.splice( from, 1 )[ 0 ];
      this._colOrder.splice( target, 0, item );
      this._saveColConfig();
      this._renderColConfig();
      this._reRenderTable();
    } // _moveColTo


    /** Pointer-based column reorder (HTML5 DnD is unreliable in embedded browsers). */
    _colConfigGripDown( e, item, body ) {
      if ( e.button !== 0 ) return;
      e.preventDefault();
      e.stopPropagation();
      const fromCi = parseInt( item.dataset.ci, 10 );
      if ( Number.isNaN( fromCi ) ) return;
      const grip = e.currentTarget;
      const clearMarks = () => {
        body.querySelectorAll( '.drag-before, .drag-after' ).forEach( el => {
          el.classList.remove( 'drag-before', 'drag-after' );
        } );
      };
      let overEl = null;
      let after = false;
      let moved = false;
      item.classList.add( 'is-dragging' );
      body.classList.add( 'is-reordering' );
      try { grip.setPointerCapture?.( e.pointerId ); } catch { /* ignore */ }

      const onMove = ev => {
        if ( Math.abs( ev.clientY - e.clientY ) + Math.abs( ev.clientX - e.clientX ) > 3 ) moved = true;
        const el = document.elementFromPoint( ev.clientX, ev.clientY );
        const row = el?.closest?.( '.dt-col-config-item' ) || null;
        clearMarks();
        overEl = null;
        if ( !row || row === item || !body.contains( row ) ) return;
        const rect = row.getBoundingClientRect();
        after = ev.clientY > rect.top + rect.height / 2;
        row.classList.add( after ? 'drag-after' : 'drag-before' );
        overEl = row;
      };

      const onUp = () => {
        document.removeEventListener( 'pointermove', onMove, true );
        document.removeEventListener( 'pointerup', onUp, true );
        document.removeEventListener( 'pointercancel', onUp, true );
        item.classList.remove( 'is-dragging' );
        body.classList.remove( 'is-reordering' );
        clearMarks();
        try { grip.releasePointerCapture?.( e.pointerId ); } catch { /* ignore */ }
        if ( !moved || !overEl ) return;
        const toCi = parseInt( overEl.dataset.ci, 10 );
        if ( Number.isNaN( toCi ) || toCi === fromCi ) return;
        this._moveColTo( fromCi, toCi, after );
      };

      document.addEventListener( 'pointermove', onMove, true );
      document.addEventListener( 'pointerup', onUp, true );
      document.addEventListener( 'pointercancel', onUp, true );
    } // _colConfigGripDown


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


    _csvEscape( v ) {
      const s = v == null ? '' : String( v ).replace( /\r\n|\r|\n/g, ' ' );
      return '"' + s.replace( /"/g, '""' ) + '"';
    } // _csvEscape


    _doClientExport() {
      const cols = this._vis().map( v => v.col ).filter( c => c.field );
      if ( !cols.length ) return;
      const lines = [ cols.map( c => this._csvEscape( c.title || c.field ) ).join( ',' ) ];
      ( this.filteredData || [] ).forEach( row => {
        lines.push( cols.map( c => this._csvEscape( row[ c.field ] ) ).join( ',' ) );
      } );
      const blob = new Blob( [ lines.join( '\n' ) ], { type: 'text/csv;charset=utf-8' } );
      const url = URL.createObjectURL( blob );
      const a = document.createElement( 'a' );
      a.href = url;
      a.download = 'export_' + new Date().toISOString().slice( 0, 19 ).replace( /[:T]/g, '-' ) + '.csv';
      document.body.appendChild( a );
      a.click();
      a.remove();
      URL.revokeObjectURL( url );
    } // _doClientExport


    _doExport() {
      // AJAX lists must export via server (full filtered set). Client mode uses filteredData.
      if ( this.isAjax && !this.exportUrl ) {
        console.warn( 'DataTable: ajax list missing exportUrl — cannot export full filtered set' );
        return;
      }
      if ( !this.exportUrl ) { this._doClientExport(); return; }
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
        sortCol: this.sortColField || '', sortDir: ( this.sortDir || 'asc' ).toUpperCase(),
        sortStack: JSON.stringify( this.sortStack ),
        columns: this._vis().map( v => v.col.field ).filter( Boolean ).join( ',' ), ...this.ajaxParams() };
      Object.entries( params ).forEach( ( [ k, v ] ) => {
        if ( v == null ) return;
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
.dt-pagesize{padding:5px;border:1px solid #aaa;border-radius:3px;background:transparent}
.dt-search-wrap{position:relative;display:inline-flex;align-items:center;margin:0;flex-shrink:0}
.dt-search-icon{position:absolute;left:9px;display:inline-flex;align-items:center;justify-content:center;color:#888;pointer-events:none}
.dt-search-icon svg{display:block}
.dt-search{width:148px;height:30px;padding:5px 28px 5px 30px;border:1px solid #ccc;border-radius:6px;background:#fff;font-size:13px;line-height:1.2;color:#333}
.dt-search::placeholder{color:#999}
.dt-search:focus{outline:none;border-color:var(--primary-color,#337ab7);box-shadow:0 0 0 2px rgba(51,122,183,.15)}
.dt-search::-webkit-search-decoration,.dt-search::-webkit-search-cancel-button{appearance:none}
.dt-search-clear{position:absolute;right:3px;display:none;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:none;border-radius:50%;background:transparent;color:#888;font-size:16px;line-height:1;cursor:pointer}
.dt-search-clear:not(.hidden){display:inline-flex}
.dt-search-clear:hover{color:#333;background:#eee}
.dt-filter-sm{max-width:120px;padding:5px;border:1px solid #aaa;border-radius:3px;background:transparent;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.clients-filter-group{display:flex;align-items:center;gap:3px;flex-wrap:nowrap}
.dt-scroll{overflow:auto;position:relative}
.dt-empty .dt-scroll{overflow-x:hidden}
.dt-empty-msg{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.5rem;min-height:4rem;padding:0.75rem 1rem;color:#9aa3ad;font-size:13px;text-align:center;box-sizing:border-box}
.dt-empty-msg.hidden{display:none}
.dt-empty-ico{width:40px;height:40px;opacity:.55;flex-shrink:0}
.dt-empty-lbl{line-height:1.2}
.dt-table{width:100%;border-collapse:collapse}
.dt-table th,.dt-table td{padding:6px 6px;text-align:left;white-space:nowrap}
.dt-density-compact .dt-table thead th{padding:4px 6px}
.dt-density-compact .dt-table tbody td{padding:3px 6px}
.dt-density-compact .dt-table th.sortable{padding-right:1.65em}
.dt-density-compact .dt-table tfoot th{padding:5px 2px 5px 12px}
.dt-table th{font-size:13px}.dt-table td{font-size:12px;border-bottom:1px solid #ddd}
.dt-table thead{background:var(--heading-color,#2c3e50);color:#fff;position:sticky;top:0;z-index:2}
.dt-table tfoot tr{background:#f5f5f5;position:sticky;bottom:0;z-index:1;font-size:.9em;border:none;box-shadow:0 4px 0 #f5f5f5}
.dt-table tfoot th{padding:8px 2px 8px 12px;font-weight:600;border:none}
.dt-table th.sortable{cursor:pointer;user-select:none;position:relative;padding-right:1.65em}
.dt-table th.sortable:hover{background:rgba(255,255,255,.1)}
.dt-table th .th-label{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dt-table tbody tr:hover{background:#eee;cursor:pointer}
.dt-table .fa{width:1em;color:var(--primary-color);opacity:.67;vertical-align:middle;line-height:1}
.dt-bottom{display:flex;justify-content:space-between;align-items:center;}
.dt-bottom-left{display:flex;align-items:center;gap:28px;margin:5.5px}
.dt-info{display:inline-flex;flex-wrap:wrap;align-items:baseline;column-gap:.35em;row-gap:0;font-size:13px;color:#444;padding:0;max-width:100%}
.dt-info-label,.dt-info-vals{white-space:nowrap}
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
.sort-arrows{display:inline-flex;flex-direction:column;position:absolute;right:4px;top:50%;transform:translateY(-50%);margin-left:0;line-height:.7;font-size:9px;cursor:pointer}
.sort-arrows .up,.sort-arrows .dn{opacity:.2;transition:opacity .2s}
.dt-table th.sortable:hover .sort-arrows .up,.dt-table th.sortable:hover .sort-arrows .dn{opacity:.5}
.dt-table th.sort-asc .sort-arrows .up,.dt-table th.sort-desc .sort-arrows .dn{opacity:1;color:#fff}
.dt-table th.sort-asc:hover .sort-arrows .up,.dt-table th.sort-desc:hover .sort-arrows .dn{opacity:1;color:#fff}
.dt-table th.sort-asc:hover .sort-arrows .dn,.dt-table th.sort-desc:hover .sort-arrows .up{opacity:.35}
.sort-pri{position:absolute;right:1.15em;top:2px;font-size:9px;margin-left:0;opacity:.85;font-weight:700;line-height:1}
.dt-table .left{text-align:left}.dt-table .right{text-align:right}.dt-table .center{text-align:center}
.dt-table .nowrap{white-space:nowrap}.dt-table .name{min-width:140px}.dt-table .mute{color:#888;font-size:.85em}
.dt-table .trunc{width:1px;white-space:nowrap;overflow:hidden}.dt-table .trunc-text{display:inline-block;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle}
.dt-fixed .trunc{width:auto}.dt-fixed .trunc-text{max-width:calc(100% - 1.5em)}.dt-fixed td{overflow:hidden;text-overflow:ellipsis}
.dt-loading{position:absolute;inset:0;background:rgba(255,255,255,.7);display:flex;align-items:center;justify-content:center;z-index:10}
.dt-loading.hidden{display:none}
.dt-spinner{width:32px;height:32px;border:3px solid #ddd;border-top-color:var(--primary-color,#337ab7);border-radius:50%;animation:dt-spin .8s linear infinite}
@keyframes dt-spin{to{transform:rotate(360deg)}}
@media(max-width:640px){.dt-controls{flex-direction:column;align-items:stretch}.dt-left,.dt-right{justify-content:center}.dt-bottom{flex-direction:column;align-items:stretch;gap:2px}.dt-bottom-left{justify-content:space-between;width:100%;padding:0 0 4px}.dt-info{padding:0}.dt-pagesize-bottom{padding:0;margin-right:8px}.dt-pagination{justify-content:center;flex-wrap:nowrap;gap:0}.dt-pagination .dt-btn{padding:8px 10px;min-width:34px;font-size:13px;text-align:center}.dt-pagination .dt-dots{padding:8px 2px}.dt-pg-full{display:none}.dt-pg-short{display:inline;font-size:18px;font-weight:700;line-height:1}}
.dt-col-config-wrap{position:relative;display:inline-block;overflow:visible}
.dt-col-config-wrap>button{position:relative;overflow:visible}
.dt-col-config-badge{display:none}
.dt-col-config-badge.active{display:block;position:absolute;top:-5px;right:-5px;width:9px;height:9px;border-radius:50%;background:#dc3545}
.dt-tray{position:fixed;top:0;right:0;bottom:0;z-index:1001;width:min(420px,100vw);max-width:100vw;background:#fff;box-shadow:-8px 0 28px rgba(0,0,0,.16);display:flex;flex-direction:column;transform:translateX(100%);visibility:hidden;pointer-events:none;transition:transform .22s ease,visibility 0s linear .22s}
.dt-tray.open{transform:translateX(0);visibility:visible;pointer-events:auto;transition:transform .22s ease,visibility 0s}
.dt-tray-backdrop{display:block;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.28);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility 0s linear .22s}
.dt-tray-backdrop.open{opacity:1;visibility:visible;pointer-events:auto;transition:opacity .22s ease,visibility 0s}
.dt-col-config.dt-tray{padding:0;border:none;border-radius:0;min-width:0;max-height:none;overflow:hidden}
.dt-col-config .dt-drawer-header{display:flex;align-items:center;gap:8px;padding:14px 16px 10px;border-bottom:1px solid #eee;flex-shrink:0}
.dt-col-config .dt-drawer-title{font-size:15px;font-weight:600;color:#222;margin-right:auto}
.dt-col-config-actions{display:flex;align-items:center;justify-content:space-between;padding:6px 14px;border-bottom:1px solid #eee;background:#fff;flex-shrink:0}
.dt-col-config-body{flex:1;overflow:auto;padding:2px 0 8px;-webkit-overflow-scrolling:touch;min-height:0}
.dt-col-config-rows{flex-shrink:0;border-top:1px solid #eee;background:#fff;padding:10px 14px 12px}
.dt-col-config-sec-title{font-size:12px;font-weight:600;color:#555;margin:0 0 8px}
.dt-col-config-density{display:flex;align-items:center;justify-content:space-between;gap:8px}
.dt-col-config-density-lbl{font-size:12px;color:#444}
.dt-col-config-density-opts{display:flex;align-items:center;gap:12px}
.dt-col-config-density-opts label{display:inline-flex;align-items:center;gap:4px;margin:0;font-size:12px;color:#444;cursor:pointer;white-space:nowrap}
.dt-col-config-all{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;font-weight:600;color:#555;margin:0 auto 0 0}
.dt-col-config-reset{border:none;background:transparent;font-size:12px;color:var(--primary-color,#337ab7);cursor:pointer;padding:2px 0}
.dt-col-config-reset.hidden{display:none}
.dt-col-config-item{display:flex;align-items:center;gap:2px;padding:3px 10px 3px 14px;position:relative;min-height:28px}
.dt-col-config-item.is-dragging{opacity:.4}
.dt-col-config-item.drag-before::before,.dt-col-config-item.drag-after::after{content:'';position:absolute;left:12px;right:12px;height:2px;background:var(--primary-color,#337ab7);pointer-events:none;z-index:1}
.dt-col-config-item.drag-before::before{top:0}
.dt-col-config-item.drag-after::after{bottom:0}
.dt-col-config-body.is-reordering{touch-action:none;user-select:none;-webkit-user-select:none}
.dt-col-config-body.is-reordering .dt-col-config-item{cursor:grabbing}
.dt-col-config-item label{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;line-height:1.2;white-space:nowrap;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}
.dt-col-config-move{display:flex;gap:0;flex-shrink:0}
.dt-col-config-move button{border:none;background:transparent;cursor:pointer;padding:2px 4px;font-size:10px;color:#888;line-height:1}
.dt-col-config-move button:hover{color:#333}
.dt-col-config-grip{display:inline-flex;flex-direction:row;flex-wrap:nowrap;align-items:center;justify-content:center;flex-shrink:0;width:44px;height:22px;margin:0;padding:0 4px;border:none;border-radius:3px;background:transparent;cursor:grab;color:#aaa;font-size:14px;line-height:1;letter-spacing:-2px;white-space:nowrap;user-select:none;-webkit-user-select:none;touch-action:none;box-sizing:border-box}
.dt-col-config-grip:hover{color:#555;background:#f0f0f0}
.dt-col-config-grip:active{cursor:grabbing}
.dt-tray .dt-drawer-close{display:inline-flex;align-items:center;gap:6px;margin-left:0;padding:0;border:none;background:transparent;color:#666;font-size:12px;font-weight:600;line-height:1;cursor:pointer}
.dt-tray .dt-drawer-close-x{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #ccc;border-radius:50%;flex-shrink:0}
.dt-tray .dt-drawer-close-x svg{display:block}
.dt-tray .dt-drawer-close-lbl{line-height:1}
.dt-tray .dt-drawer-close:hover{color:#333}
.dt-tray .dt-drawer-close:hover .dt-drawer-close-x{border-color:#999}
.dt-export-btn{order:99;margin-left:12px}
.dt-drawer-header{display:none}
.dt-filter-clear{display:none}
.dt-filter-wrap{position:relative;display:inline-flex;align-items:center;gap:4px;overflow:visible}
.dt-filter-btn{display:none;position:relative;overflow:visible}
.dt-filter-panel:not(.dt-tray){display:contents}
.dt-filter-badge{display:none}
.dt-filter-backdrop:not(.dt-tray-backdrop){display:none}
.dt-filter-panel label[data-label]::before{display:none}
.dt-filter-wrap.dt-af .dt-filter-btn{display:inline-flex}
.dt-filter-wrap.dt-af .dt-filter-badge.active{display:inline-flex;align-items:center;justify-content:center;position:absolute;top:-7px;right:-7px;min-width:14px;height:14px;padding:0 2px;border-radius:6px;background:#dc3545;color:#fff;font-size:10px;font-weight:700;line-height:1}
.dt-af-summary{display:flex;flex-wrap:wrap;align-items:center;gap:6px;width:100%;padding:0;box-sizing:border-box}
.dt-af-summary.hidden{display:none}
.dt-af-chip{display:inline-flex;align-items:center;gap:4px;max-width:100%;padding:2px 4px 2px 8px;border:1px solid #c5d4e0;border-radius:12px;background:#eef5fa;color:#234;font-size:12px;line-height:1.3}
.dt-af-chip-lbl{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dt-af-chip-x{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;padding:0;border:none;border-radius:50%;background:transparent;color:#678;font-size:14px;line-height:1;cursor:pointer;flex-shrink:0}
.dt-af-chip-x:hover{color:#111;background:rgba(0,0,0,.06)}
.dt-filter-panel.dt-tray{width:min(480px,100vw);padding:0;overflow:hidden}
.dt-filter-panel.dt-tray .dt-drawer-header{display:flex;align-items:center;gap:12px;padding:14px 16px 10px;border-bottom:1px solid #eee;flex-shrink:0;margin:0}
.dt-filter-panel.dt-tray .dt-drawer-title{display:inline-flex;align-items:center;height:28px;font-size:15px;font-weight:600;line-height:1;color:#222}
.dt-filter-panel.dt-tray .dt-drawer-sep{display:block;width:1px;height:14px;background:#ccc;border-radius:0;flex-shrink:0}
.dt-filter-panel.dt-tray .dt-drawer-sep.hidden{display:none}
.dt-filter-panel.dt-tray .dt-filter-clear{display:inline-flex;align-items:center;height:28px;padding:0;border:none;background:transparent;font-size:12px;font-weight:600;line-height:1;color:var(--primary-color,#337ab7);cursor:pointer}
.dt-filter-panel.dt-tray .dt-filter-clear.hidden{display:none}
.dt-filter-panel.dt-tray .dt-drawer-close{margin-left:auto}
.dt-af-body{flex:1;overflow:auto;padding:2px 12px 12px;-webkit-overflow-scrolling:touch}
.dt-af-row{display:grid;grid-template-columns:minmax(108px,1.05fr) 88px minmax(110px,1.6fr) 24px;column-gap:12px;row-gap:6px;align-items:center;padding:5px 0;border-bottom:1px solid #f2f2f2}
.dt-af-row:last-child{border-bottom:none}
.dt-af-meta{min-width:0;display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:4px 6px}
.dt-af-label{font-size:12px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:default;max-width:100%}
.dt-af-type{display:inline-block;font-size:9px;font-weight:600;letter-spacing:.02em;text-transform:uppercase;color:#777;background:#f0f2f5;border-radius:3px;padding:0 4px;line-height:1.5;flex-shrink:0}
.dt-af-op,.dt-af-vals .form-control{font-size:12px;height:28px;padding:1px 5px}
.dt-af-vals{display:flex;gap:6px;align-items:center;min-width:0}
.dt-af-vals .form-control,.dt-af-vals textarea{width:100%;min-width:0}
.dt-af-vals textarea,.dt-af-vals select[multiple]{font-size:12px;padding:4px 6px;min-height:72px;height:auto}
.dt-af-vals select[multiple]{padding:2px}
.dt-af-clear{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:1px solid #ccc;border-radius:50%;background:transparent;color:#888;cursor:pointer;flex-shrink:0}
.dt-af-clear:hover{color:#333;border-color:#999}
.dt-af-clear.hidden{display:none}
.dt-af-clear svg{display:block}
@media(max-width:640px){
.dt-col-config-wrap{order:-1}
.dt-filter-btn{display:inline-flex}
.dt-tray{width:min(100vw,480px)}
.dt-filter-panel:not(.dt-tray){display:none;position:fixed;bottom:0;left:0;right:0;z-index:1001;background:#fff;border-radius:12px 12px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.15);padding:16px;grid-template-columns:auto 1fr;gap:10px 12px;align-items:center}
.dt-filter-panel:not(.dt-tray).open{display:grid}
.dt-filter-panel:not(.dt-tray) .dt-drawer-header{display:flex;justify-content:space-between;align-items:center;grid-column:1/-1;padding:0 0 4px}
.dt-filter-panel:not(.dt-tray) .clients-filter-group{display:contents}
.dt-filter-panel:not(.dt-tray) label{display:contents}
.dt-filter-panel:not(.dt-tray) label[data-label]::before{display:block;content:attr(data-label);font-size:13px;font-weight:600;color:#555;white-space:nowrap}
.dt-filter-panel:not(.dt-tray) select{width:100%;padding:8px;font-size:14px;border:1px solid #ccc;border-radius:4px;background:#fff}
.dt-filter-badge.active{display:inline-flex;align-items:center;justify-content:center;position:absolute;top:-7px;right:-7px;min-width:14px;height:14px;padding:0 2px;border-radius:6px;background:#dc3545;color:#fff;font-size:10px;font-weight:700;line-height:1}
.dt-af-row{grid-template-columns:1fr;gap:6px}
.dt-af-op{max-width:100%}
}
`;
    document.head.appendChild( s );
  }

  F1.lib = F1.lib || {};
  F1.lib.DataTable = DataTable;

})(window.F1 = window.F1 || {});
