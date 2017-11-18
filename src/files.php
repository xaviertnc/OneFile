<?php namespace OneFile;

class Files
{
	public static function create()
	{
		return new static;
	}
	
	public function moveDirectoryFiles($sourceDir, $destinationDir)
	{
		// Get array of all source files
		$files = scandir("source");
		// Cycle through all source files
		foreach ($files as $file) {
		  if (in_array($file, array(".",".."))) continue;
		  // If we copied this successfully, mark it for deletion
		  if (copy($sourceDir.$file, $destinationDir.$file)) {
			$delete[] = $sourceDir.$file;
		  }
		}
		// Delete all successfully-copied files
		foreach ($delete as $file) {
		  unlink($file);
		}		
	}
	
	public static function deleteFile($filename)
	{
		if(file_exists($filename) && is_file($filename) && is_writable(dirname($filename)))
		{
			unlink($filename);
			return true;
		}
		else return false;
	}


	public static function cleanFilename($filename)
	{
		$file_ext = pathinfo($filename, PATHINFO_EXTENSION);
		if($file_ext) $file_ext = '.'.$file_ext;
		$p = strpos($filename, $file_ext);
		$filename = preg_replace('/[^A-Z^a-z^0-9_\-]/', '', substr($filename, 0, $p));
		return $filename.$file_ext;
	}


	public static function purgeDirectory($path)
	{
		//NOTE: Check on glob return value format... I'm not sure this next bit of code works!
		//Not very efficient!  Don't use $this->delete since it double checks too many things. 
		$files = glob(rtrim($path, '/').'/*');
		foreach($files as $index => $filename)
			$this->delete($filename);
	}


	public static function getFilenameExt($filename)
	{
		return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	}	
	
}