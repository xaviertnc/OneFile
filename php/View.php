<?php namespace F1;

use stdClass;
use Exception;

/**
 * F1 View Class - 23 June 2022
 *
 * @author C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 7.2.0 - DEV - 09 Dec 2023
 *  - Update code style. Particularly {} use.
 *  - Reorganize code.
 */

class View {
  
  public $file;
  public $data;
  public $ds = DIRECTORY_SEPARATOR;


  public function __construct( $file, $data = null ) {
    $this->file = $file;
    $this->data = empty( $data ) ?  new stdClass() : $data;
  }


  public function compile( $uncompiledFile, $compiledFile, $manifestFile, &$manifest, $level = 0 ) {
    if ( $level++ > 3 ) return '';
    $content = file_get_contents( $uncompiledFile );
    $matches = array();
    $pattern = '/\<(foreach|if) x="(.+?)"\>/';
    preg_match_all($pattern, $content, $matches);
    if ( $matches[0] ) {
      foreach ( $matches[0] as $index => $match ) {
        $subContent = "<?php {$matches[1][ $index ]}( {$matches[2][ $index ]} ): ?>";
        $content = $this->replaceContent( $match, $subContent, $content );
      }
    }
    $matches = array();
    $pattern = '/\<\/(foreach|if)\>/';
    preg_match_all($pattern, $content, $matches);
    if ( $matches[0] ) {
      foreach ( $matches[0] as $index => $match ) {
        $subContent = "<?php end{$matches[1][ $index ]}; ?>";
        $content = $this->replaceContent( $match, $subContent, $content );
      }
    }
    $matches = array();
    $pattern = '/\<eval\>(.+?)\<\/eval\>/s';
    preg_match_all($pattern, $content, $matches);
    if ( $matches[0] ) {
      foreach ( $matches[0] as $index => $match ) {
        $subContent = '<?php ' .$matches[1][ $index ]. '?>';
        $replaceOptions = [ 'indent' => 'no' ];
        $content = $this->replaceContent( $match, $subContent, $content, $replaceOptions );
      }
    }    
    $matches = array();
    $pattern = '/\<include([^>]*)\>([^>]+)\<\/include\>/';
    preg_match_all($pattern, $content, $matches);
    if ( $matches[0] ) {
      foreach ( $matches[0] as $index => $match ) {
        $attrsStr = preg_replace( '/\s+/', ' ', $matches[1][ $index ] );
        debug_log( $attrsStr, 'attrsStr = ' );
        $inclFileRef = trim( $matches[2][ $index ] );
        debug_log( $inclFileRef, 'inclFileRef = ' );
        $props = $attrsStr ? $this->parseAttrs( $attrsStr ) : [];
        debug_log( $props, 'props = ' );
        $forceInline = isset( $props[ 'inline' ] );
        $fileToInclude = $this->getIncludeFile( $inclFileRef, $props );
        debug_log( 'fileToInclude = ' . $fileToInclude );
        // Initialize $subContent to a default value.
        $subContent = "/* File not found: $inclFileRef */";
        if ( $fileToInclude and file_exists( $fileToInclude )) {
          unset( $props[ 'inline' ] );
          $manifest[ $fileToInclude ] = filemtime( $fileToInclude );
          $subContent = $this->compile( $fileToInclude, null, null, $manifest, $level );
          if ( substr( $inclFileRef, -3 ) === '.js' ) $subContent = "<script>\n$subContent\n</script>";
          else if ( substr( $inclFileRef, -4 ) === '.css' ) $subContent = "<style>\n$subContent\n</style>";
          // Customize the included content based on the props.
          foreach ( $props as $name => $val ) {
            if ( $name == 'use' ) {
              $subContent = preg_replace( "/\[data\.([^\]]+)\]/", "<?=$val->$1?>", $subContent );
              continue;
            }
            elseif ( $name == 'class' ) $val = " $val";
            $subContent = preg_replace( "/\[\:$name\]/", $val, $subContent );
          }
          $subContent = preg_replace( '/\[\:.+\]/', '', $subContent );
        }
        $replaceOptions = [ 'forceInline' => $forceInline ];
        $content = $this->replaceContent( $match, $subContent, $content, $replaceOptions );
      }
    }
    if ( $level === 1 ) {
      // Write manifest file
      $manifestContent = '<?php return ' . var_export( $manifest, true ) . '; ?>';
      file_put_contents( $manifestFile, $manifestContent );
      // Write compiled file
      $content = $this->unindentPHPCode( $content );
      $content .= PHP_EOL . '<!-- Compiled: ' . date('Y-m-d H:i:s') . ' -->';
      file_put_contents( $compiledFile, $content );  
    }
    return $content;
  }


  public function parseAttrs( $attrsStr ) {
    $attrs = []; $pattern = '/\s*(.+?)\s*=\s*("[^"]*")/';
    preg_match_all( $pattern, $attrsStr, $matches, PREG_SET_ORDER );
    foreach ( $matches as $match ) $attrs[$match[1]] = trim( $match[2], '"' );
    return $attrs;
  }


  public function replaceContent( $match, $replace, $content, $options = [] ) {
    $forceInline = isset( $options['forceInline'] ) ? $options['forceInline'] : false;
    $indent = isset( $options['indent'] ) ? $options['indent'] : 'add';
    // Split content into 3 parts: before, match, after
    $parts = explode( $match, $content );
    if ( count( $parts ) < 2 ) return $content;
    $before = $parts[0];
    // Get the last bit of $before, after the last "\n".
    $inlineBeforeMatch = substr( strrchr( $before, "\n" ), 1 );
    $inlineCodeBeforeMatch = trim( $inlineBeforeMatch );
    // If the last bit of $before is only whitespace, use it as indent.
    $indentStr = ! $inlineCodeBeforeMatch  ?  $inlineBeforeMatch : '';
    // If the last bit/line of $before is code and on the same line, we want $replace to be inline too.
    if ( $inlineCodeBeforeMatch or $forceInline ) {
      $replace = trim( preg_replace( '/\s+/', ' ', $replace ) );
    }
    elseif ( $indent == 'add' ) {
      $lines = explode( "\n", $replace );
      if ( count( $lines ) > 1 ) $replace = implode( "\n$indentStr", $lines );
    }
    $result = count( $parts ) > 2
     ? array_shift( $parts ) . $replace . implode( $match, $parts ) 
     : $parts[0] . $replace . $parts[1];
    return $result;
  }


  function unindentPHPCode( $html ) {
    $lines = explode( "\n", $html );
    $unindentedLines = [];
    $phpIndent = null;
    foreach ( $lines as $line ) {
      $phpPos = strpos( $line, "<?php" );
      if ( $phpPos !== false ) $phpIndent = $phpPos;
      if ( $phpIndent !== null && substr( $line, 0, $phpIndent ) === str_repeat( " ", $phpIndent ) ) {
        $line = substr( $line, $phpIndent );
      }
      $unindentedLines[] = $line;
      if ( strpos( $line, "?>" ) !== false ) $phpIndent = null;
    }
    return implode( "\n", $unindentedLines );
  }


  public function recompileChanges() { return true; } // Decide if we need to recompile.
  public function getManifestFile() { return str_replace( '.html', '_manifast.php', $this->file ); }
  public function getCompiledFile() { return str_replace( '.html', '_compiled.php', $this->file ); }  
  public function getIncludeFile( $inclFileRef, $props ) { return $this->getDir() . $inclFileRef; }
  public function get404File() { return $this->getDir() . '404.html'; }


  public function getFile() {
    $compile = false;
    $compiledFile = $this->getCompiledFile();
    $manifestFile = $this->getManifestFile();
    if ( file_exists( $compiledFile ) ) {
      if ( $this->recompileChanges() ) {
        $manifest = include( $manifestFile );
        $lastCompileTime = filemtime( $compiledFile );
        foreach ($manifest ?: [] as $fileToInclude => $timestamp) {
          if ( ! file_exists( $fileToInclude ) or $timestamp < filemtime( $fileToInclude ) ) {
            $compile = true;
            break;
          }
        }
      }
    }
    else $compile = true;
    if ( $compile ) {
      if ( ! file_exists( $this->file ) ) return $this->get404File();
      $manifest = array( $this->file => filemtime( $this->file ) );
      $this->compile( $this->file, $compiledFile, $manifestFile, $manifest );
    }
    return $compiledFile;
  }


  public function getDir() { return dirname( $this->file ) . $this->ds; }

  public function with( $key, $value ) { $this->data->$key = $value; return $this; }
  public function get( $key ) { return $this->data->$key ?? null; }

}