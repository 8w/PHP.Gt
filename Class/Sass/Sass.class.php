<?php class Sass {
private $_filePath;
private $_sassParser;

public function __construct($filePath) {
	$filePath = preg_replace("/\/+/", "/", $filePath);
	if(!file_exists($filePath)) {
		return false;
	}

	$this->_filePath = $filePath;
}

public function parse() {
	$this->_sassParser = new SassParser();
	// TODO - conflicts with autoprefixer...
	// $this->_sassParser->debug_info = !App_Config::isProduction();
	
	$parsedString = $this->_sassParser->toCss($this->_filePath);
	return $parsedString;
}

}#