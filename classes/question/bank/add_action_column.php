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
 * A column type for the add this question to the block action.
 *
 *
 * @package    mod_ddtaquiz
 * @category   question
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\question\bank;
defined('MOODLE_INTERNAL') || die();


/**
 * A column type for the add this question to the block action.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_action_column extends \core_question\bank\action_column_base {
    /** @var string caches a lang string used repeatedly. */
    protected $stradd;

    public function init() {
        parent::init();
        $this->stradd = get_string('addtoblock', 'ddtaquiz');
    }

    public function get_name() {
        return 'addtoquizaction';
    }

    protected function display_content($question, $rowclasses) {
        if (!question_has_capability_on($question, 'use')) {
            return;
        }
        echo \html_writer::start_div('addfromquestionbank', array('data-id' => $question->id));
        $this->print_icon('t/add', $this->stradd, new \moodle_url('/mod/ddtaquiz/edit.php'));
        echo \html_writer::end_div();
    }

    public function get_required_fields() {
        return array('q.id');
    }
}
