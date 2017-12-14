<?php

namespace App\Util\Relational;

class Data extends ArrayTable {

	private $_delimiter;
	private $_hasNames;

	public function __construct($delimiter) {
		$this->_delimiter = $this->_delimiter($delimiter);
	}

	public function fromString($string, $hasNames = FALSE) {
		if ($string = $this->_cleanBOM($string)) {
			$rows = array();
			foreach (preg_split("/\r?\n/", $string) as $str) {
				array_push($rows, str_getcsv($str, $this->_delimiter));
			}
			$names = $hasNames ? array_shift($rows) : array();
			$this->set($names, $rows);
		}
	}

	/* public function fromString($string, $hasNames = FALSE) {
	  if ($string = $this->_cleanBOM($string)) {
	  //$pattern = '/(?<=^|;)(?:"[^"]*"|[^;]*)/';
	  $pattern = '/(?<=^|;)(?:".*?"(?=;|$)|[^;]*)/';
	  $pattern = str_replace(';', $this->_delimiter, $pattern);
	  $rows = array();
	  foreach (preg_split("~\r?\n~", $string) as $str) {
	  preg_match_all($pattern, $str, $m);
	  array_push($rows, $this->_incomingRow($m[0]));
	  }
	  $names = $hasNames ? array_shift($rows) : array();
	  $this->set($names, $rows);
	  }
	  } */

	public function set(Array $names, Array $rows = array()) {
		if ($names) {
			$this->_hasNames = TRUE;
			//	$names = $this->_incomingRow($names);
		}
		//foreach ($rows as &$row) {
		//	$row = $this->_incomingRow($row);
		//}
		parent::__construct($names, $rows);
	}

	public function toString($rowsKeys = NULL, $delimiter = NULL) {
		$delimiter = $delimiter ? $this->_delimiter($delimiter) : $this->_delimiter;
		$str = '';
		if ($this->_hasNames) {
			$str = implode($delimiter, $this->_outcomingRow($this->getNames(), $delimiter)) . "\n";
		}
		foreach ($this->getRows($rowsKeys) as $row) {
			$str .= implode($delimiter, $this->_outcomingRow($row, $delimiter)) . "\n";
		}
		return $str;
	}

	public function __toString() {
		return $this->toString();
	}

	protected function _outcomingRow(Array $row, $delimiter) {
		foreach ($row as $k => $v) {
			if (strpos($v, $delimiter) !== FALSE) {
				$row[$k] = sprintf('"%s"', $v);
			}
		}
		return $row;
	}

	/* protected function _incomingRow(Array $row) {
	  foreach ($row as $k => $v) {
	  $row[$k] = preg_replace('/^"(.*)"$/', '\1', $v);
	  }
	  return $row;
	  } */

	protected function _delimiter($delimiter) {
		if (!in_array($delimiter, array(',', ';', "\t", 'tab'))) {
			throw new RelationalDataException('Data format is not supported with this delimiter');
		}
		return $delimiter == 'tab' ? "\t" : $delimiter;
	}

	private function _cleanBOM($string) {
		return substr($string, 0, 3) === pack('CCC', 0xef, 0xbb, 0xbf) ? substr($string, 3) : $string;
	}

}
