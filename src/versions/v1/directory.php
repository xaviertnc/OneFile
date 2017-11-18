<?php namespace OneFile;

use FilesystemIterator;

/**
 * By. C Moller: 27 Apr 2014
 */
class DirTreeItem
{
	/**
	 *
	 * @var string
	 */
	public $id;
		
	/**
	 *
	 * @var string
	 */
	public $text;
	
	/**
	 *
	 * @var string
	 */
	public $parent;

	/**
	 *
	 * @var string
	 */
	public $prev;
	
	/**
	 *
	 * @var string
	 */
	public $next;
	
	/**
	 *
	 * @var string
	 */
	public $firstChild;
	
	/**
	 *
	 * @var string
	 */
	public $lastChild;
	
	/**
	 *
	 * @var string
	 */
	public $path;
	
	
	function set($key, $value)
	{
		$this->$key = $value;
		return $this;
	}
	
	
	function get($key, $default = null)
	{
		if (isset($this->$key))
		{
			return $this->$key;
		}
		else
		{
			return $default;
		}
	}

	
	public function __construct($id, $text, $properties = array())
	{
		$this->id = $id;
		
		$this->text = $text;
		
		if ($properties)
		{
			foreach ($properties as $key => $value)
			{
				$this->set($key, $value);
			}
		}
	}
}

/**
 * By. C Moller: 27 Apr 2014
 */
class DirTree
{	
	/**
	 *
	 * @var string
	 */
	public $name;
		
	/**
	 *
	 * @var array of MenuItem
	 */
	public $items = array();
	
	/**
	 *
	 * @var string
	 */
	public $first;

	/**
	 *
	 * @var string
	 */
	public $last;
	
	
	/**
	 * 
	 * @param string $name
	 */
	public function __construct($name)
	{
		$this->name = $name;
	}
	
	
	function getItem($id)
	{
		return isset($this->items[$id]) ? $this->items[$id] : null;
	}
	
	
	function setItem($id, DirTreeItem $item)
	{
		$this->items[$id] = $item;
		
		return $item;
	}
	
	
	function addItem($id, $text = null, $properties = array())
	{
		$new_item = new DirTreeItem($id, $text, $properties);
						
		if ( ! $new_item->get('parent'))
		{
			if ( ! $this->items)
			{
				$this->first = $id;
			}
			else
			{
				$this->getItem($this->last)->next = $id;
				$new_item->prev = $this->last;
			}
			
			$this->last = $id;
		}
		
		$this->setItem($id, $new_item);
		
		return $new_item;
	}
	
	
	function insertItem($parentId, $id, $text = null, $properties = array())
	{
		$properties['parent'] = $parentId;
		
		$parent = $this->getItem($parentId);
		
		if ($parent)
		{
			if ( ! $parent->firstChild)
			{
				$parent->firstChild = $id;
			}
			else
			{
				$this->getItem($parent->lastChild)->next = $id;
			}

			$item = $this->addItem($id, $text, $properties);
			$item->prev = $parent->lastChild;
			$parent->lastChild = $id;
			
			return $item;
		}
	}
}

class Directory
{
	protected $path;
	
	public function __construct($path = null)
	{
		$this->path = $path;
	}
	
	public function create($path, $mode = 0777, $recursive = true, $suppress_errors = false)
	{
		if ($suppress_errors)
		{
			return @mkdir($path, $mode, $recursive);
		}
		else
		{
			return mkdir($path, $mode, $recursive);
		}
	}
	
	public function getFiles($sorting_order = null, $path = null)
	{
		if ( ! $path)
		{
			$path = $this->path;
		}
		
		return array_diff(scandir(rtrim($path, '\\/'), $sorting_order), array('..', '.'));
	}
	
	public function getFilesRecursive($sorting_order = null, $directory = null)
	{
		if ( ! $directory) { $directory = $this->path; }
		
		$directory = rtrim($directory, '\\/');
		
		$files = array();
		
		foreach(scandir($directory, $sorting_order) as $filename)
		{
			if ($filename == '.' or $filename == '..') continue;
			
			$filePath = "$directory/$filename";
			
			if (is_dir($filePath))
			{ 
				$files = array_merge($files, $this->getFilesRecursive($sorting_order, $filePath));
			}
			else
			{
				$files[$filename] = $filePath;
			}
		}
		
		return $files;
	}
	
	public function getFilesTree($sorting_order = null, $path = null)
	{
		if ( ! $path) { $path = $this->path; }
		
		$path = rtrim($path, '\\/');
		
		$tree = new DirTree($path);
		
		$directory = $tree->addItem(0, $path)->set('path', $path)->set('type', 'dir')->set('level', 0);

		$foundSubDirectory = false;
		
		$directoryItems = array();

		$stack = array();
			
		$nextItemId = 1;
		
		do {
						
			if ($nextItemId == 1 or $foundSubDirectory)
			{
				$directoryPath = $directory->path;
				$directoryItems = $this->getFiles($sorting_order, $directoryPath);
				$foundSubDirectory = false;
				$directory->subscount = 0;
				$directory->filecount = 0;
			}
			else
			{
				//Go UP one level and get parent level items from stack
				$child = $directory;
				$directory = $tree->getItem($directory->parent);
				$directoryPath = $directory->path;
				$directoryItems = $stack ? array_pop($stack) : array();				
				$directory->subscount += $child->subscount;
				$directory->filecount += $child->filecount;
			}
				
			foreach($directoryItems as $itemIndex => $filename)
			{
				$itemPath = "$directoryPath/$filename";
	
				$itemType = is_dir($itemPath) ? 'dir' : 'file';
								
				$directoryItem = $tree->insertItem($directory->id, $nextItemId, $filename)
					->set('type' , $itemType)
					->set('path', $itemPath)
					->set('level', $directory->level + 1);

				$nextItemId++;
				
				unset($directoryItems[$itemIndex]);
								
				if ($itemType === 'dir')
				{
					$directory->subscount++;
					$foundSubDirectory = true;
					$directory = $directoryItem;
					array_push($stack, $directoryItems);
					$directoryItems = array();
					break;
				}
				
				$directory->filecount++;
			}

			$notDone = ($stack or $directoryItems or $foundSubDirectory);
			
		} while ($notDone);
		
		return $tree;
	}
	
	public function dump($indentStr = '-', $indent = 0,  $fileWrapper = "%s<br>\n", $subDirWrapper = "<b>%s</b><br>\n", $path = null, $recursive = false)
	{
		$output = '';

		if ( ! $path)
		{
			$path = $this->path ? : __DIR__;
		}
		
		$dir = dir($path);

		$leftIndent = str_repeat($indentStr, $indent);

		while ($entry = $dir->read())
		{
			if ($entry == "." or $entry == "..")
			{
				continue;
			}

			if (is_file($path . DIRECTORY_SEPARATOR . $entry))
			{
				$output .= $leftIndent . sprintf($fileWrapper, $entry);

				continue;
			}

			$output .= $leftIndent . sprintf($subDirWrapper, $entry);

			if ($recursive)
			{
				$output .= $this->dump($indentStr, $indent + 1, $fileWrapper, $subDirWrapper, $path . DIRECTORY_SEPARATOR . $entry, true);
			}
		}

		$dir->close();

		return $output;
	}
	
	public function dumpRecursive($indentStr = '-', $indent = 0, $fileWrapper = "%s<br>\n", $subDirWrapper = "<b>%s</b><br>\n", $path = null)
	{
		return $this->dump($indentStr, $indent, $fileWrapper, $subDirWrapper, $path, true);
	}

	public function copyFiles($directory, $destination, $options = null)
	{
		if (! is_dir($directory)) { return false; }

		$options = $options ? : FilesystemIterator::SKIP_DOTS;

		// If the destination directory does not actually exist, we will go ahead and
		// create it recursively, which just gets the destination prepared to copy
		// the files over. Once we make the directory we'll proceed the copying.
		if ( ! is_dir($destination))
		{
			$this->create($destination, 0777, true);
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
	 */
	public function delete($directory = null, $preserve = false)
	{
		if ( ! $directory) { $directory = $this->path; }
		
		$directory = rtrim($directory, '\\/');
		
        foreach (array_diff(scandir($directory), array('.','..')) as $file)
		{ 
            is_dir("$directory/$file") ? $this->deleteFilesRecursive("$directory/$file") : unlink("$directory/$file"); 
        }

        return $preserve ? : rmdir($directory); 
	}

	/**
	 * Empty the specified directory of all its files.
	 * Subfolders and content are preserved.
	 */
	public function deleteFiles($directory = null)
	{
		if ( ! $directory) { $directory = $this->path; }
		
        foreach (array_diff(scandir($directory), array('.','..')) as $file)
		{ 
			if (is_file("$directory/$file")) { unlink("$directory/$file"); }
        }
	}

	/**
	 * Recursively delete only the contents of a directory.
	 *
	 * @return bool
	 */
	public function deleteFilesRecursive()
	{
		return $this->delete($this->path, true);
	}	
}
