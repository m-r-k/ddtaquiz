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
 * Internal library of functions for module ddtaquiz.
 *
 * All the ddtaquiz specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/blocklib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/conditionlib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/feedbacklib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/attemptlib.php');

/**
 * A class encapsulating a ddta quiz.
 *
 * @copyright  2017 Jan Emrich <jan.emrich@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class ddtaquiz {
    /** @var int the id of this ddta quiz. */
    protected $id = 0;
    /** @var int the course module id for this quiz. */
    protected $cmid = 0;
    /** @var block the main block of this quiz. */
    protected $mainblock = null;
    /** @var int the id of the main block of this ddta quiz. */
    protected $mainblockid = 0;
    /** @var int the method used for grading. 0: one attempt, 1: best attempt, 2: last attempt */
    protected $grademethod = 0;

    /** @var int if to display grades to user or not */
    protected $showgrade = 1;
    /** @var int the total sum of the max grades of the main questions instances
     * (that is without any questions inside blocks) in the ddta quiz */
    protected $maxgrade = 0;

    protected $directfeedback=0;

    protected  $timing = null;

    protected $name;

    // Constructor =============================================================

    /**
     * Constructor assuming we already have the necessary data loaded.
     * @param int $id the id of this quiz.
     * @param int $cmid the course module id for this quiz.
     * @param $name
     * @param int $mainblockid the id of the main block of this ddta quiz.
     * @param int $grademethod the method used for grading.
     * @param int $maxgrade the best attainable grade of this quiz.
     * @param int $directfeedback the best attainable grade of this quiz.
     * @param $showgrade
     *
     * @throws
     */
    public function __construct($id, $cmid, $name, $mainblockid, $grademethod, $maxgrade,$directfeedback,$showgrade) {
        $this->id = $id;
        $this->name = $name;
        $this->cmid = $cmid;
        $this->mainblock = null;
        $this->mainblockid = $mainblockid;
        $this->grademethod = $grademethod;
        $this->maxgrade = $maxgrade;
        $this->showgrade = $showgrade;
        $this->directfeedback = $directfeedback;
        $this->timing = ddtaquiz_timing::create();
    }

    /**
     * Static function to get a quiz object from a quiz id.
     *
     * @param int $quizid the id of this ddta quiz.
     * @return ddtaquiz the new ddtaquiz object.
     *
     * @throws
     */
    public static function load($quizid) {
        global $DB;

        $quiz = $DB->get_record('ddtaquiz', array('id' => $quizid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('ddtaquiz', $quizid, $quiz->course, false, MUST_EXIST);

        $ddtaquiz =  new ddtaquiz($quizid, $cm->id, $quiz->name, $quiz->mainblock, $quiz->grademethod, $quiz->maxgrade,$quiz->directfeedback,$quiz->showgrade);
        $ddtaquiz->timing->enable($quiz->timelimit, $quiz->overduehandling,$quiz->graceperiod);

        if($ddtaquiz->get_main_block()->get_name() != $ddtaquiz->get_name()) {
            $ddtaquiz->get_main_block()->set_name($ddtaquiz->get_name());

        }

        return $ddtaquiz;
    }

    /**
     * Updates quiz name
     * @param string $name
     * @throws Exception
     * @throws dml_exception
     */
    public function update_name( $name){
        if(empty($name))
            throw new Exception('Quiz name cannot be empty');
        global $DB;

        $quiz = $DB->get_record('ddtaquiz', array('id' => $this->id), '*', MUST_EXIST);
        $quiz->name = $name;

        $DB->update_record('ddtaquiz', $quiz);
    }
    /**
     * Get the main block of the quiz.
     *
     * @return block the main block of the quiz.
     */
    public function get_main_block() {
        if (!$this->mainblock) {
            $this->mainblock = block::load($this, $this->mainblockid);
            $this->enumerate();
        }
        return $this->mainblock;
    }

    /**
     * Gets the id of this quiz.
     *
     * @return int the id of this quiz.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Gets the course module id of this quiz.
     *
     * @return int the course module id of this quiz.
     */
    public function get_cmid() {
        return $this->cmid;
    }

    /**
     * Gets the course id.
     *
     * @return int the course id.
     */
    public function get_course_id() {
        list($course, $cm) = get_course_and_cm_from_cmid($this->cmid);
        return $course->id;
    }

    /**
     * Returns the maximum grade for this quiz.
     *
     * @return int the maximum grade.
     */
    public function get_maxgrade() {
        return $this->maxgrade;
    }

    /**
     * Get the context of this module.
     *
     * @return context_module the context for this module.
     */
    public function get_context() {
        return context_module::instance($this->cmid);
    }

    /**
     * Get the name of the quiz.
     *
     * @return string the name.
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Returns the number of slots in this quiz.
     *
     * @return int the number of slots used by this quiz.
     */
    public function get_slotcount() {
        $this->enumerate();
        return $this->get_main_block()->get_slotcount();
    }

    /**
     * Returns the next slot that a student should work on for a certain attempt.
     *
     * @param attempt $attempt the attempt that  the student is currently working on.
     * @return null|int the number of the next slot that the student should work on or null, if no such slot exists.
     */
    public function next_slot(attempt $attempt) {
        $this->enumerate();
        return $this->get_main_block()->next_slot($attempt);
    }

    /**
     * Enumerates the questions of this quiz.
     */
    protected function enumerate() {
        $this->get_main_block()->enumerate(1);
    }

    /**
     * Returns the slot number for an element id.
     *
     * @param int $elementid the id of the element.
     * @return null|int the slot number of the element or null, if the element can not be found.
     */
    public function get_slot_for_element($elementid) {
        $this->enumerate();
        return $this->get_main_block()->get_slot_for_element($elementid);
    }

    /**
     * Adds the questions of this quiz to a question usage.
     *
     * @param question_usage_by_activity $quba the question usage to add the questions to.
     */
    public function add_questions_to_quba(question_usage_by_activity $quba) {
        $this->get_main_block()->add_questions_to_quba($quba);
    }

    /**
     * Returns all questions of this quiz.
     *
     * @return array the block_elements representing the questions.
     */
    public function get_questions() {
        return $this->get_main_block()->get_questions();
    }

    /**
     * Returns all elements of this quiz.
     *
     * @return array the block_elements representing the elements.
     */
    public function get_elements() {
        return $this->get_main_block()->get_elements();
    }

    /**
     * Updates the maximum grade.
     */
    public function update_maxgrade() {
        global $DB;

        $grade = 0;
        foreach ($this->get_main_block()->get_children() as $child) {
            if ($child->is_question()) {
                $question = question_bank::load_question($child->get_element()->id, false);
                $mark = $question->defaultmark;
                $grade += $mark;
            }
        }

        if ($grade != $this->maxgrade) {
            $record = new stdClass();
            $record->id = $this->id;
            $record->maxgrade = $grade;
            $DB->update_record('ddtaquiz', $record);
            $this->maxgrade = $grade;
        }
    }

    /**
     * Save the overall grade for a user at a quiz to the ddtaquiz_grades table
     *
     * @return bool Indicates success or failure.
     */
    public function save_best_grade() {
        global $DB, $USER;

        $quiz = $DB->get_record('ddtaquiz', array('id' => $this->get_id()), '*', MUST_EXIST);
        $userid = $USER->id;

        // Get all the attempts made by the user.
        $attempts = attempt::get_user_attempts($this->get_id(), $userid, 'finished');

        // Calculate the best grade.
        // TODO: wie die beste Note berechnen?
        if ($this->grademethod == 0) {
            $bestgrade = end($attempts)->get_sumgrades();
        } else if ($this->grademethod == 1) {
            $max = 0;
            foreach ($attempts as $attempt) {
                $thisgrade = $attempt->get_sumgrades();
                if ($thisgrade > $max) {
                    $max = $thisgrade;
                }
            }
            $bestgrade = $max;
        } else if ($this->grademethod == 2) {
            $bestgrade = end($attempts)->get_sumgrades();
        } else {
            $bestgrade = end($attempts)->get_sumgrades();
        }
        $bestgrade = $bestgrade * $quiz->grade / $this->get_maxgrade();

        // Save the best grade in the database.
        if ($grade = $DB->get_record('ddtaquiz_grades',
                array('quiz' => $quiz->id, 'userid' => $userid))) {
            $grade->grade = $bestgrade;
            $grade->timemodified = time();
            $DB->update_record('ddtaquiz_grades', $grade);

        } else {
            $grade = new stdClass();
            $grade->quiz = $quiz->id;
            $grade->userid = $userid;
            $grade->grade = $bestgrade;
            $grade->timemodified = time();
            $DB->insert_record('ddtaquiz_grades', $grade);
        }

        ddtaquiz_update_grades($quiz, $userid);
    }

    /**
     * Round a grade to the correct number of decimal places, and format it for display.
     *
     * @param float $grade The grade to round.
     * @return float
     */
    public function format_grade($grade) {
        return format_float($grade, $this->get_grade_format());
    }

    /**
     * Determine the correct number of decimal places required to format a grade.
     *
     * @return integer
     */
    protected function get_grade_format() {
        return 2;
    }

    /**
     * Gets the number of attempts for this quiz.
     *
     * @return int the number of attempts.
     */
    public function get_num_attempts() {
        global $DB;
        return $DB->count_records('ddtaquiz_attempts', array('quiz' => $this->id));
    }

    /**
     * Checks if the quiz has any attempts, that are not a preview.
     *
     * @return boolean wether the quiz has attempts, that are not a preview.
     */
    public function has_attempts() {
        global $DB;
        $count = $DB->count_records('ddtaquiz_attempts', array('quiz' => $this->id, 'preview' => 0));
        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the grading method used by this quiz.
     *
     * @return int 0 for one attempt, 1 for best attempt, 2 for last attempt.
     */
    public function get_grademethod() {
        return $this->grademethod;
    }

    /**
     * Is a student allowed to try this quiz multiple times?
     *
     * @return bool true if the quiz may be taken multiple times by one student.
     */
    public function multiple_attempts_allowed() {
        return $this->grademethod == 1 || $this->grademethod == 2;
    }

    /**
     * Returns true if grades are to be shown to user, false otherwise
     * @return bool
     */
    public function show_grades(){
        return $this->showgrade == 1;
    }

    public function showDirectFeedback(){
        return $this->directfeedback == 1;
    }

    public function get_graceperiod(){
        return $this->timing->get_graceperiod();
    }

    public function get_overduehandling(){
        return $this->timing->get_overduehandling();
    }
}

class ddtaquiz_timing {
    private $timelimit;
    private $overduehandling;
    private $graceperiod;

    public const AUTOBANDON = 'autoabandon';
    public const AUTOSUBMIT = 'autosubmit';
    public const GRACEPERIOD = 'graceperiod';
    /**
     * ddtaquiz_timing constructor.
     * @param $timelimit
     * @param $overduehandling
     * @param $graceperiod
     */
    private function __construct($timelimit, $overduehandling, $graceperiod)
    {
        $this->timelimit = $timelimit;
        $this->overduehandling = $overduehandling;
        $this->graceperiod = $graceperiod;
    }

    public static function create():self{
        return new ddtaquiz_timing(0,self::AUTOSUBMIT,0);
    }

    /**
     * @param $timelimit
     * @param $overduehandling
     * @param $graceperiod
     * @throws Exception
     */
    public function enable($timelimit, $overduehandling, $graceperiod){
        if($timelimit > 1){
            switch ($overduehandling){
                case self::GRACEPERIOD : {
                    if($graceperiod > 60){
                        $this->timelimit = $timelimit;
                        $this->overduehandling = $overduehandling;
                        $this->graceperiod = $graceperiod;
                        break;
                    }else{
                        throw new Exception('Grace period must be greater then 1 minute' . $overduehandling);
                    }
                }

                case self::AUTOSUBMIT :
                case self::AUTOBANDON : {
                    $this->timelimit = $timelimit;
                    $this->overduehandling = $overduehandling;
                    $this->graceperiod = 0;
                    break;
                }
                default : {
                    throw new Exception('The selected timing mode is not available');
                    break;
                }
            }
        }else{
            $this->disable();
        }
    }

    /**
     *
     */
    public function disable(){
        $this->timelimit = 0;
        $this->overduehandling = self::AUTOSUBMIT;
        $this->graceperiod = 0;
    }

    /**
     * @return bool
     */
    public function enabled():bool{
        return $this->timelimit > 0;
    }

    /**
     * @return mixed
     */
    public function get_timelimit()
    {
        return $this->timelimit;
    }

    /**
     * @return mixed
     */
    public function get_overduehandling()
    {
        return $this->overduehandling;
    }

    /**
     * @return mixed
     */
    public function get_graceperiod()
    {
        return $this->graceperiod;
    }



}

/**
 * @return array string => lang string the options for handling overdue quiz
 *      attempts.
 */
function ddtaquiz_get_overdue_handling_options() {
    return array(
        'autosubmit'  => 'Open attempts are submitted automatically',
        'graceperiod' => 'There is a grace period when open attempts can be submitted, but no more questions answered',
        'autoabandon' => 'Attempts must be submitted before time expires, or they are not counted',
    );
}
