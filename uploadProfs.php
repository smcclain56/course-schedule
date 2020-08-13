<?php


try{
	$db = new PDO('sqlite:./myDB/course_sched.db');
	$db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if($_FILES["upload_file_profs"]["error"])
	{
		header("Location: index.php?error=proffilenotuploaded");
		//echo "File Could Not Be Uploaded";
	}
	else
	{
		$ok = true;
		$file = $_FILES['upload_file_profs']['tmp_name'];
		//echo $file;
		$handle = fopen($file, "r");
		if ($file == NULL) {
			error(_('Please select a file to import'));
			//TODO insert error message
			header("Location: index.php?error=proffilenull");
		}
		else {

			$clear_Professors = $db->prepare("DELETE from Professors;");
			$clear_Teaches = $db->prepare("DELETE from Teaches;");
			$clear_PrefDays = $db->prepare("DELETE from PreferredDays;");
			$clear_PrefTimes = $db->prepare("DELETE from PreferredTimes;");
			$clear_PrefCourses = $db->prepare("DELETE from PreferredCourses;");

			try{
				$clear_Professors ->execute();
				$clear_Teaches ->execute();
				$clear_PrefDays ->execute();
				$clear_PrefTimes ->execute();
				$clear_PrefCourses ->execute();
			}
			catch (Exception $e) {
				echo $e;
				exit();
			}
			$numLine = 0;
			$profIDNum = 0;
			while(($filesop = fgetcsv($handle, 1000, ",")) !== false)
			{
				if($numLine > 1){
					//$profID = $filesop[0];
					$profID = $profIDNum;
					$profName =  $filesop[0];
					$numPreps =  $filesop[1];
					$numCourses =  $filesop[2];
					$tenure =  $filesop[3];
					$canVote =  $filesop[4];
					//teaches
					$canTeach = $filesop[5];
					//preferred days
					$daysPreferTeaching = $filesop[6];
					//preferred time
					$prefTime = $filesop[7];
					//preferred courses
					$prefCourses = $filesop[8];

					// Throw an error if any of the needed columns were left blank
					if($profName == "" || $numPreps == "" || $numCourses == "" || $tenure == "" || $canVote == "" || $canTeach == ""){
						header("Location: index.php?error=profMissingAtr");
					}

					if ($ok) {
						//INSERT into professors
						$db->exec('BEGIN;');

						$prof_stmt = $db->prepare("INSERT INTO Professors VALUES (:profID, :profName, :numPreps, :numCourses, :canVote, :tenure);");

						$prof_stmt -> bindParam(':profID', $profID);
						$prof_stmt -> bindParam(':profName', $profName);
						$prof_stmt -> bindParam(':numPreps', $numPreps);
						$prof_stmt -> bindParam(':numCourses', $numCourses);
						$prof_stmt -> bindParam(':canVote', $canVote);
						$prof_stmt -> bindParam(':tenure', $tenure);

						try{
							$prof_stmt ->execute();
							$db->exec('COMMIT;');							//
						}
						catch (Exception $e) {
							echo $e;
							exit();
						}
						//insert into teaches
						if(!empty($canTeach)){

							$teach_stmt = $db->prepare("INSERT INTO Teaches VALUES (:profID, :subjID, :courseID);");

							//Parse subjectID and courseID from canTeach
							$numElements = substr_count($canTeach, ";");
							$numElements = $numElements + 1;

							$tokens = explode(";",$canTeach, $numElements);

							for($i = 0; $i < $numElements; $i=$i+1){
								$tmpCourseInfo = ltrim($tokens[$i]);
								$courseInfo = explode(" ", $tmpCourseInfo);

								$subjectID = $courseInfo[0];
								$courseID =  $courseInfo[1];

								if($subjectID == "" || $courseID == ""){
									header("Location: index.php?error=invalidTeachCourse");
								}

								$teach_stmt -> bindParam(':profID', $profID);
								$teach_stmt -> bindParam(':subjID', $subjectID);
								$teach_stmt -> bindParam(':courseID', $courseID);

								try{
									$teach_stmt ->execute();
								}
								catch (Exception $e) {
									echo $e;
									exit();
								}
							}
						}

						// prefferedTimes
						if(!empty($prefTime)){

							$pref_time_stmt = $db->prepare("INSERT INTO PreferredTimes VALUES (:profID, :preferredTime);");

							$pref_time_stmt -> bindParam(':profID', $profID);

							$numElements = substr_count($prefTime, ";");
							$numElements = $numElements + 1;

							if($numElements > 0){
								$tokens = explode(";", $prefTime, $numElements);
								for($i = 0; $i < $numElements; $i=$i+1){
									$pref_time_stmt -> bindParam(':preferredTime', $tokens[$i]);
									try{
										$pref_time_stmt ->execute();
									}
									catch (Exception $e) {
										echo $e;
										exit();
									}
								}
							}
						}
						// preferredDays
						if(!empty($daysPreferTeaching)){
							$pref_days_stmt = $db->prepare("INSERT INTO PreferredDays VALUES (:profID, :preferredDay);");
							$pref_days_stmt -> bindParam(':profID', $profID);

							$numElements = substr_count($daysPreferTeaching, ";");
							$numElements = $numElements + 1;

							if($numElements > 0){
								$days = str_replace(" ", "", $daysPreferTeaching);
								$tokens = explode(";", $days, $numElements);
								for($i = 0; $i < $numElements; $i=$i+1){
									$pref_days_stmt -> bindParam(':preferredDay', $tokens[$i]);
									try{
										$pref_days_stmt ->execute();
									}
									catch (Exception $e) {
										echo $e;
										exit();
									}
								}
							}
						}

						//insert into PreferredCourses
						if(!empty($prefCourses)){
							$prefCourse_stmt = $db->prepare("INSERT INTO PreferredCourses VALUES (:profID, :subjID, :courseID);");

							//Parse subjectID and courseID from canTeach
							$numElements = substr_count($prefCourses, ";");
							$numElements = $numElements + 1;

							$tokens = explode(";",$prefCourses, $numElements);

							for($i = 0; $i < $numElements; $i=$i+1){
								$tmpCourseInfo = ltrim($tokens[$i]);
								$courseInfo = explode(" ", $tmpCourseInfo);

								$subjectID =  $courseInfo[0];
								$courseID =  $courseInfo[1];

								// If course infos length isnt two throw and error
								if($subjectID == "" || $courseID == "" ){
									header("Location: index.php?error=invalidPrefCourse");
								}

								$prefCourse_stmt -> bindParam(':profID', $profID);
								$prefCourse_stmt -> bindParam(':subjID', $subjectID);
								$prefCourse_stmt -> bindParam(':courseID', $courseID);

								try{
									$prefCourse_stmt ->execute();
								}
								catch (Exception $e) {
									echo $e;
									exit();
								}
							}
						}

					}
					$profIDNum = $profIDNum+1;
				}
				$numLine= $numLine +1;
			}
			// If the tests pass we can insert it into the database.
		}


		$query_str = "select * from professors;";
		$result_prof_set = $db->query($query_str);

		$query_str = "select * from PreferredTimes natural join professors;";
		$result_times_set = $db->query($query_str);

		// INTO HTML - PRINT OUT TABLES
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

			<a href="index.php?error=prof-success!" >Click here to return to previous page</a>

			<h2>Professors</h2>
			<div class="container">
				<table class="table">
					<thead>
						<tr>
							<th>Professor ID</th>
							<th>Professor Name</th>
							<th>Number of Preps</th>
							<th>Number of Courses</th>
							<th>Can vote</th>
							<th>Tenure</th>
						</tr>
					</thead>

					<tbody>
						<?php
						foreach ($result_prof_set as $tuple) {
							printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", $tuple['profID'], $tuple['profName'], $tuple['numPreps'], $tuple['numCourses'], $tuple['canVote'], $tuple['tenure'] );
						}
						?>
					</tbody>
				</table>
			</div>

			<div class="empty"></div>

			<?php
						$query_str = "select * from professors;";
						$result_prof_set = $db->query($query_str);
			?>

			<h2>Courses</h2>
			<div class="container">
				<table class="table">
					<thead>
						<tr>
							<th>Professor Name</th>
							<th>Courses Able to Teach </th>
							<th>Courses Prefer to Teach </th>
						</tr>
					</thead>

					<tbody>
						<?php
						foreach ($result_prof_set as $tuple) {
							$query_teach_str = "select * from teaches where profID = '".$tuple['profID']."';";
							$result_teaches_set = $db->query($query_teach_str);
							printf("<tr><td>%s</td><td>", $tuple['profName']);

							foreach ($result_teaches_set as $teaches_tuple) {
								printf("%s %s   ",  $teaches_tuple['subjectID'], $teaches_tuple['courseID']);

							}
							printf("</td>");

							$query_prefer_str = "select * from PreferredCourses where profID = '".$tuple['profID']."';";
							$result_course_set = $db->query($query_prefer_str);
							printf("<td>");
							foreach ($result_course_set as $course_tuple) {
								printf("%s %s   ",  $course_tuple['subjectID'], $course_tuple['courseID']);

							}
							printf("</td></tr>");
						}
						?>
					</tbody>
				</table>
			</div>

			<?php
						$query_str = "select * from professors;";
						$result_prof_set = $db->query($query_str);
			?>

			<div class="empty"></div>
			<h2>Times</h2>
			<div class="container">
				<table class="table">
					<thead>
						<tr>
							<th>Professor Name</th>
							<th>Days Prefer to Teach </th>
							<th>Time of Day prefer to Teach </th>
						</tr>
					</thead>

					<tbody>
						<?php
						foreach ($result_prof_set as $tuple) {
							$query_day_str = "select * from preferredDays where profID = '".$tuple['profID']."';";
							$result_day_set = $db->query($query_day_str);
							printf("<tr><td>%s</td><td>", $tuple['profName']);

							foreach ($result_day_set as $day_tuple) {
								printf("%s",  $day_tuple['preferredDay']);

							}
							printf("</td>");

							$query_time_str = "select * from PreferredTimes where profID = '".$tuple['profID']."';";
							$result_time_set = $db->query($query_time_str);
							printf("<td>");
							foreach ($result_time_set as $time_tuple) {
								printf("%s ",  $time_tuple['preferredTime']);

							}
							printf("</td></tr>");
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
