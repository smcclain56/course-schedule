<?php
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
  //$temp_one_str = "Select AcadOrg, subjectID, courseID, section, courseName, CapEnrl, TotEnrl, WaitTot, CoreCode, Units, meetDays, meetTimes, FacilID, profName, ClassNotes, Term from FullJoin;";
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


      $fields['meetTimes'] = $fields['meetTimes']." - ".$end_time_str;
      unset($fields['endTimes']);

      fputcsv($sched, $fields);
  }
  fclose($sched);

  //let the server know that it can now display a link to download the csv
  header("Location: index.php?error=success!");
}
catch(PDOException $e){
  die('Exception : '.$e->getMessage());
}

?>
