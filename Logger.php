<?php namespace F1;

/**
 * F1 - Logger Class - 23 Jun 2022
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 2.3 - FT - 27 Dec 2024
 *   - Add Logger::todayString()
 *   - Add Logger::nowString()
 * 
 * @version 2.4 - FT - 16 Jun 2025
 *   - Add Logger::write()
 */

class Logger {

  public $path;
  public $file;
  public $fileName;
  public $disabled;
  public $logLevel;


  public function __construct( $logPath = __DIR__, $fileName = null, $logLevel = 0 ) {
    $this->setPath( $logPath );
    $this->fileName = $fileName;
    $this->file = $this->path . DIRECTORY_SEPARATOR . ( $fileName ?: 'log-' . $this->todayString() . '.txt' );
    $this->disabled = ! $logLevel;
    $this->logLevel = $logLevel;
  }


  public function write( string $message ) {
    file_put_contents( $this->file, $message, FILE_APPEND | LOCK_EX );
  }


  public function formatLog( $message, $type = null ) {
    $typePrefix = $type ? '[' . str_pad( ucfirst( $type ), 5 ) . "]:\t" : '';
    return $typePrefix . $this->nowString() . ' - ' . print_r( $message, true) . PHP_EOL;
  }


  public function nl( $count = 1 ) {
    if ( $this->disabled ) return;
    $this->write( str_repeat( PHP_EOL, $count ) );
  }


  public function log( $message, $type = null ) {
    if ( $this->disabled ) return;
    $formattedMessage = $this->formatLog( $message, $type );
    $this->write( $formattedMessage );
  }


  public function setPath( $path, $mkdir = true ) {
    if ( $mkdir and ( ! is_dir( $path ) ) ) { mkdir( $path, 0777, true ); }
    $this->path = $path;
  }


  public function setFileName( $fileName ) {
    $this->fileName = $fileName;
    $this->file = $this->path . DIRECTORY_SEPARATOR . $fileName;
  }


  public function todayString() { return date( 'Y-m-d' ); }
  public function nowString() { return date( 'Y-m-d H:i:s' ); }
  public function getFileSize() { return file_exists( $this->file ) ? filesize( $this->file ) : 0; }
  public function getFileName() { return $this->fileName; }
  public function getLevel() { return $this->logLevel; }
  public function getFile() { return $this->file; }
  public function getPath() { return $this->path; }

}