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
 *
 * @version 2.5 - FIX - 15 Sep 2026
 *   - setPath(): mkdir 02777 under umask(0); write(): chmod 0666 on a new file — Apache, cron,
 *     CLI and root-created dated log dirs stay shared-writable regardless of the creator's umask
 *
 * @version 2.5.1 - FIX - 16 Sep 2026
 *   - setPath(): mkdir 0777 not 02777 — an explicit setgid bit is EPERM for a caller outside the
 *     parent's group (kernel 6.x); the setgid parent already propagates it
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
    $new = ! is_file( $this->file );
    file_put_contents( $this->file, $message, FILE_APPEND | LOCK_EX );
    if ( $new ) chmod( $this->file, 0666 );
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
    if ( $mkdir and ! is_dir( $path ) ) { $umask = umask( 0 ); mkdir( $path, 0777, true ); umask( $umask ); }
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