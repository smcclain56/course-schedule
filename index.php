<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" href="form_style.css?" type="text/css">
	<link href="https://fonts.googleapis.com/css?family=Oxygen&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Shadows+Into+Light&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Indie+Flower&display=swap" rel="stylesheet">
	<title>Course Scheduler</title>
</head>

<body>
	<div class="main-form">
		<h1>Course Scheduler!</h1>

		<!-- <?phpif(isset($_GET['error']) && $_GET['error'] == "success!"){ ?>
		<a type="submit" class="links" href="./downloads/Formatted_Schedule.csv" download="Schedule.csv"> Download Schedule </a>
		<?php}?> -->

		<h4 class="instructions"> <strong>Step 1:</strong> Please download the templates below and fill them out according to
			the example we provided. Note that the first line is an example and should be deleted before uploading. Also note that for preferred times, morning refers to 8am - 11am, afternoon to 11am -3pm and evening 3pm - 5pm. Each professor is allowed at most one preferred time.
			To ensure sections of a given course do not overlap, you may list that this course cannot overlap with itself.
			Be sure to save the spreadsheets as csv files before submitting   </h4>
			<a class="links" href="./CourseTemplate.xltx" download="Course Excel Template"> Download Course Excel Template<br> </a>
			<a class="links" href="./ProfessorTemplate.xltx" download="Professor Excel Template"> Download Professor Excel Template</a>


			<div class="data course-data">
				<form enctype="multipart/form-data" action="uploadCourses.php" method="post">
					<p> Select course file to upload:</p>
					<input type="file" action="uploadCourses.php" name = "upload_file_courses">
					<input type="submit" value="UPLOAD COURSES">
				</form>

				<?php if(isset($_GET['error']) && $_GET['error'] == "invalidOverlap"){ ?>
					<p class="error-message">Invalid input in overlap data in csv file </p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "coursefilenull"){ ?>
					<p class="error-message">Course csv file not found </p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "coursefilenotuploaded"){ ?>
					<p class="error-message">File could not be uploaded </p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "courseMissingAtr"){ ?>
					<p class="error-message">File was missing an attribute</p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "course-success!"){ ?>
					<p class="success-message">File successfully inputted! </p>
				<?php }?>
			</div>

			<div class="data prof-data">
				<form enctype="multipart/form-data" action="uploadProfs.php" method="post">
					<input type="file" action="uploadProfs.php" name = "upload_file_profs">
					<input type="submit" value="UPLOAD PROFESSORS">
				</form>

				<?php if(isset($_GET['error']) && $_GET['error'] == "profMissingAtr"){ ?>
					<p class="error-message">File was missing an attribute</p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "invalidPrefCourse"){ ?>
					<p class="error-message">Invalid input in preferred course data</p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "invalidTeachCourse"){ ?>
					<p class="error-message">Invalid input in can teach data</p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "proffilenotuploaded"){ ?>
					<p class="error-message">File could not be uploaded </p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "proffilenull"){ ?>
					<p class="error-message">Course csv file not found </p>
				<?php }?>

				<?php if(isset($_GET['error']) && $_GET['error'] == "prof-success!"){ ?>
					<p class="success-message">File successfully inputted! </p>
				<?php }?>

			</div>


			<div class="empty"></div>


			<h4 class="instructions"> <strong>Step 2:</strong> Click here to generate matchings what
				course each professor will teach before moving forward to generate the schedules. You must generate pairings first in order to generate a schedule.</h4>

				<div class="generate-pairs">
					<form name="generate professor pairings" action="generate_prof_pairings.php" method = "post">
						<p class="button-submit" > Generate Pairing </p>
						<input type="submit" value="GENERATE">
					</form>

					<?php if(isset($_GET['error']) && $_GET['error'] == "noTeach" ){ ?>
						<p class="error-message">There is a course no professor can teach <?php echo "<p=class="."error-message"."> (".$_GET['course'].") </p>"; ?>  </p>
					<?php } ?>

					<?php if(isset($_GET['error']) && $_GET['error'] == "noMoreUnits" ){ ?>
						<p class="error-message">There is a course with no availiable professors <?php echo "<p=class="."error-message"."> (".$_GET['course'].") </p>"; ?>  </p>
					<?php } ?>

				</div>

				<div class="empty"></div>

				<h4 class="instructions"> <strong>Step 3:</strong> Click here to generate your schedules!</h4>
				<div class="generate-sched">
					<form name="generate schedules" action="generate_sched.php" method = "post">
						<p class="button-submit" > Generate Schedule </p>
						<input type="submit" value="GENERATE">
					</form>
				</div>

				<div class="empty"></div>


				<h4 class="instructions"> <strong>Step 4:</strong> Please click below if you wish to export your schedule to a
					csv file according to the registrar guidelines. This link will only appear if you have generated a schedule. </h4>

					<?php
					if(isset($_GET['error']) && $_GET['error'] == "successGen" ){
						try{
							//load in our database
							$db = new PDO('sqlite:./myDB/course_sched.db');
							$db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

							//Get the data to export .
							$delete_table_str = "drop table if exists FullJoin;";
							$delete_table = $db->query($delete_table_str);

							$create_str_one = $db->prepare("Create table FullJoin as SELECT * FROM CourseScheduled NATURAL JOIN Professors NATURAL JOIN Courses;");
							try{
								$create_str_one ->execute();
							}
							catch (Exception $e) {
								echo $e;
								exit();
							}
							//Alter the columns in FullJoin for specific formatting
							$create_str_two = $db->prepare("Alter table FullJoin add AcadOrg TEXT;");
							$create_str_three = $db->prepare("Alter table FullJoin add CapEnrl INTEGER;");
							$create_str_four = $db->prepare("Alter table FullJoin add TotEnrl INTEGER;");
							$create_str_five = $db->prepare("Alter table FullJoin add WaitTot INTEGER;");
							$create_str_six = $db->prepare("Alter table FullJoin add CoreCode TEXT;");
							$create_str_seven = $db->prepare("Alter table FullJoin add Units INTEGER;");
							$create_str_eight = $db->prepare("Alter table FullJoin add FacilID TEXT;");
							$create_str_nine = $db->prepare("Alter table FullJoin add ClassNotes TEXT;");
							$create_str_ten = $db->prepare("Alter table FullJoin add Term TEXT;");
							try{
								$create_str_two ->execute();
								$create_str_three ->execute();
								$create_str_four ->execute();
								$create_str_five ->execute();
								$create_str_six ->execute();
								$create_str_seven ->execute();
								$create_str_eight ->execute();
								$create_str_nine ->execute();
								$create_str_ten ->execute();
							}
							catch (Exception $e) {
								echo $e;
								exit();
							}

							//get the data in the correct order of columns
							$temp_one_str = "Select AcadOrg, subjectID, courseID, section, courseName, CapEnrl, TotEnrl, WaitTot, CoreCode, Units, meetDays, endTimes, meetTimes, FacilID, profName, ClassNotes, Term from FullJoin;";
						  $schedule_output = $db->query($temp_one_str);
						  $schedule_output_array = $schedule_output->fetchAll(PDO::FETCH_ASSOC);

						  $sched = fopen('./downloads/Formatted_Schedule.csv', 'w');

						  //write to file
						  $names_of_columns = array("Acad Org", "Subject", "Catalog", "Section", "Descr", "Cap Enrl", "Tot Enrl", "Wait Tot", "Core Code", "Units", "Days","Times","Facil ID", "Instructors", "Class Notes", "Term");
						  fputcsv($sched, $names_of_columns);
						  foreach ($schedule_output_array as $fields) {
						      $section_letter = chr($fields['section'] + 65);
						      $fields['section'] = $section_letter;

						      $time_format = 'H:i';
						      $end_time = DateTime::createFromFormat($time_format, $fields['endTimes']);
						      $new_end_time = $end_time->modify('-10 minutes');
						      $end_time_str = $new_end_time->format('H:i');
									if($end_time_str < "12:00"){
										$end_time_str = $end_time_str."AM";
									}
									else{
										if($end_time_str >= "13:00"){
											$end_time_time = DateTime::createFromFormat($time_format, $end_time_str);
											$new_new_end_time = $end_time_time->modify('-12 hours');
											$end_time_str = $new_new_end_time->format('H:i');
										}
										$end_time_str = $end_time_str."PM";
									}

									$start_time_str = $fields['meetTimes'];
									if($start_time_str < "12:00"){
										$start_time_str = $start_time_str."AM";
									}
									else{
										if($start_time_str >= "13:00"){
											$start_time_time = DateTime::createFromFormat($time_format, $start_time_str);
											$new_start_time = $start_time_time->modify('-12 hours');
											$start_time_str = $new_start_time->format('H:i');
										}
										$start_time_str = $start_time_str."PM";
									}

									//for days
									$new_days_str = "";
									if(strpos($fields['meetDays'], 'M') !== false){
										$new_days_str = $new_days_str.'Mo';
									}
									if(strpos($fields['meetDays'], 'T') !== false){
										$new_days_str = $new_days_str.'Tu';
									}
									if(strpos($fields['meetDays'], 'W') !== false){
										$new_days_str = $new_days_str.'We';
									}
									if(strpos($fields['meetDays'], 'R') !== false){
										$new_days_str = $new_days_str.'Th';
									}
									if(strpos($fields['meetDays'], 'F') !== false){
										$new_days_str = $new_days_str.'Fr';
									}

									$fields['meetDays'] = $new_days_str;

						      $fields['meetTimes'] = $start_time_str." - ".$end_time_str;
						      unset($fields['endTimes']);

						      fputcsv($sched, $fields);
						  }
							fclose($sched);

							//let the server know that it can now display a link to download the csv
							//header("Location: index.php?error=success!");
							?>
							<a type="submit" class="links" href="./downloads/Formatted_Schedule.csv" download="Schedule.csv"> Download Schedule </a>
							<?php
						}
						catch(PDOException $e){
							die('Exception : '.$e->getMessage());
						}
					}


					?>
				</div>
			</body>
			</html>
