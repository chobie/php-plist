<?php
require "plist.php";

foreach (glob("tests/*.plist") as $file) {
	$data = file_get_contents($file);
	$expected = Plist::parse($data);
	if ($expected != require($file . ".result")){
		printf("test: %s failed",$file);
		exit;
	}
}

echo "<h1>OK</h1>";
