<?php namespace OneFile;

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
// ------------------------------------------------------------------------


/**
 * CodeIgniter File Helpers
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/helpers/file_helpers.html
 */
// ------------------------------------------------------------------------


/**
 * OneFile Folder Class
 *
 * Method code ported from CI file_helper.php
 *
 * By: C. Moller - 28 Apr 2016
 *
 */
// ------------------------------------------------------------------------


class Folder
{
	// ------------------------------------------------------------------------
	/**
	 * Delete Files
	 *
	 * Deletes all files contained in the supplied directory path.
	 * Files must be writable or owned by the system in order to be deleted.
	 * If the second parameter is set to TRUE, any directories contained
	 * within the supplied base directory will be nuked as well.
	 *
	 * @access	public
	 * @param	string	path to file
	 * @param	bool	whether to delete any directories found in the path
	 * @return	bool
	 */
	public static function deleteFiles($path, $del_dir = FALSE, $level = 0)
	{
		// Trim the trailing slash
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if ( ! $current_dir = @opendir($path))
		{
			return FALSE;
		}
		while (FALSE !== ($filename = @readdir($current_dir)))
		{
			if ($filename != "." and $filename != "..")
			{
				if (is_dir($path.DIRECTORY_SEPARATOR.$filename))
				{
					// Ignore empty folders
					if (substr($filename, 0, 1) != '.')
					{
						deleteFiles($path.DIRECTORY_SEPARATOR.$filename, $del_dir, $level + 1);
					}
				}
				else
				{
					unlink($path.DIRECTORY_SEPARATOR.$filename);
				}
			}
		}
		@closedir($current_dir);
		if ($del_dir == TRUE AND $level > 0)
		{
			return @rmdir($path);
		}
		return TRUE;
	}


	// ------------------------------------------------------------------------
	/**
	 * Get Filenames
	 *
	 * Reads the specified directory and builds an array containing the filenames.
	 * Any sub-folders contained within the specified path are read as well.
	 *
	 * @access	public
	 * @param	string	path to source
	 * @param	bool	whether to include the path as part of the filename
	 * @param	bool	internal variable to determine recursion status - do not use in calls
	 * @return	array
	 */
	public static function getFilenames($source_dir, $include_path = FALSE, $_recursion = FALSE)
	{
		static $filenames = array();
		if ($fp = @opendir($source_dir))
		{
			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($_recursion === FALSE)
			{
				$filenames = array();
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}
			while (FALSE !== ($file = readdir($fp)))
			{
				if (@is_dir($source_dir.$file) && strncmp($file, '.', 1) !== 0)
				{
					getFilenames($source_dir.$file.DIRECTORY_SEPARATOR, $include_path, TRUE);
				}
				elseif (strncmp($file, '.', 1) !== 0)
				{
					$filenames[] = ($include_path == TRUE) ? $source_dir.$file : $file;
				}
			}
			return $filenames;
		}
		else
		{
			return FALSE;
		}
	}

}
