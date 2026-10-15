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
 * Class teacher_info
 *
 * @package    mod_istudyprintdailyatt
 * @copyright  2024 Jesus Ramirez <jram1993@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class teacher_info {

    /**
     * Get a single advisor.
     *
     * @param [integer] $chosen_advisor
     * @return [string]
     */
    public static function get_advisor($chosen_advisor) {
        global $DB;
        $sql = "SELECT * FROM {istudyteachers} ORDER By lastname ASC, firstname ASC";
        $params = null;
        $all_advisors = $DB->get_records_sql($sql, $params);
        $advisor = [];
        $count = 0;
        $advisor[$count] = 'All';
        // Create the list of advisors
       foreach ($all_advisors as $this_advisor) {
            $count = $count + 1;
            $teacher_lastname = $this_advisor->lastname;
            $teacher_firstname = $this_advisor->firstname;
            $advisor[$count] = $teacher_lastname . '-' . $teacher_firstname;
        }
        // Return the advisor.
        return $advisor[$chosen_advisor];
    }

    // Get a list of all advisors or teachers.
    public static function get_advisors() {
        global $DB;
        $sql = "SELECT * FROM {istudyteachers} ORDER By lastname ASC, firstname ASC";
        $params = null;
        $all_advisors = $DB->get_records_sql($sql, $params);
        $count = 0;
        foreach ($all_advisors as $this_advisor) {
            $count++;
            $advisor[$count] = $this_advisor->lastname . '-' . $this_advisor->firstname;
        }
        return $advisor;
    }

    // Get a list of all advisors.
    public static function get_advisors_and_all() {
        global $DB;
        $sql = "SELECT * FROM {istudyteachers} ORDER By lastname ASC, firstname ASC";
        $params = null;
        $all_advisors = $DB->get_records_sql($sql, $params);
        $count = 0;
        $advisor[$count] = 'All';
        foreach ($all_advisors as $this_advisor) {
            $count++;
            $advisor[$count] = $this_advisor->lastname . '-' . $this_advisor->firstname;
        }
        return $advisor;
    }

    // Get the current user and set as the default advisor.
    public static function get_default_advisor($advisors_list) {
        global $USER;
        $advisorid = $USER->id;
        $current_advisor = utils::get_field_data($advisorid, utils::get_fieldid('whichteacher'));
        if ($current_advisor == "Administrator" OR $current_advisor == "Parent") {
            return 0;
        }
        else {
            $default_advisor = 0;
            $count = 0;
            $current_advisor = teacher_info::get_advisor_name($advisorid);
            foreach ($advisors_list as $advisor_list) {
                $count = $count + 1;
                if (trim($advisor_list) == trim($current_advisor)) {
                    $default_advisor = $count;
                }
            }
            return $default_advisor;
        }
    }

    // Get advisor name.
    public static function get_advisor_name($advisorid) {
        global $DB;
        $this_teacher = new stdClass();
        $sql = "SELECT * FROM {istudyteachers} WHERE teacherid = '" . $advisorid . "'";
        $params = null;
        $this_teacher = $DB->get_record_sql($sql, $params);
        $advisor_name = $this_teacher->lastname . '-' . $this_teacher->firstname;
        return $advisor_name;
    }

    // Get the moodle id for the advisor.
    public static function get_whichadvisorid($myteacher) {
        global $DB;
        // Separate the first name and last name that are joined with a dash (-).
        $teacher_lastname = strstr($myteacher, '-', true);
        $myteacher_temp = strstr($myteacher, '-');
        $teacher_firstname = substr($myteacher_temp, 1);
        $this_teacher = new stdClass();
        $sql = "SELECT * FROM {istudyteachers} WHERE lastname = '" .  $teacher_lastname . "' AND firstname = '" .  $teacher_firstname . "'";
        $params = null;
        $this_teacher = $DB->get_record_sql($sql, $params);
        return $this_teacher->teacherid;
    }

    /**
     * Gets the advisor ($myteacher) room number.
     *
     * @param [type] $myteacher
     * @return void
     */
    public static function get_advisor_room($myteacher) {
        global $DB;
        // Separate the first name and last name that are joined with a dash (-).
        $teacher_lastname = strstr($myteacher, '-', true);
        $myteacher_temp = strstr($myteacher, '-');
        $teacher_firstname = substr($myteacher_temp, 1);
        $this_teacher = new stdClass();
        $sql = "SELECT * FROM {istudyteachers} WHERE lastname = '" .  $teacher_lastname . "' AND firstname = '" .  $teacher_firstname . "'";
        $params = null;
        $this_teacher = $DB->get_record_sql($sql, $params);
        return $this_teacher->room;
    }
}