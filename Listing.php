<?php
class Listing {
	var $definition = array (
		query    => '',
		'class'  => '',
		columns  => array (),
		filters  => array (), # key => value
		ordering => array (), # key => how
		autoload => true,
	);
	var $list    = array ();
	var $offset  = null;
	var $limit   = null;
	var $current = array(
		index => null,
		keys  => null,
	);
	var $query   = null;

	protected $_loaded = false;
	protected $_count  = null; 
	protected $db      = null;
	protected $tables = array (
		STR => 'ois_str',
		INT => 'ois_int',
		BIN => 'ois_blob',
	);

	function __construct($db, $offset = null, $limit = null, $current = null, $filters = null) {
		$this->db = $db;
		$this->offset = $offset;
		$this->limit  = $limit;
		$this->current[keys] = $current;
		if ($filters) {
			$this->filter($filters);
		}
		if ( ($this->definition[query] || $this->definition['class']) && ($this->definition[autoload]) ) {
			$this->load();
		}
		return $this;
	}

	public function __set($name, $value) {
		if (!$name) {
			return;
		}
		$this->definition[filters][$name] = $value;
		if (!array_key_exists($name, $this->definition[columns])) {
			$this->definition[columns][$name] = array (
				type => 'STR',
			);
		}
	}

	function is_loaded() {
		return $this->_loaded;
	}

	function count() {
		return $this->_count;
	}

	function filter($filters = null) {
		if (is_array($filters)) {
			$this->definition[filters] = $filters;
		} else {
			$this->definition[filters] = array ();
		}
		return $this;
	}

	function columns($columns = null) {
		if (is_array($columns)) {
			$this->definition[columns] = $columns;
		} else {
			$this->definition[columns] = array ();
		}
		return $this;
	}

	function order_by($ordering = null) {
		if (is_array($ordering)) {
			$this->definition['ordering'] = $ordering;
		} else {
			$this->definition['ordering'] = array ();
		}
		return $this;
	}

	function load() {
		if ($res = mysqli_query($this->db,$this->prepare_query())) {
			$res_cnt = mysqli_query($this->db,'SELECT FOUND_ROWS()');
			$res_cnt = mysqli_fetch_row($res_cnt);
			$this->_count = $res_cnt[0];
			$class;
			$this->list = array();
			if (isset($this->definition['class']) && ($this->definition['class'] != '')) {
				$class = $this->definition['class'];
			}
			while ($values = mysqli_fetch_assoc($res)) {
				if (is_array($this->current[keys]) && !sizeof(array_diff($values, $this->current[keys]))) {
					$this->current[index] = sizeof($this->list);
				}
				$curr_class = $class;
				if (isset($values['class'])) {
					$curr_class = $values['class'];

				}
				if (isset($curr_class)) {
					try {
						$value = new $class($this->db,$values[uid]);
					} catch (Exception $e) {
						$value = new Model($this->db,$values[uid]);
					}
					if ($value->is_loaded()) {
						array_push($this->list, $value);
					}
				} else {
					array_push($this->list, $values);
				}
			}
			return ($this->_loaded = true);
		}
		return ($this->_loaded = false);
	}

	function listing() {
		return $this->list;
	}

	function set_class($class) {
		$this->definition['class'] = $class;
		return $this;
	}

	public function __invoke() {
		if (func_num_args() == 0) {
			return $this->list;
		}
	}


	# ==================================================================================================

	protected function prepare_query() {
		$query = $this->definition['query'];
		# TODO: chytrejsi logika na nahrazeni pouze na spravnem miste u slozitych dotazu s pre-selecty
		if ($query) {
			$query = preg_replace('/SELECT /', 'SELECT SQL_CALC_FOUND_ROWS ', $query, 1);
		} else {
			$query = 'SELECT SQL_CALC_FOUND_ROWS om.uid, om.class FROM ois_main AS om';
			$query_suffix = '';
			foreach ($this->definition['filters'] as $key => $value) {
				$condition = '=';
				if (is_array($value)) {
					if (array_key_exists('condition', $value)) {
						$condition = $value['condition'];
					}
					$value = $value['value'];
				}
				if ($this->definition['columns'][$key]) {
					$table = $this->tables[$this->definition['columns'][$key]['type']];
				} else {
					$table = 'ois_str';
				}
				$query .= ' LEFT JOIN ' . $table . ' AS tbl_' . $key . ' ON (tbl_' . $key . '.uid = om.uid AND tbl_' . $key . '.prop_name = "' . mysqli_real_escape_string($this->db,$key) . '")';
				$query_suffix .= ' AND tbl_' . $key . '.prop_value ' . $condition . (isset($value) ? ' "' . mysqli_real_escape_string($this->db,$value) . '"' : '' ) . '';
			}

			$ord = false;
			$ord2 = '';
			foreach ($this->definition['ordering'] as $key => $value) {
				if ($key != 'uid') {
					if ($this->definition['columns'][$key]) {
						$table = $this->tables[$this->definition['columns'][$key]['type']];
					} else {
						$table = 'ois_str';
					}
					$ord .= ' LEFT JOIN ' . $table . ' AS tbl_' . $key . ' ON (tbl_' . $key . '.uid = om.uid AND tbl_' . $key . '.prop_name = "' . $key . '")';
					$ord2 = ($ord2 ? $ord2 . ', ' : '') . 'tbl_' . $key . '.prop_value ' . (strtolower($value) == 'desc' || $value == -1 ? ' DESC' : '');
				} else {
					$ord2 = ($ord2 ? $ord2 . ', ' : '') . 'om.uid ' . (strtolower($value) == 'desc' || $value == -1 ? ' DESC' : '');
				}
			}
			if ($ord) {
				$query .= $ord;
			}

			if ($this->definition['class']) {
				$query .= ' WHERE om.class = "' . mysqli_real_escape_string($this->db,$this->definition['class']) . '"';
			}
			$query .= $query_suffix;
		}
		if ($ord2) {
			$query .= ' ORDER BY ' . $ord2;
		}
		if (isset($this->limit)) {
			$query .= ' LIMIT '.(isset($this->offset) ? $this->offset.', ' : '').$this->limit;
		}
		$this->query = $query;
		return $query;
	}
}

?>
