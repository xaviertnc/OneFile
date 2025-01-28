<?php namespace F1;

use Exception;

/**
 * F1 - Session Class - 03 July 2022
 * 
 * A tiny abstraction layer to gell PHP's flakey session management features.
 * 
 *  - Allows only using a sub-set of the session data (i.e. a session scope)
 *    to avoid clashing with other code modules using the same session. 
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 3.2.0 - FT - 20 Jan 2025
 *   - Add a set() method to "set" the entire session's data.
 * 
 * @version 3.2.1 - FIX - 26 Jan 2025
 *   - Fix issue with FLASH data not being cleared after being read.
 *
 */

class Session {

  private $id;
  private $scope;
  private $sessionName;
  private $flashed = [];


  const FLASH = '__FLASH__';


  public function __construct( $scope = null, $config = [] ) {
    $this->sessionName = $config[ 'name' ] ?? ini_get( 'session.name' );
    $this->start()->setScope( $scope );
  }


  public function setScope( $scope ) {
    $this->setupFlash( $scope );
    $this->scope = $scope;
    return $this;
  }


  public function start() {
    if ( session_status() == PHP_SESSION_NONE ) {
      session_name( $this->sessionName );
      session_start();
      $this->id = session_id();
    }
    else {
      if ( empty( session_id() ) )
        throw new Exception( 'Failed to start session.' );
    }
    return $this;
  }


  public function setupFlash( $scope = null ) {
    if ( $scope ) {
      $this->flashed = $_SESSION[ $scope ][ self::FLASH ] ?? [];
      $_SESSION[ $scope ][ self::FLASH ] = [];
    } else {
      $this->flashed = $_SESSION[ self::FLASH ] ?? [];
      $_SESSION[ self::FLASH ] = [];
    }
  }


  public function set( $value ) {
    if ( $this->scope ) $_SESSION[ $this->scope ] = $value;
    else $_SESSION = $value;
  }


  public function put( $key, $value ) {
    if ( $this->scope ) $_SESSION[ $this->scope ][ $key ] = $value;
    else $_SESSION[ $key ] = $value;
  }


  public function get( $key = null, $default = null ) {
    if ( $key === null ) return $_SESSION ?? [];
    $data = ( $this->scope && isset( $_SESSION[ $this->scope ] ) ) ? $_SESSION[ $this->scope ] : $_SESSION;
    return $data[ $key ] ?? $default;
  }


  public function forget( $key ) {
    if ( $this->scope && isset( $_SESSION[ $this->scope ][ $key ] ) ) unset( $_SESSION[ $this->scope ][ $key ] );
    else if ( isset( $_SESSION[ $key ] ) ) unset( $_SESSION[ $key ] );
  }


  public function clear() {
    if ( $this->scope && isset( $_SESSION[ $this->scope ] ) ) $_SESSION[ $this->scope ] = [];
    else $_SESSION = [];
  }

  public function flash( $key, $value ) {
    // debug_log( $key, 'Flash Key: ', 3 );
    // debug_log( $value, 'Flash Value: ', 3 );
    if ( $this->scope ) $_SESSION[ $this->scope ][ self::FLASH ][ $key ] = $value;
    else $_SESSION[ self::FLASH ][ $key ] = $value;
  }

  public function getFlash( $key, $default = null ) {
    // debug_log( $this->flashed, 'Flashed Data: ', 3 );
    // debug_log( $key, 'Get Flash Key: ', 3 );
    return $this->flashed[ $key ] ?? $default;
  }

  public function destroy() { if ( session_status() != PHP_SESSION_NONE ) session_destroy(); }
  public function close() { session_write_close(); }
  
  public function getId() { return $this->id; }

}