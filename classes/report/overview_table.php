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
 * This file defines the ddtaquiz grades table.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();


/**
 * This is a table subclass for displaying the ddtaquiz grades report.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class overview_table extends attempts_table {

    protected $regradedqs = array();

    /**
     * @inheritdoc
     */
    public function __construct(\ddtaquiz $quiz, $context, $qmsubselect,
        overview_options $options, $groupstudents, $students, $questions, $reporturl) {
            parent::__construct('mod-ddtaquiz-report-overview', $quiz , $context,
                $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
    }

    /**
     * @inheritdoc
     */
    public function build_table() {
        if (!$this->rawdata) {
            return;
        }
        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    /**
     * @inheritdoc
     */
    public function other_cols($colname, $attempt) {
        if (!preg_match('/^qsgrade(\d+)$/', $colname, $matches)) {
            return null;
        }
        $slot = $matches[1];

        $question = $this->questions[$slot];
        if (!isset($this->lateststeps[$attempt->usageid][$slot])) {
            return '-';
        }

        $stepdata = $this->lateststeps[$attempt->usageid][$slot];
        $state = \question_state::get($stepdata->state);

        if ($question->defaultmark == 0) {
            $grade = '-';
        } else if (is_null($stepdata->fraction)) {
            if ($state == \question_state::$needsgrading) {
                $grade = get_string('requiresgrading', 'question');
            } else {
                $grade = '-';
            }
        } else {
            $grade = $this->quiz->format_grade($stepdata->fraction * $question->defaultmark);
        }

        if ($this->is_downloading()) {
            return $grade;
        }

        return $this->make_review_link($grade, $attempt, $slot);
    }

    /**
     * @inheritdoc
     */
    protected function requires_latest_steps_loaded() {
        return $this->options->slotmarks;
    }

    /**
     * @inheritdoc
     */
    protected function is_latest_step_column($column) {
        if (preg_match('/^qsgrade([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function get_required_latest_state_fields($slot, $alias) {
        return "$alias.fraction * $alias.maxmark AS qsgrade$slot";
    }
}