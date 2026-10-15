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
 * The main istudyprintdailyatt configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Module instance settings form
 *
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_istudyprintdailyatt_search_criteria_form extends \moodleform
{

    public function definition()
	{
		$mform = $this->_form;
		$mform->addElement('html', '<html><link rel="stylesheet" type="text/css" href="styles-search_criteria_form.css">');
		$mform->addElement('html', '<h3>Print the Daily Attendance</h3><br><table><tr><th colspan="5">');
		$mform->addElement('html', '<h4>Refine Your Search</h4></th></tr>');

		$mform->addElement('html', '<tr><td class="mycell2">Choose the form to print</td><td class="mycell3">');
		$mform->addElement('radio', 'whichform', '','#1-Print by Teacher', 1);
		$mform->addElement('html', '</td><td class="mycell">');
		$mform->addElement('radio', 'whichform', '','#2-Print an Individual Student (1 per page)', 2);
		$mform->addElement('html', '</td><td class="mycell">');
		$mform->addElement('radio', 'whichform', '','#3-Print Several Students (30 per page)', 3);
		$mform->addElement('html', '</td><td class="mycell">');
		$mform->addElement('radio', 'whichform', '','#4-Absent Today', 4);

		$mform->setDefault('whichform', 1);
		$mform->setType('whichform', PARAM_INT);
		$mform->addElement('html', '</td></tr></table>');

		// Choose the term that you are printing from.
		$defaultterm_set = get_config('mod_istudy','schoolterm');
		if ($defaultterm_set == 'Fall') { $defaultterm = 1; }
		else if ($defaultterm_set == 'Spring') { $defaultterm = 2; }
		else { $defaultterm = 3; };
/*
		// OPTIONS 1: Choose the week.
		$weeks_for_menu = mod_istudyprintdailyatt\utils::dates_by_week();
		$default_week = mod_istudyprintdailyatt\utils::term_start_date($defaultterm);
		$select = $mform->addElement('select', 'start_of_week', '<b><u>CHOOSE THE STARTING WEEK FOR THIS TERM</u></b>', $weeks_for_menu);
		// Set default to first advisor on the list
		$select->setSelected($default_week);
		// If PRINT ABSENT TODAY is chosen, disable day_of_week.
		$mform->hideIf('start_of_week', 'whichform', 'neq', 1);
*/
		// OPTIONS 1, 2 and 3: Choose the term.
		$default_week = mod_istudyprintdailyatt\utils::term_start_date($defaultterm);
		$term_dates = mod_istudyprintdailyatt\utils::get_term_dates();
		$term_options = [
			$term_dates->fall_start => 'Fall',
			$term_dates->spring_start => 'Spring',
			$term_dates->summer_start => 'Summer'
		];
		$select = $mform->addElement('select', 'term_start_date', '<b><u>CHOOSE THE TERM</u></b>', $term_options);
		// Set default to first advisor on the list
		$select->setSelected($default_week);
		// If PRINT ABSENT TODAY is chosen, disable day_of_week.
		$mform->hideIf('term_start_date', 'whichform', 'eq', 4);

		// OPTIONS 1: Choose the Advisory Teacher.
		$advisors = mod_istudyprintdailyatt\teacher_info::get_advisors();
		$default_advisor = mod_istudyprintdailyatt\teacher_info::get_default_advisor($advisors);
		$select = $mform->addElement('select', 'advisor', '<b><u>CHOOSE THE ADVISOR</u></b>', $advisors);
		// Set default to first advisor.
		$select->setSelected($default_advisor);
		// If PRINT BY THE DAY OF THE WEEK is not chosen, disable day_of_week.
		$mform->hideIf('advisor', 'whichform', 'neq', 1);

		// OPTIONS 1: Choose to print attendance by teacher accoring to day of the week.
		$my_date_today = date("l");
		switch ($my_date_today) {
			case "Monday":
				$date_today = 1;
				break;
			case "Tuesday":
				$date_today = 2;
				break;
			case "Wednesday":
				$date_today = 3;
				break;
			case "Thursday":
				$date_today = 4;
				break;
			case "Friday":
				$date_today = 5;
				break;
			default:
				$date_today = 1;
		}
		$mform->addElement('html', '<table class="table1"><tr><td class="cell_noborder" valign="top" colspan="3"><b><u>');
		$mform->addElement('static', 'weekday_title', '', 'CHOOSE THE DAY OF THE WEEK FOR TERM');
		$mform->addElement('html', '</u></b></td></tr><tr><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week', '','ALL', 0, ['class' => 'radiobuttons-lastname-all']);
		$mform->addElement('html', '<td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week', '','Monday', 1, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week', '','Tuesday', 2, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td></tr><tr><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week', '','Wednesday', 3, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week', '','Thursday', 4, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week', '','Friday', 5, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td></tr></table>');

		// $select = $mform->addElement('select', 'day_of_week', 'Choose the day of the week', $day_options_all);
		// Set default to Fall.
		$mform->setDefault('day_of_week', $date_today);
		$mform->setType('day_of_week', PARAM_INT);
		// If PRINT BY THE DAY OF THE WEEK is not chosen, disable day_of_week.
		$mform->hideIf('day_of_week', 'whichform', 'neq', 1);
		$mform->hideIf('weekday_title', 'whichform', 'neq', 1);

		// OPTION 4: Choose the week for the absences.
		$weeks_for_menu = mod_istudyprintdailyatt\utils::dates_by_week();
		$default_week_absences = mod_istudyprintdailyatt\utils::get_monday_absences();
		$select = $mform->addElement('select', 'start_of_week_absences', '<b><u>CHOOSE THE STARTING WEEK</u></b>', $weeks_for_menu);
		// Set default to first advisor on the list
		$select->setSelected($default_week_absences);

		//OPTION 4: Choose the report for the absencese (today's absences only or a detailed semester report).
		$absent_report_options = [
			1 => 'Today Only',
			2 => 'Detailed Semester Report'
		];
		$select = $mform->addElement('select', 'absent_report_option', '<b><u>CHOOSE THE ABSENT REPORT TO PRINT</u></b>', $absent_report_options);
		// Set default to first advisor on the list
		$select->setSelected(1);

		// OPTION 4: Choose the day of the week for the absences.
		$mform->addElement('html', '<table class="table1"><tr><td class="cell_noborder" valign="top" colspan="3"><b><u>');
		$mform->addElement('static', 'weekday_title_absences', '', 'CHOOSE THE DAY OF THE WEEK');
		// $mform->addElement('radio', 'day_of_week_absent', '','ALL', 6);
		$mform->addElement('html', '</u></b><tr><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week_absent', '','Monday', 1, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week_absent', '','Tuesday', 2, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week_absent', '','Wednesday', 3, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><tr><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week_absent', '','Thursday', 4, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'day_of_week_absent', '','Friday', 5, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder"></td></tr></table>');

		$mform->setDefault('day_of_week_absent', $date_today);
		$mform->setType('day_of_week_absent', PARAM_INT);

		// If PRINT BY THE DAY OF THE WEEK is not chosen, disable day_of_week.
		$mform->hideIf('start_of_week_absences', 'whichform', 'neq', 4);
		$mform->hideIf('absent_report_option', 'whichform', 'neq', 4);
		$mform->hideIf('day_of_week_absent', 'whichform', 'neq', 4);
		$mform->hideIf('weekday_title_absences', 'whichform', 'neq', 4);

/*
		// OPTION 4: Choose the day of the week for the absences.
		$day_options = [
			1 => 'Monday',
			2 => 'Tuesday',
			3 => 'Wednesday',
			4 => 'Thursday',
			5 => 'Friday'
		];
		$select = $mform->addElement('select', 'day_of_week_absent', 'Absences - Day of the Week', $day_options);
		// Set default to Fall.
		$select->setSelected($date_today);
		// If PRINT BY THE DAY OF THE WEEK is not chosen, disable day_of_week.
		$mform->hideIf('day_of_week_absent', 'whichform', 'neq', 4);
*/

		// OPTION 2 or 3 was chosen.
		// Print attendance by student.
		$advisors_all = mod_istudyprintdailyatt\teacher_info::get_advisors_and_all();
		$select = $mform->addElement('select', 'advisor_all', '<b><u>CHOOSE THE ADVISOR</u></b>', $advisors_all);
		// Set default to first advisor on the list.
		$select->setSelected(0);

		// If PRINT BY THE DAY OF THE WEEK is chosen or ABSENT TODAY, disable Choose the Advisor.
		$mform->hideIf('advisor_all', 'whichform', 'eq', 1);
		$mform->hideIf('advisor_all', 'whichform', 'eq', 4);

	// Choose to print all students or print by last name
		$mform->addElement('html', '<table class="table1"><tr><td class="cell_noborder" colspan="14" valign="top"><b><u>');
		$mform->addElement('static', 'lastnamesearch_title', '', 'CHOOSE ALL STUDENTS OR BY LAST NAME', ['class' => 'mytitle']);

		// $mform->addElement('html', '<div class="radiobuttons">');
		$mform->addElement('html', '</u></b></td></tr><tr><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','ALL', 27, ['class' => 'radiobuttons-lastname-all']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','A', 1, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','B', 2, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','C', 3, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','D', 4, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','E', 5, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','F', 6, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','G', 7, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','H', 8, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','I', 9, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','J', 10, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','K', 11, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','L', 12, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','M', 13, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td></tr><tr><td class="cell_noborder"></td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','N', 14, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td class="cell_noborder"><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','O', 15, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','P', 16, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','Q', 17, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','R', 18, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','S', 19, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','T', 20, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','U', 21, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','V', 22, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','W', 23, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','X', 24, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','Y', 25, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td><td class="cell_noborder">');
		$mform->addElement('radio', 'lastnamesearch', '','Z', 26, ['class' => 'radiobuttons-lastname']);
		$mform->addElement('html', '</td></tr></table>');

		$mform->setDefault('lastnamesearch', 27);
		$mform->setType('lastnamesearch', PARAM_INT);
		// If PRINT BY THE DAY OF THE WEEK is chosen, disable student LASTNAMESEARCH.
		$mform->hideIf('lastnamesearch', 'whichform', 'eq', 1);
		$mform->hideIf('lastnamesearch', 'whichform', 'eq', 4);
		$mform->hideIf('lastnamesearch_title', 'whichform', 'eq', 1);
		$mform->hideIf('lastnamesearch_title', 'whichform', 'eq', 4);

		$mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

		// Add standard buttons, common to all modules.
		$mform->addElement('html', '<div class="buttonsetup">');
		$this->add_action_buttons(true, 'Submit');
		$mform->addElement('html', '</div></html>');
    }
}