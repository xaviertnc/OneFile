/* global F1 */

/* tooltip.js */

(function(F1) {

  /**
   * F1 Tooltip - 11 Jan 2026
   *
   * Lightweight tooltip library using Popover API with title fallback.
   *
   * @author C. Moller <xavier.tnc@gmail.com>
   *
   * @version 1.2 - FIX - 11 Jan 2026
   *   - Fix positioning for Popover API, fix arrow CSS
   *
   * @version 1.1 - UPD - 11 Jan 2026
   *   - Convert to F1 library pattern
   *
   * @version 1.0 - INIT - 11 Jan 2026
   *   - Initial version
   */

  const supportsPopover = 'showPopover' in HTMLElement.prototype;
  let tooltipId = 0;
  const tooltips = new Map();


  function createTooltip( element, content, isHTML ) {
    const id = 'tooltip-' + ( ++tooltipId );
    const tip = document.createElement( 'div' );
    tip.id = id;
    tip.className = 'f1-tooltip';
    tip.setAttribute( 'role', 'tooltip' );

    // Arrow element (more reliable than ::before with Popover API)
    const arrow = document.createElement( 'div' );
    arrow.className = 'f1-tooltip-arrow';
    tip.appendChild( arrow );

    // Content
    const body = document.createElement( 'div' );
    body.className = 'f1-tooltip-body';
    if ( isHTML ) body.innerHTML = content;
    else body.textContent = content;
    tip.appendChild( body );

    if ( supportsPopover ) tip.popover = 'manual';
    else tip.style.display = 'none';

    document.body.appendChild( tip );
    return tip;
  } // createTooltip


  function positionTooltip( element, tip ) {
    const rect = element.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();
    const scrollY = window.scrollY;
    const scrollX = window.scrollX;
    const elementCenterX = rect.left + scrollX + ( rect.width / 2 );

    let top = rect.bottom + scrollY + 4; // ~0.34em gap below element
    let left = elementCenterX - ( tipRect.width / 2 );

    // Keep within viewport
    if ( left < scrollX + 8 ) left = scrollX + 8;
    if ( left + tipRect.width > scrollX + window.innerWidth - 8 ) {
      left = scrollX + window.innerWidth - tipRect.width - 8;
    }

    // Position arrow to point at element center
    const arrow = tip.querySelector( '.f1-tooltip-arrow' );
    if ( arrow ) {
      const arrowX = elementCenterX - left - 6; // 6 = half arrow width
      arrow.style.left = Math.max( 8, Math.min( arrowX, tipRect.width - 20 ) ) + 'px';
      arrow.style.marginLeft = '0';
    }

    tip.style.top = top + 'px';
    tip.style.left = left + 'px';
  } // positionTooltip


  function showTooltip( element, tip ) {
    if ( supportsPopover ) {
      tip.showPopover();
      positionTooltip( element, tip );
    } else {
      tip.style.display = 'block';
      positionTooltip( element, tip );
    }
  } // showTooltip


  function hideTooltip( tip ) {
    if ( supportsPopover ) tip.hidePopover();
    else tip.style.display = 'none';
  } // hideTooltip


  function initElement( element ) {
    if ( tooltips.has( element ) ) return; // Already initialized

    const title = element.getAttribute( 'title' );
    const dataContent = element.getAttribute( 'data-tippy-content' ) || element.getAttribute( 'data-tooltip' );
    const content = dataContent || title;
    if ( !content ) return;

    const isHTML = element.hasAttribute( 'data-tooltip-html' ) || element.hasAttribute( 'data-tippy-content' );
    const tip = createTooltip( element, content, isHTML );

    if ( title && !dataContent ) {
      element.removeAttribute( 'title' );
      element.setAttribute( 'data-original-title', title );
    }

    let showTimer, hideTimer;

    element.addEventListener( 'mouseenter', () => {
      clearTimeout( hideTimer );
      showTimer = setTimeout( () => showTooltip( element, tip ), 100 );
    } );

    element.addEventListener( 'mouseleave', () => {
      clearTimeout( showTimer );
      hideTimer = setTimeout( () => hideTooltip( tip ), 50 );
    } );

    element.addEventListener( 'focus', () => {
      clearTimeout( hideTimer );
      showTimer = setTimeout( () => showTooltip( element, tip ), 100 );
    } );

    element.addEventListener( 'blur', () => {
      clearTimeout( showTimer );
      hideTooltip( tip );
    } );

    tooltips.set( element, tip );
  } // initElement


  function init() {
    // Auto-init elements with title or data-tooltip attributes
    document.querySelectorAll( '[title], [data-tooltip], [data-tippy-content]' ).forEach( initElement );

    // Watch for dynamically added elements
    if ( window.MutationObserver ) {
      const observer = new MutationObserver( mutations => {
        mutations.forEach( mutation => {
          mutation.addedNodes.forEach( node => {
            if ( node.nodeType === 1 ) {
              if ( node.hasAttribute( 'title' ) || node.hasAttribute( 'data-tooltip' ) || node.hasAttribute( 'data-tippy-content' ) ) {
                initElement( node );
              }
              node.querySelectorAll?.( '[title], [data-tooltip], [data-tippy-content]' ).forEach( initElement );
            }
          } );
        } );
      } );
      observer.observe( document.body, { childList: true, subtree: true } );
    }
  } // init


  // Inject styles
  if ( !document.getElementById( 'f1-tooltip-styles' ) ) {
    const style = document.createElement( 'style' );
    style.id = 'f1-tooltip-styles';
    style.textContent = `
.f1-tooltip {
  position: absolute;
  z-index: 10000;
  pointer-events: none;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
}
.f1-tooltip-arrow {
  position: absolute;
  top: 0;
  width: 0;
  height: 0;
  border-left: 6px solid transparent;
  border-right: 6px solid transparent;
  border-bottom: 6px solid #333;
}
.f1-tooltip-body {
  margin-top: 6px; /* Match arrow height */
  background: #333;
  color: white;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 13px;
  line-height: 1.4;
  max-width: 250px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
`;
    document.head.appendChild( style );
  }


  // Initialize on DOM ready
  if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', init );
  } else {
    init();
  }

  F1.lib = F1.lib || {};
  F1.lib.Tooltip = { init, initElement };

})(window.F1 = window.F1 || {});
