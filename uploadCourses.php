<?php

try{
	$db = new PDO('sqlite:./myDB/course_sched.db');
	$db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if($_FILES["upload_file_courses"]["error"])
	{
		header("Location: index.php?error=coursefilenotuploaded");
		//echo "File Could Not Be Uploaded";
	}
	else
	{
		//clear all tables
		$clear_Courses = $db->prepare("DELETE from Courses;");
		$clear_Overlap = $db->prepare("DELETE from Overlap;");

		try{
			$clear_Courses ->execute();
			$clear_Overlap ->execute();
		}
		catch (Exception $e) {
			echo $e;
			exit();
		}

		$ok = true;
		$file = $_FILES['upload_file_courses']['tmp_name'];
		//echo $file;
		$handle = fopen($file, "r");
		if ($file == NULL) {
			error(_('Please select a file to import'));
			//redirect(page_link_to('admin_export'));
			//TODO insert error message
			header("Location: index.php?error=coursefilenull");
		}
		else {
			$numLine = 0;
			while(($filesop = fgetcsv($handle, 1000, ",")) !== false)
			{
				if($numLine > 1){
					$subjectID = ltrim($filesop[0]);
					$courseID =  ltrim($filesop[1]);
					$courseName = ltrim($filesop[2]);
					$numSections =  ltrim($filesop[3]);
					$daysPerWeek = ltrim($filesop[4]);
					$hoursPerWeek = ltrim($filesop[5]);
					$teachingUnits = ltrim($filesop[6]);

					$cannotOverlap = $filesop[7];

					if($subjectID == "" || $courseID == "" || $courseName == "" || $numSections == "" || $daysPerWeek == "" || $hoursPerWeek == ""|| $teachingUnits == ""){
						header("Location: index.php?error=courseMissingAtr");
					}
					if ($ok) {
						$stmt = $db->prepare("INSERT INTO Courses VALUES (:subjectID, :courseID, :courseName, :daysPerWeek, :numSections, :hoursPerWeek, :teachingUnits);");

						$stmt -> bindParam(':subjectID', $subjectID);
						$stmt -> bindParam(':courseID', $courseID);
						$stmt -> bindParam(':courseName', $courseName);
						$stmt -> bindParam(':numSections', $numSections);
						$stmt -> bindParam(':daysPerWeek', $daysPerWeek);
						$stmt -> bindParam(':hoursPerWeek', $hoursPerWeek);
						$stmt -> bindParam(':teachingUnits', $teachingUnits);
						try{
							//print_r($stmt);
							$stmt ->execute();
						}
						catch (Exception $e) {
							echo $e;
							exit();
						}
						//for overlap
						if(!empty($cannotOverlap)){

							$ov_stmt = $db->prepare("INSERT INTO Overlap VALUES (:subjectIDA, :courseIDA, :subjectIDB, :courseIDB);");

							$numOverlap = substr_count($cannotOverlap, ";");
							$numOverlap = $numOverlap+1;
							$tokens = explode(";",$cannotOverlap, $numOverlap);

							for($i = 0; $i < $numOverlap; $i=$i+1){
								$tmpCourseInfo = ltrim($tokens[$i]);
								$courseInfo = explode(" ", $tmpCourseInfo);
								if(count($courseInfo) != 2){
									header("Location: index.php?error=invalidOverlap");
								}
								//$courseInfo = explode(" ",$tokens[$i]);
								//TODO if course infos length isnt two throw and error
								$subjectIDOverlap = $courseInfo[0];
								$courseIDOverlap = $courseInfo[1];

								$ov_stmt -> bindParam(':subjectIDA', $subjectID);
								$ov_stmt -> bindParam(':subjectIDB', $subjectIDOverlap);
								$ov_stmt -> bindParam(':courseIDA', $courseID);
								$ov_stmt -> bindParam(':courseIDB', $courseIDOverlap);

								try{
									$ov_stmt ->execute();
								}
								catch (Exception $e) {
									echo $e;
									exit();
								}
							}
						}
					}
				}
				$numLine= $numLine +1;
			}
			// If the tests pass we can insert it into the database.
		}


		$query_str = "select * from courses;";
		$result_set = $db->query($query_str);


		$overlap_str = "select * from overlap;";
		$overlap_result = $db->query($overlap_str);

		// INTO HTML
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title>Course Scheduler</title>
			<link rel="stylesheet" type="text/css" href="./table_style.css"/>

		</head>
		<body>

			<h4 class="instructions"> Please examine the data you entered below to ensure it is
					correct. You may return to the previous page to re-upload a file if needed. </h4>

			<a href="index.php?error=course-success!" >Click here to return to previous page</a>
			<h2>Courses</h2>
			<div class="container">
				<table class="table table-striped">
					<thead>
						<tr>
							<th>Subject ID</th>
							<th>CourseID</th>
							<th>Course Name</th>
							<th>Number of Sections </th>
							<th>Days per Week</th>
							<th>Hours per Week</th>
							<th>Teaching Units</th>
						</tr>
					</thead>

					<tbody>
						<?php
						// WE CAN ALSO LOOP AN ARRAY TO QUICKLY CREATE A TABLE
						foreach ($result_set as $tuple) {
							//echo "<font color='blue'>$tuple[subjectID]</font> $tuple[courseID]</font> <br/>\n";
							printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", $tuple['subjectID'], $tuple['courseID'], $tuple['courseName'], $tuple['numSections'], $tuple['daysPerWeek'], $tuple['hoursPerWeek'], $tuple['teachingUnits'] );
						}
						?>
					</tbody>
				</table>
			</div>



		<h2> Overlaps </h2>
		</div class="container">
		<table class="table table-striped">
			<thead>
				<tr>
					<th>Course</th>
					<th>Cannot Overlap With</th>
				</tr>
			</thead>

			<tbody>
				<?php
				$query_str = "select * from courses;";
				$result_set = $db->query($query_str);

				foreach ($result_set as $tuple) {
					//echo "<font color='blue'>$tuple[subjectID]</font> $tuple[courseID]</font> <br/>\n";
					$overlap_str = "select * from overlap where subjectIDA = '".$tuple['subjectID']."' and courseIDA = ".$tuple['courseID'].";";
					$result_overlap_set = $db->query($overlap_str);

					printf("<tr><td>%s %s</td><td>", $tuple['subjectID'], $tuple['courseID'] );
					foreach($result_overlap_set as $tuple_overlap){
						printf("%s %s ", $tuple_overlap['subjectIDB'], $tuple_overlap['courseIDB']);
					}
					printf("</td></tr>");
					//printf("<tr><td>%s %s</td><td>%s %s</td></tr>", $tuple['subjectIDA'], $tuple['courseIDA'], $tuple['subjectIDB'], $tuple['courseIDB']);
				}
				?>
			</tbody>
		</table>
	</div>

</body>
</html>
<?php



}


}
catch(PDOException $e){
	die('Exception : ' .$e->getMessage());
}

?>
