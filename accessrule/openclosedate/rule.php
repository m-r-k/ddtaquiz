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
 * Implementaton of the ddtaquizaccess_openclosedate plugin.
 *
 * @package    ddtaquizaccess
 * @subpackage openclosedate
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/accessrule/accessrulebase.php');


/**
 * A rule enforcing open and close dates.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ddtaquizaccess_openclosedate extends ddtaquiz_access_rule_base {

    public static function make(ddtaquiz $ddtaquizobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the ddtaquiz has no open or close date.
        return new self($ddtaquizobj, $timenow);
    }

    public function description() {
        $result = array();
        if ($this->timenow < $this->ddtaquiz->timeopen) {
            $result[] = get_string('ddtaquiznotavailable', 'ddtaquizaccess_openclosedate',
                    userdate($this->ddtaquiz->timeopen));
            if ($this->ddtaquiz->timeclose) {
                $result[] = get_string('ddtaquizcloseson', 'ddtaquiz', userdate($this->ddtaquiz->timeclose));
            }

        } else if ($this->ddtaquiz->timeclose && $this->timenow > $this->ddtaquiz->timeclose) {
            $result[] = get_string('ddtaquizclosed', 'ddtaquiz', userdate($this->ddtaquiz->timeclose));

        } else {
            if ($this->ddtaquiz->timeopen) {
                $result[] = get_string('ddtaquizopenedon', 'ddtaquiz', userdate($this->ddtaquiz->timeopen));
            }
            if ($this->ddtaquiz->timeclose) {
                $result[] = get_string('ddtaquizcloseson', 'ddtaquiz', userdate($this->ddtaquiz->timeclose));
            }
        }

        return $result;
    }

    public function prevent_access() {
        $message = get_string('notavailable', 'ddtaquizaccess_openclosedate');

        if ($this->timenow < $this->ddtaquiz->timeopen) {
            return $message;
        }

        if (!$this->ddtaquiz->timeclose) {
            return false;
        }

        if ($this->timenow <= $this->ddtaquiz->timeclose) {
            return false;
        }

        if ($this->ddtaquiz->overduehandling != 'graceperiod') {
            return $message;
        }

        if ($this->timenow <= $this->ddtaquiz->timeclose + $this->ddtaquiz->graceperiod) {
            return false;
        }

        return $message;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $this->ddtaquiz->timeclose && $this->timenow > $this->ddtaquiz->timeclose;
    }

    public function end_time($attempt) {
        if ($this->ddtaquiz->timeclose) {
            return $this->ddtaquiz->timeclose;
        }
        return false;
    }

    public function time_left_display($attempt, $timenow) {
        // If this is a teacher preview after the close date, do not show
        // the time.
        if ($attempt->preview && $timenow > $this->ddtaquiz->timeclose) {
            return false;
        }
        // Otherwise, return to the time left until the close date, providing that is
        // less than DDTAQUIZ_SHOW_TIME_BEFORE_DEADLINE.
        $endtime = $this->end_time($attempt);
        if ($endtime !== false && $timenow > $endtime - DDTAQUIZ_SHOW_TIME_BEFORE_DEADLINE) {
            return $endtime - $timenow;
        }
        return false;
    }
}
