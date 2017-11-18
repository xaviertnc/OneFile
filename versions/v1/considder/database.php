<?php  	
function db_connect()
{
	$ok = mysql_connect( __DB_HOST__ , __DB_USER__ , __DB_PASSWORD__ );
	mysql_query('SET NAMES "utf8"');
	return $ok;
}

//Note: Once you have a connection to the MySQL server, you must also select the database to use
//with -> mysql_select_db( $dbname , $db );
function db_connect_ext($host,$user,$pw,$new_link,$dbname)
{
	$ok = mysql_connect($host,$user,$pw,$new_link);
	mysql_query('SET NAMES "utf8"');
	return $ok;
}

function db_get_databases($link_identifier='')
{
  global $main_db;
  if (!$link_identifier) $link_identifier = $main_db;
  $recordset = mysql_query ('SHOW DATABASES',$link_identifier) or die(mysql_error());
  return $recordset;
}

function db_get_tables($dbname,$link_identifier='')
{
  global $main_db;
  if (!$link_identifier) $link_identifier = $main_db;
  $recordset = mysql_query ('SHOW TABLES FROM `'.$dbname.'`',$link_identifier);
  if (mysql_error()) echo mysql_error(),BR;
  return $recordset;
}

function db_get_fields($tablename,$link_identifier='')
{
  global $main_db;
  if (!$link_identifier) $link_identifier = $main_db;
  $recordset = mysql_query ('SHOW COLUMNS FROM `'.$tablename.'`',$link_identifier);
  if (mysql_error()) echo mysql_error(),BR;
  return $recordset;
}

function db_get_databases_as_array($link_identifier='')
{
	$result = db_get_databases($link_identifier);
	if (!$result) return array();
	$i = 1;
	$dbs = array();
	while ($row = mysql_fetch_array($result))
	{
		$dbs[$i] = $row[0];
		$i++;
	}
	return $dbs;
}

function db_get_tables_as_array($dbname='',$link_identifier='')
{
	$result = db_get_tables($dbname,$link_identifier);
	if (!$result) return array();
	$i = 1;
	$tables = array();
	while ($row = mysql_fetch_array($result))
	{
		$tables[$i] = $row[0];
		$i++;
	}
	return $tables;
}

function db_get_fields_as_array($tablename='',$link_identifier='')
{
	$result = db_get_fields($tablename,$link_identifier);
	if (!$result) return array('_names_'=>array());
	$i = 1;
	$fields = array('_names_'=>array());
	while ($row = mysql_fetch_array($result))
	{
		//The array key naming with undescores is to avoid clashing of field names with the
		//subset names like 'names', 'types' etc.
		//The last declaration uses field names as keys to allow field index lookup by name
		$fields['_names_'][$i]  = $row[0];
		$fields['_types_'][$i]  = $row[1];
		$fields['_nulls_'][$i]  = $row[2];
		$fields['_keys_'][$i]   = $row[3];
		$fields['_defs_'][$i]   = $row[4];
		$fields['_extras_'][$i] = $row[5];
		$fields[$row[0]]        = $i;
		$i++;
	}
	return $fields;
}

function db_select_query($dbtable,$fields='*',$where='',$orderby='',$limit='')
{
	if (!$dbtable) die('ERROR: db_select_query - dbtable not defined');
	$query = 'SELECT '.$fields.' FROM `'.$dbtable.'`';
	if ($where) $query .= ' WHERE '.$where;
	if ($orderby) $query .= ' ORDER BY '.$orderby;
	if ($limit) $query .= ' LIMIT '.$limit;
	File::log('MySql','SELECT Query: '.$query);
	return $query;
}

function db_get_value($table,$field,$where='')
{
	$sql = db_select_query($table, $field, $where, '', 1);
	//echo 'db_get_value_sql = '.$sql; BR();
    $recordset = mysql_query($sql) or die(mysql_error());
    if (mysql_num_rows($recordset) > 0)
    {
		$rec = mysql_fetch_assoc($recordset); return $rec[$field];
    }
    else return '';
}

function db_get_value_by_id($table,$dispfield,$idfield,$idvalue)
{
	$where = '`'.$idfield.'`="'.mysql_escape_string($idvalue).'"';
	return db_get_value($table, $dispfield, $where);
}

function db_get_list(&$list,$table,$field,$keyfield='',$where='',$orderby='',$limit='',$field_alias='')
{
    if($keyfield) $select = $field.','.$keyfield; else $select = $field;
	$sql = db_select_query($table, $select, $where, $orderby, $limit);
	//echo 'db_get_list - Query:'.$sql; BR();
    $recordset = mysql_query($sql);
	if(mysql_error())
	{
		File::log('Error','db_get_list - Error: '.mysql_error().', Query: '.$sql);
		return 0;
	}
    $list = array();
	$list_items_count = mysql_num_rows($recordset);
    if ( $list_items_count > 0)
    {
        if (!$field_alias) $field_alias = $field;
		while ($rec = mysql_fetch_array($recordset)) {
			if ($keyfield) {
				$list[$rec[$keyfield]] = $rec[$field_alias];
			} else {
				$list[] = $rec[$field];
			}
		}
    }
	//echo 'List: ',get_array($list); BR();
	return $list_items_count;
}

function db_get_list_ext(&$list,$table,$select='',$keyfield='',$where='',$orderby='',$limit='')
{
    //NOTE: Remember to add the KeyField to your select fields list!
	if (!$select) {	if ($keyfield) $select=$keyfield; else $select='*';	}
    $sql = db_select_query($table, $select, $where, $orderby, $limit);

    $recordset = mysql_query($sql);
	if(mysql_error())
	{
		File::log('Error','db_get_list_ext - Error: '.mysql_error().', Query: '.$sql);
		return 0;
	}

	$list_items_count = mysql_num_rows($recordset);

	if ( $list_items_count > 0)
    {
		$n = 0;
	    $list = array();
		while($rec = mysql_fetch_assoc($recordset))
		{
			foreach($rec as $fieldname=>$fieldvalue)
			{
				if($keyfield)
				{
					if($fieldname === $keyfield)
						$keyfield_value = $fieldvalue;
					else
						$list[$keyfield_value][$fieldname] = $fieldvalue;
				}
				else {
					$list[$n][$fieldname] = $fieldvalue;
				}
			}
			$n++;
		}
    }
	
//	print_r($list); BR();
	return $list_items_count;
}

function db_inc_value($dbtable,$fieldname,$inc='',$where='')
{
	File::log('DBase','db_inc_value(): Start, inc='.$inc);
	if ($inc == '') $inc = 1;
	if (!$inc) return false;
	if ($inc > 0) $inc = '+'.$inc; else $inc = '-'.abs($inc);
	if ($where) $where = ' WHERE '.$where;
	$query = 'UPDATE '.$dbtable.' SET '.$fieldname.' = ('.$fieldname.$inc.')'.$where;
	File::log('MySql','INCREMENT/DECREMENT VALUE Query: '.$query);
	mysql_query($query);
	if (mysql_error()) {
		File::log('Error','db_inc_value() Error: '.mysql_error().', Query: '.$query);
		return false;
	}
	return true;
}

function db_dec_value($dbtable,$fieldname,$dec='',$where='')
{
	if (!$dec) $dec = 1;
	if ($dec > 0) $inc = '-'.$dec; else $inc = $dec;
	return db_inc_value($dbtable, $fieldname, $inc, $where);
}

function db_count($dbtable='',$primarykey='id',$where='')
{
	if (!$dbtable) return 0;
	//NOTE: Find out how / if poddible to optimise this...
	if ($where) $where = ' WHERE '.$where;
	$query = 'SELECT count('.$primarykey.') as totalrecs FROM '.$dbtable.$where;
	File::log('MySql','COUNT Query: '.$query);
	$results = mysql_query($query);
	if (mysql_error()) {
		File::log('Error','COUNT Query Error! Msg:'.mysql_error().', Query:'.$query);
		return false;
	}
	$result = mysql_fetch_assoc($results);
	File::log('MySql','COUNT Query Result = '.$result['totalrecs']);
	return $result['totalrecs'];
}

function db_insert($dbtable='',$data=array(),$ignore_duplicates=false)
{
	if (!$dbtable || !count($data)) return 0;
	foreach ($data as $field => $value)
	{
		$fields_array[] = $field;
//		File::log('Notice','field: '.$field.', value='.$value);
		if ($value === null) $values_array[] = 'NULL'; else	$values_array[] = '"'.mysql_escape_string($value).'"';
	}
	$fields = implode(',', $fields_array);
	$values = implode(',', $values_array);
	$ignore = $ignore_duplicates?' IGNORE':'';
	$query = 'INSERT'.$ignore.' INTO '.$dbtable.' ('.$fields.') VALUES ('.$values.')';
	File::log('MySql','INSERT Query: '.$query);
	$result = mysql_query($query);
	if (mysql_error())
		File::log('Error','DB INSERT Error ='.mysql_error().', Query = '.$query);
	if (!$result) return false;
	return mysql_insert_id();
}

//Remember - The UPDATE_DATA array might have fewer columns since some of the INSERT_DATA columns are static or are autoincrement keys! - NM 26 Sep 2012
function db_insert_update($dbtable='',$insert_data=array(), $update_data=array())
{
	if(!$dbtable || !count($insert_data)) return 0;
	foreach($insert_data as $field => $value)
	{
		$insert_fields_array[] = $field;
		if($value === null)
			$insert_values_array[] = 'NULL';
		else
			$insert_values_array[] = '"'.mysql_escape_string($value).'"';
	}

	if(!$update_data)
		$update_data = $insert_data;

	foreach($update_data as $field => $value)
	{
		if($value === null)
			$update_assignments_array[] = $field.' = NULL';
		else
			$update_assignments_array[] = $field.' = "'.mysql_escape_string($value).'"';
	}

	$insert_fields = implode(',', $insert_fields_array);
	$insert_values = implode(',', $insert_values_array);
	$update_assignments = implode(',', $update_assignments_array);

	$query = 'INSERT INTO '.$dbtable.' ('.$insert_fields.') VALUES ('.$insert_values.') ON DUPLICATE KEY UPDATE '.$update_assignments;

	File::log('MySql','INSERT-OR-UPDATE Query: '.$query);
	$result = mysql_query($query);
	if(mysql_error())
		File::log('Error','DB INSERT-OR-UPDATE Error! Msg: '.mysql_error().', Query: '.$query);

	if(!$result) return false;

	if(mysql_affected_rows() == 1)
		return mysql_insert_id();
	else
		return false;
}

function db_update($dbtable='',$data=array(),$where='',$limit='')
{
	if(!$dbtable || !count($data)) return false;
	foreach($data as $field => $value)
	{
		if($value === null) //Changed from == to === ... NM 13 Nov 2012!
			$assignments_array[] = $field.' = NULL';
		else
			$assignments_array[] = $field.' = "'.mysql_escape_string($value).'"';
	}
	if($where) $where = ' WHERE '.$where;
	if($limit) $limit = ' LIMIT '.$limit;
	$assignments = implode(',', $assignments_array);
	$query = 'UPDATE '.$dbtable.' SET '.$assignments.$where.$limit;
	File::log('MySql','UPDATE Query: '.$query);
	mysql_query($query);
	if(mysql_error())
	{
		File::log('Error','db_update(), MySql Error! Msg:'.mysql_error().', Query:'.$query);
		return false;
	}
	return mysql_affected_rows(); //Added by NM 02 Sep 2012
}

function db_update_rec_field($dbtable='',$field='',$fieldval='',$id='',$pkey='id'/*, $emptyIsNull=false*/)
{
	return db_update($dbtable, array($field=>$fieldval), $pkey.'="'.mysql_escape_string($id).'"',1);
}

function db_select_rec($dbtable='',$fields='*',$id='',$pkey='id')
{
	$result = array();
//	File::log('MySql','SELECT RECORD:');
	$query = db_select_query($dbtable, $fields, $pkey.'="'.mysql_escape_string($id).'"','',1);
	$results = mysql_query($query);
	if (mysql_error()) {
		File::log('Error','db_select_rec(), MySql Error! Msg:'.mysql_error().', Query:'.$query);
		return false;
	}
	if (mysql_num_rows($results) > 0)	{
		while ($result = mysql_fetch_assoc($results)) return $result;
	}
	return $result;
}

function db_delete($dbtable='',$where='',$limit='')
{
	if (!$dbtable || !$where) return false;
	if ($where) $where = ' WHERE '.$where;
	if ($limit) $limit = ' LIMIT '.$limit;
	$query = 'DELETE FROM '.$dbtable.$where.$limit;
	File::log('MySql','DELETE Query: '.$query);
	mysql_query($query);
	if (mysql_error()) {
		File::log('Error','DB DELETE Query Error:'.mysql_error());
		return false;
	}
	return true;
}

function db_delete_rec($dbtable='',$id='', $id_field='')
{
	if (!$dbtable || !$id) return false;
	if (!$id_field) $id_field='id';
	$where = ' WHERE '.$id_field.'="'.mysql_escape_string($id).'"';
	$query = 'DELETE FROM '.$dbtable.$where;
	File::log('MySql','DELETE RECORD Query: '.$query);
	mysql_query($query);
	if (mysql_error()) {
		File::log('Error','DELETE RECORD Query Error:'.mysql_error());
		return false;
	}
	return true;
}
