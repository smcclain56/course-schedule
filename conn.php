<?php
	$conn = new PDO('sqlite:./myDB/course_sched.db');
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$query = "CREATE TABLE IF NOT EXISTS User (username TEXT PRIMARY KEY, password TEXT, firstname TEXT, lastname TEXT, department TEXT)";

	$conn->exec($query);
?>
