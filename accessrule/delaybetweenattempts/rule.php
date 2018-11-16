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
 * Implementaton of the ddtaquizaccess_delaybetweenattempts plugin.
 *
 * @package    ddtaquizaccess
 * @subpackage delaybetweenattempts
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/accessrule/accessrulebase.php');


/**
 * A rule imposing the delay between attempts settings.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ddtaquizaccess_delaybetweenattempts extends ddtaquiz_access_rule_base {

    public static function make(ddtaquiz $ddtaquizobj, $timenow, $canignoretimelimits) {
        if (empty($ddtaquizobj->get_ddtaquiz()->delay1) && empty($ddtaquizobj->get_ddtaquiz()->delay2)) {
            return null;
        }

        return new self($ddtaquizobj, $timenow);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($this->ddtaquiz->attempts > 0 && $numprevattempts >= $this->ddtaquiz->attempts) {
            // No more attempts allowed anyway.
            return false;
        }
        if ($this->ddtaquiz->timeclose != 0 && $this->timenow > $this->ddtaquiz->timeclose) {
            // No more attempts allowed anyway.
            return false;
        }
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        if ($this->timenow < $nextstarttime) {
            if ($this->ddtaquiz->timeclose == 0 || $nextstarttime <= $this->ddtaquiz->timeclose) {
                return get_string('youmustwait', 'ddtaquizaccess_delaybetweenattempts',
                        userdate($nextstarttime));
            } else {
                return get_string('youcannotwait', 'ddtaquizaccess_delaybetweenattempts');
            }
        }
        return false;
    }

    /**
     * Compute the next time a student would be allowed to start an attempt,
     * according to this rule.
     * @param int $numprevattempts number of previous attempts.
     * @param object $lastattempt information about the previous attempt.
     * @return number the time.
     */
    protected function compute_next_start_time($numprevattempts, $lastattempt) {
        if ($numprevattempts == 0) {
            return 0;
        }

        $lastattemptfinish = $lastattempt->timefinish;
        if ($this->ddtaquiz->timelimit > 0) {
            $lastattemptfinish = min($lastattemptfinish,
                    $lastattempt->timestart + $this->ddtaquiz->timelimit);
        }

        if ($numprevattempts == 1 && $this->ddtaquiz->delay1) {
            return $lastattemptfinish + $this->ddtaquiz->delay1;
        } else if ($numprevattempts > 1 && $this->ddtaquiz->delay2) {
            return $lastattemptfinish + $this->ddtaquiz->delay2;
        }
        return 0;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        return $this->timenow <= $nextstarttime &&
        $this->ddtaquiz->timeclose != 0 && $nextstarttime >= $this->ddtaquiz->timeclose;
    }
}
