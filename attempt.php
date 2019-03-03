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

// Load the mandatory configurations and libraries
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/locallib.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/attemptlib.php');

// Get the attempt id from the parameters.
$attemptid = required_param('attempt', PARAM_INT);

$attempt = attempt::load($attemptid);
$cmid = $attempt->get_quiz()->get_cmid();

if (!$cm = get_coursemodule_from_id('ddtaquiz', $cmid)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

// Check login.
require_login($course, false, $cm);

// If the attempt is already finished, go to the review page
if ($attempt->check_state_finished()) {
    redirect($attempt->review_url());
}

$ddtaquiz = ddtaquiz::load($cm->instance);
$PAGE->set_url($attempt->attempt_url());
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($ddtaquiz->get_main_block()->get_name()));
$PAGE->set_heading($course->fullname);
$output = $PAGE->get_renderer('mod_ddtaquiz');

$options = new question_display_options();
$options->flags = question_display_options::HIDDEN;
if(!$ddtaquiz->show_grades())
    $options->marks = question_display_options::HIDDEN;

echo $OUTPUT->header();
echo $output->attempt_page($attempt, $attempt->get_current_slot(), $options, $cmid);
echo $OUTPUT->footer();
