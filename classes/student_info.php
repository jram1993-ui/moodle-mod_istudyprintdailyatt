<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_istudyprintdailyatt;

use stdClass;

/**
 * Class student_info
 *
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_info {

    // Count the number of students chosen.
    public static function get_student_chosen($submission) {
        $student_count = $submission->studentcount;
        for ($i = 1; $i <= $student_count; $i++) {
            $this_student = 'student_chosen' . $i;
            // Check to make sure that the student exists and has not been deleted (rare occurance).
            if (!property_exists($submission, $this_student)) {
                return false;
            }
            $this_studentid = $submission->$this_student;
            if ($this_studentid > 0) {
                return true;
            }
        }
        return false;
    }

    //  Get the student demographics.
    public static function get_student_att_info($student_id, $name_of_session, $name_of_session_credits) {
        global $DB;
        // Student Info
        $student_info = new stdClass();
        $student_info->id = $student_id;
        $student_info->school_id = utils::get_field_data($student_id, utils::get_fieldid('schoolid'));
        if ($student_temp = $DB->get_record('user', ['id' => $student_id] , 'firstname, lastname, deleted, suspended')) {
            $student_info->student_name = $student_temp->lastname . ', ' . $student_temp->firstname;
            $student_info->deleted = $student_temp->deleted;
            $student_info->suspended = $student_temp->suspended;
            // Find the course and break it up into its components -- example: Spanish IA/Ramirez-Jesus/650512/304.
            $this_course_temp =  utils::get_mycourse(utils::get_field_data($student_id, utils::get_fieldid($name_of_session)));
            $student_info->courseid = $this_course_temp->section;
            $student_info->course_name = $this_course_temp->subject;
            $student_info->teacher = $this_course_temp->teacher;
            $student_info->credits = utils::get_field_data($student_id, utils::get_fieldid($name_of_session_credits));
            $student_info->grade = utils::get_gradelevel(utils::get_field_data($student_id, utils::get_fieldid('graduationyear')));
            return $student_info;
        }
        else {
            $student_info->credits = 0;
            $student_info->suspended = true;
            $student_info->deleted = true;
            return $student_info;
        }
    }

    // Get all of the students for the advisor by day of the week (Monday-Friday).
    public static function get_students_by_session($this_session, $school_advisor, $school_term, $school_day_of_week) {
        global $DB;
        $mystudent_list[] = array();
        $count = -1;
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        $this_school_term = utils::get_term($school_term);
        $advisor_name = $school_advisor; // $school_advisor is a number that identifies the advisor
        // Check for a daily schedule. If 'No', student schedules are different each day of the week.
        // If 'Yes', Mondays schedule is applied to each day of the week (Monday-Friday).
        if ($school_term == 1 OR $school_term == 2) {
            $dailyschedule = get_config('mod_istudy', 'dailyschedule');
        }
        else {
            $dailyschedule = get_config('mod_istudy', 'summer_dailyschedule');
        }
        if ($dailyschedule == 'Yes') {
            $school_day_of_week = 1;
        }
        $name_of_session = $this_school_term . $week_day[$school_day_of_week] . 'session' .  $this_session;
        $name_of_session_credits = $this_school_term . $week_day[$school_day_of_week] . 'creditssession' .  $this_session;
        $course_id = utils::get_fieldid($name_of_session);  // fallmondaysession1
        $select = "SELECT * FROM {user_info_data} WHERE fieldid = " . $course_id . " AND data LIKE '%" . $advisor_name . "%'";
        $mystudents = $DB->get_records_sql($select, null, 0,0);
        // Get the data that identifies the student.
        foreach ($mystudents as $mystudent) {
            $temp_info = student_info::get_student_att_info($mystudent->userid, $name_of_session, $name_of_session_credits);
            // Don't include students that have been deleted or suspended.
            if (($temp_info->deleted == false) and ($temp_info->suspended == false)){
                $count = $count + 1;
                $mystudent_list[$count] = new stdClass();
                $mystudent_list[$count]->id = $mystudent->userid;
                $mystudent_list[$count]->school_id = $temp_info->school_id;
                $mystudent_list[$count]->student_name = $temp_info->student_name;
                $mystudent_list[$count]->grade = $temp_info->grade;
                $mystudent_list[$count]->courseid = $temp_info->courseid;
                $mystudent_list[$count]->course_name = $temp_info->course_name;
                $mystudent_list[$count]->credits = $temp_info->credits;
            }
        }
        // If no students were found, then return.
        if ($count == -1) {
            return 0;
        }
        // Sort the list by student ID.
        for ($i=0; $i<=$count; $i++){
            $val = $mystudent_list[$i];
            $j = $i-1;
            while($j>=0 && $mystudent_list[$j]->school_id > $val->school_id){
                $mystudent_list[$j+1] = $mystudent_list[$j];
                $j--;
            }
            $mystudent_list[$j+1] = $val;
        }
        // Sort by student name.
        for ($i=0; $i<=$count; $i++){
            $val = $mystudent_list[$i];
            $j = $i-1;
            while($j>=0 && $mystudent_list[$j]->student_name > $val->student_name){
                $mystudent_list[$j+1] = $mystudent_list[$j];
                $j--;
            }
            $mystudent_list[$j+1] = $val;
        }
        return $mystudent_list;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_weekly_title($school_name, $school_advisor, $school_term, $my_dayofweek) {
        $page_title = '';
        // Page Title - School Information
        $page_title = $page_title . '<h1>' . $school_name . '</h1>' .
            '<h2>' . utils::put_comma_in_name($school_advisor) . ' - ' . strtoupper(utils::get_term($school_term)) .' Daily Attendance</h2>'.
            '<h3>Starting Week - ' . date("l, M d, Y", $my_dayofweek) . '</h3><br>';
        // Table heading
        $page_title = $page_title . '<table><tr>' .
            '<th width="2%" class="cellborderall">#</th>' .
            '<th width="20%" class="cellborderbottom">STUDENT NAME</th>' .
            '<th width="16%" class="cellborderbottom">COURSE NAME</th>' .
            '<th width="2%" class="cellbordernotright">A</th>' . // Absent total
            '<th width="2%" class="cellborderbottom">S</th>' . // Present in class total
            '<th width="2%" class="cellborderbottom">T</th>' . // Distance learning total
            '<th width="13%" class="cellborderall" colspan="5">Weeks 1-5</th><th width="13%" class="cellborderall" colspan="5">Weeks 6-10</th>' .
            '<th width="13%" class="cellborderall" colspan="5">Weeks 11-15</th><th width="13%" class="cellborderall" colspan="5">Weeks 16-20</th>' .
            '<th width="4%" class="cellborderall" colspan="2"> </th></tr>';
        return $page_title;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_weekly_header($this_session, $time_session, $my_day) {
        $page_header = '';
        $page_header = $page_header . '<tr class="fontfordates"><td></td><td colspan="2" class="mycell"><center><b>Period ' . $this_session . '</b> (' . $time_session . ')</center></td><td colspan="3" class="leftborder_dark"></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[1] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[2] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[3] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[4] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[5] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[6] . '</td><td width="2%" class="cell_width"><div>' . $my_day[7] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[8] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[9] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[10] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[11] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[12] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[13] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[14] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[15] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[16] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[17] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[18] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[19] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[20] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>'. $my_day[21] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[22] . '</div></td></tr>';
        return $page_header;
    }

    // Get student attendance for an advisor and for a specific day of the week - will get 20 weeks of attendance.
    public static function get_day_attendance($school_advisor, $school_term, $school_start_of_week, $school_day_of_week) {
        global $DB;
        $mystudent_list[][] = array();
        $duplicate_student_count_id[] = array();
        $duplicate_student_count_course_number[] = array();
        $count = 0;
        $page_count = 0;
        $max_lines_per_page = 50;
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        $number_of_sessions = get_config('mod_istudy', 'number_of_sessions');
        $school_name =  get_config('mod_istudy', 'schoolname');
        $advisorid = teacher_info::get_whichadvisorid($school_advisor);
        // Get the times for each session.
        $summer = '';
        if ($school_term == 'Summer') {$summer = 'summer_';}
        $time_session[] = array() ;
        for ($t = 1; $t <= $number_of_sessions; $t++) {
            $time_session[$t] = get_config('mod_istudy', $summer . $week_day[$school_day_of_week] . 'session' . $t);
        }
        // Get the exact date for this day of the week (Monday-Friday).
        $my_dayofweek = utils::get_attendance_date($school_start_of_week, $school_day_of_week);
        // Get the dates for the 20 weeks of attendance
        $my_day = utils::get_attendance_weeks($school_start_of_week, $school_day_of_week);
        for ($this_day = 1; $this_day <=22; $this_day++) {
            if ($my_day[$this_day] <> 0) {
                $my_day_text [$this_day] = date("m/d", $my_day[$this_day]); // The text version of the date
            }
            else {
                 $my_day_text [$this_day] = '';
            }
        }
        // Initialize html page
        $advisor_info = '<link rel="stylesheet" type="text/css" href="styles-print_daily_att.css"><html><html><head><title>ATTENDANCE - ' . strtoupper($week_day[$school_day_of_week]) . '</title></head><body>';
        // Page Title - School Information
        $advisor_info = $advisor_info . student_info::get_weekly_title($school_name, $school_advisor, $school_term, $my_dayofweek);
        for ($t = 1; $t <= $number_of_sessions; $t++){
            // Table heading - with dates - if page is full, start a new page, otherwise, just print the header.
            // Page is full. Start on a new page.
            if ($page_count+1 > $max_lines_per_page) {
                // This provides a page break.
                $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></html><P style="page-break-before: always">';
                // Page Title - School Information
                $advisor_info = $advisor_info . student_info::get_weekly_title($school_name, $school_advisor, $school_term, $my_dayofweek);
                // Table heading - with dates
                $advisor_info = $advisor_info . student_info::get_weekly_header($t, $time_session[$t], $my_day_text);
                $page_count = 0;
            }
            else {
                $advisor_info = $advisor_info . student_info::get_weekly_header($t, $time_session[$t], $my_day_text);
            }
            // Get the students names - find all students that have the teacher on the day of the week and during current session.
            $mystudent_list = student_info::get_students_by_session($t, $school_advisor, $school_term, $school_day_of_week);
            // If there are no students during current session, print None.
            if ($mystudent_list == 0) {
                // New row with the student information.
                $advisor_info = $advisor_info . '<tr class="cell_student"><td class="cellborderleft"><center>0</center></td>' .
                    '<td colspan="2" class="cellborderleft"><center>None</center></td>' ;
                // Same row with attendance information attached.
                $advisor_info = $advisor_info . '<td class="att_cellborderleft">0</td><td class="att_text_right">0</td><td class="att_text_right">0</td>';
                $advisor_info = $advisor_info . '<td colspan="5" class="cellborderleft"></td><td colspan="5" class="cellborderleft">'.
                    '<td colspan="5" class="cellborderleft"></td><td colspan="5" class="cellborderleft"></td></tr>';
            }
            else {
                // Print student attendance for each subject enrolled this session.
                $count = 0;
                foreach ($mystudent_list as $mystudent) {
                    $my_absent = 0;
                    $my_present = 0;
                    $my_tardy = 0;
                    $attendance_info = '';
                    $page_count = $page_count + 1;

                    // Page is full. Start on a new page.
                    if ($page_count > $max_lines_per_page) {
                        // This provides a page break
                        $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></body></html><P style="page-break-before: always">';
                        // Page Title - School Information
                        $advisor_info = $advisor_info . student_info::get_weekly_title($school_name, $school_advisor, $school_term, $my_dayofweek);
                        // Table heading - with dates
                        $advisor_info = $advisor_info . student_info::get_weekly_header($t, $time_session[$t], $my_day_text);
                        $page_count = 0;
                    }
                    $this_attendance = new stdClass();
                    for ($i = 1; $i <= 22; $i++){
                        $dark_border_left = '';
                        if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft"';}
                        $table = 'istudy_dailyattend_enter';
                        $select = "attendance_date = :attendance_date AND student_id = :student_id AND teacher_id = :teacher_id AND course = :course AND session = :session";
                        $params =['attendance_date'=>$my_day[$i], 'student_id'=>$mystudent->id, 'teacher_id'=>$advisorid, 'course'=>$mystudent->courseid, 'session'=>$t];
                        // If the course is active, return true.
                        if ($this_attendance = $DB->get_record_select($table, $select, $params)) {
                            $my_attendance = $this_attendance->attendance_status;
                            if ($my_attendance == 0) {
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>-</center></td>';
                            }
                            else if ($my_attendance == 1) {
                                $my_absent = $my_absent + 1;
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><b><center>A</center></b></td>';
                            }
                            else if ($my_attendance == 2) {
                                $my_present = $my_present + 1;
                                $dark_border_left = ' class="cellbordernotleft_small"';
                                if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft_small"';}
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>P</center></td>';
                            }
                            else if ($my_attendance == 3) {
                                $my_present = $my_present + 1;
                                $dark_border_left = ' class="cellbordernotleft_small"';
                                if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft_small"';}
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>D</center></td>';
                            }
                            else if ($my_attendance == 4) {
                                $my_tardy = $my_tardy + 1;
                                $dark_border_left = ' class="cellbordernotleft_small"';
                                if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft_small"';}
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>T</center></td>';
                            }
                        }
                        else {
                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'></td>';
                        }
                    }

                    $count = $count + 1;
                    // New row with the student information.
                    $advisor_info = $advisor_info . '<tr class="cell_student"><td class="cellborderleft_and_right"><center>' . $count . '</center></td>' .
                        '<td><p class="cut_text_student">' . $mystudent->student_name . ' (' . $mystudent->grade . ')</p></td>' .
                        '<td><p class="cut_text_course">' . $mystudent->course_name . ' [' . $mystudent->credits . ']</p></td>';
                    // Same row with attendance information attached.
                    $advisor_info = $advisor_info . '<td class="att_cellborderleft">' . $my_absent . '</td><td class="att_text_right">' . $my_present . '</td><td class="att_text_right">' . $my_tardy . '</td>';
                    $advisor_info = $advisor_info . $attendance_info . '</tr>';
                }
            }
        }
        $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></body></html>';
        // Print the students by subject.
        $advisor_print_newtab = "<script>
                var newWindow = window.open('');
                newWindow.document.write('$advisor_info');
            </script>";
        echo $advisor_print_newtab;
        return;
    }

    // Get a list of the student using the search criteria.
    public static function get_studentlist($advisorlist, $lastnamesearch) {
        global $DB;
        $table = '{' . 'user' . '}';
        $select = " lastname LIKE " . "'" . $lastnamesearch . "'";
        $params = null;
        $sort =" ORDER BY lastname ASC, firstname ASC";
        $fields='id, firstname, lastname, email, deleted, suspended';
        $sql = "SELECT " . $fields . " FROM " . $table . " WHERE " . $select . $sort;
        $mystudent_lists = $DB->get_records_sql($sql, $params, 0, 0);
        $count = 0;
        $students_were_found = false;
        foreach ($mystudent_lists as $mystudent_list) {
            $record_found = true;
            $student_id = $mystudent_list->id;
            // Check for admin user, guest user, and anon user. If found, skip.
            if (($mystudent_list->firstname == 'Admin') OR (substr($mystudent_list->firstname, 0, 4) == 'anon')
                OR (substr($mystudent_list->firstname, 0, 5) == 'Guest') OR ($mystudent_list->deleted) OR ($mystudent_list->suspended)){
                $record_found = false; }
            // Get the year and term according to the search criteria.
            if ($record_found == true) {
                $whichuser_status = utils::get_field_data($student_id, utils::get_fieldid('whichteacher'));
                $myadvisor = utils::get_field_data($student_id, utils::get_fieldid('advisor'));
                // Check to see if user is an advisor.  If so, exclude user.
                if ($whichuser_status <> 'Student') {
                        $record_found = false; }
                // Check to see if the all adivsors or single advisor chosen match up.
                else if ($advisorlist <> 'All' AND $advisorlist <> $myadvisor) {
                    $record_found = false; }
            }
            if ($record_found == true) {
                $students_were_found = true;
                $count = $count +1;
                $students_found[$count] = new stdClass();
                // Student id, last name, & first name
                $students_found[$count]->id = $student_id;
                $students_found[$count]->schoolid = utils::get_field_data($student_id, utils::get_fieldid('schoolid'));
                $students_found[$count]->email = $mystudent_list->email;
                $students_found[$count]->lastname = $mystudent_list->lastname;
                $students_found[$count]->firstname = $mystudent_list->firstname;
                // Student grade level
                $my_gradelevel = utils::get_gradelevel(utils::get_field_data($student_id, utils::get_fieldid('graduationyear')));
                $students_found[$count]->gradelevel = $my_gradelevel;
                // Student's advisor
                $my_advisor = utils::get_field_data($student_id, utils::get_fieldid('advisor'));
                $students_found[$count]->advisor = $my_advisor;
                // Student's date of birth
                $students_found[$count]->birthdate = utils::get_field_data($student_id, utils::get_fieldid('dob'));
                // Student's grade point average
                $students_found[$count]->gpa = utils::get_field_data($student_id, utils::get_fieldid('gpa'));
                // Student's enrollment date
                $students_found[$count]->startdate = utils::get_field_data($student_id, utils::get_fieldid('startdate'));
            }
        }
        if ($students_were_found == false){
            return NULL; }
        else {
            return $students_found;  }
    }

    // Get student demographics.
    public static function get_individual_info($student_id) {
        global $DB;
        // Student Info
        $student_info = new stdClass();
        $student_info->id = $student_id;
        $student_info->school_id = utils::get_field_data($student_id, utils::get_fieldid('schoolid'));
        $student_temp = $DB->get_record('user', ['id' => $student_id] , 'firstname, lastname, deleted, suspended');
        $student_info->student_name = $student_temp->lastname . ', ' . $student_temp->firstname;
        $student_info->deleted = $student_temp->deleted;
        $student_info->suspended = $student_temp->suspended;
        $student_info->grade = utils::get_gradelevel(utils::get_field_data($student_id, utils::get_fieldid('graduationyear')));
        $temp_startdate = utils::get_field_data($student_id, utils::get_fieldid('startdate'));
        if (intval($temp_startdate) > 0) {
            $student_info->startdate = date("m-d-y", $temp_startdate);
        }
        else {
            $student_info->startdate = '';
        }
        $my_advisor = utils::get_field_data($student_id, utils::get_fieldid('advisor'));
        $student_info->advisor = utils::put_comma_in_name($my_advisor);
        return $student_info;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_individual_header($week_day, $my_day) {
        $week_day_text[1] = 'Monday';
        $week_day_text[2] = 'Tuesday';
        $week_day_text[3] = 'Wednesday';
        $week_day_text[4] = 'Thursday';
        $week_day_text[5] = 'Friday';
        $page_header = '<h1><u>' . $week_day_text[$week_day] . '</u></h1><table>';
        $page_header = $page_header . '<tr class="fontfordates"><td width="2%" ><center>#</center></td><td width="20%" class="mycell"><center><b>Course</center></td><td width="16%"><center><b>Teacher</center></td>'.
            '<td width="2%" class="leftborder_dark"><center><b>A</b></center></td>' . // Absent total
            '<td width="2%"><center>S</center></td>' . // Present and Distance Learner in class total
            '<td width="2%"><center>T</center></td>' . // Tardy total
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[$week_day][1] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][2] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][3] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[$week_day][4] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][5] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[$week_day][6] . '</td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][7] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][8] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[$week_day][9] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][10] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[$week_day][11] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][12] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][13] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[$week_day][14] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][15] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[$week_day][16] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][17] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][18] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[$week_day][19] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][20] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>'. $my_day[$week_day][21] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[$week_day][22] . '</div></td></tr>';
        return $page_header;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_idividual_title($school_name, $student_name, $student_grade, $school_id, $school_term, $my_dayofweek) {
        $page_title = '';
        // Page Title - School Information
        $page_title = $page_title . '<h1><u>' . $school_name . '</u></h1>' .
            '<h4>' . $student_name . ' (' . $student_grade . ') - ID#' . $school_id . '</h4>' .
            '<h3>' . strtoupper(utils::get_term($school_term)) .' Daily Attendance</h3>'.
            '<h2>Starting Week - ' . date("l, M d, Y", $my_dayofweek) . '</h2><br>';
        return $page_title;
    }

    // Get the student course list.
    public static function get_individual_course_info($student_id, $this_term, $day_of_week, $this_session) {
        global $DB;
        // Days of the week
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        // Terms
        $term_text[1] = 'fall';
        $term_text[2] = 'spring';
        $term_text[3] = 'summer';
        // Inialize the course counter.
        $count = 0;
        // Get the current student course. If it equals 'None', then skip it.
        $name_of_session = $term_text[$this_term] . $week_day[$day_of_week] . 'session' . $this_session;
        $name_of_session_credits = $term_text[$this_term] . $week_day[$day_of_week] . 'creditssession' . $this_session;
        $complete_course_temp = utils::get_field_data($student_id, utils::get_fieldid($name_of_session));
        if ($complete_course_temp <> 'None') {
            $count = $count + 1;
            $this_course_temp = new stdClass();
            $this_course_temp =  utils::get_mycourse($complete_course_temp);
            $student_info[$count] = new stdClass();
            $student_info[$count]->courseid = $this_course_temp->section;
            $student_info[$count]->course_name = $this_course_temp->subject;
            $student_info[$count]->teacher = $this_course_temp->teacher;
            $student_info[$count]->credits = utils::get_field_data($student_id, utils::get_fieldid($name_of_session_credits));
        }
        // Check for completed or dropped courses.
        $current_term = new stdClass();
        $current_term = utils::get_semester_dates($this_term);
        $sql = "SELECT * FROM {istudy_dailyattend_enter} WHERE student_id = " . $student_id . " AND session = " . $this_session . " AND " .
            "day_of_week = " . $day_of_week . " AND attendance_date >= " . $current_term->start . " AND attendance_date <= " . $current_term->finish;
        $params = null;
        if ($DB->record_exists_sql($sql, $params)) {
            $this_student_courses = $DB->get_records_sql($sql, $params);
            foreach ($this_student_courses as $this_course) {
                if ($count == 0) {
                    $count = $count + 1;
                    $student_info[$count] = new stdClass();
                    $student_info[$count]->courseid = $this_course->course;
                    $student_info[$count]->teacher = teacher_info::get_advisor_name($this_course->teacher_id);
                    $student_info[$count]->course_name = utils::get_course_name($this_course->course);
                    $student_info[$count]->credits = 0;
                }
                else {
                    $duplicate_course = false;
                    for ($i = 1; $i <= $count; $i++) {
                        // If this is the first course on the list OR this course is not already on the list then add the course.
                        if ($student_info[$i]->courseid == $this_course->course) {
                            $duplicate_course = true;
                        }
                    }
                    if (!$duplicate_course) {
                        $count = $count + 1;
                        $student_info[$count] = new stdClass();
                        $student_info[$count]->courseid = $this_course->course;
                        $student_info[$count]->teacher = teacher_info::get_advisor_name($this_course->teacher_id);
                        $student_info[$count]->course_name = utils::get_course_name($this_course->course);
                        $student_info[$count]->credits = 0;
                    }
                }
            }
        }
        // Return the student count.
        $student_info[0] = new stdClass();
        $student_info[0]->count = $count;
        return $student_info;
    }

    // Get individual - will get 20 weeks of attendance.
    public static function get_individual_attendance($submission) {
        global $DB;
        $school_term = $submission->schoolterm;
        $starting_monday = $submission->startofweek;
        // Adjust Monday so that the time is set to 12:00 pm.
        $starting_monday = utils::set_to_12pm($starting_monday);
        $student_count = $submission->studentcount;
        $number_of_sessions = get_config('mod_istudy', 'number_of_sessions');
        $school_name =  get_config('mod_istudy', 'schoolname');
        $students_per_tab = 50;
        $page_count = 0;
        $attendance_for_students = '';
        // There are 86400 seconds in a day.
        $day_of_week[1] = 0;
        $day_of_week[2] = 86400;
        $day_of_week[3] = 86400+86400;
        $day_of_week[4] = 86400+86400+86400;
        $day_of_week[5] = 86400+86400+86400+86400;
        // This array is used in searching for courses
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        // This array is for the title of each day.
        $week_day_text[1] = 'Monday';
        $week_day_text[2] = 'Tuesday';
        $week_day_text[3] = 'Wednesday';
        $week_day_text[4] = 'Thursday';
        $week_day_text[5] = 'Friday';
        // Check for a daily schedule. If 'No', student schedules are different each day of the week.
        // If 'Yes', Mondays schedule is applied to each day of the week (Monday-Friday).
        if ($school_term == 1 OR $school_term == 2) {
            $dailyschedule = get_config('mod_istudy', 'dailyschedule');
        }
        else {
            $dailyschedule = get_config('mod_istudy', 'summer_dailyschedule');
        }
        // Get the dates for the 22 weeks of attendance.
        $my_day[][] = array() ;
        $my_day_text[][] = array();
        // Get the date for each week for Monday only.
        $this_week = utils::get_attendance_weeks($starting_monday, 1);
        for ($i = 1; $i <= 5; $i++) {
            for ($j = 1; $j <=22; $j++) {
                // Check that it's an actual date.
                if ($this_week[$j] > 0) {
                    $my_day[$i][$j] = $this_week[$j] +  $day_of_week[$i];
                    $my_day_text[$i][$j] = date("m/d", $my_day[$i][$j]); // The text version of the date
                }
                else {
                    $my_day[$i][$j] = 0;
                    $my_day_text[$i][$j] = '';
                }
            }
        }
        for ($this_count = 1; $this_count <= $student_count; $this_count++) {
            $student_id = (int)$submission->{'student_chosen' . $this_count};
            // If the studentid is greater than 0, then this student was chosen.
            if ($student_id > 0) {
                $page_count = $page_count + 1;
                // If $page_count is more than $students_per_tab, print the tab and then start a new tab for the student schedules.
                if ($page_count > $students_per_tab) {
                    // Print the student schedules.
                    $attendance_print_newtab = "<script>
                        var newWindow = window.open('iStudy');
                        newWindow.document.write('$attendance_for_students');
                    </script>";
                    echo $attendance_print_newtab;
                    $page_count = 1;
                    $attendance_for_students = '';
                }
                $this_student = student_info::get_individual_info($student_id);
                // Add a page break before the next page - do not add break if it's the first page.
                if ($attendance_for_students <> '') {
                   $attendance_for_students = $attendance_for_students . '<P style="page-break-before: always">';
                }
                // Initialize html page.
                $student_info = '<link rel="stylesheet" type="text/css" href="styles-print_daily_att.css"><html><head><title>Attendance - Student</title></head><body>';
                // Page Title - School Information
                $student_info = $student_info . student_info::get_idividual_title($school_name, $this_student->student_name,
                    $this_student->grade, $this_student->school_id, $school_term, $starting_monday);
                for ($d = 1; $d <= 5; $d++) {
                    // Table heading - with dates
                    $count = 0;
                    for ($t = 1; $t <= $number_of_sessions; $t++){
                        // Get the student info - find the student course (if any) on the day of the week and during current session.
                        $temp_day_of_week = $d;
                        if ($dailyschedule == 'Yes') {
                            $temp_day_of_week = 1;
                        }
                        $temp_student = student_info::get_individual_course_info($student_id,$school_term, $temp_day_of_week, $t);
                        if ($temp_student[0]->count > 0) {
                            // Get attendance for course for this day of the week and each period - include completed or dropped courses.
                            for ($c = 1; $c <= $temp_student[0]->count; $c++){
                                // Get the attendance data for each student.
                                // Print a student list for each subject.
                                $my_absent = 0;
                                $my_present = 0;
                                $my_tardy = 0;
                                $attendance_info = '';
                                $count++;
                                // If this is the 1st class found for this weekday, create the table header with the dates.
                                if ($count == 1) {
                                    $student_info = $student_info . student_info::get_individual_header($d, $my_day_text);
                                }
                                for ($i = 1; $i <= 22; $i++){
                                    $dark_border_left = '';
                                    if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft"';}

                                    $this_attendance = new stdClass();
                                    $table = 'istudy_dailyattend_enter';
                                    $select = "attendance_date = :attendance_date AND student_id = :student_id AND course = :course AND session = :session";
                                    $params =['attendance_date'=>$my_day[$d][$i], 'student_id'=>$student_id, 'course'=>$temp_student[$c]->courseid, 'session'=>$t];
                                    // If the course is active, return true.
                                    if ($this_attendance = $DB->get_records_select($table, $select, $params)) {
                                        $my_attendance = 0;
                                        foreach ($this_attendance AS $student_attendance) {
                                            if ($my_attendance <> 2 AND $my_attendance <> 3) {
                                                // The student was present in person or a distance learner.
                                                if ($student_attendance->attendance_status == 2 OR $student_attendance->attendance_status == 3) {
                                                    $my_attendance = $student_attendance->attendance_status;
                                                }
                                                // The student was tardy.
                                                else if (($student_attendance->attendance_status == 4) AND ($my_attendance <> 2 OR $my_attendance <> 3)){
                                                    $my_attendance = 2;
                                                }
                                                // The student was absent.
                                                else if (($student_attendance->attendance_status == 1) AND ($my_attendance == 0)) {
                                                    $my_attendance = 1;
                                                }
                                            }
                                        }
                                        if ($my_attendance == 0) {
                                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>-</center></td>';
                                        }
                                        else if ($my_attendance == 1) {
                                            $my_absent = $my_absent + 1;

                                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'><b><center>A</center></b></td>';
                                        }
                                        else if ($my_attendance == 2) {
                                            $my_present = $my_present + 1;
                                            $dark_border_left = ' class="cellbordernotleft_small"';
                                            if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft_small"';}
                                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>P</center></td>';
                                        }
                                        else if ($my_attendance == 3) {
                                            $my_present = $my_present + 1;
                                            $dark_border_left = ' class="cellbordernotleft_small"';
                                            if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft_small"';}
                                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>D</center></td>';
                                        }
                                        else if ($my_attendance == 4) {
                                            $my_tardy = $my_tardy + 1;
                                            $dark_border_left = ' class="cellbordernotleft_small"';
                                            if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {$dark_border_left = ' class="cellborderleft_small"';}
                                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>T</center></td>';
                                        }
                                    }
                                    else {
                                        $attendance_info = $attendance_info . '<td' . $dark_border_left .'></td>';
                                    }
                                }
                                // New row with the student information. If zero credits, highlight the row.
                                if ($temp_student[$c]->credits <> '0') {
                                    $student_info = $student_info . '<tr class="cell_student"><td class="cellborderleft"><center>' . $t . '</center></td>' .
                                        '<td class="cellborderleft">' . $temp_student[$c]->course_name . ' [' . $temp_student[$c]->credits . ']</td>' .
                                        '<td>' . $temp_student[$c]->teacher . '</td>';
                                }
                                else {
                                    $student_info = $student_info . '<tr class="cell_student"><td class="cellborderleft"><center>' . $t . '</center></td>' .
                                        '<td bgcolor="#FBFCD6" class="cellborderleft">' . $temp_student[$c]->course_name . ' [' . $temp_student[$c]->credits . ']</td>' .
                                        '<td bgcolor="#FBFCD6">' . $temp_student[$c]->teacher . '</td>';
                                }
                                // Same row with attendance information attached.
                                $student_info = $student_info . '<td class="att_cellborderleft"><b>' . $my_absent . '</b></td><td class="att_text_right">' . $my_present . '</td><td class="att_text_right">' . $my_tardy . '</td>';
                                $student_info = $student_info . $attendance_info . '</tr>';
                            }
                        }
                    }
                    // If courses were found, close the table for the day of the week and courses. If not, get the day of the week and print None.
                    if ($count > 0) {
                        $student_info = $student_info . '</table></html>';
                    }
                    else {
                        $student_info = $student_info . '<h1><u>' . $week_day_text[$d] . '</u></h1><h3>None</h3>';
                    }
                }
                $student_info = $student_info . '<br><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></body></html>';
                // Add the student attendance to the page of students.
                $attendance_for_students = $attendance_for_students . $student_info;
            }
        }
        // Print the attendance for students that have not been printed yet - the final tab.
        $attendance_print_newtab = "<script>
            var newWindow = window.open('iStudy');
            newWindow.document.write('$attendance_for_students');
        </script>";
        echo $attendance_print_newtab;
        return;
    }

    // Create the page title for attendance totals.
    public static function get_totals_header($week_day, $my_day) {
        $week_day_text[1] = 'Monday';
        $week_day_text[2] = 'Tuesday';
        $week_day_text[3] = 'Wednesday';
        $week_day_text[4] = 'Thursday';
        $week_day_text[5] = 'Friday';
        $page_header = '';
        $page_header = $page_header . '<th class="cellbordernotright"><div>' . $my_day[$week_day][1] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][2] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][3] . '</div></th>' .
            '<th class="cellborderbottom"><div>'. $my_day[$week_day][4] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][5] . '</div></th>' .
            '<th class="cellbordernotright"><div>' . $my_day[$week_day][6] . '</th><th class="cellborderbottom"><div>' . $my_day[$week_day][7] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][8] . '</div></th>' .
            '<th class="cellborderbottom"><div>'. $my_day[$week_day][9] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][10] . '</div></th>' .
            '<th class="cellbordernotright"><div>' . $my_day[$week_day][11] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][12] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][13] . '</div></th>' .
            '<th class="cellborderbottom"><div>'. $my_day[$week_day][14] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][15] . '</div></th>' .
            '<th class="cellbordernotright"><div>' . $my_day[$week_day][16] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][17] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][18] . '</div></th>' .
            '<th class="cellborderbottom"><div>'. $my_day[$week_day][19] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][20] . '</div></th>' .
            '<th class="cellbordernotright"><div>'. $my_day[$week_day][21] . '</div></th><th class="cellborderbottom"><div>' . $my_day[$week_day][22] . '</div></th></tr>';
        return $page_header;
    }

    // Create the page title for the attendance totals.
    public static function get_totals_title($school_name, $school_term, $my_dayofweek) {
        $page_title = '';
        // Page Title - School Information
        $page_title = $page_title . '<h1><u>' . $school_name . '</u></h1>' .
            '<h3>' . strtoupper(utils::get_term($school_term)) .' Attendance Totals</h3>'.
            '<h2>Starting Week - ' . date("l, M d, Y", $my_dayofweek) . '</h2><br>';
        // Table heading
        $page_title = $page_title . '<table>' .
            '<tr class="fontfordates"><th width="3%" class="cellborderall">#</th>' .
            '<th width="12%" class="cellborderbottom"><center>ID#/Start Date</center></th>' .
            '<th width="39%" class="cellborderbottom">STUDENT NAME</th>' .
            '<th colspan="2" width="6%" class="cellbordernotright">Totals</th>' ;
        return $page_title;
    }

    // Create the page title for the attendance totals.
    public static function count_attendance($studentid, $start_date, $end_date, $attendance_status) {
        global $DB;
        $table = '{' . 'istudy_dailyattend_enter' . '}';
        $select = " student_id = " . $studentid . " AND attendance_status = " . $attendance_status . " AND attendance_date >= " . $start_date .
            " AND attendance_date <= " . $end_date;
        $params = null;
        $fields='id';
        $sql = "SELECT COUNT('" . $fields . "') FROM " . $table . " WHERE " . $select;
        $records_counted = $DB->count_records_sql($sql, $params);
        return $records_counted;
    }

    // Get a list of students and attendance totals for each - will get 20 weeks of attendance.
    public static function get_attendance_totals($submission) {
        $school_term = $submission->schoolterm;
        $starting_monday = $submission->startofweek;
        $student_count = $submission->studentcount;
        $school_name =  get_config('mod_istudy', 'schoolname');
        $count = 0;
        // There are 86400 seconds in a day. Add to each day to get the next day.
        $day_of_week[1] = 0;
        $day_of_week[2] = 86400;
        $day_of_week[3] = 86400+86400;
        $day_of_week[4] = 86400+86400+86400;
        $day_of_week[5] = 86400+86400+86400+86400;
        // This array is used in searching for courses
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        // This array is for the title of each day.
        $week_day_text[1] = 'Monday';
        $week_day_text[2] = 'Tuesday';
        $week_day_text[3] = 'Wednesday';
        $week_day_text[4] = 'Thursday';
        $week_day_text[5] = 'Friday';
        $my_day[][] = array() ;
        $my_day_text[][] = array() ;
        // Get the date for each week for Monday only.
        $this_week = utils::get_attendance_weeks($starting_monday, 1);
        for ($i = 1; $i <= 5; $i++) {
            for ($j = 1; $j <=22; $j++) {
                 // Check that it's an actual date.
                if ($this_week[$j] > 0) {
                    $my_day[$i][$j] = $this_week[$j] +  $day_of_week[$i];
                    $my_day_text[$i][$j] = date("m/d", $my_day[$i][$j]); // The text version of the date
                }
                else {
                    $my_day[$i][$j] = 0;
                    $my_day_text[$i][$j] = '';
                }
            }
        }
        // Initialize html page - Page Title - School Information - dates.
        $student_info = '<link rel="stylesheet" type="text/css" href="styles-print_daily_att.css"><html><head><title>Attendance - Student List</title></head><body>';
        $student_info = $student_info . student_info::get_totals_title($school_name, $school_term, $starting_monday);
        $student_info = $student_info . student_info::get_totals_header(1, $my_day_text);
        for ($this_count = 1; $this_count <= $student_count; $this_count++) {
            $student_id = $submission->{'student_chosen' . $this_count};
            // If the studentid is greater than 0, then this student was chosen.
            if ($student_id > 0) {
                $this_student = student_info::get_individual_info($student_id);
                $my_absent = 0;
                $my_present = 0;
                $attendance_absent_weeks = '';
                $attendance_present_weeks = '';
                $count++;
                for ($i = 1; $i <= 22; $i++){
                    $dark_border_left = '';
                    $dark_border_left_red = ' class="cell_is_red"';
                    if ($i == 1 or $i == 6 or $i == 11 or $i == 16 or $i == 21) {
                        $dark_border_left = ' class="cellborderleft"';
                        $dark_border_left_red = ' class="cellborderleft_red"';
                    }
                    // Find the number of absences for this student this week.
                    $absences_for_week = student_info::count_attendance($student_id, $my_day[1][$i], $my_day[5][$i], 1);
                    $my_absent = $my_absent + $absences_for_week;
                    $attendance_absent_weeks = $attendance_absent_weeks . '<td' . $dark_border_left_red .'><center>';
                    // If the number of absences is zero, enter a dash instead of a zero.
                    if ($absences_for_week == 0){
                        $attendance_absent_weeks = $attendance_absent_weeks . '-</center></td>';
                    }
                    else {
                        $attendance_absent_weeks = $attendance_absent_weeks . $absences_for_week .'</center></td>';
                    }
                    // Find the number of days of attendance(Present=2, Distance Learning=3) for this student this week.
                    $days_present_for_week = student_info::count_attendance($student_id, $my_day[1][$i], $my_day[5][$i], 2) +
                    student_info::count_attendance($student_id, $my_day[1][$i], $my_day[5][$i], 3);
                    $my_present = $my_present + $days_present_for_week;
                    $attendance_present_weeks = $attendance_present_weeks . '<td' . $dark_border_left . '><center>';
                    // If the number of absences is zero, enter a dash instead of a zero.
                    if ($days_present_for_week == 0){
                        $attendance_present_weeks = $attendance_present_weeks . '-</center></td>';
                    }
                    else {
                        $attendance_present_weeks = $attendance_present_weeks . $days_present_for_week .'</center></td>';
                    }
                }
                // New row with the student information.
                $student_info = $student_info . '<tr class="cell_student_bold"><td class="cellborderleft_gray" rowspan="2"><center>' . $count . '</center></td>' .
                    '<td class="cellborderleft_red"><center>' . $this_student->school_id . '</center></td>' .
                    '<td class="cell_is_red">' . $this_student->student_name . ' (' . $this_student->grade . ')</td>';
                // Same row as student informatioin - with absences attached.
                $student_info = $student_info . '<td class="cellborderleft_red">A</td><td class="cell_is_red"><center>' . $my_absent . '</center></td>' .
                    $attendance_absent_weeks . '</tr>';
                // Second row - attendance information attached, when present in person or online.
                $student_info = $student_info . '<tr class="cells_row_two"><td class="cellborderleft"><center>' . $this_student->startdate .
                    '</center></td><td><b>Advisor:</b> ' . $this_student->advisor . '</td>' .
                    '<td class="cellborderleft">P</td><td><center>' . $my_present . '</center></td>' .
                    $attendance_present_weeks . '</tr>';
                // Print only 30 students per page.
                // Print the title and the header of the table with date for 20 Mondays($d=1) starting with $my_day_text.
                if (fmod($count, 30) == 0) {
                    $student_info = $student_info . '</table><h4>A = Absent, P = Present in person, D = Distance learner</h4><P style="page-break-before: always">';
                    $student_info = $student_info . student_info::get_totals_title($school_name, $school_term, $starting_monday);
                    $student_info = $student_info . student_info::get_totals_header(1, $my_day_text);
                }
            }
        }
        $student_info = $student_info . '</table><br><h4>A = Absent, P = Present in person or Distance learner</h4></body></html>';
        // Print the students by subject.
        $advisor_print_newtab = "<script>
                var newWindow = window.open('');
                newWindow.document.write('$student_info');
            </script>";
        echo $advisor_print_newtab;
        return;
    }

    //  Get the student demographics.
    public static function get_student_absent_info($student_id, $teacher_id, $course_id, $name_of_session, $name_of_session_credits) {
        global $DB;
        // Student Info
        $student_info = new stdClass();
        $student_info->id = $student_id;
        $student_info->school_id = utils::get_field_data($student_id, utils::get_fieldid('schoolid'));
        if ($student_temp = $DB->get_record('user', ['id' => $student_id] , 'firstname, lastname, deleted, suspended')) {
            $student_info->student_name = $student_temp->lastname . ', ' . $student_temp->firstname;
            $student_info->deleted = $student_temp->deleted;
            $student_info->suspended = $student_temp->suspended;
            // Find the course and break it up into its components -- example: Spanish IA/Ramirez-Jesus/650512/304.
            $this_course_temp =  utils::get_mycourse(utils::get_field_data($student_id, utils::get_fieldid($name_of_session)));
            // Check to see if the student is currently taking the course or else is has been dropped.
            if ($course_id == $this_course_temp->section) {
                $student_info->courseid = $this_course_temp->section;
                $student_info->course_name = $this_course_temp->subject;
                $student_info->teacher = $this_course_temp->teacher;
                $student_info->credits = utils::get_field_data($student_id, utils::get_fieldid($name_of_session_credits));
            }
            else {
                $student_info->courseid = $course_id;
                $student_info->course_name = utils::get_course_name($course_id);
                $student_info->teacher = teacher_info::get_advisor_name($teacher_id);
                $student_info->credits = 0;
            }
            $student_info->grade = utils::get_gradelevel(utils::get_field_data($student_id, utils::get_fieldid('graduationyear')));
            return $student_info;
        }
        else {
            $student_info->credits = 0;
            $student_info->suspended = true;
            $student_info->deleted = true;
            return $student_info;
        }
    }

    // Get all of the students for the advisor by day of the week (Monday-Friday).
    public static function get_absences_by_session($this_session,$school_term, $school_day_of_week, $date_for_absences) {
        global $DB;
        $mystudent_list = [];
        $count = -1;
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        $this_school_term = utils::get_term($school_term);
        $name_of_session = $this_school_term . $week_day[$school_day_of_week] . 'session' .  $this_session;
        $name_of_session_credits = $this_school_term . $week_day[$school_day_of_week] . 'creditssession' .  $this_session;
        $select = "SELECT * FROM {istudy_dailyattend_enter} WHERE attendance_date = " . $date_for_absences .
            " AND session = " . $this_session . " AND attendance_status = 1"; // Attendance status 1 means absent
        $mystudents = $DB->get_records_sql($select, null, 0,0);
        // Get the data that identifies the student.
        foreach ($mystudents as $mystudent) {
            $temp_info = student_info::get_student_absent_info($mystudent->student_id, $mystudent->teacher_id, $mystudent->course, $name_of_session, $name_of_session_credits);
            // Don't include students that have been deleted or suspended.
            if (($temp_info->deleted == false) AND ($temp_info->suspended == false)){
                $count = $count + 1;
                $mystudent_list[$count] = new stdClass();
                $mystudent_list[$count]->id = $mystudent->student_id;
                $mystudent_list[$count]->school_id = $temp_info->school_id;
                $mystudent_list[$count]->student_name = $temp_info->student_name;
                $mystudent_list[$count]->grade = $temp_info->grade;
                $mystudent_list[$count]->courseid = $temp_info->courseid;
                $mystudent_list[$count]->course_name = $temp_info->course_name;
                $mystudent_list[$count]->teacher = $temp_info->teacher;
                $mystudent_list[$count]->credits = $temp_info->credits;
            }
        }
        // If no students were found, then return.
        if ($count == -1) {
            return 0;
        }
        // Sort the list by student ID.
        for ($i=0; $i<=$count; $i++){
            $val = $mystudent_list[$i];
            $j = $i-1;
            while($j>=0 && $mystudent_list[$j]->school_id > $val->school_id){
                $mystudent_list[$j+1] = $mystudent_list[$j];
                $j--;
            }
            $mystudent_list[$j+1] = $val;
        }
        // Sort by student name.
        for ($i=0; $i<=$count; $i++){
            $val = $mystudent_list[$i];
            $j = $i-1;
            while($j>=0 && $mystudent_list[$j]->student_name > $val->student_name){
                $mystudent_list[$j+1] = $mystudent_list[$j];
                $j--;
            }
            $mystudent_list[$j+1] = $val;
        }
        return $mystudent_list;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_absences_title($school_name, $school_term, $date_for_absences) {
        $page_title = '';
        // Page Title - School Information
        $page_title = $page_title . '<h1>' . $school_name . '</h1><h2>' . strtoupper(utils::get_term($school_term)) .' Daily Attendance - ABSENCES</h2>'.
            '<h3>' . date("l, M d, Y", $date_for_absences) . '</h3><br>';
        // Table heading
        $page_title = $page_title . '<table><tr>' .
            '<th width="2%" class="cellborderall">#</th>' .
            '<th width="5%" class="cellborderbottom">ID#</th>' .
            '<th width="20%" class="cellborderbottom">STUDENT NAME</th>' .
            '<th width="13%" class="cellborderbottom">COURSE NAME</th>' .
            '<th width="9%" class="cellborderbottom">TEACHER</th>' .
            '<th width="2%" class="cellbordernotright">A</th>' . // Absent total
            '<th width="2%" class="cellborderbottom">S</th>' . // Present in class total
            '<th width="2%" class="cellborderbottom">T</th>' . // Distance learning total
            '<th width="12%" class="cellborderall" colspan="5"></th><th width="12%" class="cellborderall" colspan="5"></th>' .
            '<th width="12%" class="cellborderall" colspan="5"></th><th width="9%" class="cellborderall">NOTES</th></tr>';
        return $page_title;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_absences_header($this_session, $time_session, $my_day, $date_for_absences) {
        $page_header = '';
        $page_header = $page_header . '<tr class="fontfordates"><td></td><td colspan="4" class="mycell"><center><b>Period ' . $this_session . '</b> (' . $time_session . ')</center></td><td colspan="3" class="leftborder_dark"></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[1] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[2] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[3] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[4] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[5] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[6] . '</td><td width="2%" class="cell_width"><div>' . $my_day[7] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[8] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[9] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[10] . '</div></td>' .
            '<td width="2%" class="leftborder_dark"><div>' . $my_day[11] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[12] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[13] . '</div></td>' .
            '<td width="2%" class="cell_width"><div>'. $my_day[14] . '</div></td><td width="2%" class="cell_width"><div>' . $my_day[15] . '</div></td>' .
            '<td width="2%" class="mycell"><center>' . date("m/d/y", $date_for_absences) . '</center></td></tr>';
        return $page_header;
    }

    // Get the exact date for the month, day, and year for the day chosen. It will only go 14 weeks back.
    public static function get_absences_startdate($school_term, $date_for_absences) {
        // Monday
        $my_date_adjustment['1'] = 0;
        // Tuesday
        $my_date_adjustment['2'] = 1;
        // Wednesday
        $my_date_adjustment['3'] = 2;
        // Thursday
        $my_date_adjustment['4'] = 3;
        // Friday
        $my_date_adjustment['5'] = 4;
        // Make sure that only the 14 weeks prior to the date for the absenses are included. It will check up to 20 weeks.
        $this_start_date = utils::term_start_date($school_term);
        // Adjust the day so that the time is set to 12:00 pm.
        $this_start_date = utils::set_to_12pm($this_start_date);
        $date_in_15weeks = utils::set_to_12pm(strtotime("-14 week", $date_for_absences));
        // If the date in 15 weeks is before the semester startdate, adjust it.
        while ($date_in_15weeks < $this_start_date) {
            $date_in_15weeks = utils::set_to_12pm(strtotime("+1 week", $date_in_15weeks));
        }
        return $date_in_15weeks;
    }

    // Get student attendance for an advisor and for a specific day of the week - will get 20 weeks of attendance.
    public static function get_todays_absences($school_term, $school_start_of_week, $school_day_of_week, $date_for_absences) {
        global $DB;
        $mystudent_list[][] = array();
        $duplicate_student_count_id[] = array();
        $duplicate_student_count_course_number[] = array();
        $count = 0;
        $page_count = 0;
        $max_lines_per_page = 55;
        $week_day[1] = 'monday';
        $week_day[2] = 'tuesday';
        $week_day[3] = 'wednesday';
        $week_day[4] = 'thursday';
        $week_day[5] = 'friday';
        // Adjust Monday so that the time is set to 12:00 pm.
        $school_start_of_week = utils::set_to_12pm($school_start_of_week);
        $number_of_sessions = get_config('mod_istudy', 'number_of_sessions');
        $school_name =  get_config('mod_istudy', 'schoolname');
        // Check for a daily schedule. If 'No', student schedules are different each day of the week.
        // If 'Yes', Mondays schedule is applied to each day of the week (Monday-Friday).
        $temp_school_day_of_week = $school_day_of_week;
        if ($school_term == 1 OR $school_term == 2) {
            $dailyschedule = get_config('mod_istudy', 'dailyschedule');
        }
        else {
            $dailyschedule = get_config('mod_istudy', 'summer_dailyschedule');
        }
        if ($dailyschedule == 'Yes') {
            $temp_school_day_of_week = 1;
        }
        // Get the times for each session.
        $summer = '';
        if ($school_term == 'Summer') {$summer = 'summer_';}
        $time_session[] = array() ;
        for ($t = 1; $t <= $number_of_sessions; $t++) {
            $time_session[$t] = get_config('mod_istudy', $summer . $week_day[$school_day_of_week] . 'session' . $t);
        }
        // Get the exact date for this day of the week (Monday-Friday).
        $my_dayofweek = student_info::get_absences_startdate($school_term, $date_for_absences);
        // Get the dates for the 15 weeks of attendance.
        for ($this_day = 1; $this_day <=15; $this_day++) {
            $adjusted_date = strtotime("+" . ($this_day - 1) . " week", $my_dayofweek);
            // Adjust the day so that the time is set to 12:00 pm.
            $adjusted_date = utils::set_to_12pm($adjusted_date);
            $my_day[$this_day] = $adjusted_date; // the actual date
            $my_day_text [$this_day] = date("m/d", $adjusted_date); // The text version of the date
        }
        // Initialize html page.
        $advisor_info = '<link rel="stylesheet" type="text/css" href="styles-absences_today.css"><html><head><title>Absent Today</title></head><body>';
        // Page Title - School Information
        $advisor_info = $advisor_info . student_info::get_absences_title($school_name, $school_term, $date_for_absences);
        for ($t = 1; $t <= $number_of_sessions; $t++){
            // Print the heading. Don't print it if you've reached the bottom of the page.
            // Page is full. Start on a new page.
            if ($page_count >= $max_lines_per_page) {
                // This provides a page break.
                $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></html><P style="page-break-before: always">';
                // Page Title - School Information
                $advisor_info = $advisor_info . student_info::get_absences_title($school_name, $school_term, $date_for_absences);
                // Table heading - with dates
                $advisor_info = $advisor_info . student_info::get_absences_header($t, $time_session[$t], $my_day_text, $date_for_absences);
                // The header for the period takes up 3 lines and is therefore initialized at 3.
                $page_count = 3;
            }
            else {
                // Table heading - with dates
                $advisor_info = $advisor_info . student_info::get_absences_header($t, $time_session[$t], $my_day_text, $date_for_absences);
                // The header for the period takes up 3 lines.
                $page_count = $page_count + 3;
            }
            // Get the students names - find all students that have the teacher on the day of the week and during current session.
            $mystudent_list = student_info::get_absences_by_session($t, $school_term, $temp_school_day_of_week, $date_for_absences);
            // If there are no students during current session, print None.
            if ($mystudent_list == 0) {
                // New row with the student information.
                $advisor_info = $advisor_info . '<tr class="cell_student"><td class="cellborderleft"><center>0</center></td>' .
                    '<td colspan="4" class="cellborderleft"><center>None</center></td>' ;
                // Same row with attendance information attached.
                $advisor_info = $advisor_info . '<td class="att_cellborderleft">0</td><td class="att_text_right">0</td><td class="att_text_right">0</td>';
                $advisor_info = $advisor_info . '<td colspan="5" class="cellborderleft"></td><td colspan="5" class="cellborderleft">'.
                    '<td colspan="5" class="cellborderleft"></td><td class="cellborderleft"></td></tr>';
                    $page_count = $page_count + 1;
            }
            else {
                // Print student attendance for each subject enrolled this session.
                $count = 0;
                foreach ($mystudent_list as $mystudent) {
                    $my_absent = 0;
                    $my_present = 0;
                    $my_tardy = 0;
                    $attendance_info = '';
                    $page_count = $page_count + 1;
                    // Page is full. Start on a new page.
                    if ($page_count > $max_lines_per_page) {
                        // This provides a page break.
                        $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></body></html><P style="page-break-before: always">';
                        // Page Title - School Information
                        $advisor_info = $advisor_info . student_info::get_absences_title($school_name, $school_term, $date_for_absences);
                        // Table heading - with dates
                        $advisor_info = $advisor_info . student_info::get_absences_header($t, $time_session[$t], $my_day_text, $date_for_absences);
                        // The header for the period takes up 3 lines and is therefore initialized at 3.
                        $page_count = 3;
                    }
                    for ($i = 1; $i <= 15; $i++){
                        // The dark border starts empty and becomes class="cellborderleft if the cell requires a dark border on the left.
                        $dark_border_left = '';
                        if ($i == 1 or $i == 6 or $i == 11 or $i == 16) {$dark_border_left = ' class="cellborderleft"';}
                        $table = 'istudy_dailyattend_enter';
                        $select = "attendance_date = :attendance_date AND student_id = :student_id AND course = :course AND session = :session";
                        $params =['attendance_date'=>$my_day[$i], 'student_id'=>$mystudent->id, 'course'=>$mystudent->courseid, 'session'=>$t];
                        // If the course is active, return true.
                        if ($this_attendance = $DB->get_record_select($table, $select, $params)) {
                            $my_attendance = $this_attendance->attendance_status;
                            if ($my_attendance == 0) {
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>-</center></td>';
                            }
                            else if ($my_attendance == 1) {
                                $my_absent = $my_absent + 1;
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><b><center>A</center></b></td>';
                            }
                            else if ($my_attendance == 2) {
                                $my_present = $my_present + 1;
                                $dark_border_left = ' class="cellbordernotleft_small"';
                                if ($i == 1 or $i == 6 or $i == 11 or $i == 16) {$dark_border_left = ' class="cellborderleft_small"';}
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>P</center></td>';
                            }
                            else if ($my_attendance == 3) {
                                $my_present = $my_present + 1;
                                $dark_border_left = ' class="cellbordernotleft_small"';
                                if ($i == 1 or $i == 6 or $i == 11 or $i == 16) {$dark_border_left = ' class="cellborderleft_small"';}
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>D</center></td>';
                            }
                            else {
                                $my_tardy = $my_tardy + 1;
                                $dark_border_left = ' class="cellbordernotleft_small"';
                                if ($i == 1 or $i == 6 or $i == 11 or $i == 16) {$dark_border_left = ' class="cellborderleft_small"';}
                                $attendance_info = $attendance_info . '<td' . $dark_border_left .'><center>T</center></td>';
                            }
                        }
                        else {
                            $attendance_info = $attendance_info . '<td' . $dark_border_left .'></td>';
                        }
                    }
                    // Get the notes (if any) for this date.
                    $absence_notes = '';
                    $table = 'istudy_dailyattend_enter';
                    $select = "attendance_date = :attendance_date AND student_id = :student_id AND course = :course AND session = :session";
                    $params =['attendance_date'=>$date_for_absences, 'student_id'=>$mystudent->id, 'course'=>$mystudent->courseid, 'session'=>$t];
                    // If the course is active, return true.
                    if ($my_notes = $DB->get_record_select($table, $select, $params)) {
                        $absence_notes = $my_notes->notes;
                    }
                    $count = $count + 1;
                    // New row with the student information.
                    $advisor_info = $advisor_info . '<tr class="cell_student"><td class="cellborderleft"><center>' . $count . '</center></td>' .
                        '<td class="cellborderleft"><p class="cut_text_studentid">' . $mystudent->school_id . '</p></td>' .
                        '<td><p class="cut_text_student">' . $mystudent->student_name . ' (' . $mystudent->grade . ')</p></td>' .
                        '<td><p class="cut_text_course">' . $mystudent->course_name . ' [' . $mystudent->credits . ']</p></td>' .
                        '<td><p class="cut_text_advisor">' . utils::last_name_only($mystudent->teacher) . '</p></td>';
                    // Same row with attendance information attached.
                    $advisor_info = $advisor_info . '<td class="att_cellborderleft">' . $my_absent . '</td><td class="att_text_right">' . $my_present .
                        '</td><td class="att_text_right">' . $my_tardy . '</td>';
                    $advisor_info = $advisor_info . $attendance_info . '<td class="mycell"><p class="cut_text_notes">' . utils::remove_extra_symbols($absence_notes) . '</p></td></tr>';
                }
            }
        }
        // Add the legend at the end.
        $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner, S = Sum (P + D)</h4></body></html>';
        // Print the students by subject.
        $advisor_print_newtab = "<script>
                var newWindow = window.open('');
                newWindow.document.write('$advisor_info');
            </script>";
        echo $advisor_print_newtab;
        return;
    }

    // Create the page title for daily attendance by day of week.
    public static function get_absent_today_title($school_name, $school_term, $date_for_absences) {
        $page_title = '';
        // Page Title - School Information
        $page_title = $page_title . '<h1>' . $school_name . '</h1><h2>' . strtoupper(utils::get_term($school_term)) .' Daily Attendance - ABSENCES</h2>'.
            '<h3>' . date("l, M d, Y", $date_for_absences) . '</h3><br>';
        // Table heading
        $page_title = $page_title . '<table><tr>' .
            '<th width="2%" class="cellborderall">#</th>' .
            '<th width="8%" class="cellborderbottom">ID#</th>' .
            '<th width="34%" class="cellbordernotleft">STUDENT NAME</th>' .
            '<th width="2%" class="cellborderbottom_absent">P1</th>' .
            '<th width="2%" class="cellborderbottom_absent">P2</th>' .
            '<th width="2%" class="cellborderbottom_absent">P3</th>' .
            '<th width="2%" class="cellborderbottom_absent">P4</th>' .
            '<th width="2%" class="cellborderbottom_absent">P5</th>' .
            '<th width="2%" class="cellborderbottom_absent">P6</th>' .
            '<th width="2%" class="cellborderbottom_absent">P7</th>' .
            '<th width="2%" class="cellborderbottom_absent">P8</th>' .
            '<th width="40%" class="cellborderall">NOTES</th></tr>';
        return $page_title;
    }

    // Get student attendance for an advisor and for a specific day of the week - will get 20 weeks of attendance.
    public static function get_absent_today_only($school_term, $date_for_absences) {
        $mystudent_list = [];
        $mystudent_list = student_info::get_absent_students($date_for_absences);
        $count = $mystudent_list[0]->id;
        $page_count = 0;
        $max_lines_per_page = 60;
        $school_name =  get_config('mod_istudy', 'schoolname');
        // Initialize html page.
        $advisor_info = '<link rel="stylesheet" type="text/css" href="styles-absences_today.css"><html><head><title>Absent Today</title></head><body>';
        // Page Title - School Information
        $advisor_info = $advisor_info . student_info::get_absent_today_title($school_name, $school_term, $date_for_absences);
        if ($count > 0) {
            for ($i = 1; $i <= $count; $i++) {
                $this_student_absences = student_info::get_abences_student_today($date_for_absences, $mystudent_list[$i]->id);
                // Print the heading. Don't print it if you've reached the bottom of the page.
                // Page is full. Start on a new page.
                if ($page_count >= $max_lines_per_page) {
                    // This provides a page break.
                    $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner</h4></html><P style="page-break-before: always">';
                    // Page Title - School Information
                    $advisor_info = $advisor_info . student_info::get_absences_title($school_name, $school_term, $date_for_absences);
                    // The header for the period takes up 3 lines and is therefore initialized at 3.
                    $page_count = 3;
                }
                // New row with the student information.
                $advisor_info = $advisor_info . '<tr><td><center>' . $i . '</center></td>' .
                    '<td class="cellborderleft"><center>' . $this_student_absences->school_id . '</center></td>' .
                    '<td class="cellborderright">' . $mystudent_list[$i]->fullname . ' (' . $this_student_absences->grade . ')</td>';
                // Print the attendance for each period.
                for ($p=1; $p<=8; $p++) {
                    // Print the attendance status (A=absent, T=Tardy, P=present, D=distance learner)./
                    $attendance_status = $this_student_absences->$p;
                    $advisor_info = $advisor_info . '<td><center>' . $attendance_status . '</center></td>';
                }
                $advisor_info = $advisor_info . '<td class="mycell">' . utils::remove_extra_symbols($this_student_absences->notes) . '</td></tr>';
                $page_count++;
            }
            // Add the legend at the end.
            $advisor_info = $advisor_info . '</table><h4>A = Absent, T = Tardy, P = Present in person, D = Distance learner</h4></body></html>';
        }
        else {
            $advisor_info = $advisor_info . '</table><br><br><h4>NO STUDENTS ARE ABSENT TODAY</h4></body></html>';
        }
        // Print the students by subject.
        $advisor_print_newtab = "<script>
                var newWindow = window.open('');
                newWindow.document.write('$advisor_info');
            </script>";
        echo $advisor_print_newtab;
        return;
    }

    // Get a list of the students that were absent today.
    public static function get_absent_students($date_for_absences) {
        global $DB;
        $sql = "SELECT * FROM {istudy_dailyattend_enter} WHERE attendance_date = " . $date_for_absences . " AND attendance_status = 1 ORDER By student_id ASC";
        $params = null;
        $absences_studentids = $DB->get_records_sql($sql, $params);
        $count = 0;
        $previous_studentid = 0;
        $my_students = [];
        foreach ($absences_studentids AS $absent_studentid) {
            // Check for a valid student id ad check for duplicates.
            $this_studentid = $absent_studentid->student_id;
            if ($this_studentid > 0 AND $this_studentid <> $previous_studentid) {
                if ($this_student = $DB->get_record('user', ['id' => $this_studentid], 'firstname, lastname, deleted, suspended')) {
                    // Skip the students that no longer attend the school.
                    if (!(($this_student->deleted) AND ($this_student->suspended))) {
                        $count++;
                        $my_students[$count] = new stdClass();
                        $my_students[$count]->id = $this_studentid;
                        $lastname_firstname = $this_student->lastname . ', ' . $this_student->firstname;
                        $my_students[$count]->fullname = $lastname_firstname;
                        $previous_studentid = $this_studentid;
                    }
                }
            }
        }
        // Sort the students alphabetically.
        for ($i=1; $i<=$count; $i++) {
            $val =  $my_students[$i];
            $j = $i-1;
            while ($j>=1 && $my_students[$j]->fullname > $val->fullname){
                 $my_students[$j+1] =  $my_students[$j];
                $j--;
            }
             $my_students[$j+1] = $val;
        }
        // Return the student count on array zero.
        $my_students[0] = new stdClass();
        $my_students[0]->id = $count;
        return $my_students;
    }

    //  Get the student demographics.
    public static function get_abences_student_today($date_for_absences, $student_id) {
        global $DB;
        $number_of_sessions = get_config('mod_istudy', 'number_of_sessions');
        $notes_for_student = '';
        $absence_label = [
            0 => '-',
            1 => '<b>A</b>',
            2 => 'P',
            3 => 'D',
            4 => 'T'
        ];
        // Student Info
        $student_info = new stdClass();
        $student_info->school_id = utils::get_field_data($student_id, utils::get_fieldid('schoolid'));
        $student_info->grade = utils::get_gradelevel(utils::get_field_data($student_id, utils::get_fieldid('graduationyear')));
        // Initialize the periods (period 1 to 8).
        for ($i=1; $i<=8; $i++) {
            $student_info->$i = '';
        }
        // If it exists, get the attendance data.
        for ($i=1; $i<=$number_of_sessions; $i++) {
            $sql = "SELECT * FROM {istudy_dailyattend_enter} WHERE  attendance_date = " . $date_for_absences . " AND student_id = " . $student_id . " AND session = " . $i;
            $params = null;
            $strictness = IGNORE_MULTIPLE;
            $temp_attendance = new stdClass();
            if ($temp_attendance = $DB->get_record_sql($sql, $params, $strictness)) {
            //if ($temp_attendance = $DB->get_record('istudy_dailyattend_enter', ['attendance_date'=>$date_for_absences, 'student_id'=>$student_id, 'session'=>$i], 'attendance_status, notes')) {
                $temp_status = $temp_attendance->attendance_status;
                $student_info->$i = $absence_label[$temp_status];
                $temp_notes = trim($temp_attendance->notes);
                // If not empty, new notes found.
                if ($temp_notes <> '') {
                    // If notes are already present on the list of notes, add a comma at the end to separate from the new notes.
                    if ($notes_for_student == '') {
                        $notes_for_student = $temp_notes;
                    }
                    else {
                        $notes_for_student = $notes_for_student . ' | ' . $temp_notes;
                    }
                }
            }
        }
        $student_info->notes = $notes_for_student;
        return $student_info;
    }
}