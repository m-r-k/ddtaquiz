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
 * Back-end code for handling data about quizzes.
 *
 * There are classes for loading all the information about a quiz and attempts.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A class encapsulating a block and the questions it contains, and making the information available to scripts like view.php.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class block {
    /** @var int the id of the block. */
    protected $id = 0;
    /** @var ddtaquiz the quiz, this block belongs to. */
    protected $quiz = null;
    /** @var string the name of the block. */
    protected $name = '';
    /**
     * @var array of {@link block_element}, that are contained in this block.
     * Do NOT use directly. Use {@link get_children()} instead. This is due to possible lazy loading of the children.
     */
    protected $children = null;
    /** @var condition the condition of this block. */
    protected $condition = null;
    /** @var int the slotnumber of the first question in this block. */
    protected $startingslot = 0;
    /** @var int the number of slots in this block. */
    protected $slotcount = 0;

    // Constructor =============================================================
    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param int $id the id of the block.
     * @param ddtaquiz $quiz the id of the quiz, this block belongs to.
     * @param string $name the name of the block.
     * @param array $children an array of block_element representing the parts of this block.
     * @param condition $condition the condition under which this block should
     */
    public function __construct($id, ddtaquiz $quiz, $name, $children, condition $condition) {
        $this->id = $id;
        $this->quiz = $quiz;
        $this->name = $name;
        $this->children = $children;
        $this->condition = $condition;
    }

    /**
     * Static function to get a block object from a block id.
     *
     * @param ddtaquiz $quiz the quiz, this block belongs to.
     * @param int $blockid the block id.
     * @return block the new block object.
     */
    public static function load(ddtaquiz $quiz, $blockid) {
        global $DB;

        $block = $DB->get_record('ddtaquiz_block', array('id' => $blockid), '*', MUST_EXIST);
        $condition = condition::load($block->conditionid);

        return new block($blockid, $quiz, $block->name, null, $condition);
    }

    /**
     * Static function to create a new block in the database.
     *
     * @param ddtaquiz $quiz the quiz this block belongs to.
     * @param string $name the name of the block.
     * @return block the new block object.
     */
    public static function create(ddtaquiz $quiz, $name) {
        global $DB;

        $condition = condition::create();
        $block = new stdClass();
        $block->name = $name;
        $block->conditionid = $condition->get_id();
        $blockid = $DB->insert_record('ddtaquiz_block', $block);

        return new block($blockid, $quiz, $block->name, null, $condition);
    }

    /**
     * Returns the id of the block.
     *
     * @return int the id of this block.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Checks whether this is the main block of the quiz.
     *
     * @return bool true if this is the main block of the quiz.
     */
    public function is_main_block() {
        return $this->id == $this->quiz->get_main_block()->get_id();
    }

    /**
     * Returns the quiz of the block.
     *
     * @return ddtaquiz the quiz, this block belongs to.
     */
    public function get_quiz() {
        return $this->quiz;
    }

    /**
     * Returns the name of the block.
     *
     * @return string the name of this block.
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Sets the name of the block.
     *
     * @param string $name new name of the block.
     */
    public function set_name(string $name) {

        if(empty($name))
            throw new Exception('Block name cannot be empty');

        global $DB;

        $this->name = $name;

        $record = new stdClass();
        $record->id = $this->id;
        $record->name = $name;
        $DB->update_record('ddtaquiz_block', $record);
    }

    /**
     * Returns the children block.
     *
     * @return array an array of {@link block_element}, which represents the children of this block.
     */
    public function get_children() {
        $this->load_children();
        return $this->children;
    }

    /**
     * Loads the children for the block.
     *
     * @return block_element the new block object.
     */
    protected function load_children() {
        global $DB;

        // If the children are already loaded we dont need to do anything.
        if ($this->children !== null) {
            return;
        }

        $children = $DB->get_records('ddtaquiz_qinstance', array('blockid' => $this->id), 'slot', 'id');

        $this->children = array_map(function($id) {
            return block_element::load($this->quiz, $id->id);
        },
        array_values($children));
    }

    /**
     * Removes the child with the given ddtaquiz_qinstance id.
     *
     * @param int $id the id of the child to remove.
     */
    public function remove_child($id) {
        if (!$this->get_quiz()->has_attempts()) {
            global $DB;

            $DB->delete_records('ddtaquiz_qinstance', array('id' => $id));

            // Necessary because now the loaded children information is outdated.
            $this->children = null;
        }
    }

    /**
     * Checks whether the block or subblock has any questions.
     *
     * @return bool true if there are questions in this block.
     */
    public function has_questions() {
        foreach ($this->get_children() as $element) {
            if ($element->is_question()) {
                return true;
            } else if ($element->is_block() && $element->get_element()->has_questions()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adds the questions of this block to a question usage.
     *
     * @param question_usage_by_activity $quba the question usage to add the questions to.
     */
    public function add_questions_to_quba(question_usage_by_activity $quba) {
        foreach ($this->get_children() as $element) {
            $element->add_questions_to_quba($quba);
        }
    }

    /**
     * Returns all questions of this block and its descendants.
     *
     * @return array the block_elements representing the questions.
     */
    public function get_questions() {
        $questions = array();
        foreach ($this->get_children() as $child) {
            if ($child->is_question()) {
                array_push($questions, $child);
            } else if ($child->is_block()) {
                $questions = array_merge($questions, $child->get_element()->get_questions());
            }
        }
        return $questions;
    }

    /**
     * Returns all blocks of this block and its descendants.
     *
     * @return array the block_elements representing the blocks.
     */
    public function get_blocks() {
        $blocks = array();
        foreach ($this->get_children() as $child) {
            if ($child->is_block()) {
                array_push($blocks, $child);
                $blocks = array_merge($blocks, $child->get_element()->get_blocks());
            }
        }
        return $blocks;
    }

    /**
     * Returns all elements of this block and its descendants.
     *
     * @return array the block_elements representing the elements.
     */
    public function get_elements() {
        $elements = array();
        foreach ($this->get_children() as $child) {
            array_push($elements, $child);
            if ($child->is_block()) {
                $elements = array_merge($elements, $child->get_element()->get_elements());
            }
        }
        return $elements;
    }

    /**
     * Returns the id of the parent block or false, if this block has no parent block.
     *
     * @return bool|int the the id of the parent block or false.
     */
    public function get_parentid() {
        // If this is the main block, there is no parent block.
        if ($this->is_main_block()) {
            return false;
        } else {
            // Top down search in the block-tree to find the parent.
            return $this->quiz->get_main_block()->search_parent($this->id)->get_id();
        }
        return false;
    }

    /**
     * Finds the parent of a block.
     *
     * @param int $childid the id of the child to find the parent for.
     * @return bool|block the parent block or fals, if the parent can not be found.
     */
    protected function search_parent($childid) {
        foreach ($this->get_children() as $element) {
            if ($element->is_block()) {
                $block = $element->get_element();
                if ($block->get_id() == $childid) {
                    return $this;
                }
                if ($parent = $block->search_parent($childid)) {
                    return $parent;
                }
            }
        }
        return false;
    }

    /**
     * Gets the condition under which this block should be shown to a student.
     *
     * @return condition the condition under which to show this block.
     */
    public function get_condition() {
        return $this->condition;
    }

    /**
     * Returns the number of the last slot in this block.
     *
     * @return int the number of the last slot in this block.
     */
    public function get_last_slot() {
        return $this->startingslot + $this->slotcount - 1;
    }

    /**
     * Adds a new question to the block.
     *
     * @param int $questionid the id of the question to be added.
     */
    public function add_question($questionid) {
        if (!$this->get_quiz()->has_attempts()) {
            global $DB;

            $qinstance = new stdClass();
            $qinstance->blockid = $this->id;
            $qinstance->blockelement = $questionid;
            $qinstance->type = 0;
            $qinstance->grade = 0;
            $qinstance->slot = count($this->get_children());

            $id = $DB->insert_record('ddtaquiz_qinstance', $qinstance);

            $this->load_children();
            array_push($this->children, block_element::load($this->quiz, $id));
        }
    }

    /**
     * Adds a new subblock to the block.
     *
     * @param block $block the block to be added as a subblock.
     */
    public function add_subblock(block $block) {
        if (!$this->get_quiz()->has_attempts()) {
            global $DB;

            $qinstance = new stdClass();
            $qinstance->blockid = $this->id;
            $qinstance->blockelement = $block->get_id();
            $qinstance->type = 1;
            $qinstance->grade = 0;
            $qinstance->slot = count($this->get_children());

            $id = $DB->insert_record('ddtaquiz_qinstance', $qinstance);

            $this->load_children();
            array_push($this->children, block_element::load($this->quiz, $id));
        }
    }

    /**
     * Returns all block_elements that can be used for a condition on this block.
     *
     * @return array the block_elements that can be used for a condition.
     */
    public function get_condition_candidates() {
        return $this->get_previous_questions();
    }

    /**
     * Returns all questions that might be asked ahead of this block. Used to find adequate questions for use in conditions.
     *
     * @return array the block_elements of the questions ahead of this block.
     */
    protected function get_previous_questions() {
        $parent = $this->quiz->get_main_block()->search_parent($this->id);
        $thisblockelement = null;
        foreach ($parent->get_children() as $element) {
            if ($element->is_block() && $element->get_element()->get_id() == $this->id) {
                $thisblockelement = $element;
                break;
            }
        }
        if (is_null($thisblockelement)) {
            return array();
        }

        $questions = $this->quiz->get_questions();
        //slot for element return the current slot for the block, this means that the number of previous questions is
        // block slot - 1
        $count = $this->quiz->get_slot_for_element($thisblockelement->get_id()) - 1;
        return array_slice($questions, 0, $count, true);
    }

    /**
     * Returns the number of slots in this block. Requires a prior call to enumerate.
     *
     * @return int the number of slots used by this block.
     */
    public function get_slotcount() {
        return $this->slotcount;
    }

    /**
     * Returns the slot number for an element id. Requires a prior call to enumerate.
     *
     * @param int $elementid the id of the element.
     * @return null|int the slot number of the element or null, if the element can not be found.
     */
    public function get_slot_for_element($elementid) {
        $slot = $this->startingslot;
        foreach ($this->get_children() as $child) {
            if ($child->get_id() == $elementid) {
                return $slot;
            }
            if ($child->is_question()) {
                $slot++;
            } else if ($child->is_block()) {
                $block = $child->get_element();
                $childslot = $block->get_slot_for_element($elementid);
                if (is_null($childslot)) {
                    $slot += $block->get_slotcount();
                } else {
                    return $childslot;
                }
            } else {
                debugging('Unsupported element type');
            }
        }
        return null;
    }

    /**
     * Enumerates the questions in this block.
     *
     * @param int $startingslot the slotnumber to start counting at.
     * @return int hte number of slots used by this block.
     */
    public function enumerate($startingslot) {
        $this->startingslot = $startingslot;
        $count = 0;
        foreach ($this->get_children() as $element) {
            if ($element->is_question()) {
                $count += 1;
            } else if ($element->is_block()) {
                $count += $element->get_element()->enumerate($startingslot + $count);
            }
        }
        $this->slotcount = $count;
        return $count;
    }

    /**
     * Returns the next slot that a student should work on for a certain attempt.
     *
     * @param attempt $attempt the attempt that  the student is currently working on.
     * @return null|int the number of the next slot that the student should work on or null, if no such slot exists.
     */
    public function next_slot(attempt $attempt) {
        $currentslot = $attempt->get_current_slot();
        if ($currentslot >= $this->get_last_slot() || !$this->get_condition()->is_fullfilled($attempt)) {
            return null;
        }
        $slot = $this->startingslot;
        foreach ($this->get_children() as $child) {
            if ($child->is_question()) {
                if ($currentslot < $slot) {
                    return $slot;
                }
                $slot += 1;
            } else if ($child->is_block()) {
                $block = $child->get_element();
                $childslot = $block->next_slot($attempt);
                if (!is_null($childslot)) {
                    return $childslot;
                }
                $slot += $block->get_slotcount();
            }
        }
        return null;
    }

    /**
     * Updates the elements of this block to match a given order.
     *
     * @param array $order an array holding the ids of the block_elements of this block in the desired order.
     */
    public function update_order($order) {
        if (!$this->get_quiz()->has_attempts()) {
            foreach ($this->get_children() as $child) {
                for ($i = 0; $i < count($order); $i++) {
                    if ($child->get_id() == $order[$i]) {
                        $child->update_slot($i);
                    }
                }
            }
        }
    }

    /**
     * Returns the achieved grade for this block in a certain attempt.
     *
     * @param attempt $attempt the attempt for which to return the grade_grade.
     * @return int the achieved grade in the attempt.
     */
    public function get_grade(attempt $attempt) {
        $sum = 0;
        foreach ($this->get_children() as $child) {
            $grade = $child->get_grade($attempt);
            if (is_null($grade)) {
                $grade = 0;
            }
            $sum += $grade;
        }
        return $sum;
    }

    /**
     * Returns the maximum attainable grade for this block.
     *
     * @return int the maximum attainable grade.
     */
    public function get_maxgrade() {
        $sum = 0;
        foreach ($this->get_children() as $child) {
            if ($child->is_question()) {
                $question = question_bank::load_question($child->get_element()->id);
                $sum += $question->defaultmark;
            } else if ($child->is_block()) {
                $sum += $child->get_element()->get_maxgrade();
            }
        }
        return $sum;
    }
}


/**
 * A class encapsulating a block element, which is either a question or another block.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class block_element {
    /** @var int the id of the block_element. */
    protected $id = 0;
    /** @var ddtaquiz the quiz, this element belongs to. */
    protected $quiz = null;
    /** @var int the type of the block_element: 0 = question, 1 = block. */
    protected $type = 0;
    /** @var int the id of the element referenced. */
    protected $elementid = 0;
    /** @var object the {@link block} or question, this element refers to. */
    protected $element = null;
    /** @var int the slot of this element. */
    protected $slot = 0;

    // Constructor =============================================================
    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param int $id the id of the block_elem.
     * @param ddtaquiz $quiz the quiz this reference belongs to.
     * @param int $type the type of this block_element.
     * @param int $elementid the id of the element referenced.
     * @param object $element the element referenced by this block.
     * @param int $slot the slot of this element.
     */
    public function __construct($id, ddtaquiz $quiz, $type, $elementid, $element, $slot) {
        $this->id = $id;
        $this->quiz = $quiz;
        $this->type = $type;
        $this->elementid = $elementid;
        $this->element = $element;
        $this->slot = $slot;
    }

    /**
     * Static function to get a block_element object from its id.
     *
     * @param ddtaquiz $quiz the quiz this reference belongs to.
     * @param int $blockelementid the blockelement id.
     * @return block the new block object.
     */
    public static function load(ddtaquiz $quiz, $blockelementid) {
        global $DB;

        $questioninstance = $DB->get_record('ddtaquiz_qinstance', array('id' => $blockelementid), '*', IGNORE_MISSING);

        $element = null;
        if (!$questioninstance) {
            return null;
        } else if ($questioninstance->type == 0) {
            $element = $DB->get_record('question', array('id' => $questioninstance->blockelement), '*', MUST_EXIST);
        } else if ($questioninstance->type == 1) {
            $element = block::load($quiz, $questioninstance->blockelement);
        } else {
            return null;
        }
        return new block_element($blockelementid, $quiz, (int)$questioninstance->type,
            (int)$questioninstance->blockelement, $element, $questioninstance->slot);
    }

    /**
     * Returns the id of the qinstance database row.
     *
     * @return int the row id.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Returns the quiz this block element belongs to.
     *
     * @return ddtaquiz the quiz.
     */
    public function get_quiz() {
        return $this->quiz;
    }

    /**
     * Return whether this element is a question.
     *
     * @return bool whether this element is a question.
     */
    public function is_question() {
        return $this->type === 0;
    }

    /**
     * Return whether this element is a block.
     *
     * @return bool whether this element is a block.
     */
    public function is_block() {
        return $this->type === 1;
    }

    /**
     * Return the element.
     *
     * @return object the element.
     */
    public function get_element() {
        return $this->element;
    }

    /**
     * Updates this element to reside at a new slot.
     *
     * @param int $slot the new slot for this element.
     */
    public function update_slot($slot) {
        if ($slot != $this->slot) {
            global $DB;

            $record = new stdClass();
            $record->id = $this->id;
            $record->slot = $slot;

            $DB->update_record('ddtaquiz_qinstance', $record);
        }
    }

    /**
     * Returns the achieved grade for this element in a certain attempt.
     *
     * @param attempt $attempt the attempt for which to return the grade_grade.
     * @return null|int the achieved grade in the attempt or null, if it has no (complete) mark yet.
     */
    public function get_grade(attempt $attempt) {
        if ($this->is_question()) {
            $slot = $this->quiz->get_slot_for_element($this->id);
            return $attempt->get_grade_at_slot($slot);
        } else if ($this->is_block()) {
            return $this->element->get_grade($attempt);
        } else {
            debugging('Unsupported element type: ' . $this->type);
            return 0;
        }
    }

    /**
     * Returns the name of the element.
     * The format is: #. name
     *
     * @return string The name of the element.
     */
    public function get_name() {
        if ($this->is_question()) {
            //return $this->quiz->get_slot_for_element($this->id) . '. ' . $this->element->name;
            return $this->element->name;
        }
        if ($this->is_block()) {
            //return $this->quiz->get_slot_for_element($this->id) . '. ' . $this->element->get_name();
            return $this->element->get_name();
        }
    }

    /**
     * Checks whether the element can be edited.
     *
     * @return bool True if it may be edited, false otherwise.
     */
    public function may_edit() {
        if ($this->is_question()) {
            $question = $this->element;
            return !empty($question->id) &&
            (question_has_capability_on($question, 'edit', $question->category) ||
                question_has_capability_on($question, 'move', $question->category));
        }
        if ($this->is_block()) {
            return true;
        }
    }

    /**
     * Checks whether the element can be viewed.
     *
     * @return bool True if it may be viewed, false otherwise.
     */
    public function may_view() {
        if ($this->is_question()) {
            $question = $this->element;
            return !empty($question->id) &&
            question_has_capability_on($question, 'view', $question->category);
        }
        if ($this->is_block()) {
            return true;
        }
    }

    /**
     * Get a URL for the edit page of this element.
     *
     * @param array $params paramters to use for the url.
     * @return \moodle_url the edit URL of the element.
     */
    public function get_edit_url(array $params) {
        global $CFG;

        if ($this->is_question()) {
            $questionparams = array_merge($params, array('id' => $this->element->id));
            return new moodle_url("$CFG->wwwroot/question/question.php", $questionparams);
        }
        if ($this->is_block()) {
            $blockparams = array_merge($params, array('bid' => $this->element->get_id()));
            unset($blockparams['returnurl']);
            return new moodle_url('edit.php', $blockparams);
        }
    }

    /**
     * Adds the question(s) of this element to a question usage.
     *
     * @param question_usage_by_activity $quba the question usage to add the questions to.
     */
    public function add_questions_to_quba(question_usage_by_activity $quba) {
        if ($this->is_question()) {
            $question = question_bank::load_question($this->get_element()->id, false);
            $quba->add_question($question);
        } else if ($this->is_block()) {
            $this->get_element()->add_questions_to_quba($quba);
        }
    }
}