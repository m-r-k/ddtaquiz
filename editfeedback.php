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
 * Displays a page to edit a feedback block.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/locallib.php');
require_once($CFG->dirroot.'/question/editlib.php');

$blockid = required_param('bid', PARAM_INT);
$save = optional_param('save', 0, PARAM_INT);
$domain = optional_param('domain', 0, PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) = question_edit_setup('editq',
    '/mod/ddtaquiz/editfeedback.php', true);

// Check login.
require_login($cm->course, false, $cm);

require_capability('mod/ddtaquiz:manage', $contexts->lowest());

$ddtaquiz = ddtaquiz::load($quiz->id);
$feedbackblock = feedback_block::load($blockid, $ddtaquiz);

$thispageurl->param('bid', $blockid);

$PAGE->set_url($thispageurl);

if ($save) {
    $name = required_param('blockname', PARAM_TEXT);
    $feedbackblock->set_name($name);

    $feedbacktext = optional_param('feedbacktext', '', PARAM_RAW);
    if(isset($_POST['usesquestions']))
        $uses = $_POST['usesquestions'];
    else
        $uses = [];

    $feedbackblock->update($name, $feedbacktext, $uses);

    // Condition.
    if ($domain) {
        try {
            if (array_key_exists('domainname', $_POST)) {
                $feedbackblock->get_condition()->set_name($_POST['domainname']);
            }
            if (array_key_exists('domaingrade', $_POST)) {
                $feedbackblock->get_condition()->set_grade($_POST['domaingrade']);
            }
            if (array_key_exists('domaingrade2', $_POST)) {
                $feedbackblock->get_condition()->set_grade2($_POST['domaingrade2']);
            }
            if (array_key_exists('domainreplace', $_POST)) {
                $feedbackblock->get_condition()->set_replace($_POST['domainreplace']);
            }
        } catch (Exception $e) {
            $_SESSION['edit-error'] .= \mod_ddtaquiz\output\ddtaquiz_bootstrap_render::createAlert('danger', $e->getMessage());
        }
    } else {
        try {
            if (array_key_exists('conditionparts', $_POST)) {
                $feedbackblock->get_condition()->updateSingleParts($_POST['conditionparts']);
            }else{
                $feedbackblock->get_condition()->updateSingleParts([]);// delete when non existent

            }
            if (array_key_exists('conditionMQParts', $_POST)) {
                $feedbackblock->get_condition()->updateMQParts($_POST['conditionMQParts']);
            }else{
                $feedbackblock->get_condition()->updateMQParts([]); // delete when non existent
            }
            $useand = optional_param('use_and', null, PARAM_INT);
            if (!is_null($useand)) {
                $feedbackblock->get_condition()->set_use_and($useand);
            }
        } catch (Exception $e) {
            $_SESSION['edit-error'] .= \mod_ddtaquiz\output\ddtaquiz_bootstrap_render::createAlert('danger', $e->getMessage());
        }
    }
    if (optional_param('done', 0, PARAM_INT)) {
        $nexturl = new moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $cmid));
        redirect($nexturl);
    }
}

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('editingfeedbackx', 'ddtaquiz', $feedbackblock->get_name()));

$output = $PAGE->get_renderer('mod_ddtaquiz', 'edit');

echo $OUTPUT->header();

echo $output->edit_feedback_page($_SESSION['edit-error'], $feedbackblock, $thispageurl);
$_SESSION['edit-error'] = '';
echo $OUTPUT->footer();