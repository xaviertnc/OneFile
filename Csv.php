<?php namespace OneFile;

/**
 * Data Source to CSV File Service
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 26 Jul 2018
 *
 */
class Csv {

  public $delimiter = ',';
  public $enclosure = '"';

  
	public function __construct($delimiter = null, $enclosure = null)
	{
    if ($delimiter) { $this->delimiter = $delimiter; }
    if ($enclosure) { $this->enclosure = $enclosure; }
	}

  
  /*
   * @param integer $lineNo
   * @param array $csvColumns Array of CsvColumn
   * @param object $objSourceData
   * @param string $emptyCol What to show if a column has no value.
   *
   * @return array Array of strings. Array of CSV column values.
   *   
   */
	public function getLine($lineNo, $csvColumns, $objSourceData = null, $emptyCol = '')
	{
    $line = [];
 
    $dataRec = (array) $objSourceData;
    foreach ($csvColumns as $csvColumn)
    {
      $line[] = $csvColumn->render($lineNo, $dataRec, $emptyCol);
    }

		return $line;
	}

  
	public function getLines($csvColumns, $sourceData, $skipTitles = false, $emptyCol = '')
	{
    $lines = [];
    if ( ! $skipTitles)
    {
      $csvTitlesLine = [];
      foreach ($csvColumns as $csvColumn) { $csvTitlesLine[] = $csvColumn->title; }
      $lines[] = $csvTitlesLine;
    }
    $hasIndexColumn = ($csvColumns[0]->name == '#');
    foreach ($sourceData?:[] as $rowIndex => $objSourceData)
    {
      $lineNo = $hasIndexColumn ? $rowIndex + 1 : null;
      $lines[] = $this->getLine($lineNo, $csvColumns, $objSourceData, $emptyCol);
    }
    return $lines;
	}
  
  
  public function lines2csv(array $lines, $filename = null)
  {
    if ( ! $lines) { return; }
    if ( ! $filename)
    {
      // To the output buffer
      ob_start();
      $df = fopen("php://output", 'w');
      foreach ($lines as $line) {
        fputcsv($df, $line, $this->delimiter, $this->enclosure);
      }
      fclose($df);
      return ob_get_clean();
    }
    
    // Direct to file
    $df = fopen($filename, 'w');
    foreach ($lines as $line) {
      fputcsv($df, $line, $this->delimiter, $this->enclosure);
    }
    fclose($df);  
  }
  
  
  public function download($filename, $csvColumns, $sourceData, $skipTitles = false, $emptyCol = '')
  {
    // disable caching
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");

    // force download  
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");

    // disposition / encoding on response body
    header("Content-Disposition: attachment;filename={$filename}");
    header("Content-Transfer-Encoding: binary");

    $lines = $this->getLines($csvColumns, $sourceData, $skipTitles, $emptyCol);
    
    echo $this->lines2csv($lines);
    die();
  }
  
  
  public function writeToFile($filename, $csvColumns, $sourceData, $skipTitles = false, $emptyCol = '')
  {    
    // $filePath = $this->outputDir . '/' . $fileBaseName . date('_YmdHis') . '.csv';
    $lines = $this->getLines($csvColumns, $sourceData, $skipTitles, $emptyCol);
    $this->lines2csv($lines, $filename);
  }
  
} // end: Class Csv



/*
 * Define the title and render function of every CSV column
 */
class CsvColumn {

	public $name;
	public $title;
	public $renderFn;
  
  // Override me
  // By emptyCol render "$dataRec[$columnName]" 
  protected function _getDefaultRenderFn()
  {
    $columnName = $this->name;
    return function($lineNo, $dataRec = null, $emptyCol = null) use ($columnName) {
      if (isset($dataRec[$columnName])) { return $dataRec[$columnName]; }
      return $emptyCol;
    };
  }

  /*
   *  @param callable $renderFn function($lineNo, $dataRec = null, $emptyCol = null) { .. }
   *  @return object $this Allow method chaining
   */
  public function setRenderFn($renderFn)
  {
    $this->renderFn = $renderFn;
    return $this;
  }
	
  public function __construct($name, $title = null, $renderFn = null)
	{
		$this->name = $name;
    $this->title = $title ?: $name;
		$this->renderFn = $renderFn ?: $this->_getDefaultRenderFn();
	}
  
  public function render($lineNo, $dataRec = null)
  {
    return call_user_func_array($this->renderFn, [$lineNo, $dataRec]);
  }
  
} // end: Class CsvColumn
