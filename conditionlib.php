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
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
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
    public function __construct($id, $parts, $useand) {
        $this->id = $id;
        $this->parts = $parts;
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
            $partobjs = array_map(function($part) {
                return new condition_part($part->id, $part->type, $part->on_qinstance, $part->grade);
            },
            array_values($parts));
        } else {
            $partobjs = array();
            $useand = true;
        }
        return new condition($id, $partobjs, $useand);
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

        return new condition($id, array(), $record->useand);
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
     * Checks whether this condition is met for a certain attempt.
     *
     * @param object $attempt the attempt to check this part of the condition for.
     * @return bool whether this condition is fullfilled.
     */
    public function is_fullfilled($attempt) {
        if ($this->useand) {
            foreach ($this->parts as $part) {
                if (!$part->is_fullfilled($attempt)) {
                    return false;
                }
            }
            return true;
        } else {
            foreach ($this->parts as $part) {
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
     * Updates the condition using the submitted array.
     *
     * @param array $conditionparts the array from the form.
     */
    public function update($conditionparts) {
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
            } else { // Insert new condition parts.
                $this->add_part($part['type'], $part['question'], $part['points']);
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