<?php class FileOrganiserTest extends PHPUnit_Framework_TestCase {

private $_html = <<<HTML
<!doctype html>
<html>
<head>
	<meta charset="utf-8" />
	<link rel="stylesheet" href="/Main.scss" />
	<script src="/Gt.js"></script>
	<script src="/AppScript.js"></script>
</head>
<body>
	<h1>Hello, PHPUnit!</h1>
</body>
</html>
HTML;


public function setUp() {
	createTestApp();
	require_once(GTROOT . "/Class/Css2Xpath/Css2Xpath.class.php");
	require_once(GTROOT . "/Framework/Component/Dom.php");
	require_once(GTROOT . "/Framework/Component/DomEl.php");
	require_once(GTROOT . "/Framework/Component/DomElClassList.php");
	require_once(GTROOT . "/Framework/Component/DomElCollection.php");
	require_once(GTROOT . "/Framework/FileOrganiser.php");
}

public function tearDown() {
	removeTestApp();
}

public function testInitialWebrootIsEmpty() {
	$webroot = APPROOT . "/www";
	$diff = array_diff(["Go.php"], scandir($webroot));
	$this->assertEmpty($diff, "Unexpected www directory contents");
}

/**
 * Test that cache is invalid when the www.cache file hasn't been made.
 */
public function testCheckFilesNew() {
	$fileOrganiser = new FileOrganiser();
	$cacheInvalid = $fileOrganiser->checkFiles();

	$this->assertTrue($cacheInvalid, "Cache should be invalid.");
}

/**
 * Test that the cache is valid after FileOrganiser's methods have been called.
 */
public function testCheckFilesWhenCached() {
	file_put_contents(APPROOT . "/Asset/SomeAssetData.dat", "Asset contents");
	file_put_contents(APPROOT . "/Script/Main.js", "alert('Script!')");
	file_put_contents(APPROOT . "/Style/Main.css", "* { color: red; }");

	$fileOrganiser = new FileOrganiser();
	$cacheInvalid = $fileOrganiser->checkFiles();
	$this->assertTrue($cacheInvalid);

	if($cacheInvalid) {
		$clientSideCompiler = new ClientSideCompiler();
		$fileOrganiser->clean();
		$fileOrganiser->update();
		$fileOrganiser->process($clientSideCompiler);
		$fileOrganiser->compile($clientSideCompiler);	
	}

	$cacheInvalid = $fileOrganiser->checkFiles();
	$this->assertFalse($cacheInvalid);
}

/**
 * Test that the files in the source directories are actually copied to the
 * www directory.
 */
public function testFilesAreCopied() {
	$wwwDir = APPROOT . "/www";
	$fileContents = array(
		"Asset/SomeAssetData.dat" => "Asset content",
		"Script/Main.js" => "alert('Script!')",
		"Style/Main.css" => "* { color: red; }",
	);
	foreach ($fileContents as $subPath => $contents) {
		file_put_contents(APPROOT . "/$subPath", $contents);
	}

	$fileOrganiser = new FileOrganiser();
	$cacheInvalid = $fileOrganiser->checkFiles();

	if($cacheInvalid) {
		$clientSideCompiler = new ClientSideCompiler();
		$fileOrganiser->clean();
		$fileOrganiser->update();
		$fileOrganiser->process($clientSideCompiler);
		$fileOrganiser->compile($clientSideCompiler);	
	}

	foreach ($fileContents as $subPath => $contents) {
		$filePath = "$wwwDir/$subPath";
		$this->assertFileExists($filePath);
		$actualContents = file_get_contents($filePath);
		$this->assertEquals($contents, $actualContents);
	}
}

/**
 * Test that SCSS files are converted to CSS, and the source SCSS file is not
 * present in www directory after the processing. The "compiled" css that is
 * hard-coded here will be stripped of all white-space when comparing to the 
 * actual white-space.
 */
public function testScssIsProcessed() {
	$wwwDir = APPROOT . "/www";
	$fileContents = array(
		"Style/TestVar.scss" => [
			"Source" => "\$red = #fa124e; * { color: \$red; }",
			"Compiled" => "* { color: #fa124e; }"],
		"Style/TestNest.scss" => [
			"Source" => "p { color: red; a { color: blue; } }",
			"Compiled" => "p { color: red; } p a { color: blue; }"],
		"Style/SubDir/TestMixin.scss" => [
			"Source" => "@mixin Test() { color: red; } p { @include Test(); }",
			"Compiled" => "p { color: red; }"],
	);
	foreach ($fileContents as $subPath => $contents) {
		$dir = dirname(APPROOT . "/$subPath");
		if(!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}

		file_put_contents(APPROOT . "/$subPath", $contents["Source"]);
	}

	$fileOrganiser = new FileOrganiser();
	$cacheInvalid = $fileOrganiser->checkFiles();

	if($cacheInvalid) {
		$clientSideCompiler = new ClientSideCompiler();
		$fileOrganiser->clean();
		$fileOrganiser->update();
		$fileOrganiser->process($clientSideCompiler);
		// DON'T TEST THIS: $fileOrganiser->compile($clientSideCompiler);
	}

	foreach ($fileContents as $subPath => $contents) {
		$filePath = "$wwwDir/$subPath";
		// $filePath still points to .scss file.
		$this->assertFileNotExists($filePath);

		$filePath = preg_replace("/.scss$/i", ".css", $filePath);
		$actualContents = file_get_contents($filePath);

		$actual_stripped = preg_replace('/\s+/', '', $actualContents);
		$compiled_stripped = preg_replace('/\s+/', '', $contents["Compiled"]);

		$this->assertEquals($actual_stripped, $compiled_stripped);
	}
}

/**
 * Test that when isClientCompiled is set, the www directory files get compiled
 * into single scripts.
 */
public function testClientSideCompilation() {

}

/**
 * Test that client side files added to the DOM head by PageTools are handled
 * in the correct way.
 */
public function testPageToolClientSide() {

}

}#