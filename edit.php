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
 * Page to edit ddtaquizzes
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the ddtaquiz does not already have student attempts
 * The left column lists all questions that have been added to the current ddtaquiz.
 * The lecturer can add questions from the right hand list to the ddtaquiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a ddtaquiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the ddtaquiz
 * add          Adds several selected questions to the ddtaquiz
 * addrandom    Adds a certain number of random questions to the ddtaquiz
 * repaginate   Re-paginates the ddtaquiz
 * delete       Removes a question from the ddtaquiz
 * savechanges  Saves the order and grades for questions in the ddtaquiz
 *
 * @package    mod_ddtaquiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $ddtaquiz, $pagevars) =
        question_edit_setup('editq', '/mod/ddtaquiz/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$ddtaquizhasattempts = ddtaquiz_has_attempts($ddtaquiz->id);

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $ddtaquiz->course), '*', MUST_EXIST);
$ddtaquizobj = new ddtaquiz($ddtaquiz, $cm, $course);
$structure = $ddtaquizobj->get_structure();

// You need mod/ddtaquiz:manage in addition to question capabilities to access this page.
require_capability('mod/ddtaquiz:manage', $contexts->lowest());

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'ddtaquizid' => $ddtaquiz->id
    )
);
$event = \mod_ddtaquiz\event\edit_page_viewed::create($params);
$event->trigger();

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the ddtaquiz.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $ddtaquiz->questionsperpage, PARAM_INT);
    ddtaquiz_repaginate_questions($ddtaquiz->id, $questionsperpage );
    ddtaquiz_delete_previews($ddtaquiz);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current ddtaquiz.
    $structure->check_can_be_edited();
    ddtaquiz_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    ddtaquiz_add_ddtaquiz_question($addquestion, $ddtaquiz, $addonpage);
    ddtaquiz_delete_previews($ddtaquiz);
    ddtaquiz_update_sumgrades($ddtaquiz);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current ddtaquiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            ddtaquiz_require_question_use($key);
            ddtaquiz_add_ddtaquiz_question($key, $ddtaquiz, $addonpage);
        }
    }
    ddtaquiz_delete_previews($ddtaquiz);
    ddtaquiz_update_sumgrades($ddtaquiz);
    redirect($afteractionurl);
}

if ($addsectionatpage = optional_param('addsectionatpage', false, PARAM_INT)) {
    // Add a section to the ddtaquiz.
    $structure->check_can_be_edited();
    $structure->add_section_heading($addsectionatpage);
    ddtaquiz_delete_previews($ddtaquiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the ddtaquiz.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    ddtaquiz_add_random_questions($ddtaquiz, $addonpage, $categoryid, $randomcount, $recurse);

    ddtaquiz_delete_previews($ddtaquiz);
    ddtaquiz_update_sumgrades($ddtaquiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', '', PARAM_RAW_TRIMMED), true);
    if (is_float($maxgrade) && $maxgrade >= 0) {
        ddtaquiz_set_grade($maxgrade, $ddtaquiz);
        ddtaquiz_update_all_final_grades($ddtaquiz);
        ddtaquiz_update_grades($ddtaquiz, 0, true);
    }

    redirect($afteractionurl);
}

// Get the question bank view.
$questionbank = new mod_ddtaquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $ddtaquiz);
$questionbank->set_ddtaquiz_has_attempts($ddtaquizhasattempts);
$questionbank->process_actions($thispageurl, $cm);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-ddtaquiz-edit');

$output = $PAGE->get_renderer('mod_ddtaquiz', 'edit');

$PAGE->set_title(get_string('editingddtaquizx', 'ddtaquiz', format_string($ddtaquiz->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_ddtaquiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$ddtaquizeditconfig = new stdClass();
$ddtaquizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$ddtaquizeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {ddtaquiz_slots}
     WHERE ddtaquizid = ?", array($ddtaquiz->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $ddtaquizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('ddtaquiz_edit_config', $ddtaquizeditconfig);
$PAGE->requires->js('/question/qengine.js');

// Questions wrapper start.
echo html_writer::start_tag('div', array('class' => 'mod-ddtaquiz-edit-content'));

echo $output->edit_page($ddtaquizobj, $structure, $contexts, $thispageurl, $pagevars);

// Questions wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
