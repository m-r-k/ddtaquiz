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
 * This file keeps track of upgrades to the ddtaquiz module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute ddtaquiz upgrade from the given old version.
 *
 * @param int $oldversion the current version number.
 * @return bool
 */
function xmldb_ddtaquiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2019021125) {
            try{
            // Update all records in 'course_modules' for labels to have showdescription = 1.
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz ADD directfeedback INT(1) NOT NULL DEFAULT 1 AFTER mainblock"
            );

            // Label savepoint reached.
            upgrade_mod_savepoint(true, 2019021125, 'ddtaquiz');
            }
            catch(Exception $e){

            }
    }

    if($oldversion < 2019021125){
        try{
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz ADD timelimit BIGINT(10) NOT NULL DEFAULT 0 AFTER timemodified"
            );
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz ADD overduehandling VARCHAR(16) NOT NULL DEFAULT 'autoabandon' AFTER timelimit"
            );

            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz ADD graceperiod BIGINT(10) NOT NULL DEFAULT 0 AFTER overduehandling"
            );


            // Label savepoint reached.
            upgrade_mod_savepoint(true, 2019021125, 'ddtaquiz');
        }
        catch(Exception $e){

        }
    }

    if($oldversion < 2019021126){
        try{
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz ADD domains VARCHAR(255) NOT NULL DEFAULT ' ' AFTER  mainblock"
            );
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz_qinstance ADD domains VARCHAR(255) NOT NULL DEFAULT ' '"
            );

            // Label savepoint reached.
            upgrade_mod_savepoint(true, 2019021126, 'ddtaquiz');
        }
        catch(Exception $e){

        }
    }

    if($oldversion < 2019021701){
        try{

            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz_feedback_block ADD domainfeedback INT(1) NOT NULL DEFAULT 0 AFTER feedbacktext"
            );

            // Label savepoint reached.
            upgrade_mod_savepoint(true, 2019021701, 'ddtaquiz');
        }
        catch(Exception $e){

        }
    }

    if($oldversion < 2019021703){
        try{

            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz_condition ADD domaintype INT(5) AFTER useand"
            );
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz_condition ADD domainname VARCHAR(100) AFTER domaintype"
            );
            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz_condition ADD domainreplace VARCHAR(100) AFTER domainname"
            );

            // Label savepoint reached.
            upgrade_mod_savepoint(true, 2019021703, 'ddtaquiz');
        }
        catch(Exception $e){

        }
    }

    if($oldversion < 2019021704){
        try{

            $DB->execute(
                "ALTER TABLE mdl_ddtaquiz_condition ADD domaingrade DECIMAL(10,5) AFTER domainreplace"
            );

            // Label savepoint reached.
            upgrade_mod_savepoint(true, 2019021704, 'ddtaquiz');
        }
        catch(Exception $e){

        }
    }

    return true;
}
