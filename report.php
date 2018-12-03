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
 * Displays a report for a ddta quiz.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

$id = required_param('id', PARAM_INT);
$mode = required_param('mode', PARAM_ALPHA);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'ddtaquiz');

require_login($course, false, $cm);

$ddtaquiz = ddtaquiz::load($cm->instance);
$context = $ddtaquiz->get_context();

require_capability('mod/ddtaquiz:viewreports', $context);

$url = new moodle_url('/mod/ddtaquiz/report.php', array('id' => $cm->id));
$url->param('mode', $mode);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

// Collect the necessary.

$PAGE->set_title($ddtaquiz->get_name());
$PAGE->set_heading($course->fullname);

//TODO: get modes to display the grade/response overview
if ($mode == 'responses') {
    $report = new mod_ddtaquiz\report\responses();
} else if($mode='combined') {
    $report = new mod_ddtaquiz\report\combined();
}else{
    // ... $mode = 'overview' as default.
    $report = new mod_ddtaquiz\report\overview();
}

if ($report) {
    $report->display($cm, $course, $ddtaquiz);
}

echo $OUTPUT->footer();

// Trigger event.
$params = array(
    'context' => $context,
    'other' => array(
        'quizid' => $ddtaquiz->get_id(),
        'reportname' => $mode
    )
);
$event = \mod_ddtaquiz\event\report_viewed::create($params);
$event->trigger();