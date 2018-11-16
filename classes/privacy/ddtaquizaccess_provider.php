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
 * The ddtaquizaccess_provider interface provides the expected interface for all 'ddtaquizaccess' ddtaquizaccesss.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_ddtaquiz\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\approved_contextlist;

/**
 * The ddtaquizaccess_provider interface provides the expected interface for all 'ddtaquizaccess' ddtaquizaccesss.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface ddtaquizaccess_provider extends \core_privacy\local\request\plugin\subplugin_provider {

    /**
     * Export all user data for the specified user, for the specified ddtaquiz.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being exported
     * @param   \stdClass       $user The user to export data for
     * @return  \stdClass       The data to be exported for this access rule.
     */
    public static function export_ddtaquizaccess_user_data(\ddtaquiz $ddtaquiz, \stdClass $user) : \stdClass;

    /**
     * Delete all data for all users in the specified ddtaquiz.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being deleted
     */
    public static function delete_ddtaquizaccess_data_for_all_users_in_context(\ddtaquiz $ddtaquiz);

    /**
     * Delete all user data for the specified user, in the specified ddtaquiz.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being deleted
     * @param   \stdClass       $user The user to export data for
     */
    public static function delete_ddtaquizaccess_data_for_user(\ddtaquiz $ddtaquiz, \stdClass $user);
}
