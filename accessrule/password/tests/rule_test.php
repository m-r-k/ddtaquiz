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
 * Unit tests for the ddtaquizaccess_password plugin.
 *
 * @package    ddtaquizaccess
 * @subpackage password
 * @category   phpunit
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ddtaquiz/accessrule/password/rule.php');


/**
 * Unit tests for the ddtaquizaccess_password plugin.
 *
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ddtaquizaccess_password_testcase extends basic_testcase {
    public function test_password_access_rule() {
        $ddtaquiz = new stdClass();
        $ddtaquiz->password = 'frog';
        $cm = new stdClass();
        $cm->id = 0;
        $ddtaquizobj = new ddtaquiz($ddtaquiz, $cm, null);
        $rule = new ddtaquizaccess_password($ddtaquizobj, 0);
        $attempt = new stdClass();

        $this->assertFalse($rule->prevent_access());
        $this->assertEquals($rule->description(),
            get_string('requirepasswordmessage', 'ddtaquizaccess_password'));
        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));
    }
}
