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
 * Rest endpoint for ajax editing of ddtaquiz structure.
 *
 * @package   mod_ddtaquiz
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

// Initialise ALL the incoming parameters here, up front.
$ddtaquizid     = required_param('ddtaquizid', PARAM_INT);
$class      = required_param('class', PARAM_ALPHA);
$field      = optional_param('field', '', PARAM_ALPHA);
$instanceid = optional_param('instanceId', 0, PARAM_INT);
$sectionid  = optional_param('sectionId', 0, PARAM_INT);
$previousid = optional_param('previousid', 0, PARAM_INT);
$value      = optional_param('value', 0, PARAM_INT);
$column     = optional_param('column', 0, PARAM_ALPHA);
$id         = optional_param('id', 0, PARAM_INT);
$summary    = optional_param('summary', '', PARAM_RAW);
$sequence   = optional_param('sequence', '', PARAM_SEQUENCE);
$visible    = optional_param('visible', 0, PARAM_INT);
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command.
$maxmark    = optional_param('maxmark', '', PARAM_FLOAT);
$newheading = optional_param('newheading', '', PARAM_TEXT);
$shuffle    = optional_param('newshuffle', 0, PARAM_INT);
$page       = optional_param('page', '', PARAM_INT);
$ids        = optional_param('ids', '', PARAM_SEQUENCE);
$PAGE->set_url('/mod/ddtaquiz/edit-rest.php',
        array('ddtaquizid' => $ddtaquizid, 'class' => $class));

require_sesskey();
$ddtaquiz = $DB->get_record('ddtaquiz', array('id' => $ddtaquizid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('ddtaquiz', $ddtaquiz->id, $ddtaquiz->course);
$course = $DB->get_record('course', array('id' => $ddtaquiz->course), '*', MUST_EXIST);
require_login($course, false, $cm);

$ddtaquizobj = new ddtaquiz($ddtaquiz, $cm, $course);
$structure = $ddtaquizobj->get_structure();
$modcontext = context_module::instance($cm->id);

echo $OUTPUT->header(); // Send headers.

// All these AJAX actions should be logically atomic.
$transaction = $DB->start_delegated_transaction();

// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers,
// so we simulate it with the action URL param.
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

$result = null;

switch($requestmethod) {
    case 'POST':
    case 'GET': // For debugging.
        switch ($class) {
            case 'section':
                $table = 'ddtaquiz_sections';
                $section = $structure->get_section_by_id($id);
                switch ($field) {
                    case 'getsectiontitle':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $result = array('instancesection' => $section->heading);
                        break;
                    case 'updatesectiontitle':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $structure->set_section_heading($id, $newheading);
                        $result = array('instancesection' => format_string($newheading));
                        break;
                    case 'updateshufflequestions':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $structure->set_section_shuffle($id, $shuffle);
                        $result = array('instanceshuffle' => $section->shufflequestions);
                        break;
                }
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        if (!$previousid) {
                            $section = $structure->get_section_by_id($sectionid);
                            if ($section->firstslot > 1) {
                                $previousid = $structure->get_slot_id_for_slot($section->firstslot - 1);
                                $page = $structure->get_page_number_for_slot($section->firstslot);
                            }
                        }
                        $structure->move_slot($id, $previousid, $page);
                        ddtaquiz_delete_previews($ddtaquiz);
                        $result = array('visible' => true);
                        break;

                    case 'getmaxmark':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $slot = $DB->get_record('ddtaquiz_slots', array('id' => $id), '*', MUST_EXIST);
                        $result = array('instancemaxmark' => ddtaquiz_format_question_grade($ddtaquiz, $slot->maxmark));
                        break;

                    case 'updatemaxmark':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        if ($structure->update_slot_maxmark($slot, $maxmark)) {
                            // Grade has really changed.
                            ddtaquiz_delete_previews($ddtaquiz);
                            ddtaquiz_update_sumgrades($ddtaquiz);
                            ddtaquiz_update_all_attempt_sumgrades($ddtaquiz);
                            ddtaquiz_update_all_final_grades($ddtaquiz);
                            ddtaquiz_update_grades($ddtaquiz, 0, true);
                        }
                        $result = array('instancemaxmark' => ddtaquiz_format_question_grade($ddtaquiz, $maxmark),
                                'newsummarks' => ddtaquiz_format_grade($ddtaquiz, $ddtaquiz->sumgrades));
                        break;

                    case 'updatepagebreak':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $slots = $structure->update_page_break($id, $value);
                        $json = array();
                        foreach ($slots as $slot) {
                            $json[$slot->slot] = array('id' => $slot->id, 'slot' => $slot->slot,
                                                            'page' => $slot->page);
                        }
                        $result = array('slots' => $json);
                        break;

                    case 'deletemultiple':
                        require_capability('mod/ddtaquiz:manage', $modcontext);

                        $ids = explode(',', $ids);
                        foreach ($ids as $id) {
                            $slot = $DB->get_record('ddtaquiz_slots', array('ddtaquizid' => $ddtaquiz->id, 'id' => $id),
                                    '*', MUST_EXIST);
                            if (ddtaquiz_has_question_use($ddtaquiz, $slot->slot)) {
                                $structure->remove_slot($slot->slot);
                            }
                        }
                        ddtaquiz_delete_previews($ddtaquiz);
                        ddtaquiz_update_sumgrades($ddtaquiz);

                        $result = array('newsummarks' => ddtaquiz_format_grade($ddtaquiz, $ddtaquiz->sumgrades),
                                'deleted' => true, 'newnumquestions' => $structure->get_question_count());
                        break;

                    case 'updatedependency':
                        require_capability('mod/ddtaquiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        $value = (bool) $value;
                        $structure->update_question_dependency($slot->id, $value);
                        $result = array('requireprevious' => $value);
                        break;
                }
                break;
        }
        break;

    case 'DELETE':
        switch ($class) {
            case 'section':
                require_capability('mod/ddtaquiz:manage', $modcontext);
                $structure->remove_section_heading($id);
                $result = array('deleted' => true);
                break;

            case 'resource':
                require_capability('mod/ddtaquiz:manage', $modcontext);
                if (!$slot = $DB->get_record('ddtaquiz_slots', array('ddtaquizid' => $ddtaquiz->id, 'id' => $id))) {
                    throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
                }
                $structure->remove_slot($slot->slot);
                ddtaquiz_delete_previews($ddtaquiz);
                ddtaquiz_update_sumgrades($ddtaquiz);
                $result = array('newsummarks' => ddtaquiz_format_grade($ddtaquiz, $ddtaquiz->sumgrades),
                            'deleted' => true, 'newnumquestions' => $structure->get_question_count());
                break;
        }
        break;
}

$transaction->allow_commit();
echo json_encode($result);
