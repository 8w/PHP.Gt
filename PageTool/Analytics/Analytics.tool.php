<?php
use RoadTest\Utility\Logger\LoggerFactory;

class Analytics_PageTool extends PageTool {

private static $CUSTOM_DIMENSION_KEY = "Gt.Analytics.customDimensions";
private static $END_SESSION_KEY = "Gt.Analytics.endSession";
const USER_TYPE = "dimension1";
const BRANDING = "dimension2";

/**
 * Google Analytics.
 * This simple PageTool doesn't have any functionality in the go() method.
 * Instead, pass the tracking code into the track() method.
 */
public function go($api, $dom, $template, $tool) { }

/**
 * Injects the required JavaScript code where needed to start tracking using
 * Google Analytics.
 *
 * @param string $trackingCode Your Google Analytics account code, looks like
 * this: UA-12345678-1
 */
public function track($trackingCode) {
	$logger = LoggerFactory::get($this);
	$responseCode = http_response_code();

    if($responseCode >= 300 && $responseCode < 400) {
		// don't bother going any further - this is a redirect
		return;
	}

	if(!$this->_dom instanceof Dom) {
		// No dom initialised... can't track.
		return;
	}

	$js = file_get_contents(dirname(__FILE__) . "/Include/Analytics.tool.js");
	if($js === false) {
		$logger->error("Couldn't find Google Analytics script!");
		return;
	}

	$js = str_replace("{ANALYTICS_CODE}", $trackingCode, $js);
    if(Session::exists(self::$CUSTOM_DIMENSION_KEY)) {
        $customDimensions = Session::get(self::$CUSTOM_DIMENSION_KEY);
        foreach ($customDimensions as $key => $value) {
			$js .= "
				ga('set', '{$key}', '{$value}');";
		}
	}

	if(Session::get(self::$END_SESSION_KEY) === true) {
		$js .= "
			ga('send', 'pageview', {'sessionControl': 'start'}); ";
	} else {
        $js .= "
			ga('send', 'pageview');";
    }

	$scriptToInsertBefore = null;
	$existingScript = $this->_dom["head > script"];
	if($existingScript->length > 0) {
		$scriptToInsertBefore = $existingScript[0];
	}

	$script = $this->_dom->createElement(
		"script",
		["data-PageTool" => "Analytics"],
		// finish-up with the send command
		$js
	);

	$this->_dom["head"]->insertBefore($script, $scriptToInsertBefore);
}

public function customDimension($name, $value)
{
    Session::set(self::$CUSTOM_DIMENSION_KEY . ".{$name}", $value);
}

public function endSession() {
    Session::delete(self::$CUSTOM_DIMENSION_KEY);
    Session::set(self::$END_SESSION_KEY, true);
}
}#
