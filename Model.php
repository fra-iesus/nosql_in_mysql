<?php
class Model {
	var $definition = array (
		'class'         => '',
		'columns'       => array (),
		'keys'          => array('uid'),
		'autoloaders'   => array (),
		'autoincrement' => null
	);
	var $values     = array ();
	var $old_values = array ();

	protected $tables = array (
		'STR' => 'ois_str',
		'INT' => 'ois_int',
		'BIN' => 'ois_blob'
	);

	protected $loaded_classes = array ();
	protected $_loaded = false;
	protected $db      = null;

	function __construct() {
		if (!is_array($this->definition['columns'])) {
			$this->definition['columns'] = array();
		}
		if (!is_array($this->definition['keys'])) {
			$this->definition['keys'] = array();
		}
		if (!is_array($this->definition['autoloaders'])) {
			$this->definition['autoloaders'] = array();
		}
		$args = func_get_args();
		$this->db = $args[0];
		if (sizeof($args) == 2) {
			if (!sizeof($this->definition['keys']) & is_array($args[1]) && sizeof($args[1]) == 1) {
				$args[1] = $args[1][0];
			}
			if (is_array($args[1]) ) {
				$args = $args[1];
				if ( sizeof($args) == sizeof($this->definition['keys']) ) {
					call_user_func_array(array($this , 'assign_keys'), $args);
					$this->load();
				}
			} elseif ($args[1] > 0) {
				$this->uid($args[1]);
				$this->load();
			}
		}
		return $this;
	}

	function is_loaded() {
		return $this->_loaded;
	}

	function load($force = false) {
		if ((!$force && $this->_loaded) || (!$this->values['uid'] && !sizeof($this->definition['keys']))) {
			return;
		}
		$this->_loaded = false;
		if (!$this->values['uid']) {
			$query = 'SELECT om.uid';
			$join = '';
			foreach ($this->definition['keys'] as $key => $value) {
				if (array_key_exists($key, $this->definition['columns'])) {
					$table = $this->tables[$this->definition['columns'][$key]['type']];
				} else {
					$table = 'ois_str';
				}
				$join .= (array_key_exists($value, $this->values) ? 'INNER' : 'LEFT' ) . ' JOIN ' . $table .' AS tbl_' . $value . ' ' .
					'ON (' .
						'om.uid = tbl_' . $value . '.uid AND ' .
						'tbl_' . $value . '.prop_name = "' . mysqli_real_escape_string($this->db,$value) . '"' .
						' AND tbl_' . $value . '.prop_value = ' . $this->prepare_value($value) .
					') ';
			}
			$query .= ' FROM ois_main AS om ' . $join . 'WHERE om.class = "' . mysqli_real_escape_string($this->db,$this->definition['class']) . '"';
			if ($res = mysqli_query($this->db,$query)) {
				if ($values = mysqli_fetch_assoc($res)) {
					$this->values['uid'] = $values['uid'];
				}
			}
			if (!$this->values['uid']) {
				return false;
			}
		}
		$this->old_values = array ();
		foreach ($this->tables as $key => $value) {
			$query = 'SELECT om.class, tbl_col.* ' .
				'FROM ois_main om ' .
				'INNER JOIN ' . $value . ' AS tbl_col ON (om.uid = tbl_col.uid) ' .
				'WHERE om.uid = ' . $this->prepare_value('uid');
			if (!$this->load_by_query($query, $key)) {
				return false;
			}
		}
		return $this->_loaded;
	}

	function save() {
		$params = $this->all_values();
		if (!$this->_loaded) {
			if (!$this->uid) {
				$this->next_uid();
			}
			$query = 'INSERT INTO ois_main (uid, class) VALUES (' . $this->prepare_value('uid') . ', "' . mysqli_real_escape_string($this->db,$this->definition['class']) . '")';
			if ($res = mysqli_query($this->db,$query)) {
				$this->values['uid'] = mysqli_insert_id($this->db);
			} else {
				return false;
			}
		}
		foreach ($this->values as $key => $value) {
			if ($key != 'uid' && !$this->definition['columns'][$key]['saved']) {
				if ($this->definition['columns'][$key]['loaded']) {
					$query = 'UPDATE ' . $this->tables[$this->definition['columns'][$key]['type']] . ' ' .
					'SET prop_value = ' . $this->prepare_value($key) . ' ' .
					'WHERE uid = ' . $this->prepare_value('uid') . ' AND prop_name = "' . mysqli_real_escape_string($this->db,$key) . '"';
					if (!$res = mysqli_query($this->db,$query)) {
						return false;
					}
				} else {
					$query = 'INSERT INTO ' . $this->tables[$this->definition['columns'][$key]['type']] . ' ' .
					'(uid, prop_name, prop_value) ' .
					'VALUES (' . $this->prepare_value('uid') . ', "' . mysqli_real_escape_string($this->db,$key) . '", ' . $this->prepare_value($key) . ')';
					if (!$res = mysqli_query($this->db,$query)) {
						return false;
					}
					$this->definition['columns'][$key]['saved'] = true;
					$this->definition['columns'][$key]['loaded'] = true;
				}
			}
		}
		$this->old_values = array ();
		return ($this->_loaded = true);
	}

	function delete() {
		if (!$this->_loaded) {
			return false;
		}
		foreach ($this->tables as $key => $value) {
			$query = 'DELETE FROM ' . $value . ' WHERE uid = ' . $this->prepare_value('uid');
			if (!mysqli_query($this->db,$query)) {
				return false;
			}
		}
		$query = 'DELETE FROM ois_main WHERE uid = ' . $this->prepare_value('uid');
		if (!mysqli_query($this->db,$query)) {
			return false;
		}
		$this->definition['columns'] = array();
		$this->values = array();
		$this->_loaded = false;
		return true;
	}

	function changed_columns() {
		$changed = array();
		foreach ($this->definition['columns'] as $key => $value) {
			if (!$this->definition['columns'][$key]['saved']) {
				$changed[$key] = $this->old_values[$key];
			}
		}
		return $changed;
	}

	function set_class($class) {
		$this->definition['class'] = $class;
		return $this;
	}

	function get_class() {
		return $this->definition['class'];
	}

	function parse_all($params, $type = 'STR') {
		foreach ($params as $key => $value) {
			if (!is_array($value)) {
				$this->$key($value, $type);
			}
		}
		return $this;
	}

	function to_hash($deep = 0) {
		if ($deep > 16) {
			return;
		}
		$hash = array();
		foreach ($this->definition['columns'] as $key => $value) {
			$hash[$key] = $this->values[$key];
		}
		foreach ($this->loaded_classes as $class_name => $class) {
			$hash[$class_name] = $class->to_hash($deep + 1);
		}
		$hash[uid] = $this->values[uid];
		return $hash;
	}

	function upload($column, $file, $type = 'BIN') {
		if ($file['error'] == UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
			$this->$column(file_get_contents($file['tmp_name']), $type);
		}
	}

	public function parse_params($params) {
		foreach ( $this->definition['columns'] as $key => $val ) {
			if (isset($params[$key])) {
				$this->$key = $params[$key];
			} else {
				$this->$key = '';
			}
		}
		if (isset($params['uid'])) {
			$this->uid = $params['uid'];
		}
	}

	public function __invoke() {
		if (func_num_args() == 1) {
			return $this->values[func_get_arg(0)];
		} else {
			$name = func_get_arg(0);
			$value = func_get_arg(1);
			if (!isset($this->values[$name]) || $this->values[$name] != $value) {
				$this->old_values[$name] = $this->values[$name];
				$this->values[$name] = $value;
				if (array_key_exists($name, $this->definition['columns']) || $name == 'uid') {
					if ($name != 'uid') {
						$this->definition['columns'][$name]['saved'] = false;
					}
				} else {
					$this->definition['columns'][$name] = array (
						required => false,
						type     => 'STR',
						saved    => false,
						loaded   => false
					);
				}
			}
		}
	}

	public function __set($name, $value) {
		if (!$name) {
			return;
		}
		if (array_key_exists($name, $this->definition['columns']) || $name == 'uid') {
			if ($name != 'uid' && $this->values[$name] != $value) {
				$this->definition['columns'][$name]['saved'] = false;
			}
		} else {
			$this->definition['columns'][$name] = array (
				required => false,
				type     => 'STR',
				saved    => false,
				loaded   => false
			);
		}
		$this->old_values[$name] = $this->values[$name];
		$this->values[$name] = $value;
	}

	public function __call($name, $arguments) {
		if (array_key_exists($name, $this->definition['columns']) || $name == 'uid') {
			if ($name != 'uid' && $this->values[$name] != $arguments[0]) {
				$this->definition['columns'][$name]['saved'] = false;
			}
		} else {
			$this->definition['columns'][$name] = array (
				required => false,
				type     => $arguments[1] ? $arguments[1] : 'STR',
				saved    => false,
				loaded   => false
			);
		}
		$this->old_values[$name] = $this->values[$name];
		$this->values[$name] = $arguments[0];
	}

	public function __get($name) {
		if (array_key_exists($name, $this->definition['autoloaders'])) {
			if (!array_key_exists($name, $this->loaded_classes)) {
				$class = $this->definition['autoloaders'][$name];
				$class_name = $class['class'];
				$keys = array();
				foreach ($class['keys'] as $key => $value) {
					if (is_numeric($value)) {
						array_push($keys, $value);
					} else {
						array_push($keys, $this->$value);
					}
				}
				try {
					$this->loaded_classes[$name] = new $class_name($this->db,$keys);
				} catch (Exception $e) {
					$this->loaded_classes[$name] = new Model($this->db,$keys);
					$this->loaded_classes[$name]->set_class($class_name);
				}
			}
			return $this->loaded_classes[$name];
		}
		if (array_key_exists($name, $this->definition['columns']) || $name == 'uid') {
			return $this->values[$name];
		} else {
			// die('unknown method/variable - '.$name);
		}
		return;
	}

	public function __isset($name) {
		return array_key_exists($name, $this->values);
	}

	public function __unset($name) {
		$this->old_values[$name] = $this->values[$name];
		unset($this->values[$name]);
	}

	public function __toString() {
		return $this->definition['class'];
	}

	# ==================================================================================================

	function next_uid() {
		$query = 'SELECT MAX(uid)+1 AS uid FROM ois_main WHERE uid < 2000001';
		if ($res = mysqli_query($this->db,$query)) {
			if ($values = mysqli_fetch_assoc($res)) {
				$this->uid = $values['uid'];
			}
		}
		return false;
	}


	protected function load_by_query($load_query, $type = 'STR') {
		if ($res = mysqli_query($this->db, $load_query)) {
			while ($values = mysqli_fetch_assoc($res)) {
				if ($this->definition['class'] && $this->definition['class'] != $values['class']) {
					return false;
				}
				if (!$this->definition['class']) {
					$this->definition['class'] = $values['class'];
				}
				$this->values['uid'] = $values['uid'];
				$key = $values['prop_name'];
				$this->values[$key] = $values['prop_value'];
				if (!array_key_exists($key, $this->definition['columns'])) {
					$this->definition['columns'][$key] = array (
						'required' => false,
						'type' => $type
					);
				}
				$this->definition['columns'][$key]['saved'] = true;
				$this->definition['columns'][$key]['loaded'] = true;
			}
			$this->_loaded = true;
		}
		return true;
	}


	protected function assign_keys() {
		$args = func_get_args();
		foreach ( $this->definition['keys'] as $key ) {
			$this->values[$key] = array_shift($args);
			$this->definition['columns'][$key]['saved'] = false;
		}
	}

	protected function columns() {
		$columns = array();
		foreach ( $this->definition['columns'] as $column => $val ) {
			array_push($columns, $column);
		}
		return $columns;
	}

	protected function prepare_value($column) {
		if ($column == 'modified') {
			return 'NOW()';
		}
		$value = null;
		if (isset($this->values[$column])) {
			$value = $this->values[$column];
		} elseif (isset($this->definition['columns'][$column]['default'])) {
			$value = $this->definition['columns'][$column]['default'];
		}
		if (isset($value)) {
			switch ($this->definition['columns'][$column]['type']) {
				case 'INT':
					$value = '"'.(int)$value.'"';
					break;
				case 'BOOL':
					$value = $value ? 'TRUE' : 'FALSE';
					break;
				default:
					$value = '"'.mysqli_real_escape_string($this->db,$value).'"';
			}
		} else {
			$value = 'NULL';
		}
		return $value;
	}

	protected function keys_values() {
		$values = array();
		foreach ($this->definition['keys'] as $key) {
			array_push($values, $this->prepare_value($key));
		}
		return $values;
	}

	protected function all_values() {
		$values = array();
		foreach ($this->columns() as $key) {
			if (!isset($this->values[$key]) && $this->definition['columns'][$key]['required']) {
				# die("Missing required value for column $key in table ".$this->definition['table']);
			} else {
				array_push($values, $this->prepare_value($key));
			}
		}
		return $values;
	}

}

?>
