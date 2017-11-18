<?php namespace KragDag\Services;

/**
 * Data Object State Tracker Class
 *
 * @author: C. Moller
 * @date: 12 Jan 2017
 *
 * Should be able to show / track data state and flow through all or specific page data objects.
 * E.g. Track and show object state before and after initializing, updating, importing and exporting data.
 *
 * Not sure if this can be done in an easy and useful way yet...
 *
 * Currently we need to insert capture lines at every point!
 *
 * We also need to make this class GLOBAL. Otherwise we need to inject it everywhere we try to use it!
 *
 */


use Application;
use Format;
use Log;

class DataObjectState
{
	public $snapshot = [];
	public $pass;

	public function __construct($snapshot = null, $pass = null)
	{
		if ($snapshot) $this->snapshot = $snapshot;
		if (!is_null($pass)) $this->pass = $pass;
	}

	public function setPass($pass)
	{
		$this->pass = $pass;
	}

	public function setSnapshot($snapshot)
	{
		$this->snapshot = $snapshot;
	}

	public function renderPassFail()
	{
		return isset($this->pass) ? ('<span class="vm-' . ($this->pass ? 'pass' : 'fail') . '">' . ($this->pass ? 'PASS' : 'FAIL') . '</span>') : '';
	}

	public function renderSnapshot()
	{
		return '<pre>' . print_r($this->snapshot, true) . '</pre>';
	}

	public function render()
	{
		$this->renderPassFail();
		$this->renderSnapshot();
	}
}


class DataObject
{
	public $id;
	public $name;
	public $type;
	public $state;
	public $instance = null;

	protected $__states = [];


	public function __construct($name, $type)
	{
		$this->name = $name;
		$this->type = $type;
		$this->addState(0, null, true);
	}

	public function setInstance($instance)
	{
		$this->instnance = $instance;
		return $this;
	}

	public function addState($state, $snapshot, $pass)
	{
		if ( ! is_string($state)) {
			$state = ViewModel::$states[$state];
		}
		$this->state = $state;
		$this->__states[$state] = new DataObjectState($snapshot, $pass);
		return $this;
	}

	public function setId($id)
	{
		$this->id = $d;
		return $this;
	}

	public function exists()
	{
		return !empty($this->instance);
	}

	public function state()
	{
		return $this->state;
	}

	public function renderStates($withSnapshot = false)
	{
		$html = '<ul class="vm-states">';
		foreach($this->__states as $name => $state)
		{
			if ($withSnapshot)
			{
				$html .= $state->render();
			}
			else
			{
				$html .= '<li class="vm-state-head"><label>' . $name . ':</label>' . $state->renderPassFail() . '</li>';
			}
		}
		$html .= '</ul>';
		return $html;
	}

	public function render()
	{
		$html =  '<div class="view-object">';
		$html .= '  <div class="obj-title">' . Format::title(Format::snake($this->name)) . '</div>';
		$html .= '  <ul class="obj-properties">';
		$html .= '      <li>Type: ' . $this->type .'</li>';
		$html .= '      <li>State: ' . $this->state . '</li>';
		$html .= '      <li>' . $this->renderStates() . '</li>';
		$html .= '  </ul>';
		$html .= '</div>';
		return $html;
	}
}


class DataStore extends DataObject {}
class DataStructure extends DataObject {}
class DataModel extends DataObject {}
class DataList extends DataObject {}
class DataUiModel extends DataObject {}
class DomainModel extends DataObject {}


class Tracker
{
	public static $states =
	[
		'non-existent',
		'exists-but-empty',
		'got-initialvalues',
		'got-initialvalues-transformed',
		'initialvalues-applied',
		'got-workingstate',
		'got-workingstate-transformed',
		'workingstate-applied',
		'got-flashed-errors',
		'got-flashed-errors-transformed',
		'flashed-errors-applied',
		'got-userinputs',
		'got-userinputs-transformed',
		'got-userinputs-validated',
		'got-validation-errors',
		'userinputs-applied',
		'got-content-to-flash',
		'got-content-to-save',
	];

	protected $__data = [
		'dataStores' 		=> [],
		'dataStructures' 	=> [],
		'dataModels' 		=> [],
		'dataLists' 		=> [],
		'dataUiModels' 		=> [],
		'domainModels' 		=> []
	];

	public $scenario = 'unknown';
	public $scenarios =
	[
		'unknown',
		'get_new',
		'get_update',
		'post_new',
		'post_update',
	];
	public $objGroups = [
		'dataStores',
		'dataStructures',
		'dataModels',
		'dataLists',
		'dataUiModels',
		'domainModels'
	];

	public $name;
	public $storeDir;


	public function __construct($name = 'viewmodel', $storeDirectory = __ROOT__)
	{
		$this->name = $name;
		$this->storeDir = $storeDirectory;
		Application::add_event('shutdown', 'shutdown', $this, get_called_class());
		$this->load();
	}


	public function setScenario($scenario)
	{
		$this->scenario = $scenario;
		return $this;
	}

	public function addDataStore($name, $type) { $this->__data['dataStores'][$name] = new DataStore($name, $type);	}
	public function addDataStruct($name, $type) { $this->__data['dataStructures'][$name] = new DataStructure($name, $type); }
	public function addDataModel($name, $type) { $this->__data['dataModels'][$name] = new DataModel($name, $type); }
	public function addDataList($name, $type) { $this->__data['dataLists'][$name] = new DataList($name, $type); }
	public function addUiModel($name, $type) { $this->__data['dataUiModels'][$name] = new DataUiModel($name, $type); }
	public function addDomainModel($name, $type) { $this->__data['domainModels'][$name] = new DomainModel($name, $type); }

	public function getDataStore($name) { return $this->__data['dataStores'][$name];	}
	public function getDataStruct($name) { return $this->__data['dataStructures'][$name]; }
	public function getDataModel($name) { return $this->__data['dataModels'][$name]; }
	public function getDataList($name) { return $this->__data['dataLists'][$name]; }
	public function getUiModel($name) { return $this->__data['dataUiModels'][$name]; }
	public function getDomainModel($name) { return $this->__data['domainModels'][$name]; }


	public function render()
	{
		$html = '';
		foreach ($this->objGroups as $group)
		{
			$html .= '<div class="vm-group"><h4>' . Format::title(Format::snake($group)) . ':</h4>' . NL;
			foreach (isset($this->__data[$group]) ? $this->__data[$group] : [] as $obj)
			{
				$html .= $obj->render() . NL;
			}
			$html .= '</div>';
		}
		return $html;
	}


	public function save()
	{
		Log::vm('ViewModel::save(), Start');
		$next_id = (int)file_get_contents($this->storeDir . '/next-id.php');
		$next_id++; file_put_contents($this->storeDir . '/next-id.php', $next_id);
		$dir = $this->storeDir . '/' . $this->name;
		$filename_printr = $dir . '/' . $this->scenario . '_print_' . $next_id . '.php';
		$filename_serialized = $dir . '/serialized.php';
		if ( ! file_exists($dir)) { Log::vm('ViewModel::save(), Dir not found! Create: ' . $dir); mkdir($dir); }
		file_put_contents($filename_serialized, serialize($this->__data));
		file_put_contents($filename_printr, print_r($this->__data, true));
	}


	public function load()
	{
		Log::vm('ViewModel::load(), Start');
		$dir = $this->storeDir . '/' . $this->name;
		$filename = $dir . '/serialized.php';
		$this->__data = file_exists($filename) ? unserialize(file_get_contents($filename)) : [];
	}


	public function initialized()
	{
		$initialized = !empty($this->__data);
		Log::shutdown('ViewModel::initialized(), ' . ($initialized ? 'YES' : 'NO'));
		return $initialized;
	}


	public function shutdown()
	{
		Log::shutdown('ViewModel::shutdown(), Start');
		$this->save();
	}


	public function __toString()
	{
		return $this->render();
	}
}
