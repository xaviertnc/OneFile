<?php namespace OneFile;

/*
 * $path_parts = pathinfo('/www/htdocs/inc/lib.inc.php');
 * 
 * echo $path_parts['dirname'];
 * echo $path_parts['basename'];
 * echo $path_parts['extension'];
 * echo $path_parts['filename']; // since PHP 5.2.0
 * 
 * The above example will output:
 * 
 * /www/htdocs/inc
 * lib.inc.php
 * php
 * lib.inc	
 */

class File
{
	/**
	 *
	 * @var string
	 */
	protected $filePath;

	/**
	 *
	 * @var string
	 */
	protected $path;

	/**
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 *
	 * @var string
	 */
	protected $nameonly;

	/**
	 *
	 * @var string
	 */
	protected $ext;

	/**
	 *
	 * @var octal
	 */
	protected $mode = 0775;
	
	/**
	 *
	 * @var boolean
	 */
	protected $mapped = false;

	
	public function __construct($path = null, $filename = null, $mode = 0775)
	{
		$this->setFilePath($path, $filename);
		$this->mode = $mode;
	}
	
	protected function mapInfo()
	{
		$fileInfo = pathinfo($this->getFilePath());
		
		if ( ! $fileInfo) return;
		
		$this->path = isset($fileInfo['dirname']) ? $fileInfo['dirname'] : null;
		
		$this->filename = isset($fileInfo['basename']) ? $fileInfo['basename'] : null;
		
		$this->ext = isset($fileInfo['extension']) ? $fileInfo['extension'] : null;
		
		$this->nameonly = isset($fileInfo['filename']) ? $fileInfo['filename'] : null;
	}
	
	public function getFilePath()
	{
		if (is_array($this->filePath))
		{
			$this->filePath = implode(DIRECTORY_SEPARATOR, $this->filePath);
		}
		
		return $this->filePath;
	}
	
	public function getPath()
	{
		if( ! $this->mapped) { $this->mapInfo(); }
		return $this->path;
	}
	
	public function getFilename()
	{
		if( ! $this->mapped) { $this->mapInfo(); }
		return $this->filename;
	}
	
	public function getNameOnly()
	{
		if( ! $this->mapped) { $this->mapInfo(); }
		return $this->nameonly?:'noname';
	}
	
	public function getExt()
	{
		if( ! $this->mapped) { $this->mapInfo(); }
		return $this->ext;
	}
	
	public function getMode()
	{
		return $this->mode;
	}
	
	public function setMode($mode)
	{
		$this->mode = $mode;
		return $this;
	}
	
	public function setFilePath($filePath)
	{
		$this->mapped = false;
		$this->filePath = $filePath;
		return $this;
	}
	
	public function setPath($path)
	{
		$this->mapped = false;		
		$this->filePath = $this->makeFilePath($path);
		return $this;
	}
	
	public function setFilename($filename, $ext = null)
	{
		$this->mapped = false;
		$this->filePath = $this->makeFilePath(null, $filename, $ext);
		return $this;
	}
	
	public function setExt($ext)
	{
		$this->mapped = false;
		$this->filePath = $this->makeFilePath(null, null, $ext);
		return $this;
	}
	
	public function write($data, $append = false)
	{
		$filePath = $this->getFilePath();
		
		if ( ! file_exists($filePath))
		{
			$oldumask = umask(0);
			
			$dirname = dirname($filePath);
			@mkdir($dirname, $this->mode, true);
			
			umask($oldumask);
		}
		
		if ($append)
		{
			$options = FILE_APPEND | LOCK_EX;
		}
		else
		{
			$options = LOCK_EX;
		}
		
		file_put_contents($filePath, $data, $options);

		chmod($filePath, $this->mode);		
	}
	
	public function append($data)
	{
		return $this->write($data, true);
	}
	
	/**
	 * 
	 * @param type $this->filePath
	 * @return boolean
	 */
	public function delete($nocheck = false, $silentFail = false)
	{
		if ($nocheck)
		{
			if ($silentFail)
			{
				@unlink($this->getFilePath());
			}
			else
			{
				unlink($this->getFilePath());
			}
		}
		elseif(is_file($this->getFilePath()))
		{
			if ($silentFail)
			{
				@unlink($this->getFilePath());
			}
			else
			{
				unlink($this->getFilePath());
			}
		}
		
		return $this;
	}
	
	/**
	 * Makes a new file path based on the parameters provided and existing file properties.
	 * If a path or filename is not specified, the corresponding current file property value will
	 * be used.
	 * 
	 * If "Extension" is not specified, it is assumed that the extension is included in the filename value.
	 * 
	 * If "Extension" is specified without a filename value, the current "NameOnly" property of the file will
	 * be used as the name part of the filename.
	 * 
	 * @param string $path
	 * @param string $filename
	 * @param string $extension
	 * @return string
	 */
	public function makeFilePath($path = null, $filename = null, $extension = null)
	{
		if (is_array($path))
		{
			$path = implode(DIRECTORY_SEPARATOR, $path);
		}

		$path = $path?:$this->getPath();
				
		if ($extension)
		{			
			$filename = $filename ? $filename . '.' . $extension : $this->getNameOnly() . '.' . $extension;
		}
		else
		{
			$filename = $filename?:$this->getFilename();
		}
		
		if ($filename)
		{
			$path = rtrim($path, '/') . '/';
		}

		return $path . $filename;
	}
	
	/**
	 * 
	 * @param type $filename
	 * @return type
	 */
	public function sanitize($filename)
	{
		return preg_replace('/[^A-Za-z0-9_\-\.]+/', '', $filename);
	}

	/**
	 * Get the file's last modification time.
	 *
	 * @return int
	 */
	public function lastModified()
	{
		return filemtime($this->filePath);
	}	
	
	/**
	 * 
	 * @param integer $size in Bytes
	 * @return type
	 */
	public function sizeToString($size = null)
	{
		if (is_null($size) and $this->filePath)
		{
			$size = filesize($this->filePath);
		}

		if ($size < 1024)
			return $size . ' B';
		elseif ($size < 1048576)
			return round($size / 1024, 2) . ' KB';
		elseif ($size < 1073741824)
			return round($size / 1048576, 2) . ' MB';
		elseif ($size < 1099511627776)
			return round($size / 1073741824, 2) . ' GB';
		else
			return round($size / 1099511627776, 2) . ' TB';
	}
	
	/**
	 * 
	 * @param integer $size
	 * @param string $units
	 * @return integer
	 */
	public function convertSize($size = null, $units = null)
	{
		switch ($units)
		{
			case 'B' : return $size;
			case 'KB': return $size * 1024;
			case 'MB': return $size * 1048576;
			case 'GB': return $size * 1073741824;
		}

		return $size;
	}
	
	/**
	 * This method assumes you already found that the file path is
	 * not available and it should find the next possible name.
	 * 
	 * @param string $filePath
	 * @param integer $duplicateIndexLimit
	 * @return string
	 */	
	public function getNextPossibleName($filePath, $duplicateIndexLimit = 100)
	{	
		$file = new static($filePath); //Make another file instance within the File class itself!
		
		$originalName = $file->getNameOnly();
		
		$ext = $file->getExtension();
		
		$duplicateIndex = 0;
		
		// $duplicationLimit = Endless loop precaution measure + Prevent excessive performance hit
		while ($file->exists() and $duplicateIndex < $duplicateIndexLimit)
		{
			$file->setFilename($originalName . "_$duplicateIndex" . $ext);
			$duplicateIndex++;
		}

		return $file->getFilePath();
	}
	
	/**
	 * Return the quarter for a timestamp.
	 * @returns integer
	 */
	protected function quarter($ts) {
	   return ceil(date('n', $ts)/3);
	}
	
	public function mkdir($dir, $mode = 0775)
	{
		$oldumask = umask(0);

		if ( ! mkdir($dir, $mode, true))
		{
			umask($oldumask);
			return false;
		}

		umask($oldumask);			
	}

	/**
	 * Adds a subfolder path in-front of the supplied filename
	 * to enable saving the file in a specific file group
	 * 
	 * @param string $groupName
	 * @param string $groupFormat
	 * @param time $groupDate
	 * @return string
	 */
	public function makeGroupPath($groupName = '', $groupFormat = 'Y', $groupDate = null)
	{	
		if( ! $groupDate) { $groupDate = time(); }
		
		switch ($groupFormat)
		{
			case 'YQ':
				$datePath = date('Y', $groupDate) . 'Q' . $this->quarter($groupDate);
				break;
			
			//The next option is covered by the default action, but it is shown here to 
			//remind that you can add subfolder slashes to the format string!
			case 'Y/m':
				$datePath = date('Y/m', $groupDate);
				break;

			default:
				if ($groupFormat)
				{
					$datePath = date($groupFormat, $groupDate);
				}
				else
				{
					$datePath = '';
				}
		}
		
		if ($groupName) { $prefix_parts[] =  $groupName; }
		
		if ($datePath) { $prefix_parts[] = $datePath; }

		if ($prefix_parts)
		{
			return implode(DIRECTORY_SEPARATOR, $prefix_parts);
		}
		else
		{
			return '';
		}
	}
	
	public function addGroupPath($groupName = '', $groupFormat = 'Y', $groupDate = null)
	{
		$path = $this->getPath();
		
		if ($path and $path !== '.') { $path_parts[] = $this->getPath(); }
		
		$path_parts[] = $this->makeGroupPath($groupName, $groupFormat, $groupDate);
		
		$this->setFilePath(implode(DIRECTORY_SEPARATOR, $path_parts), $this->getFilename());
		
		return $this;
	}
	
	/**
	 *
	 * Moves a file from one location to another.
	 * If OVERWRITE_DESTFILE = FALSE , a number will be added to the end of the
	 * destination filename to prevent overwriting the existing file!
	 *
	 * @param string $destFilePath
	 * @param boolean $overwrite Don't over-write an existing dest file TRUE / FALSE
	 * @param boolean $force
	 * @param octal $mode
	 * @param boolean $move
	 * @return string|boolean Move Succuessfull = Dest Filename / Else FALSE
	 */
	public function copy($destFilePath, $overwrite = true, $force = false, $mode = 0775, $silentFail = false, $move = false)
	{
		if ( ! is_file($this->getFilePath()))
		{
			return false;
		}

		$destPath = dirname($destFilePath);
		
		if ( ! is_dir($destPath))
		{
			$this->mkdir($destPath, $mode);
		}

		if ( ! $overwrite and file_exists($destFilePath))
		{
			if ( ! $force)
			{
				return false;
			}
			
			//Force copy, but use an indexed version of the destination filename
			$destFilePath = $this->getNextPossibleName($destFilePath);
		}

		if ( ! copy($this->getFilePath(), $destFilePath))
		{
			return false;
		}

		chmod($destFilePath, $mode);

		if ($move)
		{
			$this->delete(true, $silentFail);
		}
		
		//Move this file instance to point to the newly copied / moved file.
		$this->setFilePath($destFilePath);

		return $destFilePath;
	}

	public function move($dest_path, $overwrite = true, $force = false, $mode = 0775, $silentFail = false)
	{
		return $this->copy($dest_path, $overwrite, $force, $mode, $silentFail, true);
	}
	
	public function read()
	{
		if ($this->isFile())
		{
			return file_get_contents($this->getFilePath());
		}
	}
	
	public function exists()
	{
		return file_exists($this->getFilePath());
	}
	
	public function isFile()
	{
		return is_file($this->getFilePath());
	}
	
	public function __toString()
	{
		return $this->getFilePath();
	}
}