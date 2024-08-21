/**
 * F1JS Modal - Simplify Modal Popups - 15 July 2022
 * 
 * @author C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 4.1.0 - FT - 03 Jan 2023
 * 
 */


function extend( obj, props ) { 
  for ( let key in props || {} ) { obj[ key ] = props[ key ]; } }


export default class Modal {

  constructor( options )
  {
    const defaults = {
      selector: '.modal',
      formController: null,
      resetFormOnShow: 0,
      clearFormOnShow: 0,
      focusFormOnShow: 0,
      draggable: 1,
    };
    extend ( this, defaults );
    extend( this, options );
    this.elm = options.elm || document.querySelector( this.selector );
    this.elm.addEventListener( 'click', this.onClick );
    this.elm.CONTROL = this;
    this.ENTITY = null;
    this.elClose = this.elm.querySelector( '.modal-close' );
    if ( this.elClose ) this.elClose.addEventListener( 'click', this.onCloseClick );
    this.elModalInner = this.elm.querySelector( '.modal-inner' );
    this.elHeader = this.elm.querySelector( 'header' );
    this.elTitle = this.elm.querySelector( '.modal-title' );
    this.elBody = this.elm.querySelector( '.modal-body' );
    this.elFooter = this.elm.querySelector( 'footer' );
    if ( this.draggable ) this.makeDraggable();
    // Do more custom stuff with options.afterInit()...
    if ( this.afterInit ) return this.afterInit();
  }

  onClick( event ) { 
    if ( event.target.classList.contains( 'modal' ) ) event.target.CONTROL.close();
  }

  onCloseClick( event )
  {
    if ( event ) event.preventDefault();  
    const elModal = event.target.closest('.modal');
    elModal.CONTROL.close();
  }

  close()
  {
    console.log( 'Modal::close()' );
    document.documentElement.classList.remove( 'has-modal' );
    this.elm.classList.remove( 'open' );
    this.ENTITY = null;
  }

  show( options )
  {
    console.log( 'Modal::show(), options:', options, this );
    options = options || {};
    if ( options.event ) event.preventDefault();
    if ( options.header ) this.elHeader.innerHTML = options.header;
    else if ( options.title ) this.elTitle.innerHTML = this.title = options.title;
    if ( options.body ) this.elBody.innerHTML = options.body;
    if ( options.footer ) this.elFooter.innerHTML = options.footer;
    if ( options.afterShow ) options.afterShow( this );
    document.documentElement.classList.add( 'has-modal' );
    this.elm.classList.add( 'open' );
    if ( this.formController )
    {
      if ( options.data ) this.formController.init( options.data );  
      else if ( this.clearFormOnShow ) this.formController.clear();
        else if ( this.resetFormOnShow ) this.formController.reset();

      if ( this.focusFormOnShow ) this.formController.focus();
    }
    this.ENTITY = options.data;
  }

  makeDraggable()
  {
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    const elModalInner = this.elModalInner;

    function elementDrag( e ) {
      e = e || window.event;
      e.preventDefault();
      console.log( 'elementDrag' );
      // calculate the new cursor position:
      pos1 = pos3 - e.clientX;
      pos2 = pos4 - e.clientY;
      pos3 = e.clientX;
      pos4 = e.clientY;
      // set the element's new position:
      elModalInner.style.top = (elModalInner.offsetTop - pos2) + "px";
      elModalInner.style.left = (elModalInner.offsetLeft - pos1) + "px";
    }

    function dragMouseDown( e ) {
      console.log( 'dragMouseDown' );
      e = e || window.event;
      e.preventDefault();
      // get the mouse cursor position at startup:
      pos3 = e.clientX;
      pos4 = e.clientY;
      document.addEventListener( 'mouseup', closeDragElement );
      document.addEventListener( 'mousemove', elementDrag );
    }

    function closeDragElement() {
      console.log( 'closeDragElement' );
      // stop moving when mouse button is released:
      document.removeEventListener( 'mouseup', closeDragElement );
      document.removeEventListener( 'mousemove', elementDrag );
    }

    this.elHeader.addEventListener( 'mousedown', dragMouseDown );
  }

}