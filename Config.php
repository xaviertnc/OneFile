<?php namespace F1;

use InvalidArgumentException;


/** 
 * F1 - Config Class - 20 Jun 2023
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 2.0 - FT - 08 Jan 2024
 *   - Major functionality change
 *   - Add support to "lazy load" config groups.
 *   - Add Config::loadGroup() method.
 */

class Config {

  private $settings = [];
  private $configDir;


  public function __construct( $configDir ) {
    if ( ! is_dir( $configDir ) || ! is_readable( $configDir ) )
      throw new InvalidArgumentException( 'Directory does not exist' .
        ' or is not readable: ' . $configDir );
    $this->configDir = $configDir;
  }


  private function loadGroup( $group ) {
    $filePath = $this->configDir . DIRECTORY_SEPARATOR . $group . '.php';
    if ( ! file_exists( $filePath ) )
      throw new InvalidArgumentException( 'Config file not found' .
      ' for group: ' . $group );
    $this->settings[$group] = require $filePath;
  }


  public function get( $group = null, $key = null, $default = null ) {
    if ( $group === null ) return $this->settings;
    if ( ! array_key_exists( $group, $this->settings ) ) $this->loadGroup( $group );
    if ( $key === null ) return $this->settings[$group];
    return $this->settings[$group][$key] ?? $default;
  }


  public function set( string $group, string $key, $value ) {
    if ( ! array_key_exists( $group, $this->settings ) )
      throw new InvalidArgumentException( 'Config::set, Group' .
        ' not found: ' . $group );
    $this->settings[$group][$key] = $value;
  }

}
