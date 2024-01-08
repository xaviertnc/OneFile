<?php namespace F1;

use InvalidArgumentException;


/** 
 * F1 - Config Class - 20 Jun 2023
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 1.2 - DEV - 08 Jan 2024
 *   - Make Config::get() more versatile.
 *      - Throw InvalidArgumentException if group not found.
 *      - Allow for default value if the key is not found.
 */

class Config {

  private array $settings;


  /**
   * Scan $configDir for config group files "[group].php"
   * and store the arrays returned in $this->settings[group];
   */
  public function __construct( string $configDir ) {
    $this->settings = [];
    if ( ! is_dir( $configDir ) or ! is_readable( $configDir ) ) {
      $message = 'Directory does not exist or is not readable: ' . $configDir;
      throw new InvalidArgumentException( $message ); }
    $files = scandir( $configDir );
    foreach ( $files as $file ) {
      if ( $file[0] === '.' ) continue;
      $group = str_replace( '.php', '', $file );
      $this->settings[$group] = require $configDir . DIRECTORY_SEPARATOR . $file;
    }
  }


  public function get( string $group = null, string $key = null,  $default = null ) {
    if ( $group === null )
      return $this->settings;
    if ( $key === null )
      throw new InvalidArgumentException( 'Config::get, Key not specified: ' . $group );
    if ( ! array_key_exists( $group, $this->settings ) )
      throw new InvalidArgumentException( 'Config::get, Group not found: ' . $group );
    if ( ! array_key_exists( $key, $this->settings[$group] ) )
      return $default;
    return $this->settings[$group];
  }


  public function set( string $group, string $key, $value ) {
    if ( ! array_key_exists( $group, $this->settings ) )
      throw new InvalidArgumentException( 'Config::set, Group not found: ' . $group );
    $this->settings[$group][$key] = $value;
  }

}
