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
 * Defines the custom question bank view used on the Edit block page.
 *
 * @package    mod_ddtaquiz
 * @category   external
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\question\bank;

defined('MOODLE_INTERNAL') || die();

/**
 * Subclass to customise the view of the question bank for the block editing screen.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_view extends \core_question\bank\view {


    /**
     * Constructor
     * @param \question_edit_contexts $contexts
     * @param \moodle_url $pageurl
     * @param \stdClass $course course settings
     * @param \stdClass $cm activity settings.
     */
    public function __construct($contexts, $pageurl, $course, $cm) {
        parent::__construct($contexts, $pageurl, $course, $cm);
    }


    protected function wanted_columns() {
        $questionbankcolumns = array(
            'add_action_column',
            'checkbox_column',
            'question_type_column',
            'question_name_column');

        foreach ($questionbankcolumns as $fullname) {
            if (! class_exists($fullname)) {
                if (class_exists('mod_ddtaquiz\\question\\bank\\' . $fullname)) {
                    $fullname = 'mod_ddtaquiz\\question\\bank\\' . $fullname;
                } else if (class_exists('core_question\\bank\\' . $fullname)) {
                    $fullname = 'core_question\\bank\\' . $fullname;
                } else {
                    throw new \coding_exception("No such class exists: $fullname");
                }
            }
            $this->requiredcolumns[$fullname] = new $fullname($this);
        }
        return $this->requiredcolumns;
    }

    /**
     * Renders the html question bank (same as display, but returns the result).
     *
     * Note that you can only output this rendered result once per page, as
     * it contains IDs which must be unique.
     *
     * @return string HTML code for the form.
     */
    public function render($tabname, $page, $perpage, $cat, $recurse, $showhidden, $showquestiontext) {
        ob_start();
        echo \html_writer::start_div('questionbankcontent');
        $this->display($tabname, $page, $perpage, $cat, $recurse, $showhidden, $showquestiontext);
        echo \html_writer::end_div();
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

    // Do not display this.
    protected function display_options_form($showquestiontext, $scriptpath = '/mod/ddtaquiz/edit.php',
        $showtextoption = false) {
        foreach ($this->searchconditions as $searchcondition) {
            echo $searchcondition->display_options($this);
        }
    }

    // Do not display this.
    protected function create_new_question_form($category, $canadd) {
    }

    protected function display_bottom_controls($totalnumber, $recurse, $category, \context $catcontext, array $addcontexts) {
        $canuseall = has_capability('moodle/question:useall', $catcontext);

        echo '<div class="modulespecificbuttonscontainer">';
        if ($canuseall) {
            // Add selected questions to the quiz.
            $params = array(
                'type' => 'submit',
                'id' => 'addselected',
                'name' => 'add',
                'value' => get_string('addselectedquestionstoquiz', 'ddtaquiz'),
            );
            echo \html_writer::empty_tag('input', $params);
        }
        echo "</div>\n";
    }
}