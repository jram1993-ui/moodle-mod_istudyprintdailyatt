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
 * Prints a particular instance of istudyprintattendanc
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_istudyprintattendanc
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/choose_student_form.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'istudyprintdailyatt');
$istudyprintdailyatt = $DB->get_record('istudyprintdailyatt', array('id'=> $cm->instance), '*', MUST_EXIST);
$istudyprintdailyattid = $cm->instance;
$PAGE->set_url('/mod/istudyprintdailyatt/choose_student.php', array('id' => $cm->id));

require_login($course, true, $cm);

$context = $cm->context;

$school_term = $USER->schooltermpda;
$school_startofweek = $USER->startofweekpda;
$school_whichform = $USER->whichformpda;
$advisorid = $USER->advisorpda;
$advisor_name = mod_istudyprintdailyatt\teacher_info::get_advisor($advisorid);
$lastnamesearch = mod_istudyprintdailyatt\utils::get_lastnamesearch($USER->lastnamesearchpda);

echo $OUTPUT->header();

// Display form.
$formparams = array('schoolterm' => $school_term, 'startofweek' => $school_startofweek, 'whichform' => $school_whichform,
	'advisor_name' => $advisor_name, 'lastnamesearch' => $lastnamesearch);
$mform = new mod_istudyprintdailyatt_choose_student_form(null, $formparams);
$mform->set_data($istudyprintdailyattid);
if ($mform->is_cancelled()) {
	// Cancelled forms redirect to the course main page.
	$url = new moodle_url('/course/view.php', array('id' => $course->id));
	redirect($url);
	return;

} else if ($data = $mform->get_data()) {
	// Make sure at least 1 student was chosen.
	$student_was_chosen = mod_istudyprintdailyatt\student_info::get_student_chosen($data);
	if ($student_was_chosen) {
		// Print the individual student attendance.
		if ($data->whichform == 2) {
			// Get the course data
			mod_istudyprintdailyatt\student_info::get_individual_attendance($data);
			// Changes have been saved - reload the page and output the message 'Changes Saved'.
			$USER->just_savedpda = 'Yes';
			$mform->reset_checkboxes($data);
		}
		// List the students chosen and print their total weekly attendance.
		else {
			// Get the course data
			mod_istudyprintdailyatt\student_info::get_attendance_totals($data);
			// Changes have been saved - reload the page and output the message 'Changes Saved'.
			$USER->just_savedpda = 'Yes';
			$mform->reset_checkboxes($data);
		}
	}
	else{
		$USER->just_savedpda = 'Error';
		$choose_studenturl = new moodle_url('/mod/istudyprintdailyatt/choose_student.php', array('id' => $cm->id));
		redirect($choose_studenturl);
	}
} else {
	// Form has not been submitted or there was an error.
	// Just display the form.
	$mform->set_data(array('id' => $id));
}

// Print the page header.
$PAGE->set_title(format_string($istudyprintdailyatt->name));
$PAGE->set_heading(format_string($course->fullname));

// Finish the page.
if ($USER->just_savedpda == 'Yes') {
	echo $OUTPUT->notification('STUDENT ATTENDANCE HAS BEEN PRINTED - If attendance did not print, please turn off your popup blocker for this site', 'notifysuccess');
	$USER->just_savedpda = 'No';
}
elseif ($USER->just_savedpda == "Error") {
	echo $OUTPUT->notification('ERROR: Please choose at least 1 student.');
	$USER->just_savedsch = 'No';
}
$mform->display();
echo $OUTPUT->footer();