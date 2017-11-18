<?php

include 'database.php';


class DbField
{
	public $name, $type, $allow_null, $is_index, $def_value, $extras;

	function __construct($field_def)
	{
		$this->name		  = $field_def[0];
		$this->type		  = $field_def[1];
		$this->allow_null = $field_def[2];
		$this->is_index	  = $field_def[3];
		$this->def_value  = $field_def[4];
		$this->extras	  = $field_def[5];
		//echo $this,BR;
	}

	function __toString()
	{
		return 'FIELD: name='.$this->name.', type='.$this->type.', allow_NULL='.$this->allow_null.
			', is_index='.$this->is_index.', def_value ='.$this->def_value.', extras = '.$this->extras;
	}
}

class DbRecord
{
	public $table,$name,$rec_id,$rec_id_field,$post_prefix,$data,$fieldsets,$fieldset;

	function __construct(DbTable $table, $name, $record_id, $record_id_field='', $post_prefix='')
	{
		$this->table = $table;
		$this->name = $name;
		$this->rec_id = $record_id;
		if (!$record_id_field) $this->rec_id_field = $table->pk->name; else $this->rec_id_field = $record_id_field;
		$this->post_prefix = $post_prefix;

		$this->data = array($record_id_field => $record_id);
		$this->fieldsets['Default'] = '*';
		$this->fieldset = '*';
	}

	function add_fieldset($name,$include_fields='Default',$exclude_fields='',$set_as_default=true)
	{
		if (!$name) return false;
		if ($exclude_fields) { //we want to subtract from an existing fieldset!
			$include_fields = $this->fieldsets[$include_fields]; //include_fields should be the name of an existing fieldset
			if ($include_fields == '*') $include_parts = $this->table->fieldnames; else	$include_parts = explode(',',$include_fields);
			$exclude_parts = explode(',',$exclude_fields);
			$fieldset = implode(',',array_diff($include_parts, $exclude_parts));
		} else $fieldset = $include_parts;
		$this->fieldsets[$name] = $fieldset;
		if ($set_as_default) $this->fieldset = $fieldset;
	}

	function select_fieldset($fieldset_name)
	{
		$this->fieldset = $this->fieldsets[$fieldset_name];
	}

	function save_state()
	{
		$_SESSION[$this->name.'_state'] = serialize($this->data);
	}

	function get_updates()
	{
		/*** Get POST data ***/
		foreach ($this->table->fields as $field)
		{
			if (isset($_POST[$field->name])) $this->data[$field->name] = $_POST[$field->name];
		}
		$this->save_state();
	}

	function restore_state()
	{
		$this->data = unserialize($_SESSION[$this->name.'_state']);
		$this->get_updates();
	}

	function set_state(&$data)
	{
		$this->data = $data;
	}

	function db_read()
	{
		$this->data = db_select_rec($this->table->name, $this->fieldset, $this->rec_id, $this->rec_id_field);
		$this->save_state();
	}

	function db_update()
	{
		return db_update($this->table->name, $this->data, $this->table->pk->name.' = "'.$this->rec_id.'"');
	}

	function db_insert()
	{
		$table = $this->table;
		if ($table->autoincrement) unset($this->data[$table->pk->name]);
		/** Do INSERT */
		$new_id = db_insert($table->name, $this->data);
		$this->data[$table->pk->name]= $new_id;
		if ($this->rec_id_field == $table->pk->name) $this->rec_id = $new_id;
		return $new_id;
	}

	function db_insert_as($new_id)
	{
		$table = $this->table;
		$this->data[$table->pk->name] = $new_id;
		/** Do INSERT */
		$new_id = db_insert($table->name, $this->data); //I know I re-assign a given var! It is just for error checking.. my excuse ;-)
		$this->rec_id = $new_id;
		return $new_id;
	}

	function get_data($initialize=false)
	{
		if (empty($_POST) || $initialize) $this->db_read(); else $this->restore_state();
	}

	function __toString()
	{
		$result = 'RECORD:'.NL;
		$result .= 'RecID = '.$this->rec_id.NL;
		$result .= 'RecID_Field = '.$this->rec_id_field.NL;
		$result .= 'Name = '.$this->name.NL;
		$result .= 'DbTableName = '.$this->table->name.NL;
		if ($this->table->pk) $result .= 'DbTablePK = '.$this->table->pk->name.NL; else $result .= 'DbTablePK = Not Defined';
		$result .= 'PostPrefix = '.$this->post_prefix.NL;
		if (!empty($this->data)) $result .= print_assoc_array ('DATA', $this->data, false).NL; else $result .= 'DATA = Empty'.NL;
		return $result;
	}
}

class DbRecordList
{
	/**
	 *
	 * @var DbTable
	 */
	public $table;
	
	public
		$name,
		$itemcount,
		$fieldsets,
		$fieldset,
		$records;

	function __construct(DbTable $table, $name)
	{
		$this->table = $table;
		$this->name = $name;
		$this->itemcount = 0;
		$this->records = array();
		$this->fieldsets['Default'] = '*';
		$this->fieldset = '*';
	}

	function add_fieldset($name,$include_fields='Default',$exclude_fields='',$set_as_default=true)
	{
		if (!$name) return false;
		if ($exclude_fields) { //we want to subtract from an existing fieldset!
			$include_fields = $this->fieldsets[$include_fields]; //include_fields should be the name of an existing fieldset
			if ($include_fields == '*') $include_parts = $this->table->fieldnames; else	$include_parts = explode(',',$include_fields);
			$exclude_parts = explode(',',$exclude_fields);
			$fieldset = implode(',',array_diff($include_parts, $exclude_parts));
		} else $fieldset = $include_parts;
		$this->fieldsets[$name] = $fieldset;
		if ($set_as_default) $this->fieldset = $fieldset;
	}

	function select_fieldset($fieldset_name)
	{
		$this->fieldset = $this->fieldsets[$fieldset_name];
	}

	function get_records($where='',$orderby='',$limit='',$fieldset_name='Default')
	{
		$fields = $this->fieldsets[$fieldset_name];

		$sql = db_select_query($this->table->name, $fields, $where, $orderby, $limit);

		$records = mysql_query($sql) or File::log('Error','DbRecordList->get_records() - Error: '.mysql_error().', Query: '.$sql);

		$this->itemcount = mysql_num_rows($records);

		if ($this->itemcount > 0)
		{
			$pk = $this->table->pk->name;
			$n=0;
			while ($data = mysql_fetch_assoc($records)) {
				$record_id = $data[$pk];
				$record = new DbRecord($this->table, 'ListItem'.$n, $record_id);
				$record->set_state($data);
				$this->records[$record_id] = $record;
				$n++;
			}
		}

		return $this->itemcount;
	}
}

class DbTable
{
	public
		$name,
		$fields,
		$fieldnames,
		$autoincrement,
		/** @var DbField */ $pk;

	function __construct($tablename)
	{
		$this->name = $tablename;
		$field_defs = db_get_fields($tablename);
		while ($field_def = mysql_fetch_array($field_defs))	{
			$field = new DbField($field_def);
			if ($field->is_index == 'PRI') $this->pk = $field;
			$this->fields[$field->name] = $field;
			$this->fieldnames[] = $field->name;
		}
		if ($this->pk && $this->pk->extras == 'auto_increment') $this->autoincrement = true; else $this->autoincrement = false;
	}

	function __toString()
	{
		$result = 'TABLE '.$this->name.NL;
		$i = 0;
		foreach ($this->fields as $name=>$field) {
			if ($i) $result .= NL;
			$result .= $name.' - '.$field->type.'';
			$i++;
		}
		if ($this->pk) $result .= NL.NL.'Primary Key = '.$this->pk->name;
		return $result;
	}
}

class DbConnection
{
	public $DB;
	public $DB_NAME;
	public $DB_USER;
	public $DB_PASSWORD;
	public $DB_HOST = 'localhost';


	public function __construct($name,$user,$password,$host=null)
	{
		$this->DB_NAME = $name;
		$this->DB_USER = $user;
		$this->DB_PASSWORD = $password;
		
		if($host)
			$this->DB_HOST = $host;
	}
	
	function connect($name=null)
	{
		if(!$name)
			$name = $this->DB_NAME;
		
		$this->DB = mysql_connect( $this->DB_HOST , $this->DB_USER , $this->DB_PASSWORD ) or die('Failed to connect to database: '.$name);
		
		mysql_select_db( $name, $this->DB );

		mysql_query('SET NAMES "utf8"', $this->DB);
		
		return $this->DB;
	}
}