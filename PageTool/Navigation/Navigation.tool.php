<?php class Navigation_PageTool extends PageTool {
/**
 * Used to automate an application or website's navigation system.
 * Adds a class of "selected" to the relevant "body nav li".
 */
private $_navElements;

public function go($api, $dom, $template, $tool) {
	$this->_navElements = $dom["body nav"];

	$target = strtok($_SERVER['REQUEST_URI'], '?');
	$target = strtok($target, '#');
	$target = strtok($target, ".");
	$targetBase = strtok($target, "/");
	
	$target = str_replace("/", "\/", $target);
	$targetBase = str_replace("/", "\/", $targetBase);

	foreach($this->_navElements as $nav) {
		$navLiTags = $nav["ul li, ol li, menu li"];

		foreach($navLiTags as $li) {
			$pattern = $patternBase = null;

			if($li->hasAttribute("data-selected-pattern")) {
				$pattern = $li->getAttribute("data-selected-pattern");
			}
			else {
				$pattern = "/$target(.html)?$/";
				$patternBase = "/$targetBase(.html)?$/";
			}

			// Match the current URL with the anchor's href.
			$href = $li["a"]->getAttribute("href");
			if(preg_match($pattern, $href) > 0
			|| ($href === "/" && $target === "\/Index") ) {
				$li->addClass("selected");
				if($li->hasClass("tree")) {
					$li->addClass("open");
				}
			}
			else if(preg_match($patternBase, $href) > 0) {
				$li->addClass("selected");
				if($li->hasClass("tree")) {
					$li->addClass("open");
				}
			}
		}
	}
}

}#