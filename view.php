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
 * Prints a particular instance of istudyprintdailyatt
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/search_criteria_form.php');

global $DB, $USER;

/**
 * Course module id.
 */
$id = optional_param('id', 0, PARAM_INT);

/**
 * Activity instance id.
 */
$i = optional_param('i', 0, PARAM_INT);
if ($id) {
    $cm = get_coursemodule_from_id('istudyprintdailyatt', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('istudyprintdailyatt', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('istudyprintdailyatt', array('id' => $i), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('istudyprintdailyatt', $moduleinstance->id, $course->id, false, MUST_EXIST);
}
$istudyprintdailyattid = $cm->instance;

require_login($course, true, $cm);

$modulecontext = \context_module::instance($cm->id);

$PAGE->set_url('/mod/istudyprintdailyatt/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Output starts here.
echo $OUTPUT->header();

$mform = new mod_istudyprintdailyatt_search_criteria_form();
$mform->set_data($istudyprintdailyattid);
if ($mform->is_cancelled()) {
	// Cancelled forms redirect to the course main page.
	$url = new moodle_url('/course/view.php', array('id' => $course->id));
	redirect($url, 'Returning to Course Page', 1);
} else if ($data = $mform->get_data()) {
	$school_whichform = $data->whichform;
	// Get the 20 week attendance for a specific teacher and day of the week.
	if ($school_whichform == 1) {
		//$school_startofweek = $data->start_of_week;
		$school_startofweek = $data->term_start_date;
		$school_term = mod_istudyprintdailyatt\utils::get_this_term($school_startofweek);
		$school_day_of_week = $data->day_of_week;
		$school_advisor1 = $data->advisor;
		$advisor_name = mod_istudyprintdailyatt\teacher_info::get_advisor($school_advisor1);
		if ($school_day_of_week == 0) {
			// All was chosen - print attendace for each day of the week (Monday-Friday).
			for ($i = 1; $i <= 5; $i++) {
				mod_istudyprintdailyatt\student_info::get_day_attendance($advisor_name, $school_term, $school_startofweek, $i);
			}
			echo $OUTPUT->notification('YOUR DOCUMENT HAS BEEN PRINTED - If it did not print, please turn off your popup blocker for this site', 'notifysuccess');
		}
		else {
			// Print the attendance for the day of the week that was chosen.
			mod_istudyprintdailyatt\student_info::get_day_attendance($advisor_name, $school_term, $school_startofweek, $school_day_of_week);
			echo $OUTPUT->notification('YOUR DOCUMENT HAS BEEN PRINTED - If it did not print, please turn off your popup blocker for this site', 'notifysuccess');
		}
	}
	// Get the 20 week attendance for a specific student.
	else if ($school_whichform == 2) {

		$school_lastnamesearch = $data->lastnamesearch;
		$USER->schooltermpda = mod_istudyprintdailyatt\utils::get_this_term($data->term_start_date);
		$USER->startofweekpda = $data->term_start_date;
		$USER->whichformpda = $school_whichform;
		$USER->advisorpda = $data->advisor_all;
		$USER->lastnamesearchpda = $school_lastnamesearch;
		// The student daily attendance has not been printed yet. Set to Yes after the first print.
		$USER->just_savedpda = 'No';

		$choose_studenturl = new moodle_url('/mod/istudyprintdailyatt/choose_student.php', array('id' => $cm->id));
		redirect($choose_studenturl);

	}
	// Get a list of the 20 week attendance totals for the students chosen.
	else if ($school_whichform == 3) {

		$school_lastnamesearch = $data->lastnamesearch;
		$USER->schooltermpda = mod_istudyprintdailyatt\utils::get_this_term($data->term_start_date);
		$USER->startofweekpda = $data->term_start_date;
		$USER->whichformpda = $school_whichform;
		$USER->advisorpda = $data->advisor_all;
		$USER->lastnamesearchpda = $school_lastnamesearch;

		$choose_studenturl = new moodle_url('/mod/istudyprintdailyatt/choose_student.php', array('id' => $cm->id));
		redirect($choose_studenturl);

	}
	// Option 4 was chosen. Print the absences for a specific date.
	else {
		// $school_startofweek = $data->start_of_week;
		$school_startofweek_absences = $data->start_of_week_absences;
		$absent_report_chosen = $data->absent_report_option;
		$school_term_absent = mod_istudyprintdailyatt\utils::get_this_term($school_startofweek_absences);
		$school_day_of_week_absent = $data->day_of_week_absent;
		$date_for_absences = mod_istudyprintdailyatt\utils::get_attendance_date($school_startofweek_absences, $school_day_of_week_absent);
		if ($absent_report_chosen == 1) {
			// Print today's absences by student (today only).
			// Print the absences for today by period (detailed semester report);
			mod_istudyprintdailyatt\student_info::get_absent_today_only($school_term_absent, $date_for_absences);
		}
		else {
			// Print the absences for today by period (detailed semester report);
			mod_istudyprintdailyatt\student_info::get_todays_absences($school_term_absent, $school_startofweek_absences, $school_day_of_week_absent, $date_for_absences);
		}

		echo $OUTPUT->notification('YOUR DOCUMENT HAS BEEN PRINTED - If it did not print, please turn off your popup blocker for this site', 'notifysuccess');
	}
} else {
	// Form has not been submitted or there was an error.
	// Just display the form.
	$mform->set_data(array('id' => $id));
}

$mform->display();
echo $OUTPUT->footer();