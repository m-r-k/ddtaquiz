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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/locallib.php');

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

$event = \mod_ddtaquiz\event\attempt_reviewed::create($params);
$event->trigger();

$ddtaquiz = ddtaquiz::load($cm->instance);


$options = new question_display_options();
$options->flags = question_display_options::HIDDEN;
if($ddtaquiz->getQuizmodes()===0)
    $options->marks = question_display_options::HIDDEN;

$PAGE->set_url($attempt->review_url());
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($ddtaquiz->get_main_block()->get_name()));
$PAGE->set_heading($course->fullname);

$timestart = $attempt->get_start_time();
$timefinish = $attempt->get_finish_time();
$timetaken = format_time($timefinish - $timestart);

$summarydata = array();
$summarydata['startedon'] = array(
    'title'   => get_string('startedon', 'ddtaquiz'),
    'content' => userdate($timestart));

$summarydata['state'] = array(
    'title'   => get_string('attemptstate', 'ddtaquiz'),
    'content' => $attempt->get_state());

$summarydata['completedon'] = array(
    'title'   => get_string('completedon', 'ddtaquiz'),
    'content' => userdate($timefinish));

$summarydata['timetaken'] = array(
    'title'   => get_string('timetaken', 'ddtaquiz'),
    'content' => $timetaken);

$a = new stdClass();
$a->grade = $ddtaquiz->format_grade($attempt->get_sumgrades());
$a->maxgrade = $ddtaquiz->format_grade($ddtaquiz->get_maxgrade());
$summarydata['marks'] = array(
    'title'   => get_string('marks', 'ddtaquiz'),
    'content' => get_string('outofshort', 'ddtaquiz', $a));

$output = $PAGE->get_renderer('mod_ddtaquiz');

$feedback = feedback::get_feedback($attempt->get_quiz());

echo $OUTPUT->header();

echo $output->review_page($attempt, $options, $summarydata, $feedback);

echo $OUTPUT->footer();
