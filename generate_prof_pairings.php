<?php

try{
  srand();
  //load in our database
  $db = new PDO('sqlite:./myDB/course_sched.db');
  $db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $clear_courseSched = $db->prepare("DELETE from CourseScheduled;");
  try{
    $clear_courseSched ->execute();
  }
  catch (Exception $e) {
    echo $e;
    exit();
  }
  //best ranking
  $best_score = -1;
  for($i = 0; $i < 1; $i = $i + 1){
    //Step 1: match professors to courses, populated Teaches
    $db->exec('BEGIN;');
    //hashmap where keys are profID and values are numPreps and numCourses
    $profHash = array();

    $query_str = "select * from professors;";
    $result_set = $db->query($query_str);

    $index = 0;
    foreach($result_set as $tuple) {
      $profHash[$index] = [
        'numPreps' => $tuple[2],
        'numCourses' => $tuple[3],
        'numAssigned' => 0
      ];
      $index = $index + 1;
    }
    //Make a HashMap, where courses are keys and values are professors
    $courseHash = [];

    $courses_query_str = "select * from courses;";
    $courses_result_set = $db->query($courses_query_str);

    $courseSchedID = 0;
    foreach($courses_result_set as $tuple) {
      //for each course, find profs that can teach it.
      $currSubject = $tuple[0];
      $currCourse = $tuple[1];

      $teaches_query_str = "select * FROM teaches WHERE subjectID = '".$currSubject."' and courseID = ".$currCourse.";";
      $teaches_this_course = $db->query($teaches_query_str); //$teaches_this_course holds list of profs that can teach this course

      //create array of profs that can teach this course
      $profs = array();

      foreach($teaches_this_course as $profTuple){
        array_push($profs, $profTuple[0]); // 0 because index of profID
      }
      // populate Couses hash map and insert into CourseSched Relation
      $courseNumSections = $tuple[4]; //34becuse index of numsections
      //echo "num sections: ".$courseNumSections."<br>";
      for($sectNum = 0; $sectNum < $courseNumSections; $sectNum = $sectNum + 1){
        //populate Couses hash map
        $courseHash[$courseSchedID] = [
          'courseSchedID' => $courseSchedID,
          'profs' => $profs
        ];
        //insert into CourseSched Relation
        if($i == 0){
          $stmt = $db->prepare("INSERT INTO CourseScheduled VALUES (:courseSchedID, :subjectID, :courseID, :section, NULL, NULL, NULL, NULL);");

          $stmt -> bindParam(':courseSchedID', $courseSchedID);
          $stmt -> bindParam(':subjectID', $tuple[0]);
          $stmt -> bindParam(':courseID', $tuple[1]);
          $stmt -> bindParam(':section', $sectNum);

          try{
            $stmt ->execute();
          }
          catch (Exception $e) {
            echo $e;
            exit();
          }
        }
        $courseSchedID = $courseSchedID + 1;
      }
    }

    // //if any course has NO profs that can teach it, throw an error.
    if (sizeof($courseHash[shortestCourse($courseHash)]['profs']) == 0){
      //ERROR there exists a course that no profs can teach
      header("Location: index.php?error=noTeach&course=".shortestCourse($courseHash));

      // echo "There is a course that no prof can teach!"; //TODO tell user which course this is.
      $temp_query = "Select * from CourseScheduled where courseSchedID = ".shortestCourse($courseHash).";";
      $course_no_prof = $db->query($temp_query);
      foreach($course_no_prof as $tuple){
        header("Location: index.php?error=noTeach&course=".$tuple['subjectID']." ".$tuple['courseID']);
      }
    }
    else {
      //Schedule away!!
      $coursesLeft = sizeof($courseHash);
      while($coursesLeft > 0){
        if (sizeof($courseHash[shortestCourse($courseHash)]['profs']) == 0){
          //ERROR there exists a course that no profs can teach
          header("Location: index.php?error=noMoreUnits&course=".shortestCourse($courseHash));
          // echo "There is a course that no prof can teach!"; //TODO tell user which course this is.
          $temp_query = "Select * from CourseScheduled where courseSchedID = ".shortestCourse($courseHash).";";
          $course_no_prof = $db->query($temp_query);
          foreach($course_no_prof as $tuple){
            header("Location: index.php?error=noMoreUnits&course=".$tuple['subjectID']." ".$tuple['courseID']);
          }
        }

        if (sizeof($courseHash[shortestCourse($courseHash)]['profs']) ==  1){
          //there are courses that only one prof can teach. Assign these.
          $courseHash = phaseA($courseHash, $profHash, $db);
        }
        else{
          $courseHash = phaseB($courseHash, $profHash, $db);
        }
        $coursesLeft = $coursesLeft - 1;
      }
    }
    //generate score

    $temp_score = 0;

    $query_str = "select * from CourseScheduled;";
    $result_set = $db->query($query_str);
    foreach($result_set as $tuple) {
      $query_str = "select * from PreferredCourses where profID = ".$tuple['profID']." and courseID = ".$tuple['courseID']." and subjectID = '".$tuple['subjectID']."';";
      $good_matches = $db->query($query_str);
      $good_matches_array = $good_matches->fetch(PDO::FETCH_ASSOC);
      if($good_matches_array && sizeof($good_matches_array) > 0){
        $temp_score = $temp_score + 1;
      }
    }
    if($temp_score > $best_score){
      $best_score = $temp_score;
      $db->exec('COMMIT;');
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

    <h4 class="instructions"> Please examine the data you entered below to ensure it is
      correct. You may return to the previous page to re-upload a file if needed. </h4>

      <a href="index.php" >Click here to return to previous page</a>

      <h2>Professor Pairings</h2>
      <div class="container">
        <table class="table">
          <thead>
            <tr>
              <th>Course</th>
              <th>Section</th>
              <th>Professor</th>
            </tr>
          </thead>

          <tbody>
            <?php
            // WE CAN ALSO LOOP AN ARRAY TO QUICKLY CREATE A TABLE
            $query_str = "select * from courseScheduled natural join professors;";
            $result_set = $db->query($query_str);
            foreach ($result_set as $tuple) {
              //echo "<font color='blue'>$tuple[subjectID]</font> $tuple[courseID]</font> <br/>\n";
              $section_letter = chr($tuple['section'] + 65);
              printf("<tr><td>%s %s</td><td>%s</td><td>%s</td></tr>", $tuple['subjectID'], $tuple['courseID'], $section_letter, $tuple['profName'] );
            }
            ?>
          </tbody>
        </table>
      </div>
    </body>
    </html>
    <?php

  $db = null;
}
catch(PDOException $e){
  die('Exception : ' .$e->getMessage());
}

//returns a course with the fewest profs that can teach it (random if tie)
function shortestCourse($courseHash){
  $min = 10000; //YIKES
  $minIdx = -1;
  foreach($courseHash as $csID => $course_info ){
    if(!$courseHash[$csID]['profs']){
      return $csID;
    }
    if($courseHash[$csID]['profs'] && sizeof($courseHash[$csID]['profs']) < $min){
      $min = sizeof($courseHash[$csID]['profs']);
      $minIdx = $csID;
    }
  }
  return $minIdx;
}

//deals with courses where there is only one professor that can teach it
function phaseA($courseHash, $profHash, $db){
  $course_to_schedule = $courseHash[shortestCourse($courseHash)];
  $the_courseSchedID = $course_to_schedule['courseSchedID'];
  $the_profID = reset($courseHash[$the_courseSchedID]['profs']);
  $courseHash = assign($the_courseSchedID, $the_profID, $courseHash, $db);
  return $courseHash;

}

//deals with courses where there are multiple professors that can teach it
function phaseB($courseHash, $profHash, $db){
  $course_to_schedule = $courseHash[shortestCourse($courseHash)];
  $the_courseSchedID = $course_to_schedule['courseSchedID'];
  //$the_profID_idx = breakTheTie($the_courseSchedID, $courseHash);
  $the_profID_idx = array_rand($courseHash[$the_courseSchedID]['profs']);
  $the_profID = $courseHash[$the_courseSchedID]['profs'][$the_profID_idx];
  $courseHash = assign($the_courseSchedID, $the_profID, $courseHash, $db);
  return $courseHash;
}

function breakTheTie($a_courseSchedID, $courseHash){
  //start with random... will need to change this.
  $max = sizeof($courseHash[$a_courseSchedID]['profs'])-1;
  $rand = rand(0, $max);
  return $rand;
}

function assign($a_courseSchedID, $a_profID, $courseHash, $db){
  global $profHash;
  //update courseScheduled relation
  $assign_query_str = $db->prepare("update CourseScheduled SET profID = ".$a_profID." WHERE courseSchedID = ".$a_courseSchedID.";");
  try{
    $assign_this_course =$assign_query_str->execute();
  }
  catch (Exception $e) {
    echo $e;
    exit();
  }
  $courses_query_str = "select * from courses natural join courseScheduled where courseSchedID = ".$a_courseSchedID.";";
  $this_tuple_str = $db->query($courses_query_str);
  $tup_one = $this_tuple_str->fetch(PDO::FETCH_ASSOC);

  $teaching_units = $tup_one['teachingUnits'];
  //update profs (numCourses, numPreps, numAssigned)
  $profHash[$a_profID]['numCourses'] = ($profHash[$a_profID]['numCourses'] - $teaching_units);//numCourses
  $profHash[$a_profID]['numAssigned'] = ($profHash[$a_profID]['numAssigned'] + $teaching_units);//numAssigned
  //check if prof already teaching another section of this course
  $this_tuple = $db->query("select * from CourseScheduled where courseSchedID = ".$a_courseSchedID.";");
  $tup = $this_tuple->fetch(PDO::FETCH_ASSOC);
  $this_subjID = $tup['subjectID'];
  $this_courseID = $tup['courseID'];

  //are there any courses that have already been scheduled besides this one where its the same course and taught by the same prof
  //if there are any courses where they are already teaching this course, don't decrement num preps
  $prof_check = $db->query("select * from CourseScheduled where subjectID = '".$this_subjID."' and courseID = ".$this_courseID." and profID = ".$a_profID." and courseSchedID != ".$a_courseSchedID.";");
  $prof_check_array = $prof_check->fetch(PDO::FETCH_ASSOC);

  //check if the course we are pairing is a different course, if so, decrement the number of preps for that prof
  if ($prof_check_array && sizeof($prof_check_array) == 0){
    $profHash[$a_profID]['numPreps'] = ($profHash[$a_profID]['numPreps'] - 1); //numPreps
  }
  elseif (!$prof_check_array){
    $profHash[$a_profID]['numPreps'] = ($profHash[$a_profID]['numPreps'] - 1); //numPreps
  }
  //delete course from courseHash
  unset($courseHash[$a_courseSchedID]);
  //remove prof from necessary prof lists in courseHash
  //if numCourses == 0, remove prof from all courses' lists
  if($profHash[$a_profID]['numCourses'] <= 0){
    foreach ($courseHash as $csID){
      $prof_list = $csID['profs'];
      if (in_array($a_profID, $prof_list)){
        //get index of profID in $prof_list
        $prof_idx = array_search($a_profID, $prof_list);
        //remove profID from prof_list
        unset($courseHash[$csID['courseSchedID']]['profs'][$prof_idx]);
      }
    }
  }

  //if num preps is zero, this prof needs to be deleted from the prof lists in course hash where that course is not one that the
  // prof is already teaching
  elseif($profHash[$a_profID]['numPreps'] <= 0){
    foreach ($courseHash as $csID){
      $prof_list = $csID['profs'];
      //is the prof in this array?
      if (in_array($a_profID, $prof_list)){
        $prof_idx = array_search($a_profID, $prof_list);
        //get the section and courseID for the current course
        $this_tuple_cur = $db->query("select * from CourseScheduled where courseSchedID = ".$csID['courseSchedID'].";");
        $tup_cur = $this_tuple_cur->fetch(PDO::FETCH_ASSOC);
        $this_subjID_cur = $tup_cur['subjectID'];
        $this_courseID_cur = $tup_cur['courseID'];
        //are they the same as the one we just paired?
        if($this_subjID_cur == $this_subjID && $this_courseID == $this_courseID_cur){
          unset($courseHash[$csID['courseSchedID']]['profs'][$prof_idx]); // remove that prof from the list
        }
      }
    }
  }
  return $courseHash;
}

?>
