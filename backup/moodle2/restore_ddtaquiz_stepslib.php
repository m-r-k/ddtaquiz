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
 * Define all the restore steps that will be used by the restore_ddtaquiz_activity_task
 *
 * @package   mod_ddtaquiz
 * @category  backup
 * @copyright 2018 Jan Emrich <jan.emrich@stud.tu-darmstadt.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one ddtaquiz activity
 *
 * @package   mod_ddtaquiz
 * @category  backup
 * @copyright 2018 Jan Emrich <jan.emrich@stud.tu-darmstadt.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_ddtaquiz_activity_structure_step extends restore_questions_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('ddtaquiz', '/activity/ddtaquiz');

        if ($userinfo) {
            $paths[] = new restore_path_element('grade', '/activity/ddtaquiz/grades/grade');

            // Process the attempt data.
            $quizattempt = new restore_path_element('attempt', '/activity/ddtaquiz/attempts/attempt');
            $paths[] = $quizattempt;

            // Add states and sessions.
            $this->add_question_usages($quizattempt, $paths);
        }
        $paths[] = new restore_path_element('block', '/activity/ddtaquiz/blocks/block');
        $paths[] = new restore_path_element('block_element_question', '/activity/ddtaquiz/block_elements/block_element_question');
        $paths[] = new restore_path_element('block_element_block', '/activity/ddtaquiz/block_elements/block_element_block');
        $paths[] = new restore_path_element('condition', '/activity/ddtaquiz/conditions/condition');
        $paths[] = new restore_path_element('condition_part', '/activity/ddtaquiz/condition_parts/condition_part');
        $paths[] = new restore_path_element('feedback_block', '/activity/ddtaquiz/feedback_blocks/feedback_block');
        $paths[] = new restore_path_element('feedback_use',
            '/activity/ddtaquiz/feedback_blocks/feedback_block/feedback_uses/feedback_use');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     */
    protected function process_ddtaquiz($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $oldmainblock = $data->mainblock;
        $data->course = $this->get_courseid();

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }

        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }

        if ($data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Create the ddtaquiz instance.
        $mainblock = new stdClass();
        $mainblock->name = $data->name;
        $newmainblock = $DB->insert_record('ddtaquiz_block', $mainblock);

        $data->mainblock = $newmainblock;

        $newitemid = $DB->insert_record('ddtaquiz', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('block', $oldmainblock, $newmainblock);
    }

    protected function process_grade($data) {
        global $DB;

        $data = (object) $data;

        $data->quiz = $this->get_new_parentid('ddtaquiz');

        $newitemid = $DB->insert_record('ddtaquiz_grades', $data);
    }

    protected function process_attempt($data) {
        global $DB;

        $data = (object) $data;

        $data->quiz = $this->get_new_parentid('ddtaquiz');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // The data is actually inserted into the database later in inform_new_usage_id.
        $this->currentquizattempt = clone($data);
    }

    protected function process_condition($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $newitemid = $DB->insert_record('ddtaquiz_condition', $data);
        $this->set_mapping('condition', $oldid, $newitemid);
    }

    protected function process_block($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        if (!is_null($this->get_mappingid('block', $data->id, null))) {
            return;
        }

        $data->conditionid = $this->get_mappingid('condition', $data->conditionid);
        $newitemid = $DB->insert_record('ddtaquiz_block', $data);
        $this->set_mapping('block', $oldid, $newitemid);
    }

    protected function process_block_element_question($data) {
        global $DB;

        $userinfo = $this->get_setting_value('userinfo');
        $data = (object) $data;
        $oldid = $data->id;

        $data->blockid = $this->get_mappingid('block', $data->blockid);
        $data->blockelement = $this->get_mappingid('question', $data->blockelement);

        $newitemid = $DB->insert_record('ddtaquiz_qinstance', $data);
        $this->set_mapping('block_element', $oldid, $newitemid);
    }

    protected function process_block_element_block($data) {
        global $DB;

        $userinfo = $this->get_setting_value('userinfo');
        $data = (object) $data;
        $oldid = $data->id;

        $data->blockid = $this->get_mappingid('block', $data->blockid);
        $data->blockelement = $this->get_mappingid('block', $data->blockelement);

        $newitemid = $DB->insert_record('ddtaquiz_qinstance', $data);
        $this->set_mapping('block_element', $oldid, $newitemid);
    }

    protected function process_condition_part($data) {
        global $DB;

        $data = (object) $data;
        $data->conditionid = $this->get_mappingid('condition', $data->conditionid);
        $data->on_qinstance = $this->get_mappingid('block_element', $data->on_qinstance);

        $newitemid = $DB->insert_record('ddtaquiz_condition_part', $data);
    }

    protected function process_feedback_block($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->quizid = $this->get_new_parentid('ddtaquiz');
        $data->conditionid = $this->get_mappingid('condition', $data->conditionid);

        $newitemid = $DB->insert_record('ddtaquiz_feedback_block', $data);
        $this->set_mapping('feedback_block', $oldid, $newitemid);
    }

    protected function process_feedback_use($data) {
        global $DB;
        $feedbackuse = new restore_path_element('feedback_use', array('id'),
                array('feedbackblockid', 'questioninstanceid'));

        $data = (object) $data;
        $oldid = $data->id;

        $data->feedbackblockid = $this->get_new_parentid('feedback_block');
        $data->questioninstanceid = $this->get_mappingid('block_element', $data->questioninstanceid);

        $newitemid = $DB->insert_record('ddtaquiz_feedback_uses', $data);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Restore any files belonging to responses.
        foreach (question_engine::get_all_response_file_areas() as $filearea) {
            $this->add_related_files('question', $filearea, 'question_attempt_step');
        }
        // Add ddtaquiz related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_ddtaquiz', 'intro', null);
    }

    protected function inform_new_usage_id($newusageid) {
        global $DB;

        $data = $this->currentquizattempt;

        $oldid = $data->id;
        $data->quba = $newusageid;

        $newitemid = $DB->insert_record('ddtaquiz_attempts', $data);
    }
}