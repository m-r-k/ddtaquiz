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
 * Displays a page to edit a ddta quiz.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

$blockid = optional_param('bid', 0, PARAM_INT);
$addquestion = optional_param('addquestion', 0, PARAM_INT);
$save = optional_param('save', 0, PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) = question_edit_setup('editq', '/mod/ddtaquiz/edit.php', true);

// Check login.
require_login($cm->course, false, $cm);

require_capability('mod/ddtaquiz:manage', $contexts->lowest());

// Trigger event.
$params = array(
    'courseid' => $cm->course,
    'context' => $contexts->lowest(),
    'other' => array(
        'quizid' => $quiz->id
    )
);
$event = \mod_ddtaquiz\event\edit_page_viewed::create($params);
$event->trigger();

// If no block id was passed, we default to editing the main block of the quiz.
if (!$blockid) {
    $blockid = $quiz->mainblock;
}

$thispageurl->param('bid', $blockid);

$PAGE->set_url($thispageurl);

$ddtaquiz = ddtaquiz::load($quiz->id);
$block = block::load($ddtaquiz, $blockid);
$feedback = feedback::get_feedback($ddtaquiz);
$errorOutput = '';

if ($save) {

    try{

        // Save the name.
        $name = required_param('blockname', PARAM_TEXT);
        $block->set_name($name);

        if($block->is_main_block())
            $block->get_quiz()->update_name($name);


        // Save the condition.
        if (array_key_exists('conditionparts', $_POST)) {
            $block->get_condition()->updateSingleParts($_POST['conditionparts']);
        }else{
            $block->get_condition()->updateSingleParts([]);

        }
        if (array_key_exists('conditionMQParts', $_POST)) {

            $block->get_condition()->updateMQParts($_POST['conditionMQParts']);
        }else{
            $block->get_condition()->updateMQParts([]);

        }
        $useand = optional_param('use_and', null, PARAM_INT);
        if (!is_null($useand)) {
            $block->get_condition()->set_use_and($useand);
        }

        $domains = [];
        foreach ($_POST as $key => $value) {
            if(substr($key, 0, 6) == "domain") {
                $domain = substr($key, 7);
                $domain = explode("-", $domain);
                if (array_key_exists($domain[0], $domains)) {
                    $domain[1] = $domain[1].",".$domains[$domain[0]];
                }
                $domains[$domain[0]] = $domain[1];
            }
        }
        global $DB;
        foreach ($domains as $qKey => $qDomain) {
            $DB->set_field("ddtaquiz_qinstance", "domains", $qDomain, ["id" => $qKey]);
        }
    }catch(Exception $e){
        $errorOutput .= \mod_ddtaquiz\output\ddtaquiz_bootstrap_render::createAlert('danger',$e->getMessage());
    }
    // Update the order of the elements.
    $order = optional_param_array('elementsorder', array(), PARAM_INT);
    $block->update_order($order);

    // Update the maximum grade of the quiz in case it changed.
    $ddtaquiz->update_maxgrade();

    // Take different actions, depending on which submit button was clicked.
    if (optional_param('done', 0, PARAM_INT)) {
        if ($parentid = $block->get_parentid()) {
            $nexturl = new moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $cmid, 'bid' => $parentid));
        } else {
            $nexturl = new moodle_url('/mod/ddtaquiz/view.php', array('id' => $cmid));
        }
    } else if ($delete = optional_param('delete', 0, PARAM_INT)) {
        $block->remove_child($delete);
        $nexturl = $thispageurl;
    } else if ($feedbackdelete = optional_param('feedbackdelete', 0, PARAM_INT)) {
        $feedback->remove_block($feedbackdelete);
        $nexturl = $thispageurl;
    } else if ($edit = optional_param('edit', 0, PARAM_INT)) {
        $element = block_element::load($ddtaquiz, $edit);
        $elementparams = array('cmid' => $cmid, 'returnurl' => $thispageurl->out_as_local_url(false));
        $nexturl = $element->get_edit_url($elementparams);
    } else if ($feedbackedit = optional_param('feedbackedit', 0, PARAM_INT)) {
        $feedbackblock = feedback_block::load($feedbackedit, $ddtaquiz);
        $nexturl = new moodle_url('/mod/ddtaquiz/editfeedback.php',
            array('cmid' => $cmid, 'bid' => $feedbackedit));
    } else if ($questionid = optional_param('addfromquestionbank', 0, PARAM_INT)) {
        $block->add_question($questionid);
        $nexturl = $thispageurl;
    } else if (optional_param('add', false, PARAM_BOOL)) {
        // Add selected questions to the current quiz.
        $rawdata = (array) data_submitted();
        foreach ($rawdata as $key => $value) { // Parse input for question ids.
            if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
                $key = $matches[1];
                $block->add_question($key);
            }
        }
        $nexturl = $thispageurl;
    } else if (optional_param('addnewblock', 0, PARAM_INT)) {
        $newblock = block::create($ddtaquiz, get_string('blockname', 'ddtaquiz'));
        $block->add_subblock($newblock);
        $nexturl = new moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $cmid, 'bid' => $newblock->get_id()));
    } else if ($qtype = optional_param('qtype', null, PARAM_TEXT)) {
        $nexturl = new moodle_url('/question/question.php', array(
            'category' => question_make_default_categories($contexts->all())->id,
            'courseid' => $PAGE->course->id,
            'cmid' => $cmid,
            'qtype' => $qtype,
            'returnurl' => $thispageurl->out_as_local_url(false),
            'appendqnumstring' => 'addquestion'
        ));
    } else if (optional_param('addfeedback', 0, PARAM_INT)) {
        $feedbackblock = feedback_block::create($ddtaquiz, get_string('feedbackblockdefaultname', 'ddtaquiz'));
        $nexturl = new moodle_url('/mod/ddtaquiz/editfeedback.php',
            array('cmid' => $cmid, 'bid' => $feedbackblock->get_id()));
    } else {
        $nexturl = new moodle_url('/mod/ddtaquiz/view.php', array('id' => $cmid));
    }
    //TODO: should i leave it as session variable
    $_SESSION['edit-error'] = $errorOutput;
    redirect($nexturl);
}

if ($addquestion) {
    $block->add_question($addquestion);
}

$PAGE->set_pagelayout('incourse');
if ($block->is_main_block()) {
    $PAGE->set_title(get_string('editingquizx', 'ddtaquiz', format_string($quiz->name)));
} else {
    $PAGE->set_title(get_string('editingblockx', 'ddtaquiz', format_string($block->get_name())));
}

$errorOutput = $_SESSION['edit-error'];
$_SESSION['edit-error'] = '';

$output = $PAGE->get_renderer('mod_ddtaquiz', 'edit');
echo $OUTPUT->header();
echo $output->edit_page($errorOutput, $block, $thispageurl, $pagevars, $feedback);

echo $OUTPUT->footer();