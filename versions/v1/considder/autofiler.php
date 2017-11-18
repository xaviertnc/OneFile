<?php namespace OneFile;

/**
 * File: auto-filer.class.php
 *
 * @author C. Moller - 30 May 2012
 * 
 */
class AutoFiler
{
	protected
		$source_path,
		$dest_base_path,
		$source_filename,
		$filer_style,
		$dest_path,
		$dest_sub_path,
		$dest_filename,
		$dest_file_ext,
		$params;

	public
		$filer_datetime;

	/**
	 * Return the quarter for a timestamp.
	 * @returns integer
	 */
	protected function quarter($ts) {
	   return ceil(date('n', $ts)/3);
	}

	protected function sanitize_value($value='')
	{
		if (!$value) return '';
		return preg_replace('/[^a-z0-9]/i','',$value);
	}

	//Can override for custom filing scheme
	protected function make_subpath()
	{
//		File::log('Filer','AutoFiler::make_subpath(), Start');
		switch($this->filer_style)
		{
			case 'Y':
				return date('Y',$this->filer_datetime);
			case 'YQ':
				return date('Y',$this->filer_datetime).'Q'.$this->quarter($this->filer_datetime);
			case 'Ym':
				return date('Ym',$this->filer_datetime);
			case 'Y/m':
				return date('Y',$this->filer_datetime) . '/' . date('m',$this->filer_datetime);
			case 'type/Ym':
				return $this->params['type'] . '/' . date('Ym',$this->filer_datetime);
			case 'type':
				return $this->params['type'];
			default:
//				File::log('Filer','AutoFiler::make_subpath(), Return Empty String');
				return '';
		}
	}

	//Can override for custom filing scheme
	protected function make_filename()
	{
		return $this->source_filename;
	}

	public function move($source_path, $source_filename, $dest_file_ext='', $params=array(), $overwrite_destfile=true, $delete_sourcefile=true, $permissions=0775)
	{
		$this->source_path = $source_path;
		$this->source_filename = $source_filename;
		$this->dest_file_ext = $dest_file_ext;
		$this->params = $params;
		$this->dest_filename = $this->make_filename();
		$this->dest_sub_path = $this->make_subpath();

		if($this->dest_sub_path)
		{
			$this->dest_path = $this->dest_base_path . '/' . $this->dest_sub_path;
			$this->dest_sub_path .= '/';
		}
		else
			$this->dest_path = $this->dest_base_path . '/' . $this->dest_sub_path;

		$result = File::move($this->source_path,$this->dest_path,
			$this->source_filename,$this->dest_filename,$overwrite_destfile,$delete_sourcefile,$permissions);

		if(!$result)
		{
			File::log('Error','AutoFiler::move(), Operation Failed! Log "File" events to see detail error messages.');
			$result = $this->dest_filename;
		}

		return $this->dest_sub_path . $result;
	}

	public function copy($source_path, $source_filename, $dest_file_ext='', $params=array(), $overwrite_destfile=true, $permissions=0775)
	{
		$this->source_path = $source_path;
		$this->source_filename = $source_filename;
		$this->dest_file_ext = $dest_file_ext;
		$this->params = $params;
		$this->dest_filename = $this->make_filename();
		$this->dest_sub_path = $this->make_subpath();

		if($this->dest_sub_path)
		{
			$this->dest_path = $this->dest_base_path . '/' . $this->dest_sub_path;
			$this->dest_sub_path .= '/';
		}
		else
			$this->dest_path = $this->dest_base_path . '/' . $this->dest_sub_path;

		File::log('Filer','AutoFiler::copy(), source_filename = '.$this->source_filename);

		$result = File::copy($this->source_path,$this->dest_path,
			$this->source_filename,$this->dest_filename,$overwrite_destfile,$permissions);

		if(!$result)
		{
			File::log('Warning','AutoFiler::copy(), Operation Failed...  Probably because source file: "'.
				$this->source_filename.'" could not be found!');
			$result = $this->dest_filename;
		}

		return $this->dest_sub_path . $result;
	}

	public function __construct($dest_base_path, $filer_style='', $filer_datetime=null)
	{
		$this->dest_base_path = $dest_base_path;
		$this->filer_style = $filer_style;
		
		if($filer_datetime)
			$this->filer_datetime = strtotime($filer_datetime);
		else
			$this->filer_datetime = time();
	}
}
