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
 * This script lists all the instances of ddtaquiz in a particular course
 *
 * @package    mod_ddtaquiz
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/ddtaquiz/index.php', array('id'=>$id));
if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => $coursecontext
);
$event = \mod_ddtaquiz\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strddtaquizzes = get_string("modulenameplural", "ddtaquiz");
$PAGE->navbar->add($strddtaquizzes);
$PAGE->set_title($strddtaquizzes);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strddtaquizzes, 2);

// Get all the appropriate data.
if (!$ddtaquizzes = get_all_instances_in_course("ddtaquiz", $course)) {
    notice(get_string('thereareno', 'moodle', $strddtaquizzes), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the feedback header.
$showfeedback = false;
foreach ($ddtaquizzes as $ddtaquiz) {
    if (ddtaquiz_has_feedback($ddtaquiz)) {
        $showfeedback=true;
    }
    if ($showfeedback) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

array_push($headings, get_string('ddtaquizcloses', 'ddtaquiz'));
array_push($align, 'left');

if (course_format_uses_sections($course->format)) {
    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
} else {
    array_unshift($headings, '');
}
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/ddtaquiz:viewreports', $coursecontext)) {
    array_push($headings, get_string('attempts', 'ddtaquiz'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_any_capability(array('mod/ddtaquiz:reviewmyattempts', 'mod/ddtaquiz:attempt'),
        $coursecontext)) {
    array_push($headings, get_string('grade', 'ddtaquiz'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'ddtaquiz'));
        array_push($align, 'left');
    }
    $showing = 'grades';

    $grades = $DB->get_records_sql_menu('
            SELECT qg.ddtaquiz, qg.grade
            FROM {ddtaquiz_grades} qg
            JOIN {ddtaquiz} q ON q.id = qg.ddtaquiz
            WHERE q.course = ? AND qg.userid = ?',
            array($course->id, $USER->id));
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
// Get all closing dates.
$timeclosedates = ddtaquiz_get_user_timeclose($course->id);
foreach ($ddtaquizzes as $ddtaquiz) {
    $cm = get_coursemodule_from_instance('ddtaquiz', $ddtaquiz->id);
    $context = context_module::instance($cm->id);
    $data = array();

    // Section number if necessary.
    $strsection = '';
    if ($ddtaquiz->section != $currentsection) {
        if ($ddtaquiz->section) {
            $strsection = $ddtaquiz->section;
            $strsection = get_section_name($course, $ddtaquiz->section);
        }
        if ($currentsection) {
            $learningtable->data[] = 'hr';
        }
        $currentsection = $ddtaquiz->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$ddtaquiz->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$ddtaquiz->coursemodule\">" .
            format_string($ddtaquiz->name, true) . '</a>';

    // Close date.
    if (($timeclosedates[$ddtaquiz->id]->usertimeclose != 0)) {
        $data[] = userdate($timeclosedates[$ddtaquiz->id]->usertimeclose);
    } else {
        $data[] = get_string('noclose', 'ddtaquiz');
    }

    if ($showing == 'stats') {
        // The $ddtaquiz objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = ddtaquiz_attempt_summary_link_to_reports($ddtaquiz, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        $attempts = ddtaquiz_get_user_attempts($ddtaquiz->id, $USER->id, 'all');
        list($someoptions, $alloptions) = ddtaquiz_get_combined_reviewoptions(
                $ddtaquiz, $attempts);

        $grade = '';
        $feedback = '';
        if ($ddtaquiz->grade && array_key_exists($ddtaquiz->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = ddtaquiz_format_grade($ddtaquiz, $grades[$ddtaquiz->id]);
                $a->maxgrade = ddtaquiz_format_grade($ddtaquiz, $ddtaquiz->grade);
                $grade = get_string('outofshort', 'ddtaquiz', $a);
            }
            if ($alloptions->overallfeedback) {
                $feedback = ddtaquiz_feedback_for_grade($grades[$ddtaquiz->id], $ddtaquiz, $context);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over ddtaquiz instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
