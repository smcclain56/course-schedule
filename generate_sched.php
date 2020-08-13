<?php

try{
  srand();

  //load in our database
  $db = new PDO('sqlite:./myDB/course_sched.db');
  $db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  //for each tuple in course schedule (start sequential, TODO make access random?)

  $best_score = 100000000000; //YIKES

  $times_range = array ('morning' => array("08:00","10:59"), 'afternoon' => array("11:00","14:59"), 'evening' => array("15:00", "16:50"));

  for($i = 0; $i < 100; $i = $i + 1){
    $bad_sched = 0;
    $day_spread_array = array('M' => 0, 'T' => 0, 'W' => 0, 'R' => 0, 'F' => 0);
    $db->exec('BEGIN;');

    $query_str = "select * from CourseScheduled;";
    $result_set = $db->query($query_str);

    //delete any times from course CourseScheduled
    $clear_times = $db->prepare("UPDATE CourseScheduled set meetTimes = NULL, endTimes = NULL, meetDays = NULL;");
    try{
      $clear_times ->execute();
    }
    catch (Exception $e) {
      echo $e;
      exit();
    }
    $time_prefs_score = 0;
    $day_prefs_score = 0;

    $spread_days_score = 0;
    $section_overlaps_score = 0;

    $past_assignments = array();
    $index = 0;

    $result_set_array = $result_set->fetchAll(PDO::FETCH_ASSOC);
    shuffle($result_set_array);

    //foreach($result_set as $course) {
    foreach($result_set_array as $course){
      // (0) get length of individual class sessions (we assume all classes are the same length. TODO, change this?)
      $subjectID = $course['subjectID'];
      $courseID = $course['courseID'];

      $this_course_str = "select * from Courses where subjectID = '".$subjectID."' and courseID = ".$courseID.";";
      $this_course = $db->query($this_course_str);
      $this_course_array = $this_course->fetch(PDO::FETCH_ASSOC);
      $daysPerWeek = $this_course_array['daysPerWeek'];
      $hoursPerWeek = $this_course_array['hoursPerWeek'];
      $classLengthHrs = $hoursPerWeek / $daysPerWeek;
      $classLengthMin = ($hoursPerWeek * 60) / $daysPerWeek;

      // (1) check if prof has day prefs to pick days for class
      $profID = $course['profID'];
      $prof_day_prefs_str = "select * from PreferredDays where profID = ".$profID.";";
      $prof_day_prefs = $db->query($prof_day_prefs_str);

      $day_prefs = array();
      $has_prefs = false;
      foreach($prof_day_prefs as $pref_day) {
        array_push($day_prefs, $pref_day[1]);
        $has_prefs = true;
      }

      if ($has_prefs){
        //assign course days based on prof's preferences
        $past_assignments = assign_days($daysPerWeek, $day_prefs, $past_assignments, $course['courseSchedID']);
        $day_combo = end($past_assignments[$course['courseSchedID']]);
      }
      else{
        //assign course days without preferences
        $past_assignments = assign_days($daysPerWeek, array("F", "M", "R", "T", "W"), $past_assignments, $course['courseSchedID']);
        $day_combo = end($past_assignments[$course['courseSchedID']]);
      }
      // enter days in database
      $assign_query_str = $db->prepare("update CourseScheduled SET meetDays = '".$day_combo."' where courseSchedID = ".$course['courseSchedID'].";");
      try{
        $assign_these_days = $assign_query_str->execute();
      }
      catch(Exception $e){
        echo $e;
        exit();
      }


      // (2) check if prof has time prefs to pick time for class
      $prof_time_prefs_str = "select * from PreferredTimes where profID = ".$profID.";";
      $prof_time_prefs = $db->query($prof_time_prefs_str);

      $time_prefs = array();
      $has_prefs = false;
      foreach($prof_time_prefs as $pref_time) {
        array_push($time_prefs, $pref_time[1]);
        $has_prefs = true;
      }
      if ($has_prefs){
        //assign course days based on prof's preferences
        $filled = assign_time($day_combo, $time_prefs[0], $classLengthMin, $subjectID, $courseID, $course, $db, $past_assignments, $course['courseSchedID'], $daysPerWeek);
      }
      else{
        //assign course days without preferences
        $filled = assign_time($day_combo, "random", $classLengthMin, $subjectID, $courseID, $course, $db, $past_assignments, $course['courseSchedID'], $daysPerWeek);
      }
      if($filled == 0){
        //bad schedule! a course cannot be scheduled.
        $bad_sched = 1;
        break;
      }
    }

    if ($bad_sched == 1){
      $db->exec('ROLLBACK;');
      continue;
    }

    $query_str = "select * from CourseScheduled;";
    $result_set = $db->query($query_str);
    foreach($result_set as $tuple) {
      $time_of_days_fallsinto = array();

      if(overlaps($times_range['morning'][0],$times_range['morning'][1], $tuple['meetTimes'], $tuple['endTimes']) == 0){
        array_push($time_of_days_fallsinto, 'morning');
      }
      if(overlaps($times_range['afternoon'][0],$times_range['afternoon'][1], $tuple['meetTimes'], $tuple['endTimes']) == 0){
        array_push($time_of_days_fallsinto, 'afternoon');
      }
      if(overlaps($times_range['evening'][0],$times_range['evening'][1], $tuple['meetTimes'], $tuple['endTimes']) == 0){
        array_push($time_of_days_fallsinto, 'evening');
      }
      //loop through time of days fallsinto check if this if none of them are preferece for the prof and add if so
      $noPrefsMet = 1;
      for($j = 0; $j < sizeof($time_of_days_fallsinto); $j = $j+1){
        // echo "time of days fall into ".$time_of_days_fallsinto[$j]."<br>";
         $query_str = "select * from PreferredTimes where preferredTime = '".$time_of_days_fallsinto[$j]."';";
        $set = $db->query($query_str);
        $set_array = $set->fetch(PDO::FETCH_ASSOC);
        if($set_array && sizeof($set_array) > 0){
          $noPrefsMet = 0;
        }
      }
      if($noPrefsMet == 1){
        $time_prefs_score = $time_prefs_score+1;
      }

      //for days
      $day_combo = $tuple['meetDays'];

      $day_combo_chars = str_split($day_combo);
      //print_r($day_combo_chars);
      for($k = 0; $k < sizeof($day_combo_chars); $k = $k + 1){
        $prof_days_str = "select * from PreferredDays where profID = ".$tuple['profID']." and preferredDay = '".$day_combo_chars[$k]."';";
        $prof_days = $db->query($prof_days_str);
        $prof_days_array = $prof_days->fetch(PDO::FETCH_ASSOC);
        if($prof_days_array && sizeof($prof_days_array) == 0){
          $day_prefs_score = $day_prefs_score + 1;
        }

        //for spread days
        $time_format = 'H:i';
        $start_hour = DateTime::createFromFormat($time_format, $tuple['meetTimes']);
        $end_hour = DateTime::createFromFormat($time_format, $tuple['endTimes']);

        //$minutes_to_add = round(abs($end_hour - $start_hour)/60,2);
        $minutes_to_add_obj = date_diff($end_hour, $start_hour, TRUE);
        $minutes_to_add = ($minutes_to_add_obj->h * 60) + $minutes_to_add_obj->i;
        $day_spread_array[$day_combo_chars[$k]] = $day_spread_array[$day_combo_chars[$k]] + $minutes_to_add;

        //for overlaps
        $over_str = "select * from CourseScheduled where subjectID = '".$tuple['subjectID']."' and courseID = ".$tuple['courseID']." and
                                                                                                  ((meetTimes >= '".$tuple['meetTimes']."' and meetTimes < '".$tuple['endTimes']."')
                                                                                               or (endTimes > '".$tuple['meetTimes']."' and endTimes <= '".$tuple['endTimes']."')
                                                                                               or (meetTimes <= '".$tuple['meetTimes']."' and endTimes >= '".$tuple['endTimes']."'));";
        $over = $db->query($over_str);
        foreach($over as $tuple){
          $section_overlaps_score = $section_overlaps_score+1;
        }

      }
    }
    $max_hours = max($day_spread_array);
    $min_hours = min($day_spread_array);

    $day_spread = abs($max_hours - $min_hours);

    $temp_spread_score = $day_spread; //add time spread
    $temp_overlap_score = $section_overlaps_score;

    $temp_pref_score = $time_prefs_score;
    $temp_pref_score = $temp_pref_score + $day_prefs_score;

    //Difference between highest and lowest temp score
    $max = max($temp_pref_score, $temp_spread_score, $temp_overlap_score);
    $min = min($temp_pref_score, $temp_spread_score, $temp_overlap_score);
    $difference = abs($max - $min);

    //Average between all three scores
    $average = ($temp_pref_score + $temp_spread_score + $temp_overlap_score) / 3;

    //Sum of Difference and Average = temp score
    $temp_score = $difference + $average;

    if($temp_score < $best_score){
      $best_score = $temp_score;
      $db->exec('COMMIT;');							//
    }
    else{
      $db->exec('ROLLBACK;');
    }
  }
  // INTO HTML
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Course Scheduler</title>
    <link rel="stylesheet" type="text/css" href="./table_style.css"/>

  </head>
  <body>

    <h4 class="instructions"> Here's the schedule! Return to the previous page to export.</h4>
      <a href="index.php?error=successGen" >Click here to return to previous page to download schedule</a>

      <h2>Schedule</h2>
      <div class="container">
        <table class="table">
          <thead>
            <tr>
              <th>Course</th>
              <th>Section</th>
              <th>Course Name</th>
              <th>Professor</th>
              <th>Days</th>
              <th>Meet Times</th>
            </tr>
          </thead>

          <tbody>
            <?php
            // WE CAN ALSO LOOP AN ARRAY TO QUICKLY CREATE A TABLE
            $query_str = "select * from courseScheduled natural join professors natural join courses;";
            $result_set = $db->query($query_str);
            foreach ($result_set as $tuple) {
              //echo "<font color='blue'>$tuple[subjectID]</font> $tuple[courseID]</font> <br/>\n";
              $section_letter = chr($tuple['section'] + 65);
              printf("<tr><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s-%s</td></tr>", $tuple['subjectID'], $tuple['courseID'], $section_letter, $tuple['courseName'],  $tuple['profName'], $tuple['meetDays'], $tuple['meetTimes'], $tuple['endTimes'] );

            }
            ?>
          </tbody>
        </table>
      </div>
      <div class="empty"></div>

    </body>
    </html>
    <?php

}
catch(PDOException $e){
  die('Exception : ' .$e->getMessage());
}

function overlaps($range_start, $range_end, $course_start, $course_end){
  if(($course_start >= $range_start && $course_start < $range_end) || ($course_end > $range_start && $course_end <= $range_end) || ($course_start >= $range_start && $course_end <= $range_end)){
    return 0;
  }
  return 1;
}

//functions:
function assign_days($numDays, $prefs, $past_assignments, $csID){
  //array that maps from numDays to an ordered list of day combos, where the head of the list is the most typical combo
  $typical_days = array(1 => array("M", "T", "W", "R", "F"), 2 => array("RT", "MW", "FM"), 3 => array("FMW", "FMT", "FMR", "FRT"), 4 => array("FMRT", "FMRW", "FMTW")); //TODO add to or change these lists???

  if ($numDays == 5){
    //return the one option for 5 days/week classes
    $day_combo = "MTWRF";
    $past_assignments[$csID] = array($day_combo); //update $past_assignments
    return $past_assignments;
  }
  elseif (sizeof($prefs) == 5 && $numDays == 1) {
    // the prof prefers all days... which is the same as having to preferences
    // assign them a random day
    $rand = rand(0, 4); //TODO if we feel so inclined, pick the day with the least scheduled courses in it already.

    $day_combo = $typical_days[$numDays][$rand];
    $past_assignments[$csID] = array($day_combo); //update $past_assignments
    return $past_assignments;
  }
  else{
    //convert $prefs to a string
    $prefs_string = implode("", $prefs);

    // find first day combo in $typical_days that has > 50% overlap with prof's preferred days
    // if no combo has > 50% overlap, assign the head of the $typical_days array.
    foreach ($typical_days[$numDays] as $day_combo){
      //calculate percent overlap
      $num_sim = similar_text($day_combo, $prefs_string);
      $percent_overlap = $num_sim / strlen($day_combo);

      if ($percent_overlap > 0.5){ //TODO refine
        $past_assignments[$csID] = array($day_combo); //update $past_assignments


        return $past_assignments;
      }
    }
    //no day matched prefs enough, so return most typical day combo (aka the head of the list)
    $day_combo = $typical_days[$numDays][0];
    $past_assignments[$csID] = array($day_combo); //update $past_assignments

    return $past_assignments;
  }
}

function assign_time($day_combo, $time_pref, $classLengthMin, $subjectID, $courseID, $course, $db, $past_assignments, $csID, $numDays){

  $times = array ('earliest' => "08:00", 'morning' => "08:00", 'afternoon' => "11:00", 'evening' => "15:00", 'latest' => "16:59", 'random' => array("8:00", "9:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00")); //TODO can revise these if desired, change optimal time?


  //TODO maybe don't assume class starts at the same time every day.
  // (0) pretend to plop course at beginning of pref time, check if it violates constraints
  if ($time_pref == 'random'){
    $rand_time = rand(0, 8);
    $start_time = $times[$time_pref][$rand_time];
  }
  else{
    $start_time = $times[$time_pref];
  }
  //convert string to time
  $time_format = 'H:i';
  $start_time_time = DateTime::createFromFormat($time_format, $start_time);
  $start_time_time_temp = clone $start_time_time;
  $end_time_time = $start_time_time_temp->modify('+'.$classLengthMin.' minutes');
  $end_time = $end_time_time->format('H:i');



  $filled = 0;
  while ($filled == 0 and (!$day_combo == NULL)){
    //clone initial start and end times for decrease_phase(), becase these times are changed during increase_phase().
    $inc_start_time = $start_time;
    $inc_start_time_time = clone ($start_time_time);
    $inc_end_time = $end_time;
    $inc_end_time_time = clone ($end_time_time);
    //Try assigning by increasing class time
    $filled = increase_phase($db, $course, $times, $inc_start_time, $inc_start_time_time, $inc_end_time, $inc_end_time_time, $subjectID, $courseID, $day_combo);

    if ($filled == 0){
      //clone initial start and end times for decrease_phase(), becase these times are changed during increase_phase().
      $dec_start_time = $start_time;
      $dec_start_time_time = clone ($start_time_time);
      $dec_end_time = $end_time;
      $dec_end_time_time = clone ($end_time_time);
      //Try assigning by decreasing class time
      $filled = decrease_phase($db, $course, $times, $dec_start_time, $dec_start_time_time, $dec_end_time, $dec_end_time_time, $subjectID, $courseID, $day_combo);

      if ($filled == 0){
        //re-assign class days and try again
        $past_assignments = reassign($csID, $past_assignments, $numDays, $db);
        if (!$past_assignments[$csID]){
          break;
        }
        $day_combo = end($past_assignments[$csID]);
      }
    }
  }
  return $filled;

}

function increase_phase($db, $course, $times, $start_time, $start_time_time, $end_time, $end_time_time, $subjectID, $courseID, $day_combo){
  //bump up class time 30 min until filled (return true) or exceed latest class time (return false)
  $assigned = 0; //false
  $idx = 0;
  while($assigned == 0){

    //check for common hour
    $common_hr_start = "12:00";
    $common_hr_end = "13:20";
    if( (strpos($day_combo, "W") != False) && ((($start_time >= $common_hr_start) && ($start_time < $common_hr_end)) || (($end_time > $common_hr_start) && ($end_time <= $common_hr_end)) || (($start_time <= $common_hr_start) && ($end_time >= $common_hr_end)) )) {
      $start_time_time->modify('+30 minutes');
      $end_time_time->modify('+30 minutes');
      $start_time = $start_time_time->format('H:i');
      $end_time = $end_time_time->format('H:i');

      //If the end_time goes over lastest class time, return $assigned == 0.
      if ($start_time > $times['latest']){
        return $assigned;
      }
    }
    else{

      //Get courses that occur at this time on any day
      $overlap_courses_str = "select * from CourseScheduled where (meetTimes < '".$start_time."' AND endTimes > '".$start_time."') OR
      (meetTimes < '".$end_time."' AND endTimes > '".$end_time."') OR
      (meetTimes >= '".$start_time."' AND endTimes <= '".$end_time."') OR
      (meetTimes < '".$start_time."' AND endTimes > '".$end_time."');";
      $overlap_courses = $db->query($overlap_courses_str);
      $overlap_courses_array = $overlap_courses->fetch(PDO::FETCH_ASSOC);

      if ($overlap_courses_array){

        $overlap_courses_str = "(select * from CourseScheduled where (meetTimes < '".$start_time."' AND endTimes > '".$start_time."') OR
        (meetTimes < '".$end_time."' AND endTimes > '".$end_time."') OR
        (meetTimes >= '".$start_time."' AND endTimes <= '".$end_time."') OR
        (meetTimes < '".$start_time."' AND endTimes > '".$end_time."'))";

        //we have to select from the days. So for each char in $day_combo, select $overlap_courses_str and if ANY of the
        //days overlap with courses they shouldn't, move the course 30 min.
        $overlaps_str_A = "(select DISTINCT * from Overlap where (subjectIDA = '".$subjectID."' AND courseIDA = ".$courseID."))";
        $overlaps_str_B = "(select DISTINCT * from Overlap where (subjectIDB = '".$subjectID."' AND courseIDB = ".$courseID."))";
        //get join between overlap courses and overlap relation... if this isn't empty, move this course up by 30 min and try again.

        $bad_overlaps_A = "select section, courseSchedID, meetDays, subjectIDB as subjectID, courseIDB as courseID from ".$overlap_courses_str." NATURAL JOIN ".$overlaps_str_A.";";
        $bad_overlaps_B = "select section, courseSchedID, meetDays, subjectIDA as subjectID, courseIDA as courseID from ".$overlap_courses_str." NATURAL JOIN ".$overlaps_str_B.";";

        $overlap_courses_A = $db->query($bad_overlaps_A);
        $overlap_courses_B = $db->query($bad_overlaps_B);
        //discard overlaping courses that don't fall on the same day as this one.
        $overlaps_A = $overlap_courses_A->fetch(PDO::FETCH_ASSOC);
        $overlaps_B = $overlap_courses_B->fetch(PDO::FETCH_ASSOC);

        //Get courses that this prof is already teaching at this time.
        $bad_overlaps_prof_str = "select profID, section, courseSchedID, meetDays, subjectID, courseID from CourseScheduled where ((meetTimes < '".$start_time."' AND endTimes > '".$start_time."') OR
        (meetTimes < '".$end_time."' AND endTimes > '".$end_time."') OR
        (meetTimes >= '".$start_time."' AND endTimes <= '".$end_time."') OR
        (meetTimes < '".$start_time."' AND endTimes > '".$end_time."')) AND
        (profID = ".$course['profID'].");";
        $bad_overlaps_prof = $db->query($bad_overlaps_prof_str);
        $bad_overlaps_prof_array = $bad_overlaps_prof->fetchAll(PDO::FETCH_ASSOC);

        $kept_overlaps_A = array();
        $kept_overlaps_B = array();
        $kept_overlaps_prof = array();

        //Only keep overlap courses that have same day(s)
        foreach($overlap_courses_A as $overlap){
          // print_r($overlap);
          //calculate percent overlap of days of courses
          $num_sim = similar_text($day_combo, $overlap['meetDays']);
          $percent_overlap = $num_sim / strlen($day_combo);

          if ($percent_overlap > 0){
            //keep overlap if there is any overlap in days
            array_push($kept_overlaps_A, $overlap);
          }
        }
        foreach($overlap_courses_B as $overlap){
          //calculate percent overlap of days of courses
          $num_sim = similar_text($day_combo, $overlap['meetDays']);
          $percent_overlap = $num_sim / strlen($day_combo);

          if ($percent_overlap > 0){
            //keep overlap if there is any overlap in days
            array_push($kept_overlaps_B, $overlap);
          }
        }
        for ($i = 0; $i < sizeof($bad_overlaps_prof_array); $i = $i+1){
          $overlap = $bad_overlaps_prof_array[$i];

          // //calculate percent overlap of days of courses
          $num_sim = similar_text($day_combo, $overlap['meetDays']);
          $percent_overlap = $num_sim / strlen($day_combo);

          if ($percent_overlap > 0){
            //keep overlap if there is any overlap in days
            array_push($kept_overlaps_prof, $overlap);
          }
        }
        //If any of the courses on overlaping days have overlaping times, move the course by 30 min
        if ($kept_overlaps_A || $kept_overlaps_B || $kept_overlaps_prof){
          if (sizeof($kept_overlaps_A) > 0 || sizeof($kept_overlaps_B) > 0 || sizeof($kept_overlaps_prof) > 0){
            $start_time_time->modify('+30 minutes');
            $end_time_time->modify('+30 minutes');
            $start_time = $start_time_time->format('H:i');
            $end_time = $end_time_time->format('H:i');

            //If the end_time goes over lastest class time, return $assigned == 0.
            if ($end_time > $times['latest']){
              return $assigned;
            }
          }
        }
        //If no courses with overlaping days have overlaping times, assign the course to this time
        else{
          $assigned = 1;

          $assign_query_str = $db->prepare("update CourseScheduled SET meetTimes = '".$start_time."', endTimes = '".$end_time."' where courseSchedID = ".$course['courseSchedID'].";");
          try{
            $assign_these_times = $assign_query_str->execute();
          }
          catch(Exception $e){
            echo $e;
            exit();
          }

          return $assigned;
        }

        $overlap_courses_A = null;
        $overlap_courses_B= null;
        $bad_overlaps_prof_array = null;
      }
      //If no courses with overlaping days have overlaping times, assign the course to this time
      else{
        $assigned = 1;

        $assign_query_str = $db->prepare("update CourseScheduled SET meetTimes = '".$start_time."', endTimes = '".$end_time."' where courseSchedID = ".$course['courseSchedID'].";");
        try{
          $assign_these_times = $assign_query_str->execute();
        }
        catch(Exception $e){
          echo $e;
          exit();
        }

        return $assigned;
      }
    }
  }
}

function decrease_phase($db, $course, $times, $start_time, $start_time_time, $end_time, $end_time_time, $subjectID, $courseID, $day_combo){

  //bump down class time 30 min until filled (return true) or exceed earliest class time (return false)
  $assigned = 0; //false
  $idx = 0;
  while($assigned == 0){

    //check for common hour
    $common_hr_start = "12:00";
    $common_hr_end = "13:20";
    if( (strpos($day_combo, "W") != False) && ((($start_time >= $common_hr_start) && ($start_time < $common_hr_end)) || (($end_time > $common_hr_start) && ($end_time <= $common_hr_end)) || (($start_time <= $common_hr_start) && ($end_time >= $common_hr_end)) )) {
      $start_time_time->modify('-30 minutes');
      $end_time_time->modify('-30 minutes');
      $start_time = $start_time_time->format('H:i');
      $end_time = $end_time_time->format('H:i');

      //If the end_time goes over lastest class time, return $assigned == 0.
      if ($start_time < $times['earliest']){
        return $assigned;
      }
    }
    else{

      //Get courses that occur at this time on any day
      $overlap_courses_str = "select * from CourseScheduled where (meetTimes < '".$start_time."' AND endTimes > '".$start_time."') OR
      (meetTimes < '".$end_time."' AND endTimes > '".$end_time."') OR
      (meetTimes >= '".$start_time."' AND endTimes <= '".$end_time."') OR
      (meetTimes < '".$start_time."' AND endTimes > '".$end_time."');";
      $overlap_courses = $db->query($overlap_courses_str);
      $overlap_courses_array = $overlap_courses->fetch(PDO::FETCH_ASSOC);

      if ($overlap_courses_array){

        $overlap_courses_str = "(select * from CourseScheduled where (meetTimes < '".$start_time."' AND endTimes > '".$start_time."') OR
        (meetTimes < '".$end_time."' AND endTimes > '".$end_time."') OR
        (meetTimes >= '".$start_time."' AND endTimes <= '".$end_time."') OR
        (meetTimes < '".$start_time."' AND endTimes > '".$end_time."'))";

        //we have to select from the days. So for each char in $day_combo, select $overlap_courses_str and if ANY of the
        //days overlap with courses they shouldn't, move the course 30 min.
        $overlaps_str_A = "(select DISTINCT * from Overlap where (subjectIDA = '".$subjectID."' AND courseIDA = ".$courseID."))";
        $overlaps_str_B = "(select DISTINCT * from Overlap where (subjectIDB = '".$subjectID."' AND courseIDB = ".$courseID."))";
        //get join between overlap courses and overlap relation... if this isn't empty, move this course up by 30 min and try again.

        $bad_overlaps_A = "select section, courseSchedID, meetDays, subjectIDB as subjectID, courseIDB as courseID from ".$overlap_courses_str." NATURAL JOIN ".$overlaps_str_A.";";
        $bad_overlaps_B = "select section, courseSchedID, meetDays, subjectIDA as subjectID, courseIDA as courseID from ".$overlap_courses_str." NATURAL JOIN ".$overlaps_str_B.";";
        $overlap_courses_A = $db->query($bad_overlaps_A);
        $overlap_courses_B = $db->query($bad_overlaps_B);
        //discard overlaping courses that don't fall on the same day as this one.
        $overlaps_A = $overlap_courses_A->fetch(PDO::FETCH_ASSOC);
        $overlaps_B = $overlap_courses_B->fetch(PDO::FETCH_ASSOC);

        //Get courses that this prof is already teaching at this time.
        $bad_overlaps_prof_str = "select profID, section, courseSchedID, meetDays, subjectID, courseID from CourseScheduled where ((meetTimes < '".$start_time."' AND endTimes > '".$start_time."') OR
        (meetTimes < '".$end_time."' AND endTimes > '".$end_time."') OR
        (meetTimes >= '".$start_time."' AND endTimes <= '".$end_time."') OR
        (meetTimes < '".$start_time."' AND endTimes > '".$end_time."')) AND
        (profID = ".$course['profID'].");";
        //  echo "<br> Bad overlaps prof: ".$bad_overlaps_prof_str."<br>";
        $bad_overlaps_prof = $db->query($bad_overlaps_prof_str);
        $bad_overlaps_prof_array = $bad_overlaps_prof->fetchAll(PDO::FETCH_ASSOC);

        $kept_overlaps_A = array();
        $kept_overlaps_B = array();
        $kept_overlaps_prof = array();

        //Only keep overlap courses that have same day(s)
        foreach($overlap_courses_A as $overlap){
          //calculate percent overlap of days of courses
          $num_sim = similar_text($day_combo, $overlap['meetDays']);
          $percent_overlap = $num_sim / strlen($day_combo);

          if ($percent_overlap > 0){
            //keep overlap if there is any overlap in days
            array_push($kept_overlaps_A, $overlap);
          }
        }
        foreach($overlap_courses_B as $overlap){
          //calculate percent overlap of days of courses
          $num_sim = similar_text($day_combo, $overlap['meetDays']);
          $percent_overlap = $num_sim / strlen($day_combo);

          if ($percent_overlap > 0){
            //keep overlap if there is any overlap in days
            array_push($kept_overlaps_B, $overlap);
          }
        }
        for ($i = 0; $i < sizeof($bad_overlaps_prof_array); $i = $i+1){
          $overlap = $bad_overlaps_prof_array[$i];

          // //calculate percent overlap of days of courses
          $num_sim = similar_text($day_combo, $overlap['meetDays']);
          $percent_overlap = $num_sim / strlen($day_combo);

          if ($percent_overlap > 0){
            //keep overlap if there is any overlap in days
            array_push($kept_overlaps_prof, $overlap);
          }
        }
        //If any of the courses on overlaping days have overlaping times, move the course by 30 min
        if ($kept_overlaps_A || $kept_overlaps_B || $kept_overlaps_prof){
          if (sizeof($kept_overlaps_A) > 0 || sizeof($kept_overlaps_B) > 0 || sizeof($kept_overlaps_prof) > 0){
            $start_time_time->modify('-30 minutes');
            $end_time_time->modify('-30 minutes');
            $start_time = $start_time_time->format('H:i');
            $end_time = $end_time_time->format('H:i');

            //If the end_time goes over lastest class time, return $assigned == 0.
            if ($start_time < $times['earliest']){
              return $assigned;
            }
          }
        }
        //If no courses with overlaping days have overlaping times, assign the course to this time
        else{
          $assigned = 1;

          $assign_query_str = $db->prepare("update CourseScheduled SET meetTimes = '".$start_time."', endTimes = '".$end_time."' where courseSchedID = ".$course['courseSchedID'].";");
          try{
            $assign_these_times = $assign_query_str->execute();
          }
          catch(Exception $e){
            echo $e;
            exit();
          }

          return $assigned;
        }

        $overlap_courses_A = null;
        $overlap_courses_B= null;
        $bad_overlaps_prof_array = null;
      }
      //If no courses with overlaping days have overlaping times, assign the course to this time
      else{
        $assigned = 1;

        $assign_query_str = $db->prepare("update CourseScheduled SET meetTimes = '".$start_time."', endTimes = '".$end_time."' where courseSchedID = ".$course['courseSchedID'].";");
        try{
          $assign_these_times = $assign_query_str->execute();
        }
        catch(Exception $e){
          echo $e;
          exit();
        }

        return $assigned;
      }

    }

  }
}

function reassign($csID, $past_assignments, $numDays, $db){
  //TODO re-assign the days of a class, keeping track of past day assignments in a hashmap.
  //If all day assignments have been tried, throw an error. (In reality we should re-assign other classes... but that seems too hard.)
  $typical_days = array(1 => array("M", "T", "W", "R", "F"), 2 => array("RT", "MW", "FM", "FW", "MR", "FT"), 3 => array("FMW", "FMT", "FMR", "FRT", "MRT"), 4 => array("FMRT", "FMRW", "FMTW", "FRTW", "FMRT"), 5 => array("FMRTW")); //TODO add to or change these lists???

  foreach ($typical_days[$numDays] as $day_combo){
    //check if it's already been assigned. If not, add and return it.
    if (!in_array($day_combo, $past_assignments[$csID])){
      array_push($past_assignments[$csID], $day_combo); //add $day_combo
      //UPDATE courseScheduled relation
      $reassign_str = $db->prepare("update CourseScheduled SET meetDays = '".$day_combo."' where courseSchedID = ".$csID.";");
      try{
        $reassign = $reassign_str->execute();
      }
      catch(Exception $e){
        echo $e;
        exit();
      }

      return $past_assignments;
    }
  }
  //All day combos have been tried, throw an error.
  $past_assignments[$csID] = NULL;
  return $past_assignments;
}

?>
