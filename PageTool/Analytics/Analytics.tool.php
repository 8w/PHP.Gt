<?php
use RoadTest\Utility\Logger\LoggerFactory;

class Analytics_PageTool extends PageTool
{

    const USER_TYPE = "dimension1";
    const BRANDING = "dimension2";
    const ANTI_SPAM = "dimension3";
    private static $CUSTOM_DIMENSION_KEY = "Gt.Analytics.customDimensions";
    private static $EVENT_KEY = "Gt.Analytics.events";
    private static $END_SESSION_KEY = "Gt.Analytics.endSession";

    /**
     * Google Analytics.
     * This simple PageTool doesn't have any functionality in the go() method.
     * Instead, pass the tracking code into the track() method.
     */
    public function go($api, $dom, $template, $tool)
    {
    }

    /**
     * Injects the required JavaScript code where needed to start tracking using
     * Google Analytics.
     *
     * @param string $trackingCode Your Google Analytics account code, looks like
     *                             this: UA-12345678-1
     */
    public function track($trackingCode)
    {
        $logger = LoggerFactory::get($this);
        $responseCode = http_response_code();

        if ($responseCode >= 300 && $responseCode < 400) {
            // don't bother going any further - this is a redirect
            return;
        }

        if (!$this->_dom instanceof Dom) {
            // No dom initialised... can't track.
            return;
        }

        $js = file_get_contents(dirname(__FILE__) . "/Include/Analytics.tool.js");
        if ($js === false) {
            $logger->error("Couldn't find Google Analytics script!");
            return;
        }

        $js = str_replace("{ANALYTICS_CODE}", $trackingCode, $js);

        // enable remarketing
        $js .= "\nga('require', 'displayfeatures');";

        // send any pending events
        if (Session::exists(self::$EVENT_KEY)) {
            $events = Session::get(self::$EVENT_KEY);
            foreach ($events as $event) {
                $js .= "\nga('send', {
                        hitType: 'event',
                        eventCategory: '{$event["eventCategory"]}',
                        eventAction:   '{$event["eventAction"]}',
                        eventLabel:    '{$event["eventLabel"]}',
                        eventValue:    '{$event["eventValue"]}'
                    });
                ";

                $logger->debug(
                    sprintf("Sending GA event: category=%s, action=%s, label=%s, value=%s",
                        $event["eventCategory"],
                        $event["eventAction"],
                        $event["eventLabel"],
                        $event["eventValue"]));
            }
            // delete them now they've been loaded for send
            Session::delete(self::$EVENT_KEY);
        }

        if (!Session::exists(self::$CUSTOM_DIMENSION_KEY)) {
            // this is a new session so add the custom dimension to filter-out spam entries
            $this->customDimension(self::ANTI_SPAM, "true");
        }

        $customDimensions = Session::get(self::$CUSTOM_DIMENSION_KEY);
        foreach ($customDimensions as $key => $value) {
            $js .= "\nga('set', '{$key}', '{$value}');";

            $logger->debug(
                sprintf("Sending GA custom dimension: %s => %s",
                    $key,
                    $value));
        }

        if (Session::exists(self::$END_SESSION_KEY)) {
            $js .= "\nga('send', 'pageview', {'sessionControl': 'start'});\n";
            Session::delete(self::$END_SESSION_KEY);
        } else {
            $js .= "\nga('send', 'pageview');\n";
        }

        $scriptToInsertBefore = null;
        $existingScript = $this->_dom["head > script"];
        if ($existingScript->length > 0) {
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

    public function sendEvent(
        string $eventCategory,
        string $eventAction,
        string $eventLabel = null,
        int $eventValue = null
    ) {
        Session::push(self::$EVENT_KEY, [
            "eventCategory" => $eventCategory,
            "eventAction" => $eventAction,
            "eventLabel" => $eventLabel,
            "eventValue" => $eventValue
        ]);
    }

    public function endSession()
    {
        Session::delete(self::$CUSTOM_DIMENSION_KEY);
        Session::set(self::$END_SESSION_KEY, true);
    }
}#
