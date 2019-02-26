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
 * Back-end code for handling conditions on blocks and feedback.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * A class encapsulating the condition, under which a block should be shown to a student.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class condition {
    /** @var int the id of this condition. */
    protected $id = 0;
    /** @var array the parts this condition is made from. */
    protected $parts = null;
    /** @var null  */
    protected $mqParts = null;
    /** @var bool whether the parts are connected with and. Otherwise they are connected with or. */
    protected $useand = true;

    // Constructor =============================================================
    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param int $id the id of this condition.
     * @param array $parts the parts this condition is made from.
     * @param bool $useand whether the parts are connected with and. Otherwise they are connected with or.
     */
    public function __construct($id, $parts,$mqParts, $useand) {
        $this->id = $id;
        $this->parts = $parts;
        $this->mqParts = $mqParts;
        $this->useand = $useand;
    }

    /**
     * Loads the condition for one block from the database.
     *
     * @param int $id the id of the block to get the condition for.
     * @return condition the loaded condition.
     */
    public static function load($id) {
        global $DB;

        if ($id) {
            $useand = $DB->get_field('ddtaquiz_condition', 'useand', array('id' => $id), MUST_EXIST);

            $parts = $DB->get_records('ddtaquiz_condition_part', array('conditionid' => $id));
            $mqParts = $DB->get_records('ddtaquiz_mq_condition_part', array('conditionid' => $id));
            $partobjs = array_map(
                function($part) {
                    return new condition_part($part->id, $part->type, $part->on_qinstance, $part->grade);
                },
                array_values($parts)
            );
            $mqPartobjs = array_map(
                function($part) {
                    global $DB;
                    $elements = $DB->get_records('ddtaquiz_mq_questions', array('condition_id' => $part->id));
                    $elements = array_map(
                        function($element) {
                            return $element->question_id;
                        },
                        array_values($elements)
                    );
                    return new \multiquestions_condition_part($part->id, $part->type, $elements, $part->grade);
                },
                array_values($mqParts)
            );
        } else {
            $partobjs = array();
            $mqPartobjs = array();
            $useand = true;
        }
        return new condition($id, $partobjs,$mqPartobjs, $useand);
    }

    /**
     * Inserts a new condition into the database.
     *
     * @return condition the newly created condtion part.
     */
    public static function create() {
        global $DB;

        $record = new stdClass();
        $record->useand = true;

        $id = $DB->insert_record('ddtaquiz_condition', $record);

        return new condition($id, [], [], $record->useand);
    }

    /**
     * Adds a part to this condition.
     *
     * @param int $type the type of this condition.
     * @param int $elementid the id of the element this condition references.
     * @param int $grade the grade this condition is relative to.
     */
    public function add_part($type, $elementid, $grade) {
        $part = condition_part::create($this, $type, $elementid, $grade);
        array_push($this->parts, $part);
    }

    /**
     * @param $type
     * @param $elements
     * @param $grade
     * @throws dml_exception
     */
    public function add_multiquestions_part($type, $elements, $grade){
        $part = multiquestions_condition_part::create($this, $type, $elements, $grade);
        array_push($this->mqParts, $part);
    }

    /**
     * Checks whether this condition is met for a certain attempt.
     *
     * @param object $attempt the attempt to check this part of the condition for.
     * @return bool whether this condition is fullfilled.
     */
    public function is_fullfilled($attempt) {
        if ($this->useand) {
            foreach (array_merge($this->parts,$this->mqParts) as $part) {
                if (!$part->is_fullfilled($attempt)) {
                    return false;
                }
            }
            return true;
        } else {
            foreach (array_merge($this->parts,$this->mqParts) as $part) {
                if ($part->is_fullfilled($attempt)) {
                    return true;
                }
            }
            return false;
        }
    }


    /**
     * Sets how the parts of this condition are connected.
     *
     * @param bool $useand whether the parts of this condition should be connected with and. Or is used otherwise.
     */
    public function set_use_and($useand) {
        if ($this->useand != $useand) {
            global $DB;

            $this->useand = $useand;

            $record = new stdClass();
            $record->id = $this->id;
            $record->useand = $this->useand;
            $DB->update_record('ddtaquiz_condition', $record);
        }
    }

    /**
     * Returns the id of this condition.
     *
     * @return int the id of this condition.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Returns whether the parts are connected with and.
     *
     * @return bool true if the parts are connected with and, false for a connection with or.
     */
    public function get_useand() {
        return $this->useand;
    }

    /**
     * Returns the parts of this condition.
     *
     * @return array the parts of this condition.
     */
    public function get_parts() {
        return $this->parts;
    }

    /**
     * @return null
     */
    public function get_mqParts() {
        return $this->mqParts;
    }

    /**
     * @param $conditionparts
     * @throws Exception
     * @throws dml_exception
     */
    public function updateSingleParts($conditionparts) {
        foreach ($this->parts as $existingpart) {
            $deleted = true;
            foreach ($conditionparts as $part) {
                if (array_key_exists('id', $part) && $part['id'] == $existingpart->get_id()) {
                    $deleted = false;
                    break;
                }
            }
            if ($deleted) {
                global $DB;
                $DB->delete_records('ddtaquiz_condition_part', array('id' => $existingpart->get_id()));
            }
        }
        foreach ($conditionparts as $part) {
            // Update existing condition parts.
            if (array_key_exists('id', $part)) {
                foreach ($this->parts as $existingpart) {
                    if ($existingpart->get_id() == $part['id']) {
                        $existingpart->update($part['type'], $part['question'], $part['points']);
                        break;
                    }
                }
            } else {
                $this->add_part($part['type'], $part['question'], $part['points']);
            }
        }
    }

    /**
     * @param $conditionparts
     * @throws Exception
     * @throws dml_exception
     */
    public function updateMQParts($conditionparts) {
        /** @var multiquestions_condition_part $existingpart */
        foreach ($this->mqParts as $existingpart) {
            //if some part was deleted remove it from database
            $deleted = true;
            foreach ($conditionparts as $part) {
                if (array_key_exists('id', $part) && $part['id'] == $existingpart->get_id()) {
                    $deleted = false;
                    break;
                }
            }
            if ($deleted) {
                global $DB;
                //delete condition questions firstly so conflicts, from foreign keys, can be avoided
                $DB->delete_records('ddtaquiz_mq_questions', array('condition_id' => $existingpart->get_id()));
                // after we delete the condition part
                $DB->delete_records('ddtaquiz_mq_condition_part', array('id' => $existingpart->get_id()));
            }
        }
        foreach ($conditionparts as $part) {
            if(!array_key_exists('questions',$part))
                throw new Exception('You must select at least one question, before adding condition!');

            // Update existing condition parts.
            if (array_key_exists('id', $part)) {
                // Insert new condition parts.
                /** @var multiquestions_condition_part $existingpart */
                foreach ($this->mqParts as $existingpart) {
                    if ($existingpart->get_id() == $part['id']) {
                        $existingpart->update($part['type'], $part['questions'], $part['points']);
                        break;
                    }
                }
            } else { // Insert new condition parts.
                $this->add_multiquestions_part($part['type'], $part['questions'], $part['points']);
            }
        }
    }
}

/**
 * A class encapsulating one part of a condition, under which a block should be shown to a student.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class condition_part {
    // Block_condition type.
    /** */
    const WAS_DISPLAYED     = 0;
    /** condition that student has less points than a set amount */
    const LESS              = 1;
    /** condition that student has less or more points than a set amount */
    const LESS_OR_EQUAL     = 2;
    /** condition that student has more points than a set amount */
    const GREATER           = 3;
    /** condition that student has more or the same points than a set amount */
    const GREATER_OR_EQUAL  = 4;
    /** condition that student has the same points than a set amount */
    const EQUAL             = 5;
    /** condition that student has not the same points than a set amount */
    const NOT_EQUAL         = 6;

    /** @var int the id of the block_condition. */
    protected $id = 0;
    /**
     * @var int the type of the block_condition. One of WAS_DISPLAYED, LESS, LESS_OR_EQUAL,
     * GREATER, GREATER_OR_EQUAL or EQUAL.
     */
    protected $type = 0;
    /** @var int the id of the element this condition references. */
    protected $elementid = 0;
    /** @var int the grade this condition is relative to. */
    protected $grade = 0;

    // Constructor =============================================================
    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param int $id the id of the block_elem.
     * @param int $type the type of this condition.
     * @param int $elementid the id of the element this condition references.
     * @param int $grade the grade this condition is relative to.
     */
    public function __construct($id, $type, $elementid, $grade) {
        $this->id = $id;
        $this->type = $type;
        $this->elementid = $elementid;
        $this->grade = $grade;
    }

    /**
     * Inserts a new condition part into the database.
     *
     * @param condition $condition the condition to add this part to.
     * @param int $type the type of this condition.
     * @param int $elementid the id of the element this condition references.
     * @param int $grade the grade this condition is relative to.
     * @return condition_part the newly created condtion part.
     */
    public static function create(condition $condition, $type, $elementid, $grade) {
        global $DB;

        $record = new stdClass();
        $record->conditionid = $condition->get_id();
        $record->on_qinstance = $elementid;
        $record->type = $type;
        $record->grade = $grade;

        $id = $DB->insert_record('ddtaquiz_condition_part', $record);

        return new condition_part($id, $type, $elementid, $grade);
    }

    /**
     * Checks whether this part of the condition is met for a certain attempt.
     *
     * @param attempt $attempt the attempt to check this part of the condition for.
     * @return bool whether this part of the condition is fullfilled.
     */
    public function is_fullfilled(attempt $attempt) {
        $referencedelement = block_element::load($attempt->get_quiz(), $this->elementid);
        if (is_null($referencedelement)) {
            return false;
        }
        $achievedgrade = $referencedelement->get_grade($attempt);
        if (is_null($achievedgrade)) {
            $achievedgrade = 0;
        }
        switch ($this->type) {
            case self::LESS:
                return $achievedgrade < $this->grade;
            case self::LESS_OR_EQUAL:
                return $achievedgrade <= $this->grade;
            case self::GREATER:
                return $achievedgrade > $this->grade;
            case self::GREATER_OR_EQUAL:
                return $achievedgrade >= $this->grade;
            case self::EQUAL:
                return $achievedgrade == $this->grade;
            case self::NOT_EQUAL:
                return $achievedgrade != $this->grade;
            default:
                debugging('Unsupported condition part type: ' . $this->type);
                return true;
        }
    }

    /**
     * Gets the id of this condition part.
     *
     * @return int the id of this condition part.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Gets the type of this condition part.
     *
     * @return int the type of this condition part.
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Gets the grade of this condition part.
     *
     * @return int the grade of this condition part.
     */
    public function get_grade() {
        return $this->grade;
    }

    /**
     * Gets the element id this condition part is about.
     *
     * @return int the element id.
     */
    public function get_elementid() {
        return $this->elementid;
    }

    /**
     * Updates the part to the new values.
     *
     * @param int $type the new type.
     * @param int $elementid the id of the new element to refer to.
     * @param int $grade the new grade to use for this part.
     */
    public function update($type, $elementid, $grade) {
        if ($this->type != $type || $this->elementid != $elementid || $this->grade != $grade) {
            global $DB;

            $this->type = $type;
            $this->elementid = $elementid;
            $this->grade = $grade;

            $record = new stdClass();
            $record->id = $this->id;
            $record->type = $this->type;
            $record->elementid = $this->elementid;
            $record->grade = $this->grade;

            $DB->update_record('ddtaquiz_condition_part', $record);
        }
    }
}

class multiquestions_condition_part {
    // Block_condition type.
    /** */
    const WAS_DISPLAYED     = 0;
    /** condition that student has less points than a set amount */
    const LESS              = 1;
    /** condition that student has less or more points than a set amount */
    const LESS_OR_EQUAL     = 2;
    /** condition that student has more points than a set amount */
    const GREATER           = 3;
    /** condition that student has more or the same points than a set amount */
    const GREATER_OR_EQUAL  = 4;
    /** condition that student has the same points than a set amount */
    const EQUAL             = 5;
    /** condition that student has not the same points than a set amount */
    const NOT_EQUAL         = 6;

    /** @var int the id of the block_condition. */
    protected $id = 0;
    /**
     * @var int the type of the block_condition. One of WAS_DISPLAYED, LESS, LESS_OR_EQUAL,
     * GREATER, GREATER_OR_EQUAL or EQUAL.
     */
    protected $type = 0;
    /** @var array the ids of the elements this condition references. */
    protected $elements = [];
    /** @var int the grade this condition is relative to. */
    protected $grade = 0;

    // Constructor =============================================================

    /**
     * multiquestions_condition_part constructor.
     * @param $id
     * @param $type
     * @param $elements
     * @param $grade
     */
    public function __construct($id, $type, $elements, $grade) {
        $this->id = $id;
        $this->type = $type;
        $this->elements = $elements;
        $this->grade = $grade;
    }

    /**
     * @param condition $condition
     * @param $type
     * @param $elements
     * @param $grade
     * @return multiquestions_condition_part
     * @throws dml_exception
     */
    public static function create(condition $condition, $type, $elements, $grade) {
        global $DB;

        // conditions of this type consists of id, type and grade
        $record = new stdClass();
        $record->conditionid = $condition->get_id();
        $record->type = $type;
        $record->grade = $grade;

        // the id returned when its inserted into database, will be used for relation tables
        $partId = $DB->insert_record('ddtaquiz_mq_condition_part', $record);
        foreach ($elements as $id){
            // questions consists of conditon id they are related to, and id of the question
            $record = new stdClass();
            $record->condition_id = $partId;
            $record->question_id = $id;
            $DB->insert_record('ddtaquiz_mq_questions', $record);
         }

        return new multiquestions_condition_part($partId, $type, $elements, $grade);
    }

    /**
     * @param attempt $attempt
     * @return bool
     */
    public function is_fullfilled(attempt $attempt) {
        // total grade of questions of interest
        $achievedGrade = 0;
        foreach ($this->elements as $questionId){
            $referencedElement = block_element::load($attempt->get_quiz(), $questionId);
            if (is_null($referencedElement)) {
                continue;
            }
            $achievedGrade += $referencedElement->get_grade($attempt);
        }

        // basing on type return if the condition is fullfilled
        switch ($this->type) {
            case self::LESS:
                return $achievedGrade < $this->grade;
            case self::LESS_OR_EQUAL:
                return $achievedGrade <= $this->grade;
            case self::GREATER:
                return $achievedGrade > $this->grade;
            case self::GREATER_OR_EQUAL:
                return $achievedGrade >= $this->grade;
            case self::EQUAL:
                return $achievedGrade == $this->grade;
            case self::NOT_EQUAL:
                return $achievedGrade != $this->grade;
            default:
                debugging('Unsupported condition part type: ' . $this->type);
                return true;
        }
    }

    /**
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * @return int
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * @return int
     */
    public function get_grade() {
        return $this->grade;
    }

    /**
     * @return array
     */
    public function get_elements() {
        return $this->elements;
    }

    /**
     * @param $type
     * @param $elements
     * @param $grade
     * @throws dml_exception
     */
    public function update($type, $elements, $grade) {
        global $DB;
        // if type or grade has changed update condition
        if ($this->type != $type || $this->grade != $grade) {

            $this->type = $type;
            $this->grade = $grade;

            $record = new stdClass();
            $record->id = $this->id;
            $record->type = $this->type;
            $record->grade = $this->grade;

            $DB->update_record('ddtaquiz_mq_condition_part', $record);
        }

        // for each question
        $currentElements = $this->get_elements();
        foreach ($elements as $elementId) {
            $record = new stdClass();
            $record->condition_id = $this->id;
            $record->question_id = $elementId;
            //if new , add to database
            if(!in_array($elementId,$currentElements)){
                $id = $DB->insert_record('ddtaquiz_mq_questions', $record);
            }else{
                // if not new remove from array, so it will not be deleted
                unset($currentElements[array_search($elementId,$currentElements)]);
            }
        }
        // what is left in array, must have been deselected, so it has to be removed from database
        if(isset($currentElements)){
            foreach ($currentElements as $elementId){
                $DB->delete_records('ddtaquiz_mq_questions', [
                    'question_id'=>$elementId,
                    'condition_id' => $this->id]);
            }

        }

    }
}

class domain_condition extends condition {
    // domain name
    protected $name;
    // replacement for domain abbreviation
    protected $replace;
    // condition type (see class condition part)
    protected $type;
    // reference grade for evaluation
    protected $grade = 0;

    /** */
    const WAS_DISPLAYED     = 0;
    /** condition that student has less points than a set amount */
    const LESS              = 1;
    /** condition that student has less or more points than a set amount */
    const LESS_OR_EQUAL     = 2;
    /** condition that student has more points than a set amount */
    const GREATER           = 3;
    /** condition that student has more or the same points than a set amount */
    const GREATER_OR_EQUAL  = 4;
    /** condition that student has the same points than a set amount */
    const EQUAL             = 5;
    /** condition that student has not the same points than a set amount */
    const NOT_EQUAL         = 6;

    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param int $id the id of this condition.
     * @param string $name
     * @param string $replace
     * @param int $type
     */
    public function __construct($id, $name, $replace, $type, $grade = 0) {
        $this->id = $id;
        $this->name = $name;
        $this->replace = $replace;
        $this->type = $type;
        $this->grade = $grade;
    }

    /**
     * Loads the condition for one block from the database.
     *
     * @param int $id the id of the block to get the condition for.
     * @return condition the loaded condition.
     */
    public static function load($id) {
        global $DB;

        if ($id) {
            $condition = $DB->get_record('ddtaquiz_condition', array('id' => $id));
            if (isset($condition->domainname))
                $name = $condition->domainname;
            else
                $name = "";
            if (isset($condition->domainreplace))
                $replace = $condition->domainreplace;
            else
                $replace = "";
            if (isset($condition->domaintype))
                $type = $condition->domaintype;
            else
                $type = 1;
            if (isset($condition->domaingrade))
                $grade = $condition->domaingrade;
            else
                $grade = 0;
        } else {
            $name = "";
            $replace = "";
            $type = 1;
            $grade = 0;
        }
        return new domain_condition($id, $name,$replace, $type, $grade);
    }

    /**
     * Inserts a new condition into the database.
     *
     * @return condition the newly created condtion part.
     */
    public static function create() {
        global $DB;

        $record = new stdClass();
        $record->useand = true;

        $id = $DB->insert_record('ddtaquiz_condition', $record);

        return new domain_condition($id, "", "", 0);
    }

    /**
     * Checks whether this condition is met for a certain attempt.
     *
     * @param \attempt $attempt the attempt to check this part of the condition for.
     * @return bool whether this condition is fullfilled.
     */
    public function is_fullfilled($attempt) {
        $grades = $this->get_grading($attempt);
        $achieved_grade = $grades[0];
        switch ($this->type) {
            case self::LESS:
                return $achieved_grade < $this->grade;
            case self::LESS_OR_EQUAL:
                return $achieved_grade <= $this->grade;
            case self::GREATER:
                return $achieved_grade > $this->grade;
            case self::GREATER_OR_EQUAL:
                return $achieved_grade >= $this->grade;
            case self::EQUAL:
                return $achieved_grade == $this->grade;
            case self::NOT_EQUAL:
                return $achieved_grade != $this->grade;
            default:
                debugging('Unsupported condition part type: ' . $this->type);
                return true;
        }
    }

    /**
     * Checks whether this condition is met for a certain attempt.
     *
     * @param \attempt $attempt the attempt to check this part of the condition for.
     * @return array array[0] holds achieved grade, array[1] hold total grade.
     */
    public function get_grading($attempt) {
        $grades = [];

        global $DB;
        $elements = $attempt->get_quiz()->get_elements();
        $active_elements = [];
        foreach ($elements as $element) {
            $id = $element->get_id();
            $q_instance = $DB->get_record("ddtaquiz_qinstance", ["id" => $id]);
            if (in_array($this->name, explode(";", $q_instance->domains))) {
                array_push($active_elements, $id);
            }
        }

        $achieved_grade = 0;
        $total_grade = 0;
        foreach ($elements as $element) {
            if (in_array($element->get_id(), $active_elements)) {
                $add_grade = $element->get_grade($attempt);
                if (!is_null($add_grade)) {
                    $achieved_grade += $add_grade;
                }
                $add_total_grade = 0;
                if ($element->is_question()) {
                    $question = question_bank::load_question($element->get_element()->id);
                    $add_total_grade = $question->defaultmark;
                } else if ($element->is_block()) {
                    $add_total_grade = $element->get_element()->get_maxgrade();
                }
                if (!is_null($add_total_grade)) {
                    $total_grade += $add_total_grade;
                }
            }
        }

        $grades[0] = $achieved_grade;
        $grades[1] = $total_grade;
        return $grades;
    }

    /**
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function set_name($name)
    {
        global $DB;

        $this->name = $name;
        $DB->set_field("ddtaquiz_condition", "domainname", $name, ["id" => $this->get_id()]);
    }

    /**
     * @return string
     */
    public function get_replace()
    {
        return $this->replace;
    }

    /**
     * @param string $replace
     */
    public function set_replace($replace)
    {
        global $DB;

        $this->replace = $replace;
        $DB->set_field("ddtaquiz_condition", "domainreplace", $replace, ["id" => $this->get_id()]);
    }

    /**
     * @return int
     */
    public function get_type()
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function set_type($type)
    {
        global $DB;

        $this->type = $type;
        $DB->set_field("ddtaquiz_condition", "domaintype", $type, ["id" => $this->get_id()]);
    }

    /**
     * @return int
     */
    public function get_grade()
    {
        return $this->grade;
    }

    /**
     * @param int $grade
     */
    public function set_grade($grade)
    {
        global $DB;

        $this->grade = $grade;
        $DB->set_field("ddtaquiz_condition", "domaingrade", $grade, ["id" => $this->get_id()]);
    }


}