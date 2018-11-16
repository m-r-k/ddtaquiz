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
 * This file contains the polyfil to allow a plugin to operate with Moodle 3.3 up.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_ddtaquiz\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * The trait used to provide a backwards compatibility for third-party plugins.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait legacy_ddtaquizaccess_polyfill {

    /**
     * Export all user data for the specified user, for the specified ddtaquiz.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being exported
     * @param   \stdClass       $user The user to export data for
     * @return  \stdClass       The data to be exported for this access rule.
     */
    public static function export_ddtaquizaccess_user_data(\ddtaquiz $ddtaquiz, \stdClass $user) : \stdClass {
        return static::_export_ddtaquizaccess_user_data($ddtaquiz, $user);
    }

    /**
     * Delete all data for all users in the specified ddtaquiz.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being deleted
     */
    public static function delete_ddtaquizaccess_data_for_all_users_in_context(\ddtaquiz $ddtaquiz) {
        static::_delete_ddtaquizaccess_data_for_all_users_in_context($ddtaquiz);
    }

    /**
     * Delete all user data for the specified user, in the specified ddtaquiz.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being deleted
     * @param   \stdClass       $user The user to export data for
     */
    public static function delete_ddtaquizaccess_data_for_user(\ddtaquiz $ddtaquiz, \stdClass $user) {
        static::_delete_ddtaquizaccess_data_for_user($ddtaquiz, $user);
    }
}
