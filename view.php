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
 * Prints a particular ddta quiz.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/renderer.php');


$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... ddtaquiz instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('ddtaquiz', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ddtaquiz  = $DB->get_record('ddtaquiz', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $ddtaquiz  = $DB->get_record('ddtaquiz', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $ddtaquiz->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('ddtaquiz', $ddtaquiz->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$event = \mod_ddtaquiz\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $ddtaquiz);
$event->trigger();

$context = context_module::instance($id);
$quiz = ddtaquiz::load($ddtaquiz->id);
$mainblock = $quiz->get_main_block();
$canpreview = has_capability('mod/ddtaquiz:preview', $context);
$canattempt = has_capability('mod/ddtaquiz:attempt', $context);

$canattempt = attempt::may_start_new_attempt($quiz, $USER->id);

$viewobj = new mod_ddtaquiz_view_object();

$viewobj->cmid = $id;
$viewobj->quizhasquestions = $mainblock->has_questions();
$viewobj->preventmessages = array();
$viewobj->canmanage = has_capability('mod/ddtaquiz:manage', $context);
$attempts = attempt::get_user_attempts($ddtaquiz->id, $USER->id);
$viewobj->attempts = $attempts;
$viewobj->numattempts = count($attempts);

$unfinishedattempts = attempt::get_user_attempts($ddtaquiz->id, $USER->id, 'inprogress');
$unfinished = end($unfinishedattempts);

if (!$viewobj->quizhasquestions) {
    $viewobj->buttontext = '';
} else {
    if ($unfinished) {
        $viewobj->unfinishedattempt = $unfinished->get_id();
        if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptquiz', 'ddtaquiz');
        } else if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'ddtaquiz');
        }

    } else {
        if ($canattempt) {
            if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attemptquiznow', 'ddtaquiz');
            } else {
                $viewobj->buttontext = get_string('reattemptquiz', 'ddtaquiz');
            }

        } else if ($canpreview) {
            $viewobj->buttontext = get_string('previewquiznow', 'ddtaquiz');
        }
    }
}

// Print the page header.

$PAGE->set_url('/mod/ddtaquiz/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($ddtaquiz->name));
$PAGE->set_heading(format_string($course->fullname));
$output = $PAGE->get_renderer('mod_ddtaquiz');

// Output starts here.
echo $OUTPUT->header();

echo $output->view_page($quiz, $viewobj);

// Finish the page.
echo $OUTPUT->footer();
