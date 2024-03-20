<?php namespace F1;

/**
 * F1 - File System Class - 30 Jul 2023
 *
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 1.1 - DEV - 09 Dec 2023
 *   - Update code style. {} Usage.
 *
 */

class FileSystem {

  public function copyDir( $src, $dst ) {
    $dir = opendir( $src );
    @mkdir( $dst );
    while ( false !== ( $file = readdir( $dir ) ) ) {
      if ( ( $file != '.' ) && ( $file != '..' ) ) {
        if ( is_dir( $src . '/' . $file ) ) {
          $this->copyDir( $src . '/' . $file, $dst . '/' . $file );
        } else {
          copy( $src . '/' . $file, $dst . '/' . $file );
        }
      }
    }
    closedir( $dir );
  }


  public function emptyDir( $dir, $recursive = false, $keepSubDirs = false, $depth = 0 ) {
    if ( ! $dir || $depth > 20 ) return; // prevent infinite recursion
    foreach ( array_diff( scandir( $dir ), array( '.', '..' ) ) as $file ) {
      $path = $dir . DIRECTORY_SEPARATOR . $file;
      if ( is_dir( $path ) ) {
        if ( $recursive ) $this->emptyDir( $path, true, $keepSubDirs, $depth + 1 );
        if ( $depth && !$keepSubDirs ) rmdir( $path );
      } else {
        unlink( $path );
      }
    }
  }


  public function deleteDir( $dir ) {
    $this->emptyDir( $dir, true );
    return rmdir( $dir );
  }

}
