<html>
<head>
</head>

<body>

<?php
if ($dh = opendir("/var/services/web")) {
	while (($entry_name = readdir($dh)) !== false) {
		if ("." == $entry_name || ".." == $entry_name) {
			continue;
		}
		if ("dir" == filetype($dir . $entry_name)) {
			echo "<a href=\"draw.php?datapath=" . $entry_name . "\">" . $entry_name . "</a> <p>";
		}
	}
	closedir($dh);
}
?>


</body>
</html>
