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
 * Back-end code for handling data about quizzes and the current user's attempt.
 *
 * There are classes for loading all the information about a quiz and attempts,
 * and for displaying the navigation panel.
 *
 * @package   mod_ddtaquiz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * This class extends the quiz class to hold data about the state of a particular attempt, in addition to the data about the quiz.
 *
 * @copyright  2017 Jan Emrich
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class attempt {


    /** @var string to identify the in progress state. */
    const IN_PROGRESS = 'inprogress';

    /** @var string to identify the overdue state. */
    const OVERDUE   = 'overdue';

    /** @var string to identify the finished state. */
    const FINISHED = 'finished';

    const ABANDONED = 'abandoned';


    /** @var int the id of this ddtaquiz_attempt. */
    protected $id;

    /** @var question_usage_by_activity the question usage for this quiz attempt. */
    protected $quba;

    /** @var int the quiz this attempt belongs to. */
    protected $quiz;

    /** @var int the user this attempt belongs to. */
    protected $userid;

    /** @var int the number of this attempt */
    protected $attemptnumber;

    /** @var int the current slot of the attempt. */
    protected $currentslot;

    /** @var float the sum of the grades. */
    protected $sumgrades;

    /** @var int time of starting this attempt. */
    protected $timestart;

    /** @var string state of the attempt. */
    protected $state;

    /** @var int time of finishing this attempt. */
    protected $timefinish;

    /** @var boolean preview was previewed. */
    protected $preview;

    protected $timecheckstate;
    protected $timemodified;


    // Constructor =============================================================
    /**
     * Constructor assuming we already have the necessary data loaded.
     *
     * @param int $id the id of this attempt.
     * @param question_usage_by_activity $quba the question_usages_by_activity this attempt belongs to.
     * @param ddtaquiz $quiz the quiz this attempt belongs to.
     * @param int $userid the id of the user this attempt belongs to.
     * @param int $attemptnumber the number of this attempt.
     * @param int $currentslot the current slot of this attempt.
     * @param int $timestart the time the attempt was started.
     * @param string $state the state of the attempt.
     * @param int $timefinish the time the attempt was finished.
     * @param float $sumgrades the sumof the grades.
     * @param boolean $preview attempt is a preview attempt.
     */
    public function __construct($id, question_usage_by_activity $quba, ddtaquiz $quiz,
            $userid, $attemptnumber, $currentslot = 1, $timestart, $state, $timefinish,
            $sumgrades, $preview,$timecheckstate,$timemodified) {
        $this->id = $id;
        $this->quba = $quba;
        $this->quiz = $quiz;
        $this->userid = $userid;
        $this->attemptnumber = $attemptnumber;
        $this->currentslot = $currentslot;
        $this->state = $state;
        $this->timestart = $timestart;
        $this->timefinish = $timefinish;
        $this->sumgrades = $sumgrades;
        $this->preview = $preview;
        $this->timecheckstate = $timecheckstate;
        $this->timemodified = $timemodified;
    }


    /**
     * Static function to get a attempt object from a attempt id.
     *
     * @param int $attemptid the id of this attempt.
     * @return attempt the new attempt object.
     */
    public static function load($attemptid) {
        global $DB;

        $attemptrow = $DB->get_record('ddtaquiz_attempts', array('id' => $attemptid), '*', MUST_EXIST);
        $quba = question_engine::load_questions_usage_by_activity($attemptrow->quba);
        $quiz = ddtaquiz::load($attemptrow->quiz);

        return new attempt($attemptid, $quba, $quiz, $attemptrow->userid, $attemptrow->attempt,
            $attemptrow->currentslot, $attemptrow->timestart, $attemptrow->state,
            $attemptrow->timefinish, $attemptrow->sumgrades, $attemptrow->preview,$attemptrow->timecheckstate,
            $attemptrow->timemodified);
    }

    /**
     * Static function to create a new attempt in the database.
     *
     * @param ddtaquiz $quiz the quiz this attempt belongs to.
     * @param int $userid the id of the user this attempt belongs to.
     * @param boolean $preview attempt is a preview attempt.
     * @return attempt the new attempt object.
     */
    public static function create(ddtaquiz $quiz, $userid, $preview = false) {
        global $DB;

        $quba = self::create_quba($quiz);

        $override = $DB->get_records('ddtaquiz_attempts', array('userid' => $userid, 'preview' => 1));

        $attemptrow = new stdClass();
        $attemptrow->quba = $quba->get_id();
        $attemptrow->quiz = $quiz->get_id();
        $attemptrow->userid = $userid;
        $attemptrow->currentslot = 1;
        $attemptrow->timestart = time();
        $attemptrow->state = self::IN_PROGRESS;
        $attemptrow->timefinish = 0;
        $attemptrow->sumgrades = null;
        $attemptrow->timecheckstate = null;
        $attemptrow->timemodified = $attemptrow->timestart;
        $attemptrow->sumgrades = null;
        $attemptrow->attempt = $DB->count_records('ddtaquiz_attempts',
            array('quiz' => $quiz->get_id(), 'userid' => $userid)) + 1;
        $attemptrow->preview = $preview;

        if (!$override) {
            $attemptid = $DB->insert_record('ddtaquiz_attempts', $attemptrow);
        } else {
            $DB->delete_records('ddtaquiz_attempts', array('userid' => $userid, 'preview' => 1));
            $attemptrow->attempt = $DB->count_records('ddtaquiz_attempts',
                array('quiz' => $quiz->get_id(), 'userid' => $userid)) + 1;
            $attemptid = $DB->insert_record('ddtaquiz_attempts', $attemptrow);

        }

        $quiz->get_cmid();
        // Params used by the events below.
        $params = array(
            'objectid' => $attemptid,
            'relateduserid' => $userid,
            'courseid' => $quiz->get_course_id(),
            'context' => $quiz->get_context()
        );

        // Decide which event we are using.
        /**if ($attempt->preview) { // TODO: preview
                $params['other'] = array(
		        'quizid' => $quizobj->get_quizid()
            );
            $event = \mod_ddtaquiz\event\attempt_preview_started::create($params);
            } else { **/
        $event = \mod_ddtaquiz\event\attempt_started::create($params);

        // }

        // Trigger the event.
        $event->trigger();

        $attempt = new attempt($attemptid, $quba, $quiz, $userid, $attemptrow->attempt,
            $attemptrow->currentslot, $attemptrow->timestart, $attemptrow->state,
            $attemptrow->timefinish, $attemptrow->sumgrades, $preview, $attemptrow->timecheckstate, $attemptrow->timemodified);
        return $attempt;
    }

    // Getters.

    /**
     * Returns the id of the attempt.
     *
     * @return int the id of this attempt.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Returns the quba of this attempt.
     *
     * @return question_usage_by_activity the quba of this attempt.
     */
    public function get_quba() {
        return $this->quba;
    }

    /**
     * Returns the quiz belonging to the attempt.
     *
     * @return ddtaquiz the quiz this attempt belongs to.
     */
    public function get_quiz() {
        return $this->quiz;
    }

    /**
     * Returns the id of the user.
     *
     * @return int the id of the user.
     */
    public function get_userid() {
        return $this->userid;
    }

    /**
     * Returns the number of points achieved at a certain slot in this attempt.
     *
     * @param int $slot the slot to return the grade for.
     * @return null|int the achieved points in this attempt for the slot or null, if it has no mark yet.
     */
    public function get_grade_at_slot($slot) {
        return $this->get_quba()->get_question_mark($slot);
    }

    /**
     * Returns the number of this attempt.
     *
     * @return int the number of this attempt.
     */
    public function get_attempt_number() {
        return $this->attemptnumber;
    }

    /**
     * Returns the start time of the attempt.
     *
     * @return int the start time.
     */
    public function get_start_time() {
        return $this->timestart;
    }

    public function get_timeleft(){
        return $this->get_quiz()->get_timelimit()  - (time() - $this->timestart);
    }
    public function get_graceperiod(){
        return $this->get_quiz()->get_graceperiod();
    }

    /**
     * Returns the state of the attempt.
     *
     * @return string the state.
     */
    public function get_state() {
        return $this->state;
    }

    /**
     * Returns the finish time of the attempt.
     *
     * @return int the finish time.
     */
    public function get_finish_time() {
        return $this->timefinish;
    }

    /**
     * Gets the current slot the student should work on for this attempt.
     *
     * @return int the current slot of this attempt.
     */
    public function get_current_slot() {
        return $this->currentslot;
    }

    /**
     * Gets the sum of the grades of this attempt.
     *
     * @return float the sum of the grades.
     */
    public function get_sumgrades() {
        return $this->sumgrades;
    }

    /**
     * Returns true if this attempt is a preview attempt.
     *
     * @return boolean wether this attempt is a preview attempt.
     */
    public function get_preview() {
        return $this->preview;
    }

    /**
     * Sets the current slot of this attempt.
     *
     * @param int $slot the slot this attempt should be at after this call.
     */
    public function set_current_slot($slot) {
        global $DB;

        $record = new stdClass();
        $record->id = $this->id;
        $record->currentslot = $slot;

        $DB->update_record('ddtaquiz_attempts', $record);

        $this->currentslot = $slot;
    }

    /**
     * Processes the slot.
     *
     * @param int $timenow the current time.
     */
    public function process_slot($timenow) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $quba = $this->get_quba();

        $quba->process_all_actions($timenow);
        $quba->finish_question($this->currentslot, $timenow);

        question_engine::save_questions_usage_by_activity($quba);

        $transaction->allow_commit();

        $this->next_slot();
    }

    /**
     * Checks if this attempt is finished.
     *
     * @return boolean wether this attempt is finished.
     */
    public function check_state_finished() {
        if($this->state == self::FINISHED){
            return true;
        }

        if($this->quiz->timing_activated() && $this->get_timeleft() < 0 && $this->state == self::IN_PROGRESS){
            if($this->quiz->to_abandon()){
                $this->finish_attempt(time(), self::ABANDONED);
            }else{
                $this->finish_attempt(time(), self::OVERDUE);
            }
        }

        if ($this->currentslot > $this->get_quiz()->get_slotcount() && $this->state == self::IN_PROGRESS) {
            $this->finish_attempt(time(), self::FINISHED);
        }
        return $this->currentslot > $this->get_quiz()->get_slotcount();
    }

    /**
     * Process responses during an attempt at a quiz and finish the attempt.
     *
     * @param  int $timenow the current time.
     */
    public function finish_attempt($timenow,$state) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $quba = $this->get_quba();
        if($state == self::ABANDONED)
            $quba->get_question_attempt($this->get_current_slot())->discard_autosaved_step();
        $quba->finish_all_questions($timenow);
        question_engine::save_questions_usage_by_activity($quba);

        $attemptrow = new stdClass();
        $attemptrow->id = $this->get_id();
        $attemptrow->sumgrades = $this->quba->get_total_mark();
        $attemptrow->timefinish = $timenow;
        $attemptrow->state = self::FINISHED;
        $DB->update_record('ddtaquiz_attempts', $attemptrow);

        $this->get_quiz()->save_best_grade();

        // Trigger event.
        $params = array(
            'context' => $this->get_quiz()->get_context(),
            'courseid' => $this->get_quiz()->get_course_id(),
            'objectid' => $this->get_id(),
            'relateduserid' => $this->get_userid(),
            'other' => array(
                // 'submitterid' => CLI_SCRIPT ? null : $USER->id,
                'quizid' => $this->get_quiz()->get_id()
            )
        );

        if($state == self::FINISHED)
            $event = \mod_ddtaquiz\event\attempt_finished::create($params);
        else
            $event = \mod_ddtaquiz\event\attempt_overdue::create($params);
        $event->trigger();

        $transaction->allow_commit();
    }

    /**
     * Updates the grade of this attempt.
     */
    public function update_grade() {
        global $DB;

        $record = new stdClass();
        $record->id = $this->get_id();
        $record->sumgrades = $this->quba->get_total_mark();
        $DB->update_record('ddtaquiz_attempts', $record);
    }

    /**
     * Checks if this attempt is a preview.
     *
     * @return boolean wether this attempt is a preview.
     */
    public function is_preview() {
        return $this->preview;
    }

    /**
     * Determines the next slot based on the conditions of the blocks.
     */
    public function next_slot() {
        if ($this->check_state_finished()) {
            return;
        }
        $nextslot = $this->get_quiz()->next_slot($this);
        if (!is_null($nextslot)) {
            $this->set_current_slot($nextslot);
        } else {
            $this->set_current_slot($this->quiz->get_main_block()->get_slotcount() + 1);
            $this->check_state_finished();
        }
    }

    // URL.

    /**
     * Generates the URL to view this attempt.
     *
     * @return moodle_url the URL of that attempt.
     */
    public function attempt_url() {
        return new moodle_url('/mod/ddtaquiz/attempt.php', array('attempt' => $this->id));
    }

    /**
     * Generates the URL of the review page.
     *
     * @return moodle_url the URL to review this attempt.
     */
    public function review_url() {
        return new moodle_url('/mod/ddtaquiz/review.php', array('attempt' => $this->id));
    }

    /**
     * Get the human-readable name for an attempt state.
     * @param string $state one of the state constants.
     * @return string The lang string to describe that state.
     */
    public static function state_name($state) {
        switch ($state) {
            case self::IN_PROGRESS:
                return get_string('stateinprogress', 'ddtaquiz');
            case self::FINISHED:
                return get_string('statefinished', 'ddtaquiz');
            default:
                throw new coding_exception('Unknown quiz attempt state.');
        }
    }

    /**
     * Returns the attempts of a quiz for a user.
     *
     * @param int $quizid the id of the quiz belonging to this attempt.
     * @param int $userid the id of the user belonging to this attempt.
     * @param string $state the state of the attempt.
     * @return array the attempts of a quiz belonging to a specific user.
     */
    public static function get_user_attempts($quizid, $userid, $state = 'all') {
        global $DB;
        if ($state == 'all') {
            $attemptrows = $DB->get_records('ddtaquiz_attempts', array('quiz' => $quizid, 'userid' => $userid), 'id');
        } else {
            $attemptrows = $DB->get_records('ddtaquiz_attempts',
                array('quiz' => $quizid, 'userid' => $userid, 'state' => $state), 'id');
        }
        $attempts = array_map(function($attempt) {
                            return attempt::load($attempt->id);
        },
                            array_values($attemptrows));
        return $attempts;
    }

    /**
     * Determines wether a user may start a new attempt.
     *
     * @param ddtaquiz $quiz the quiz for which to check.
     * @param int $userid the id of the user wanting to start a new attempt.
     * @return bool true if a new attempt may be started.
     */
    public static function may_start_new_attempt(ddtaquiz $quiz, $userid) {
        $context = $quiz->get_context();
        // Previews may always be started.
        if (has_capability('mod/ddtaquiz:preview', $context)) {
            return true;
        }

        if (has_capability('mod/ddtaquiz:attempt', $context) &&
            (count(self::get_user_attempts($quiz->get_id(), $userid)) == 0 ||
                $quiz->multiple_attempts_allowed())) {
            return true;
        }

        return false;
    }

    /**
     * Creates a new question usage for this attempt.
     *
     * @param ddtaquiz $quiz the quiz to create the usage for.
     * @return question_usage_by_activity the created question usage.
     */
    protected static function create_quba(ddtaquiz $quiz) {
        $quba = question_engine::make_questions_usage_by_activity('mod_ddtaquiz', $quiz->get_context());
        $quba->set_preferred_behaviour('deferredfeedback');
        $quiz->add_questions_to_quba($quba);
        $quba->start_all_questions();
        question_engine::save_questions_usage_by_activity($quba);
        return $quba;
    }
}