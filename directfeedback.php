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
 * This page prints a review of a particular quiz attempt.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

// Get submitted parameters.
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

// Trigger event.
$params = array(
    'objectid' => $attemptid,
    'relateduserid' => $attempt->get_userid(),
    'courseid' => $attempt->get_quiz()->get_course_id(),
    'context' => $attempt->get_quiz()->get_context(),
    'other' => array(
        'quizid' => $attempt->get_quiz()->get_id()
    )
);

$output = $PAGE->get_renderer('mod_ddtaquiz');

$feedback = feedback::get_feedback($attempt->get_quiz());

echo $OUTPUT->header();

echo $output->review_page($attempt, $options, $summarydata, $feedback);

echo $OUTPUT->footer();
