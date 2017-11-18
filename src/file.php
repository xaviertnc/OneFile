<?php namespace OneFile;

use FilesystemIterator;

class File
{

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
	protected $filepath;

	/**
	 *
	 * @var octal
	 */
	protected $mode;

	/**
	 * 
	 * @param type $path
	 */
	public function __construct($path = null, $filename = null, $mode = 0775)
	{
		$this->setFilePath($path, $filename);
		$this->mode = $mode;
	}

	protected function setFilePath($path = null, $filename = null)
	{
		if ($filename)
		{
			$this->path = $path;
			$this->filename = $filename;
			$this->filepath = $path . DIRECTORY_SEPARATOR . $filename;
		}
		elseif ($path)
		{
			if (is_file($path))
			{
				$this->path = dirname($path);
				$this->filename = basename($path);
				$this->filepath = $path;
			}
			else
			{
				$this->path = $path;
			}
		}
	}

	/**
	 * 
	 * @param integer $size in Bytes
	 * @return type
	 */
	public function sizeToString($size = null)
	{
		if (is_null($size) and $this->filepath)
		{
			$size = filesize($this->filepath);
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
	 * 
	 * @param type $filePath
	 * @return boolean
	 */
	public function delete($filePath = null)
	{
		if ( ! $filePath and $this->filepath)
		{
			$filePath = $this->filepath;
		}

		if (is_file($filePath) and file_exists($filePath))
		{
			unlink($filePath);
			return true;
		}
		else
			return false;
	}

	/**
	 * 
	 * @param type $filename
	 * @return type
	 */
	public function slugFilename($filename)
	{
		$ext = pathinfo($filename, PATHINFO_EXTENSION);

		if ($ext)
		{
			$ext = '.' . $ext;
		}

		$p = strpos($filename, $ext);

		$filename = preg_replace('/[^A-Z^a-z^0-9_\-]/', '', substr($filename, 0, $p));

		return $filename . $ext;
	}

	/**
	 * 
	 * @param type $filename
	 * @return type
	 */
	public function ext($filename)
	{
		return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	}

	/**
	 * Get the file's last modification time.
	 *
	 * @param  string  $path
	 * @return int
	 */
	public function lastModified($path)
	{
		return filemtime($path);
	}

	/**
	 * Get an array of all files in a directory.
	 *
	 * @param  string  $directory
	 * @return array
	 */
	public function files($directory)
	{
		$glob = glob($directory . '/*');

		if ($glob === false)
			return array();

		// To get the appropriate files, we'll simply glob the directory and filter
		// out any "files" that are not truly files so we do not end up with any
		// directories in our list, but only true files within the directory.
		return array_filter($glob, function($file) {
			return filetype($file) == 'file';
		});
	}

	/**
	 * Create a directory.
	 *
	 * @param  string  $path
	 * @param  int     $mode
	 * @param  bool    $recursive
	 * @param  bool    $force
	 * @return bool
	 */
	public function makeDirectory($path, $mode = 0777, $recursive = false, $force = false)
	{
		if ($force)
		{
			return @mkdir($path, $mode, $recursive);
		}
		else
		{
			return mkdir($path, $mode, $recursive);
		}
	}

	/**
	 * Copy a directory from one location to another.
	 *
	 * @param  string  $directory
	 * @param  string  $destination
	 * @param  int     $options
	 * @return bool
	 */
	public function copyDirectory($directory, $destination, $options = null)
	{
		if (!$this->isDirectory($directory))
			return false;

		$options = $options ? : FilesystemIterator::SKIP_DOTS;

		// If the destination directory does not actually exist, we will go ahead and
		// create it recursively, which just gets the destination prepared to copy
		// the files over. Once we make the directory we'll proceed the copying.
		if ( ! $this->isDirectory($destination))
		{
			$this->makeDirectory($destination, 0777, true);
		}

		$items = new FilesystemIterator($directory, $options);

		foreach ($items as $item)
		{
			// As we spin through items, we will check to see if the current file is actually
			// a directory or a file. When it is actually a directory we will need to call
			// back into this function recursively to keep copying these nested folders.
			$target = $destination . '/' . $item->getBasename();

			if ($item->isDir())
			{
				$path = $item->getPathname();

				if ( ! $this->copyDirectory($path, $target, $options))
					return false;
			}

			// If the current items is just a regular file, we will just copy this to the new
			// location and keep looping. If for some reason the copy fails we'll bail out
			// and return false, so the developer is aware that the copy process failed.
			else
			{
				if ( ! $this->copy($item->getPathname(), $target))
					return false;
			}
		}

		return true;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * The directory itself may be optionally preserved.
	 *
	 * @param  string  $directory
	 * @param  bool    $preserve
	 * @return bool
	 */
	public function deleteDirectory($directory, $preserve = false)
	{
		if ( ! $this->isDirectory($directory))
			return false;

		$items = new FilesystemIterator($directory);

		foreach ($items as $item)
		{
			// If the item is a directory, we can just recurse into the function and
			// delete that sub-director, otherwise we'll just delete the file and
			// keep iterating through each file until the directory is cleaned.
			if ($item->isDir())
			{
				$this->deleteDirectory($item->getPathname());
			}

			// If the item is just a file, we can go ahead and delete it since we're
			// just looping through and waxing all of the files in this directory
			// and calling directories recursively, so we delete the real path.
			else
			{
				$this->delete($item->getPathname());
			}
		}

		if ( ! $preserve)
			@rmdir($directory);

		return true;
	}

	/**
	 * Empty the specified directory of all files and folders.
	 *
	 * @param  string  $directory
	 * @return bool
	 */
	public function cleanDirectory($directory)
	{
		return $this->deleteDirectory($directory, true);
	}

	/**
	 *
	 * Moves a file from one location to another.
	 * If OVERWRITE_DESTFILE = FALSE , a number will be added to the end of the
	 * destination filename to prevent overwriting the existing file!
	 *
	 * @param string $src_path  Path ONLY + Trailing Slash Optional
	 * @param string $dest_path Path ONLY + Trailing Slash Optional
	 * @param string $src_filename
	 * @param string $dest_filename
	 * @param boolean $overwrite_destfile Don't over-write an existing dest file TRUE / FALSE
	 * @param boolean $delete_sourcefile
	 * @param octal $mode
	 * @return string|boolean Move Succuessfull = Dest Filename / Else FALSE
	 */
	public function move($src_path, $dest_path, $src_filename = null, $dest_filename = null, 
			$overwrite_destfile = true, $delete_sourcefile = true, $mode = 0775)
	{
		if ( ! $src_filename)
		{
			if(is_file($src_path))
			{
				$src_filepath = $src_path;
				$src_path = dirname($src_filepath);
				$src_filename = basename($src_filepath);
			}
			else
			{
				return false;
			}
		}

		if ( ! $dest_filename)
		{
			if(is_file($dest_path))
			{
				$dest_filepath = $src_path;
				$dest_path = dirname($dest_filepath);
				$dest_filename = basename($dest_filepath);
			}
			else
			{
				$dest_filename = $src_filename;
			}
		}

		$src_path = rtrim($src_path, DIRECTORY_SEPARATOR);
		$dest_path = rtrim($dest_path, DIRECTORY_SEPARATOR);

		$src_file = $src_path . DIRECTORY_SEPARATOR . $src_filename;
		$dest_file = $dest_path . DIRECTORY_SEPARATOR . $dest_filename;

		if ( ! file_exists($src_file))
		{
			return false;
		}

		//The "dest_filename" can sometimes contain a path segment or two, so recalc the 
		//actual path after combining "dest_path"+"dest_filename"...NM 13 Nov 2012
		$full_dest_path = dirname($dest_file);

		if ( ! file_exists($full_dest_path))
		{
			$oldumask = umask(0);

			if ( ! mkdir($full_dest_path, $mode, true))
			{
				return false;
			}

			umask($oldumask);

			//chmod($full_dest_path, $mode);
		}

		if (!$overwrite_destfile and file_exists($dest_file))
		{
			$file_ext = pathinfo($dest_filename, PATHINFO_EXTENSION);

			if ($file_ext)
			{
				$file_ext = '.' . $file_ext;
			}

			$n = 0;

			$p = strpos($dest_filename, $file_ext);

			$base_name = substr($dest_filename, 0, $p);

			$new_name = $base_name . '_' . $n . $file_ext;

			$dest_file = $dest_path . $new_name;

			//n < 1000 is an endless loop precaution measure
			while (file_exists($dest_file) and $n < 1000)
			{
				$n++;
				$new_name = $base_name . '_' . $n . $file_ext;
				$dest_file = $dest_path . $new_name;
			}

			$dest_filename = $new_name;
		}

		if ( ! copy($src_file, $dest_file))
		{
			return false;
		}

		chmod($dest_file, $mode);

		if ($delete_sourcefile)
		{
			$this->delete($src_file);
		}

		return $dest_filename;
	}

	/**
	 * 
	 * @param type $src_path
	 * @param type $dest_path
	 * @param type $src_filename
	 * @param type $dest_filename
	 * @param type $overwrite_destfile
	 * @param type $mode
	 * @return type
	 */
	public function copy($src_path, $dest_path, $src_filename = '', $dest_filename = '', $overwrite_destfile = true, $mode = 0775)
	{
		return $this->move($src_path, $dest_path, $src_filename, $dest_filename, $overwrite_destfile, false, $mode);
	}

}
