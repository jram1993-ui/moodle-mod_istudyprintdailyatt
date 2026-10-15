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
 * The main istudyprintattendanc configuration form used for entering attendance credit
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_istudyprintattendanc
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Module instance settings form
 *
 * @package    mod_istudyprintattendanc
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_istudyprintdailyatt_choose_student_form extends \moodleform
{

    public function definition()
	{
		$mform    = $this->_form;
		$school_term    	= $this->_customdata['schoolterm'];
		$school_startofweek = $this->_customdata['startofweek'];
		$school_whichform 	= $this->_customdata['whichform'];
		$advisor_name    	= $this->_customdata['advisor_name'];
		$lastnamesearch 	= $this->_customdata['lastnamesearch'];

		$mystudents = mod_istudyprintdailyatt\student_info::get_studentlist($advisor_name, $lastnamesearch);

		$mform->addElement('html', '<link rel="stylesheet" type="text/css" href="styles-choose_student_form.css">');
		$this_term = [
			1 => 'Fall',
			2 => 'Spring',
			3 => 'Summer'
		];
		$this_title = $this_term[$school_term] . ' Term (20 weeks of Attendance)';
		$mform->addElement('html', '<body><h1>' . $this_title . '</h1>');
		$mform->addElement('html', '<h2>Starting Week - ' . date ("M d, Y", $school_startofweek) . '</h2>');
		$this->add_checkbox_controller(1, 'Select ALL Students / Select NONE', '', 0);

		$mform->addElement('html', '<input type="text" id="myInput" onkeyup="myFunction()" placeholder="Search for a specific student..." title="Type in a name">');

		$mform->addElement('html', '<table id="myTable">');
		$mform->addElement('html', '<tr><th width="2%">#</th><th>Student ID#</th><th>Student Name</th>' .
			'<th>Grade</th><th>Email</th><th>Birthday</th><th>Advisor</th><th>G.P.A.</th><th>Start Date</th></tr>');

		$count = 0;
		if ($mystudents == NULL) {
			echo '<h4>No students found using the search criteria</h4>';
		}
		else {
			// List the students.  Enter the points earned and a description of the assignment for each of the 4 weeks.
			$count = 0;
			foreach ($mystudents as $mystudent) {
				$count = $count + 1;

				// First column assigns $count (the current number in the series) to each student.
				$mform->addElement('html', '<tr><td><center>');
				$mform->addElement('advcheckbox', 'student_chosen' . $count, '', $count, ['group' => 1, 'class' => 'mycheckbox'], [0, $mystudent->id]);
				$mform->addElement('html', '</center></td>');

				// Second column shows the permanent district id.
				$mform->addElement('html', '<td><center>'. $mystudent->schoolid . '</center></td>');
				// Third column displays the student name - lastname.
				$mform->addElement('html', '<td>' . $mystudent->lastname . ', ' .  $mystudent->firstname . '</td>');

				// Fifth column shows the current grade level - 9, 10, 11, or 12.
				$mform->addElement('html', '<td><center>' . $mystudent->gradelevel . '</center></td>');

				// Sixth column shows the student email.
				$mform->addElement('html', '<td>' . $mystudent->email . '</td>');

				// Seventh column shows the student birthday.
				$mform->addElement('html', '<td><center>' . mod_istudyprintdailyatt\utils::check_date($mystudent->birthdate) . '</center></td>');

				// Eighth column shows the advisory teacher for that student.
				$mform->addElement('html', '<td>' . $mystudent->advisor . '</td>');

				// Ninth column shows the student grade point average.
				$mform->addElement('html', '<td><center>' . $mystudent->gpa . '</center></td>');

				// Tenth column shows the start or enrollment date.
				$mform->addElement('html', '<td><center>' . mod_istudyprintdailyatt\utils::check_date($mystudent->startdate) . '</center></td></tr>');
			}

			$mform->addElement('html', '</table>');

			// Hidden id number for sending the student count.
			$mform->addElement('hidden', 'studentcount', $count);
			$mform->setType('studentcount', PARAM_INT);

			// Hidden id number for sending the school month.
			$mform->addElement('hidden', 'schoolterm', $school_term);
			$mform->setType('schoolterm', PARAM_TEXT);

			// Hidden id number for sending the year that goes with the school term.
			$mform->addElement('hidden', 'startofweek', $school_startofweek);
			$mform->setType('startofweek', PARAM_INT);

			// Hidden id number for sending the year that goes with the school term.
			$mform->addElement('hidden', 'whichform', $school_whichform);
			$mform->setType('whichform', PARAM_INT);

			// Hidden id number for sending the data back.
			$mform->addElement('hidden', 'id');
			$mform->setType('id', PARAM_INT);

			// Add standard buttons, common to all modules.
			$mform->addElement('html', '<br><h3>  </h3>');
			$this->add_action_buttons($cancel = true, $submitlabel='Print Attendance');

			$mform->addElement('html', '<script>
                function myFunction() {
					var input, filter, table, tr, td, i, txtValue;
					input = document.getElementById("myInput");
					filter = input.value.toUpperCase();
					table = document.getElementById("myTable");
					tr = table.getElementsByTagName("tr");
					for (i = 0; i < tr.length; i++) {
						td = tr[i].getElementsByTagName("td")[2];
						if (td) {
							txtValue = td.textContent || td.innerText;
							if (txtValue.toUpperCase().indexOf(filter) > -1) {
							tr[i].style.display = "";
							} else {
							tr[i].style.display = "none";
							}
						}
					}
				}
            </script>');
			$mform->addElement('html', '</body>');
		}
	}

	// Reset the checkboxes in the form after the form has been submitted.
	public function reset_checkboxes($submission) {
		$student_count = $submission->studentcount;
		for ($i = 1; $i <= $student_count; $i++) {
			$this_student_checkbox = 'student_chosen' . $i;
			$this->_form->setConstant($this_student_checkbox, 0);
		}
	}
}