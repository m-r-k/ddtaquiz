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
 * DDTA Quiz external API
 *
 * @package    mod_ddtaquiz
 * @category   external
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot.'/question/editlib.php');

/**
 * DDTA Quiz external functions
 *
 * @package    mod_ddtaquiz
 * @category   external
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class mod_ddtaquiz_external extends external_api {
    /**
     * Returns the description of get_questionbank.
     * @return external_function_parameters the function parameters.
     */
    public static function get_questionbank_parameters() {
        return new external_function_parameters(
            array('cmid' => new external_value(PARAM_INT, 'the course module id'),
                'bid' => new external_value(PARAM_INT, 'the id of the block, where the questions should be added'),
                'page' => new external_value(PARAM_INT, 'the page of the question bank view', VALUE_DEFAULT, 0),
                'qperpage' => new external_value(PARAM_INT, 'the number of questions per page', VALUE_DEFAULT,
                    DEFAULT_QUESTIONS_PER_PAGE),
                'qbs1' => new external_value(PARAM_RAW, 'the sort parameter', VALUE_DEFAULT, null),
                'category' => new external_value(PARAM_RAW, 'the question category', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Renders the questionbank view HTML.
     *
     * @param int $cmid the id of the course module.
     * @param int $bid the id of the block, where the questions should be added.
     * @param int $page the page of the questionbank view.
     * @param int $qperpage the number of questions per page.
     * @param string $qbs1 the sort parameter.
     * @param string $category the category of the question.
     * @return string the questionbank view HTML.
     * @throws invalid_parameter_exception
     * @throws invalid_response_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function get_questionbank($cmid, $bid, $page, $qperpage, $qbs1, $category) {
        global $PAGE;
        $params = self::validate_parameters(self::get_questionbank_parameters(),
            array('cmid' => $cmid, 'bid' => $bid, 'page' => $page, 'qperpage' => $qperpage,
                'qbs1' => $qbs1, 'category' => $category));

        $context = context_module::instance($params['cmid']);
        external_api::validate_context($context);

        $cmid = $params['cmid'];
        $thispageurl = new moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $params['cmid'], 'bid' => $params['bid']));

        list($course, $cm) = get_course_and_cm_from_cmid($cmid);

        $contexts = new question_edit_contexts($context);
        $contexts->require_one_edit_tab_cap('editq');

        $category = $params['category'];
        if (!$category) {
            $defaultcategory = question_make_default_categories($contexts->all());
            $category = "{$defaultcategory->id},{$defaultcategory->contextid}";
        }

        $pagevars = array();
        $pagevars['cat'] = $category;

        $pagevars['page'] = $params['page'];
        $pagevars['qperpage'] = $params['qperpage'];
        if ($params['qbs1']) {
            $decoded = urldecode($params['qbs1']);
            $thispageurl->param('qbs1', $decoded);
            // The view requires the sort field as a paramter.
            $_POST['qbs1'] = $decoded;
        }

        require_capability('mod/ddtaquiz:manage', $contexts->lowest());
        $questionbank = new \mod_ddtaquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm);

        $output = $PAGE->get_renderer('mod_ddtaquiz', 'edit');

        // Output.
        $content = $output->question_bank_contents($questionbank, $pagevars);
        return external_api::clean_returnvalue(self::get_questionbank_returns(),
            $content);
    }

    /**
     * Returns the return description of get_questionbank.
     * @return external_description the description.
     */
    public static function get_questionbank_returns() {
        return new external_value(PARAM_RAW, 'the questionbank view HTML');
    }
}
