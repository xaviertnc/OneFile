<?php namespace F1;

/**
 * F1 - Logger Class - 23 Jun 2022
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 2.1 - DEV - 09 Dec 2023
 *   - Reorganize code.
 */

class Logger {

  protected $path;
  protected $file;
  protected $fileName;
  protected $disabled;
  protected $logLevel;


  public function __construct( $logPath = __DIR__, $fileName = null, $logLevel = 0 ) {
    $this->path = $logPath;
    $this->fileName = $fileName;
    if ( ! is_dir( $this->path ) ) mkdir( $this->path, 0777, true );    
    $this->file = $this->path . DIRECTORY_SEPARATOR . ( $fileName ?: 'log-' . date( 'Y-m-d' ) . '.txt' );
    $this->disabled = ! $logLevel;
    $this->logLevel = $logLevel;
  }


  public function formatLog( $message, $type = null ) {
    $typePrefix = $type ? '[' . str_pad( ucfirst( $type ), 5 ) . "]:\t" : '';
    return $typePrefix . date( 'Y-m-d H:i:s' ) . ' - ' . print_r( $message, true) . PHP_EOL;
  }


  public function nl( $count = 1 ) {
    if ( $this->disabled ) return;
    file_put_contents( $this->file, str_repeat( PHP_EOL, $count ), FILE_APPEND | LOCK_EX );
  }


  public function log( $message, $type = null ) {
    if ( $this->disabled ) return;
    $formattedMessage = $this->formatLog( $message, $type );
    file_put_contents( $this->file, $formattedMessage, FILE_APPEND | LOCK_EX );
  }


  public function getFileSize() { return file_exists( $this->file ) ? filesize( $this->file ) : 0; }
  public function getFileName() { return $this->fileName; }
  public function getLevel() { return $this->logLevel; }
  public function getFile() { return $this->file; }
  public function getPath() { return $this->path; }

}