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
 * Back-end code for handling data about specialized feedback.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A class encapsulating the specialized feedback of a ddtaquiz.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class feedback {
    /** @var array the feedback blocks of this feedback. */
    protected $feedbackblocks = null;
    /** @var ddtaquiz $quiz the quiz this feedback belongs to. */
    protected $quiz = null;

    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param ddtaquiz $quiz the quiz the feedback belongs to.
     */
    public function __construct($quiz) {
        $this->quiz = $quiz;
    }

    /**
     * Gets the specialized feedback for a ddtaquiz.
     *
     * @param ddtaquiz $quiz the ddtaquiz to get the feedback for.
     * @return feedback the feedback for this quiz.
     */
    public static function get_feedback(ddtaquiz $quiz) {
        return new feedback($quiz);
    }

    /**
     * Gets the corresponding ddtaquiz for the feedback.
     *
     * @return ddtaquiz the corresponding quiz
     */
    public function get_quiz() {
        return $this->quiz;
    }

    /**
     * Returns the feedback blocks of this feedback.
     * @return array
     * @throws dml_exception
     */
    public function get_blocks() {
        if (is_null($this->feedbackblocks)) {
            global $DB;

            $records = $DB->get_records('ddtaquiz_feedback_block', array('quizid' => $this->quiz->get_id(), 'domainfeedback' => 0));
            $blocks = array_map(function ($block) {
                return feedback_block::load($block->id, $this->quiz);
            }, $records);

            $this->feedbackblocks = $blocks;
        }
        return $this->feedbackblocks;
    }

    /**
     * Checks whether specialized feedback exist for a block element.
     * @param block_element $blockelement
     * @param $attempt
     * @return bool
     * @throws dml_exception
     */
    public function has_specialized_feedback(block_element $blockelement, $attempt) {
        /** @var feedback_block $block */
        foreach ($this->get_blocks() as $block) {
            foreach ($block->get_used_question_instances() as $feedback_used_question) {
                if ($feedback_used_question->getBlockElement()->get_id() == $blockelement->get_id() && $block->get_condition()->is_fullfilled($attempt)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns the specialized feedback to be displayed in turn of the feedback for a blockelement.
     * @param block_element $blockelement
     * @param attempt $attempt
     * @return array
     * @throws dml_exception
     */
    public function get_specialized_feedback_at_element(block_element $blockelement, attempt $attempt) {
        $ret = array();
        /** @var feedback_block $block */
        foreach ($this->get_blocks() as $block) {
            $feedback_used_questions = $block->get_used_question_instances();
            if (count($feedback_used_questions) < 1) {
                continue;
            }
            /** @var feedback_used_question $feedback_used_question */
            $feedback_used_question = array_values($feedback_used_questions)[0];
            if ($block->get_condition()->is_fullfilled($attempt) &&
                $feedback_used_question->getBlockElement()->get_id() == $blockelement->get_id()) {
                    array_push($ret, new specialized_feedback($block));
            }
        }
        return $ret;
    }

    /**
     * Removes a feedbackblock from this feedback.
     *
     * @param int $id the id of the block to remove.
     * @throws dml_exception
     */
    public function remove_block($id) {
        global $DB;

        $DB->delete_records('ddtaquiz_feedback_block', array('id' => $id));

        $this->feedbackblocks = null;
    }

    /**
     * Finds the feedback block where the element is the first part of the uses.
     *
     * @param block_element $elem the block element.
     * @param attempt $attempt the attempt for which to check.
     * @return null|feedback_block the feedback block or null.
     * @throws dml_exception
     */
    public function search_uses($elem, $attempt) {
        foreach ($this->get_blocks() as $block) {
            if (!$block->get_condition()->is_fullfilled($attempt)) {
                continue;
            }
            $usedqinstances = $block->get_used_question_instances();
            if (count($usedqinstances) < 1) {
                continue;
            }
            /** @var feedback_used_question $first */
            $first = array_values($usedqinstances)[0];
            if ($first->getBlockElement()->get_id() == $elem->get_id()) {
                return $block;
            }
        }
        return null;
    }
}

/**
 * A class encapsulating a specialized feedback block.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class feedback_block {
    /** @var int the id of the feedback block. */
    protected $id = 0;
    /** @var ddtaquiz the quiz, this block belongs to. */
    protected $quiz = null;
    /** @var string the name of this feedback. */
    protected $name = '';
    /** @var condition the condition under which to use this feedback instead of the standard feedback. */
    protected $condition = null;
    /** @var string the feedbacktext. */
    protected $feedbacktext = '';
    /** @var array the ids of the question instances for which the feedback is replaced by this block. */
    protected $uses = null;

    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param int $id the id of the feedback block.
     * @param ddtaquiz $quiz the id of the quiz, this block belongs to.
     * @param string $name the name of this feedback.
     * @param condition $condition the condition under which to use this feedback instead of the standard feedback.
     * @param string $feedbacktext the feedbacktext.
     */
    public function __construct($id, $quiz, $name, $condition, $feedbacktext) {
        $this->id = $id;
        $this->quiz = $quiz;
        $this->name = $name;
        $this->condition = $condition;
        $this->feedbacktext = $feedbacktext;
    }

    /**
     * Static function to get a feedback block object from an id.
     *
     * @param int $blockid the feedback block id.
     * @param ddtaquiz $quiz the id of the quiz, this block belongs to.
     * @return feedback_block the new feedback block object.
     * @throws dml_exception
     */
    public static function load($blockid, ddtaquiz $quiz) {
        global $DB;

        $feedback = $DB->get_record('ddtaquiz_feedback_block', array('id' => $blockid));

        if ($feedback->domainfeedback) {
            $condition = domain_condition::load($feedback->conditionid);
        } else {
            $condition = condition::load($feedback->conditionid);
        }
        return new feedback_block($blockid, $quiz, $feedback->name, $condition, $feedback->feedbacktext);
    }

    /**
     * Creates a new feedback block in the database.
     *
     * @param ddtaquiz $quiz the quiz this feedbackblock belongs to.
     * @param string $name the name of the feedback block.
     * @param int $domain set "1" for domain feedback block
     * @return feedback_block the created feedback block.
     * @throws dml_exception
     */
    public static function create(ddtaquiz $quiz, $name, $domain = 0) {
        global $DB;

        if ($domain) {
            $condition = domain_condition::create();
        } else {
            $condition = condition::create();
        }

        $record = new stdClass();
        $record->name = $name;
        $record->quizid = $quiz->get_id();
        $record->conditionid = $condition->get_id();
        $record->feedbacktext = '';
        $record->domainfeedback = $domain;

        $blockid = $DB->insert_record('ddtaquiz_feedback_block', $record);

        return new feedback_block($blockid, $quiz, $name, $condition, '');
    }

    /**
     * TODO::reduce code
     * Updates the values of this feedback.
     * @param $name
     * @param $feedbacktext
     * @param $usesquestions
     * @throws dml_exception
     */
    public function update($name, $feedbacktext, $usesquestions) {
        global $DB;

        if ($this->name != $name || $this->feedbacktext != $feedbacktext) {
            $record = new stdClass();
            $record->id = $this->id;
            $record->name = $name;
            $record->feedbacktext = $feedbacktext;

            $DB->update_record('ddtaquiz_feedback_block', $record);
        }

        $old = $DB->get_records('ddtaquiz_feedback_uses', array('feedbackblockid' => $this->id), 'id');

        for ($i = 0; $i < max(array(count($usesquestions), count($old))); $i++) {
            if ($i >= count($old)) {
                $usedQuestion = $usesquestions[array_keys($usesquestions)[$i]];
                $record = new stdClass();
                $record->feedbackblockid = $this->id;
                $record->questioninstanceid =(int) $usedQuestion['questionId'];
                $record->letter = (int) $usedQuestion['letter'];

                if(key_exists('shift',$usedQuestion))
                    $shift = 1;
                else
                    $shift = 0;

                $record->shift = $shift;
                $DB->insert_record('ddtaquiz_feedback_uses', $record);
            } else if ($i >= count($usesquestions)) {
                $record = $old[array_keys($old)[$i]];
                $DB->delete_records('ddtaquiz_feedback_uses', array('id' => $record->id));
            } else {
                $record = $old[array_keys($old)[$i]];
                $usedQuestion = $usesquestions[array_keys($usesquestions)[$i]];

                if ($record->questioninstanceid != $usedQuestion['questionId']) {
                    $record->questioninstanceid = (int) $usedQuestion['questionId'];
                }

                $record->letter =(int) $usedQuestion['letter'];
                if(key_exists('shift',$usedQuestion))
                    $shift = 1;
                else
                    $shift = 0;

                $record->shift = $shift;

                $DB->update_record('ddtaquiz_feedback_uses', $record);
            }

        }
    }

    /**
     * Returns the id of the feedbackblock.
     *
     * @return int the id of the feedbackblock.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Gets the name of this feedback.
     *
     * @return string the name.
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Sets the name of the feedbackblock.
     *
     * @param string $name new name of the block.
     * @throws dml_exception
     */
    public function set_name($name) {
        global $DB;

        $this->name = $name;

        $record = new stdClass();
        $record->id = $this->id;
        $record->name = $name;
        $DB->update_record('ddtaquiz_feedback_block', $record);
    }

    /**
     * Gets the condition under which to display this feedback.
     *
     * @return mixed
     */
    public function get_condition() {
        return $this->condition;
    }

    /**
     * Returns the quiz this block belongs to.
     *
     * @return ddtaquiz the quiz this block belongs to.
     */
    public function get_quiz() {
        return $this->quiz;
    }

    /**
     * Gets the feedback text.
     *
     * @return string the feedback text.
     */
    public function get_feedback_text() {
        return $this->feedbacktext;
    }

    /**
     * Returns the block elements of the question instances whos feedback is replaced by this block.
     * @return array|feedback_used_question[]
     * @throws dml_exception
     */
    public function get_used_question_instances() {
        if (!$this->uses) {
            global $DB;
            $records = $DB->get_records('ddtaquiz_feedback_uses', array('feedbackblockid' => $this->id), 'id');
            if (is_null($records)) {
                $records = array();
            }
            $records = array_map(function ($obj) {
                $used_question = feedback_used_question::load($this->quiz, $obj->questioninstanceid,$obj->shift,$obj->letter);
                if ($used_question instanceof feedback_used_question) {
                    return $used_question;
                } else {
                    return $obj;
                }
            }, $records);

            // Delete references for block_elements that do not exist anymore.
            $records = array_filter($records, function($used_question) {
                if ($used_question instanceof feedback_used_question) {
                    return true;
                } else {
                    $this->remove_uses($used_question->id);
                    return false;
                }
            });
            $this->uses = $records;
        }
        return $this->uses;
    }

    /**
     * @param $letter
     * @return feedback_used_question|mixed|null
     * @throws dml_exception
     */
    public function get_used_question_with_letter($letter){
        foreach ($this->get_used_question_instances() as $feedback_used_question){
            if($feedback_used_question->letterEquals($letter))
                return $feedback_used_question;
        }
        return null;
    }

    /**
     * Adds a question instance to the ones used by this feedback.
     * @param $questioninstanceid
     * @param $shift
     * @param $letter
     * @throws dml_exception
     */
    public function add_question_instance($questioninstanceid,$shift,$letter) {
        global $DB;

        $record = new stdClass();
        $record->feedbackblockid = $this->id;
        $record->questioninstanceid = (int)$questioninstanceid;
        $record->shift = (int)$shift;
        $record->letter = (string)$letter;

        $DB->insert_record('ddtaquiz_feedback_uses', $record);
        $uses = feedback_used_question::load($this->quiz, $record->questioninstanceid,$record->shift,$record->letter);
        array_push($this->uses, $uses);
    }

    /**
     * Adds a question instance to the ones used by this feedback.
     * @param $id
     * @throws dml_exception
     */
    public function remove_uses($id) {
        global $DB;
        $DB->delete_records('ddtaquiz_feedback_uses', array('id' => $id));
        $this->uses = null;
    }

    /**
     * Calculates the adapted grade for the first element in the uses.
     *
     * @return int the adapted grade.
     */
    public function get_adapted_grade() {
        $uses = array_map(function(feedback_used_question $feedback_used_question){
            return $feedback_used_question->getBlockElement();
        },$this->uses);
        $qid = array_shift($uses)->get_element()->id;
        $first = question_bank::load_question($qid, false);
        $mark = $first->defaultmark;
        $sum = 0;

        /** @var block_element $element */
        foreach ($uses as $element) {
            if ($element->is_question()) {
                $question = question_bank::load_question($element->get_element()->id, false);
                $sum += $question->defaultmark;
            } else if ($element->is_block()) {
                $sum += $element->get_element()->get_maxgrade();
            }
        }
        return $mark - $sum;
    }
}

class feedback_used_question{
    protected $blockElement;
    protected $shift = false;
    protected $letter = null;

    /**
     * feedback_used_question constructor.
     * @param block_element $block_element
     * @param $shift
     * @param $letter
     */
    public function __construct(\block_element $block_element,$shift, $letter)
    {
        $this->blockElement = $block_element;
        $this->shift = $shift;
        $this->letter = $letter;
    }

    /**
     * @param $quiz
     * @param $questioninstanceid
     * @param $shift
     * @param $letter
     * @return feedback_used_question|null
     * @throws dml_exception
     */
    public static function load($quiz, $questioninstanceid,$shift,$letter) {
        $blockElement = block_element::load($quiz, $questioninstanceid);
        if($blockElement instanceof block_element)
            return new self($blockElement, $shift,$letter);
        else
            return null;
    }

    /**
     * @return block_element
     */
    public function getBlockElement(): block_element
    {
        return $this->blockElement;
    }

    /**
     * @return bool
     */
    public function isShifted(): bool
    {
        return $this->shift == 1;
    }

    /**
     * @return null
     */
    public function getLetter()
    {
        return chr($this->letter);
    }

    /**
     * @param $letter
     * @return bool
     */
    public function letterEquals($letter){
        return $letter === chr($this->letter);
    }

}
/**
 * A class encapsulating a specialized feedback.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class specialized_feedback {
    /** @var feedback_block the feedback block this feedback is constructed from. */
    protected $feedbackBlock = null;
    /**
     * Constructor.
     *
     * @param feedback_block $block the block to get the specialized feedback from.
     */
    public function __construct(feedback_block $block) {
        $this->feedbackBlock = $block;
    }

    /**
     * @return array
     * @throws dml_exception
     */
    public function get_parts() {
        $ret = array();

        $raw = $this->feedbackBlock->get_feedback_text();
        $parts = explode('[[', $raw);

        foreach ($parts as $part) {
            if (substr($part, 1, 2) == ']]') {
                /** @var feedback_used_question $usedQuestion */
                $usedQuestion = $this->feedbackBlock->get_used_question_with_letter(substr($part, 0, 1));
                array_push($ret, $usedQuestion);
                $tmp = substr($part, 3);
                if ($this->is_relevant($tmp)) {
                    array_push($ret, $tmp);
                }
            } else if ($this->is_relevant($part)) {
                array_push($ret, $part);
            }
        }

        return $ret;
    }

    /**
     * @param attempt $attempt
     * @return int|null
     * @throws dml_exception
     */
    public function get_grade(\attempt $attempt){
        /** @var feedback_used_question[] $parts */
        $parts = $this->get_parts();
        $sum = 0;
        foreach ($parts as $part){
            if(!is_string($part))
                $sum += $part->getBlockElement()->get_grade($attempt);
        }
        return $sum;
    }

    /**
     * @return int|null
     * @throws dml_exception
     */
    public function get_maxgrade(){
        /** @var feedback_used_question[] $parts */
        $parts = $this->get_parts();
        $sum = 0;
        foreach ($parts as $part){
            if(!is_string($part))
                $sum += $part->getBlockElement()->get_maxgrade();
        }
        return $sum;
    }

    /**
     * Checks whether this part is relevant for the special feedback or not.
     *
     * @param string $part the part.
     * @return boolean whether this part is relevant or not.
     */
    protected function is_relevant($part) {
        $tmp = trim(str_replace("&nbsp;", "", strip_tags($part)));
        if ($tmp != null) {
            return true;
        } else {
            return false;
        }
    }
}

class domain_feedback extends feedback {

    /**
     * Returns the feedback blocks of this feedback.
     *
     * @return array the feedback_blocks.
     * @throws dml_exception
     */
    public function get_blocks() {
        if (is_null($this->feedbackblocks)) {
            global $DB;

            $records = $DB->get_records('ddtaquiz_feedback_block', array('quizid' => $this->quiz->get_id(), 'domainfeedback' => 1));
            $blocks = array_map(function ($block) {
                return feedback_block::load($block->id, $this->quiz);
            }, $records);

            $this->feedbackblocks = $blocks;
        }
        return $this->feedbackblocks;
    }

    /**
     * Gets the specialized feedback for a ddtaquiz.
     *
     * @param ddtaquiz $quiz the ddtaquiz to get the feedback for.
     * @return feedback the feedback for this quiz.
     */
    public static function get_feedback(ddtaquiz $quiz) {
        return new domain_feedback($quiz);
    }
}