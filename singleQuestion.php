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
 * This script displays a particular page of a quiz attempt that is in progress.
 *
 * @package   mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/locallib.php');


// Get submitted parameters.
$attemptid = required_param('attemptID',  PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$slot= required_param('slot',  PARAM_INT);

$timenow = time();

if (!$cm = get_coursemodule_from_id('ddtaquiz', $cmid)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

// Check login.
require_login($course, false, $cm);

$attempt = attempt::load($attemptid);



// Check that this attempt belongs to this user.
if ($attempt->get_userid() != $USER->id) {
    throw new moodle_quiz_exception($attempt->get_quiz(), 'notyourattempt');
}
$attempt->process_bindif_slot($timenow,$slot);