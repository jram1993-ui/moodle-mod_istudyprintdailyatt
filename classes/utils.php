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
 * Class utils
 *
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class utils {

    // Remove extra characters from the string.
    public static function remove_extra_symbols($snip) {
        $snip = str_replace("'", "’", $snip); // remove apostrophe
        //$snip = str_replace(",", "", $snip); // remove comma
        $snip = str_replace("\t", '', $snip); // remove tabs
        $snip = str_replace("\n", '', $snip); // remove new lines
        $snip = str_replace("\r", '', $snip); // remove carriage returns
        return $snip;
    }

    // Make sure that the date is not zero.
    public static function check_date($submission) {
        if ($submission > 0) {
            return date("m-d-Y", $submission);
        }
        else {
            return '';
        }
    }

    // Replace the dash in the teacher name with a comma and space.
    public static function put_comma_in_name($myteacher) {
        $postion = strrpos($myteacher,"-");
        $name_with_dash = substr($myteacher, $postion);
        $name_with_comma = ', ' . substr($name_with_dash,1);
        $new_name = str_replace($name_with_dash,$name_with_comma,$myteacher);
        return $new_name;
    }

    // Return last name only.
    public static function last_name_only($myteacher) {
        $new_name = strstr($myteacher, '-', true);
        return $new_name;
    }

    // Find Monday for the date chosen.
    public static function get_monday($my_date) {
        // Adjust the time to 12:00 am for this date.
        $my_date = utils::set_to_12pm(strtotime(date("m/d/Y", $my_date)));
        // The number of days to add or subtract.
        $my_date_adjustment['Monday'] = "+0 day";
        $my_date_adjustment['Tuesday'] = "-1 day";
        $my_date_adjustment['Wednesday'] ="-2 day";
        $my_date_adjustment['Thursday'] = "-3 day";
        $my_date_adjustment['Friday'] = "-4 day";
        $my_date_adjustment['Saturday'] = "+2 day";
        $my_date_adjustment['Sunday'] = "+1 day";
        // Get the weekday.
        $date_chosen = date("l", $my_date);
        // Convert the current date to Monday.
        $adjusted_date = utils::set_to_12pm(strtotime($my_date_adjustment[$date_chosen], $my_date));
        return $adjusted_date;
    }

    // Find Monday for the date chosen.
    public static function get_monday_absences() {
        $my_date_today = strtotime('Today');
        $thisweek_monday = utils::get_monday($my_date_today);
        $month[1] = 'month01';
        $month[2] = 'month02';
        $month[3] = 'month03';
        $month[4] = 'month04';
        $month[5] = 'month05';
        $month[6] = 'month06';
        $month[7] = 'month07';
        $month[8] = 'month08';
        $month[9] = 'month09';
        $month[10] = 'month10';
        $month[11] = 'month11';
        $week[1] = 'week01date';
        $week[2] = 'week02date';
        $week[3] = 'week03date';
        $week[4] = 'week04date';
		// Choose the month (#1-11).
		// There are 86400 seconds in a day. Add 5 or 7 days to find the date for Friday.
        $seven_days = 86400+86400+86400+86400+86400+86400+86400;
        // Compare the school calendar monday dates to the monday for this week.
		for ($i = 1; $i <= 11; $i++) {
            for ($j = 1; $j <= 4; $j++) {
                $temp_monday_date_text = get_config('mod_istudy', $month[$i] . $week[$j]);
                $temp_monday_date = utils::set_to_12pm(strtotime($temp_monday_date_text));
                if ($temp_monday_date > 0) {
                    $last_saved_date =  $temp_monday_date;
                    if ($temp_monday_date == $thisweek_monday) {
                        return $thisweek_monday;
                    }
                }
            }
        }
        // Compate the summer school monday dates to the monday for this week.
		for ($i = 1; $i <= 10; $i++) {
            $last_saved_date = $last_saved_date + $seven_days;
            $temp_monday_date =  $last_saved_date;
            if ($temp_monday_date == $thisweek_monday){
                return $thisweek_monday;
            }
        }
        // If the date wasn't found, return the start date for this semesester.
        $this_schoolterm_text = get_config('mod_istudy', 'schoolterm');
        // Choose Fall as the default;
        $this_schoolterm = 1;
        if ($this_schoolterm_text == 'Spring') {
            $this_schoolterm = 2;
        }
        else {
            $this_schoolterm = 3;
        }
        $term_starting_date = utils::term_start_date($this_schoolterm);
        return $term_starting_date;
    }

    // Return a number for the weekday - Monday = 1, Tuesday = 2, Wednesday = 3, Thursday = 4, Friday = 5.
    public static function get_weekday_number($my_date) {
        $my_date_adjustment['Monday'] = 1;
        $my_date_adjustment['Tuesday'] = 2;
        $my_date_adjustment['Wednesday'] = 3;
        $my_date_adjustment['Thursday'] = 4;
        $my_date_adjustment['Friday'] = 5;
        $my_date_adjustment['Saturday'] = 5;
        $my_date_adjustment['Sunday'] = 5;
        $date_chosen = date("l", $my_date);
        return $my_date_adjustment[$date_chosen];
    }

    // Using the date for monday ($start_of_week) and the weekday ($day_of_week), find the exact calendar date.
    public static function get_attendance_date($start_of_week, $day_of_week) {
        // The number of days to add.
        $one_day = 86400;
        $my_date_adjustment[1] = 0; // Monday
        $my_date_adjustment[2] = $one_day; // Tuesday
        $my_date_adjustment[3] = $one_day * 2;  // Wednesday
        $my_date_adjustment[4] = $one_day * 3;  // Thursday
        $my_date_adjustment[5] = $one_day * 4;  // Friday
        // Convert the current date to Monday.
        $my_exact_date = utils::set_to_12pm($start_of_week + $my_date_adjustment[$day_of_week]);
        return $my_exact_date;
    }

    // Get dates for 20 weeks according to starting date and day of the week for each week.
    public static function get_attendance_weeks($this_start_of_week, $this_day_of_week) {
        $dates_by_week = [];
        // Initialize dates list.
        for ($i = 1; $i <= 22; $i++) {
            $dates_by_week[$i] = 0;
        }
        $month[1] = 'month01';
        $month[2] = 'month02';
        $month[3] = 'month03';
        $month[4] = 'month04';
        $month[5] = 'month05';
        $month[6] = 'month06';
        $month[7] = 'month07';
        $month[8] = 'month08';
        $month[9] = 'month09';
        $month[10] = 'month10';
        $month[11] = 'month11';
        $week[1] = 'week01date';
        $week[2] = 'week02date';
        $week[3] = 'week03date';
        $week[4] = 'week04date';
        // Adjust Monday so that the time is set to 12:00 pm.
        $this_start_of_week = utils::set_to_12pm($this_start_of_week);
        $day_of_week['1'] = 0; // Monday
        $day_of_week['2'] = 86400; // Tuesday
        $day_of_week['3'] = 86400+86400; // Wednesday
        $day_of_week['4'] = 86400+86400+86400; // Thursday
        $day_of_week['5'] = 86400+86400+86400+86400; // Friday
        // There are 86400 seconds in a day. Add 7 days to find the date next week.
        $seven_days = 86400+86400+86400+86400+86400+86400+86400;
        $this_term = utils::get_term_dates();
        // Assign the start and end dates of the term associated with $this_start_of_week.
        $not_a_summer_term = true;
        if ($this_start_of_week >= $this_term->fall_start AND $this_start_of_week <= $this_term->fall_end) {
            $term_start = $this_term->fall_start;
            $term_end = $this_term->fall_end;
        }
        elseif ($this_start_of_week >= $this_term->spring_start AND $this_start_of_week <= $this_term->spring_end) {
            $term_start = $this_term->spring_start;
            $term_end = $this_term->spring_end;
        }
        // Create the list for the summer dates.
        else{
            // Add the summer dates.
            $not_a_summer_term = false;
            $this_summer_week =  $this_term->summer_start;
            for ($i = 1; $i <= 10; $i++) {
                $dates_by_week[$i] = $this_summer_week + $day_of_week[$this_day_of_week];
                $this_summer_week = $this_summer_week + $seven_days;
            }
        }
        if ($not_a_summer_term) {
            $count = 0;
            // Get all of the dates for the fall term or spring term.
            for ($i = 1; $i <= 11; $i++) {
                for ($j = 1; $j <= 4; $j++) {
                    $temp_monday_date_text = get_config('mod_istudy', $month[$i] . $week[$j]);
                    $temp_monday_date = utils::set_to_12pm(strtotime($temp_monday_date_text));
                    if (($temp_monday_date >= $term_start) AND ($temp_monday_date <= $term_end)) {
                        $count++;
                        $dates_by_week[$count] = $temp_monday_date + $day_of_week[$this_day_of_week];
                    }
                }
            }
        }
        return $dates_by_week;
    }

    // Return the course information.
    public static function get_mycourse($mycourse) {
        $course_info = new stdClass();
        $course_info->subject = strstr($mycourse, '/', true);
        $mycourse = strstr($mycourse, "/");
        $mycourse = substr($mycourse, 1);
        $course_info->teacher = strstr($mycourse, '/', true);
        $mycourse = strstr($mycourse, "/");
        $mycourse = substr($mycourse, 1);
        $course_info->section = trim($mycourse);
        // $course_info->room = teacher_info::get_advisor_room($course_info->teacher);
        return $course_info;
    }

    // Return the term chosen - 1=Fall, 2=Spring, 3=Summer.
    public static function get_term($term_choice) {
        $schoolterms[1] = 'fall';
        $schoolterms[2] = 'spring';
        $schoolterms[3] = 'summer';
        return $schoolterms[$term_choice];
    }

    // Get the lastname search criteria.
    public static function get_lastnamesearch($lastnameinitial){
        if ($lastnameinitial < 27) {
            $lastname_criteria = chr($lastnameinitial + 96) . '%'; }
        else {$lastname_criteria = '%'; }
        return $lastname_criteria;
    }

    // Calculate the grade level from the graduation year.
    public static function get_gradelevel($grad_year) {
        $currentmonth = date('m');
        $currentyear = date('Y');
        if ($grad_year <= $currentyear) {
            $years_left = 0;
        }
        else if ($currentmonth > 7) {
            $years_left = $grad_year - ($currentyear + 1);
        }
        else {
            $years_left = $grad_year - $currentyear;
        }
        $my_grade_level = 12 - $years_left;
        return $my_grade_level;
    }

    // Get the user_info_field id to use in searching user_info_data.
    public static function get_fieldid($item_name) {
        global $DB;
        $item = $DB->get_record('user_info_field', ['shortname' => $item_name], 'id');
        return $item->id;
    }

    // Search user_info_data for student data using the student id and user_info_field id.
    public static function get_field_data($student_id, $itemid) {
        global $DB;
        if ($DB->record_exists('user_info_data', ['userid'=>$student_id, 'fieldid'=>$itemid])) {
            $search_results = $DB->get_record('user_info_data', ['userid'=>$student_id, 'fieldid'=>$itemid], 'data');
            return $search_results->data;
        }
        else {
            return '';
        }
    }

    // Adjust the date so that the current date today is the last date of at 20 weeks.
    public static function adjust_starting_date($this_date) {
        // Date today
        $date2 = utils::set_to_12pm(time());
        $number_of_weeks = round(($date2 - $this_date)/(7*24*60*60) - 0.5);
        // If tday and the start date are over 20 weeks apart, adjust the start date so that it is only 20 weeks away from today.
        if ($number_of_weeks >= 20) {
            $this_date = utils::set_to_12pm(strtotime("+" . ($number_of_weeks - 19) . " week", $this_date));
        }
        return $this_date;
    }

    // Get the start date for this term - fall, spring, or summer.
    public static function term_start_date($this_schoolterm) {
        // Get the starting dates for the Fall and Spring semesters
        for ($m = 1; $m <= 11; $m++) {
            // For months 1-9, zero must be inserted after month (e.g., month09), sloppy programming.
            if ($m < 10) {
                $school_month_temp = 'month0' . $m;
            }
            else {
                $school_month_temp = 'month' . $m;
            }
            for ($w = 1; $w <= 4; $w++) {
                $week_start_date[$m][$w] = utils::set_to_12pm(strtotime(get_config('mod_istudy', $school_month_temp . 'week0' . $w . 'date')));
                $week_points[$m][$w] = get_config('mod_istudy', $school_month_temp . 'week0' . $w );
            }
        }
        // Find the last week of school.
        $last_month = 11;
        $last_week = 4;
        for ($m = 1; $m <= 11; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                if ($week_points[$m][$w] > 0){
                    $last_month = $m;
                    $last_week = $w;
                }
            }
        }
        // Fall term - return the first week of school.
        if ($this_schoolterm == 1) {
            return $week_start_date[1][1];
        }
        // Spring term - return the first week in January.
        else if ($this_schoolterm == 2) {
            $first_spring_day = $week_start_date[6][1];
            // If the first day of the Spring semester starts during month5, adjust the date.
            for ($w = 4; $w >= 1; $w--) {
                if (date("n", $week_start_date[5][$w]) == '1'){
                    $first_spring_day = $week_start_date[5][$w];
                }
            }
            return $first_spring_day;
        }
        // Summer term - return the Monday after the last week of school.
        else {
            $adjusted_date =  utils::set_to_12pm(strtotime("+1 week", $week_start_date[$last_month][$last_week]));
            return $adjusted_date;
        }
    }

    // Return this school year from the beginning July 1 until June 30 at the end of the school year.
    public static function get_semester_dates($this_schoolterm) {
        $month[1] = 'month01';
        $month[2] = 'month02';
        $month[3] = 'month03';
        $month[4] = 'month04';
        $month[5] = 'month05';
        $month[6] = 'month06';
        $month[7] = 'month07';
        $month[8] = 'month08';
        $month[9] = 'month09';
        $month[10] = 'month10';
        $month[11] = 'month11';
        $week[1] = 'week01';
        $week[2] = 'week02';
        $week[3] = 'week03';
        $week[4] = 'week04';
        // Variable for the semester start and end dates.
        $semester_info = new stdClass();
        // Get the starting dates for the Fall and Spring semesters.
        for ($m = 1; $m <= 11; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                $week_start_date[$m][$w] = utils::set_to_12pm(strtotime(get_config('mod_istudy', $month[$m] . $week[$w] . 'date')));
                $week_points[$m][$w] = get_config('mod_istudy', $month[$m] . $week[$w]);
            }
        }
        // Find the last week of school.
        $last_month = 11;
        $last_week = 4;
        for ($m = 1; $m <= 11; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                if ($week_points[$m][$w] > 0){
                    $last_month = $m;
                    $last_week = $w;
                }
            }
        }
        // Fall term - return the first week of school.
        if ($this_schoolterm == '1') {
            $semester_info->start = $week_start_date[1][1];
            $semester_info->finish = utils::set_to_12pm(strtotime("12/31/" . date("Y", $week_start_date[1][1])));
        }
        // Spring term - return the first week in January.
        else if ($this_schoolterm == '2') {
            $first_spring_day = $week_start_date[6][1];
            // If the first day of the Spring semester starts during month5, adjust the date.
            for ($w = 4; $w >= 1; $w--) {
                if (date("n", $week_start_date[5][$w]) == '1'){
                    $first_spring_day = $week_start_date[5][$w];
                }
            }
            $semester_info->start = $first_spring_day;
            $semester_info->finish =  utils::set_to_12pm(strtotime("+5 days", $week_start_date[$last_month][$last_week]));
        }
        // Summer term - return the Monday after the last week of school and the last day of july.
        else {
            $semester_info->start = utils::set_to_12pm(strtotime("+1 week", $week_start_date[$last_month][$last_week]));
            $semester_info->finish = utils::set_to_12pm(strtotime("07/31/" . date("Y", $week_start_date[$last_month][$last_week])));
        }
        return $semester_info;
    }

    // Find complete course name and info using the section number ($courseid).
    public static function get_course_name($courseid) {
        global $DB;
        $select = "section = :section";
		$params =['section' => $courseid];
        if ($course_info = $DB->get_record_select('istudycourse_hours', $select, $params)) {
            return $course_info->subject;
        }
        else {
            return 'Course name not found.';
        }
    }

    // Return this school year for the fall(year_start) and the spring (year_end).
    public static function get_schoolyear($schoolterm) {
        // Find the beginning and the end of the current school year
        $month_today = strval(date("m"));
        if ($month_today >= 7) {
            $year_start = strval(date("Y"));
        }
        else {
            $year_start = strval(date("Y")) - 1;
        }
        $year_end = $year_start + 1;
        if (($schoolterm == 'Fall') OR ($schoolterm == 'fall')) {
            return $year_start;
        }
        else {
            return $year_end;
        }
    }

    // Get the starting and ending dates for each term (fall, spring, and summer).
    public static function get_term_dates() {
        $monday_dates_by_week = [];
        $monday_credits_by_week = [];
        $month[1] = 'month01';
        $month[2] = 'month02';
        $month[3] = 'month03';
        $month[4] = 'month04';
        $month[5] = 'month05';
        $month[6] = 'month06';
        $month[7] = 'month07';
        $month[8] = 'month08';
        $month[9] = 'month09';
        $month[10] = 'month10';
        $month[11] = 'month11';
        $week[1] = 'week01date';
        $week[2] = 'week02date';
        $week[3] = 'week03date';
        $week[4] = 'week04date';
        // There are 86400 seconds in a day.
        $seven_days = 86400+86400+86400+86400+86400+86400+86400;

        // Get all of the Monday dates.
        $count = 0;
        for ($i = 1; $i <= 11; $i++) {
            for ($j = 1; $j <= 4; $j++) {
                $count++;
                $monday_dates_by_week[$count] = get_config('mod_istudy', $month[$i] . $week[$j]);
                $monday_credits_by_week[$count] = get_config('mod_istudy', $month[$i] . 'week0' . $j);
            }
        }
        $fall_start_date = get_config('mod_istudy', $month[1] . $week[1]);
        $spring_start_date = get_config('mod_istudy', 'spring_start_date');

        // Find the fall end date.
        // Get the default fall end date.
        $fall_end_date = get_config('mod_istudy', 'month05week04date');
        for ($i = 1; $i <= 11; $i++) {
            if ($monday_dates_by_week[$i] == $spring_start_date) {
                $fall_end_date = $monday_dates_by_week[$i-1];
            }
        }
        // Get the spring end date.
        // Get the default spring end date.
        $spring_end_date = get_config('mod_istudy', 'month10week04date');
        $end_date_not_found = true;
        for ($i = 44; $i <= 1; $i--) {
            if ($monday_credits_by_week[$i] > 0 AND $end_date_not_found) {
                $spring_end_date = $monday_dates_by_week[$i];
                $end_date_not_found = false;
            }
        }
		$term_dates = new stdClass();
        $term_dates->fall_start = utils::set_to_12pm(strtotime($fall_start_date));
        $term_dates->fall_end = utils::set_to_12pm(strtotime($fall_end_date));
        $term_dates->spring_start = utils::set_to_12pm(strtotime($spring_start_date));
        $term_dates->spring_end = utils::set_to_12pm(strtotime($spring_end_date));
        $term_dates->summer_start = $term_dates->spring_end + $seven_days;
        // Add 9 weeks to get the end date.
        $term_dates->summer_end = $term_dates->summer_start + ($seven_days * 9); // Add 9 weeks to get the end date.
		return $term_dates;
    }

    // Get Monday's date for each week for this school year.
    public static function dates_by_week() {
        $dates_by_week = [];
        $term_dates = new stdClass();
        $term_dates = utils::get_term_dates();
        $month[1] = 'month01';
        $month[2] = 'month02';
        $month[3] = 'month03';
        $month[4] = 'month04';
        $month[5] = 'month05';
        $month[6] = 'month06';
        $month[7] = 'month07';
        $month[8] = 'month08';
        $month[9] = 'month09';
        $month[10] = 'month10';
        $month[11] = 'month11';
        $week[1] = 'week01date';
        $week[2] = 'week02date';
        $week[3] = 'week03date';
        $week[4] = 'week04date';
        $count = 0;
		// Choose the month (#1-11).
		// There are 86400 seconds in a day. Add 5 or 7 days to find the date for Friday.
        $four_days = 86400+86400+86400+86400;
        $seven_days = 86400+86400+86400+86400+86400+86400+86400;
		for ($i = 1; $i <= 11; $i++) {
            for ($j = 1; $j <= 4; $j++) {
                $temp_monday_date_text = get_config('mod_istudy', $month[$i] . $week[$j]);
                $temp_monday_date = utils::set_to_12pm(strtotime($temp_monday_date_text));
                $temp_friday_date = $temp_monday_date + $four_days;
                $temp_friday_date_text = date("m/d/y", $temp_friday_date);
                if ($temp_monday_date >= $term_dates->fall_start AND $temp_monday_date <= $term_dates->spring_end) {
                    // Each menu item will start with the week count.
                    $count++;
                    $week_label = 'Week ' . $count . ':  ';
                    if ($count < 10) {
                        $week_label = 'Week 0' . $count . ':  ';
                    }
                    $dates_by_week[$temp_monday_date] = $week_label . $temp_monday_date_text . ' - ' . $temp_friday_date_text;
                }
            }
        }
        // Summer weeks start after the last spring date.
        $last_saved_date = $term_dates->spring_end;
		for ($i = 1; $i <= 10; $i++) {
            $last_saved_date = $last_saved_date + $seven_days;
            $temp_monday_date =  $last_saved_date;
            $temp_monday_date_text = date("m/d/y", $temp_monday_date);
            $temp_friday_date = $temp_monday_date + $four_days;
            $temp_friday_date_text = date("m/d/y", $temp_friday_date);
            // Each menu item will start with the week count.
            $count++;
            $week_label = 'Week ' . $count . ':  ';
            $dates_by_week[$temp_monday_date] = $week_label . $temp_monday_date_text . ' - ' . $temp_friday_date_text;
        }
        return $dates_by_week;
    }

     // Return the term according to the date chosen (fall=1, spring=2, and summer=3).
    public static function get_this_term($date_chosen) {
        // Get the starting dates of each week for months 10 and 11.
        for ($m = 10; $m <= 11; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                $week_start_date[$m][$w] = utils::set_to_12pm(strtotime(get_config('mod_istudy', 'month' . $m . 'week0' . $w . 'date')));
                $week_points[$m][$w] = get_config('mod_istudy', 'month' . $m . 'week0' . $w );
            }
        }

        // Find the last week of school.
        $last_week_found = false;
        $last_month = 11;
        $last_week = 4;
        $one_day = 86400;
        $four_days = 86400+86400+86400+86400;
        for ($m = 11; $m >= 10; $m--) {
            for ($w = 4; $w >= 1; $w--) {
                if (($week_points[$m][$w] > 0) and ($last_week_found == false)){
                    $last_month = $m;
                    $last_week = $w;
                    $last_week_found = true;
                }
            }
        }
        $spring_start_date_text = get_config('mod_istudy', 'spring_start_date');
        $spring_start_date = utils::set_to_12pm(strtotime($spring_start_date_text)) - $one_day;
        $last_school_day = $week_start_date[$last_month][$last_week]+ $four_days;

        // The default term is 1 (fall).
        $this_term = 1;
        // Check the date against the spring start date.
        if ($date_chosen > $spring_start_date) {
            $this_term = 2;
        }
        // Check the date against the summer start date.
        if ($date_chosen > $last_school_day) {
            $this_term = 3;
        }
        return $this_term;
    }

	// Set the time to 12:00 pm for this day.
    public static function set_to_12pm($mydatetoday) {
        // Adjust the day so that the time is set to 12:00 pm.
        //$mydatetoday = ((intval($mydatetoday/86400))*86400)+(86400/2);
        return $mydatetoday;
    }
}