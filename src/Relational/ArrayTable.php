<?php

namespace Relational;

class ArrayTable {

	const ROW = 'row';
	const FIELD = 'field';

	private $_names = array();
	private $_rows = array();

	public function __construct(Array $names = NULL, Array $rows = NULL) {
		$names AND $this->setNames($names);
		$rows AND $this->setRows($rows);
	}

	public function setNames(Array $names = array()) {
		foreach ($this->_rows as &$row) {
			$row = (array) $row;
			// set names from rows
			foreach ($row as $name => $_) {
				if (!$this->_arrayKeyExists($name, $names)) {
					$names[$name] = $name;
				}
			}
		}
		// init, filter names
		foreach ($names as $name) {
			$this->_names[$name] = $name; // is_int($name) ? $name : (string) $name;
		}
		//
		$this->_initRows();
	}

	public function getNames() {
		return array_values($this->_names);
	}

	public function setRows(Array $rows = array()) {
		$this->_rows = $rows;
		$this->setNames();
	}

	public function getRows($keysRows = NULL) {
		if ($keysRows === NULL) {
			return $this->_rows;
		}
		$tmp = array();
		foreach ((array) $keysRows as $key) {
			if ($this->rowExists($key)) {
				$tmp[$key] = $this->_rows[$key];
			}
		}
		return $tmp;
	}

	public function rowExists($rowKey) {
		return $this->_arrayKeyExists($rowKey, $this->_rows);
	}

	public function fieldCount() {
		return count($this->_names);
	}

	public function fieldExists($name) {
		return in_array($name, $this->_names, TRUE);
	}

	public function fieldRename(Array $fields) {
		foreach ($fields as $old => $new) {
			if ($this->fieldExists($old)) {
				// rename keys in each rows
				foreach ($this->_rows as &$row) {
					$tmp = array();
					foreach ($row as $n => $v) {
						if ($n === $old) {
							$tmp[$new] = $v;
						} else {
							$tmp[$n] = $v;
						}
					}
					$row = $tmp;
				}
				// rename names
				$tmp = array();
				foreach ($this->_names as $n) {
					if ($n === $old) {
						$tmp[$new] = $new;
					} else {
						$tmp[$n] = $n;
					}
				}
				$this->_names = $tmp;
			}
		}
	}

	public function fieldAdd($name, $after = NULL, $defaultValue = NULL) {
		if ($this->fieldExists($name)) {
			return FALSE;
		}
		if ($after === NULL) { // put last
			$this->_names[$name] = $name;
			$this->_initRows($defaultValue);
			return TRUE;
		}
		$tmp = array();
		if ($after == -1) { // put first
			$tmp[$name] = $name;
			foreach ($this->_names as $k => $n) {
				$tmp[$k] = $n;
			}
		} else { // put after fieldname
			foreach ($this->_names as $k => $n) {
				$tmp[$k] = $n;
				if (strcmp($after, $n) == 0) {
					$tmp[$name] = $name;
				}
			}
		}
		$this->_names = $tmp;
		$this->_initRows($defaultValue);
		return TRUE;
	}

	public function fieldRemove($name) {
		$names = (array) $name;
		foreach ($names as $n) {
			if ($this->_arrayKeyExists($n, $this->_names)) {
				unset($this->_names[$n]);
			}
		}
		foreach ($this->_rows as &$row) {
			foreach ($row as $n => $_) {
				if (in_array($n, $names)) {
					unset($row[$n]);
				}
			}
		}
	}

	public function insert(Array $fields = NULL, $defaultValue = NULL) {
		$fields AND $this->setNames(array_keys($fields));
		$rowKey = array_push($this->_rows, array_fill_keys($this->_names, $defaultValue)) - 1;
		$this->_updateRow($fields, $rowKey);
		return $rowKey;
	}

	public function select($fields = NULL, $condition = NULL, Array $vars = NULL) {
		if ($condition === NULL OR $this->_isDigit($condition) OR is_array($condition)) {
			$rows = $this->getRows($condition);
		} else {
			$rows = array();
			$condition = $this->_conditionPrepare($condition, $vars);
			foreach ($this->_rows as $key => $row) {
				if ($this->_conditionOK($key, $row, $condition)) {
					$rows[$key] = $row;
				}
			}
		}
		return $this->_getRowsFields($rows, $fields);
	}

	public function update(Array $fields, $condition = NULL, Array $vars = NULL) {
		if ($this->_isDigit($condition) OR is_array($condition)) {
			return $this->_updateRow($fields, $condition);
		}
		return $this->_updateRow($fields, array_keys($this->select(NULL, $condition, $vars)));
	}

	public function delete($condition = NULL, Array $vars = NULL) {
		if ($condition === NULL) {
			$this->_rows = array();
		} elseif ($this->_isDigit($condition) OR is_array($condition)) {
			$this->_deleteRow($condition);
		} else {
			$this->_deleteRow(array_keys($this->select(NULL, $condition, $vars)));
		}
	}

	private function _initRows($defaultValue = NULL) {
		// lead to matching the name by key
		foreach ($this->_rows as &$row) {
			$_row = array();
			$key = 0;
			foreach ($this->_names as $name) {
				if ($this->_arrayKeyExists($name, $row, TRUE)) {
					$_row[$name] = $row[$name];
				} else if ($this->_arrayKeyExists($key, $row, TRUE)) {
					$_row[$name] = $row[$key];
				} else {
					$_row[$name] = $defaultValue;
				}
				++$key;
			}
			$row = $_row;
		}
	}

	private function _getRowsFields(Array $rows = NULL, $fields) {
		$fields = (array) $fields;
		if (!$fields) {
			return $rows;
		}
		$res = array();
		if ($rows) {
			foreach ($rows as $rowKey => $row) {
				foreach ($row as $n => $_) {
					foreach ($fields as $name) {
						if ($this->fieldExists($name)) {
							$res[$rowKey][$name] = $row[$name];
						}
					}
				}
			}
		}
		return $res;
	}

	private function _updateRow(Array $fields = NULL, $rowsKeys) {
		if ($fields) {
			foreach ((array) $rowsKeys as $key) {
				if ($this->rowExists($key)) {
					foreach ($fields as $n => $v) {
						if ($this->_arrayKeyExists($n, $this->_rows[$key])) {
							$this->_rows[$key][$n] = $v;
						}
					}
				}
			}
			return count($rowsKeys);
		}
		return FALSE;
	}

	private function _deleteRow($key) {
		foreach ((array) $key as $k) {
			unset($this->_rows[$k]);
		}
	}

	private function _conditionPrepare($condition, $vars) {
		if ($condition = trim($condition)) {
			$condition = preg_replace(array(
				'/\$' . static::FIELD . '\s*\[\s*`(.*?)`\s*\]/',
				'/`(.*?)`/',
				//
				'/(?<![!=])==(?!=)/',
				'/(?<![!=<>])=(?!=)/',
				'/<>/'
					), array(
				'$' . static::FIELD . '["\1"]',
				'$' . static::FIELD . '["\1"]',
				//
				'===',
				'==',
				'!='
					), $condition);
			if ($vars) {
				foreach ((array) $vars as $key => $var) {
					if (is_string($var)) {
						$var = sprintf('"%s"', addcslashes($var, '"'));
					}
					$condition = str_replace(':' . $key, $var, $condition);
				}
			}
			if ($this->_names) {
				$condition = preg_replace_callback(
						'/\$' . static::FIELD . '\s*\[\s*["\']?(.*?)["\']?\s*\]/'
						, function($m) {
					return sprintf('$' . static::FIELD . '[%s]', $this->fieldExists($m[1]) ?
							sprintf('"%s"', $m[1]) :
							$m[1]);
				}, $condition);
			}
		}
		return $condition;
	}

	private function _conditionOK($_key, Array $_row, $condition) {
		${static::ROW} = $_key;
		${static::FIELD} = $_row;
		//file_put_contents('condition.txt', $condition . "\n", 8);
		eval(sprintf('$bool=boolval(%s);', $condition));
		return $bool;
	}

	protected function _isDigit($val) {
		return is_int($val) OR ctype_digit($val);
	}

	private function _arrayKeyExists($key, $array, $strict = TRUE) {
		return $strict ? in_array($key, array_keys($array), TRUE) : array_key_exists($key, $array);
	}

}
