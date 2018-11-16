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
 * Upgrade script for the ddtaquiz module.
 *
 * @package    mod_ddtaquiz
 * @copyright  2006 Eloy Lafuente (stronk7)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Ddtaquiz module upgrade function.
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_ddtaquiz_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2016092000) {
        // Define new fields to be added to ddtaquiz.
        $table = new xmldb_table('ddtaquiz');

        $field = new xmldb_field('allowofflineattempts', XMLDB_TYPE_INTEGER, '1', null, null, null, 0, 'completionpass');
        // Conditionally launch add field allowofflineattempts.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2016092000, 'ddtaquiz');
    }

    if ($oldversion < 2016092001) {
        // New field for ddtaquiz_attemps.
        $table = new xmldb_table('ddtaquiz_attempts');

        $field = new xmldb_field('timemodifiedoffline', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'timemodified');
        // Conditionally launch add field timemodifiedoffline.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2016092001, 'ddtaquiz');
    }

    if ($oldversion < 2016100300) {
        // Find ddtaquizzes with the combination of require passing grade and grade to pass 0.
        $gradeitems = $DB->get_records_sql("
            SELECT gi.id, gi.itemnumber, cm.id AS cmid
              FROM {ddtaquiz} q
        INNER JOIN {course_modules} cm ON q.id = cm.instance
        INNER JOIN {grade_items} gi ON q.id = gi.iteminstance
        INNER JOIN {modules} m ON m.id = cm.module
             WHERE q.completionpass = 1
               AND gi.gradepass = 0
               AND cm.completiongradeitemnumber IS NULL
               AND gi.itemmodule = m.name
               AND gi.itemtype = ?
               AND m.name = ?", array('mod', 'ddtaquiz'));

        foreach ($gradeitems as $gradeitem) {
            $DB->execute("UPDATE {course_modules}
                             SET completiongradeitemnumber = :itemnumber
                           WHERE id = :cmid",
                array('itemnumber' => $gradeitem->itemnumber, 'cmid' => $gradeitem->cmid));
        }
        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2016100300, 'ddtaquiz');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018020700) {

        $table = new xmldb_table('ddtaquiz_slots');

        // Define field questioncategoryid to be added to ddtaquiz_slots.
        $field = new xmldb_field('questioncategoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'questionid');
        // Conditionally launch add field questioncategoryid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key questioncategoryid (foreign) to be added to ddtaquiz_slots.
        $key = new xmldb_key('questioncategoryid', XMLDB_KEY_FOREIGN, array('questioncategoryid'), 'question_categories', ['id']);
        // Launch add key questioncategoryid.
        $dbman->add_key($table, $key);

        // Define field includingsubcategories to be added to ddtaquiz_slots.
        $field = new xmldb_field('includingsubcategories', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'questioncategoryid');
        // Conditionally launch add field includingsubcategories.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2018020700, 'ddtaquiz');
    }

    if ($oldversion < 2018020701) {
        // This SQL fetches all "random" questions from the question bank.
        $fromclause = "FROM {ddtaquiz_slots} qs
                       JOIN {question} q ON q.id = qs.questionid
                      WHERE q.qtype = ?";

        // Get the total record count - used for the progress bar.
        $total = $DB->count_records_sql("SELECT count(qs.id) $fromclause", array('random'));

        // Get the records themselves.
        $rs = $DB->get_recordset_sql("SELECT qs.id, q.category, q.questiontext $fromclause", array('random'));

        $a = new stdClass();
        $a->total = $total;
        $a->done = 0;

        // For each question, move the configuration data to the ddtaquiz_slots table.
        $pbar = new progress_bar('updateddtaquizslotswithrandom', 500, true);
        foreach ($rs as $record) {
            $data = new stdClass();
            $data->id = $record->id;
            $data->questioncategoryid = $record->category;
            $data->includingsubcategories = empty($record->questiontext) ? 0 : 1;
            $DB->update_record('ddtaquiz_slots', $data);

            // Update progress.
            $a->done++;
            $pbar->update($a->done, $a->total, get_string('updateddtaquizslotswithrandomxofy', 'ddtaquiz', $a));
        }
        $rs->close();

        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2018020701, 'ddtaquiz');
    }

    if ($oldversion < 2018040700) {

        // Define field tags to be dropped from ddtaquiz_slots. This field was added earlier to master only.
        $table = new xmldb_table('ddtaquiz_slots');
        $field = new xmldb_field('tags');

        // Conditionally launch drop field ddtaquizid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2018040700, 'ddtaquiz');
    }

    if ($oldversion < 2018040800) {

        // Define table ddtaquiz_slot_tags to be created.
        $table = new xmldb_table('ddtaquiz_slot_tags');

        // Adding fields to table ddtaquiz_slot_tags.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('slotid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('tagid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('tagname', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table ddtaquiz_slot_tags.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('slotid', XMLDB_KEY_FOREIGN, array('slotid'), 'ddtaquiz_slots', array('id'));
        $table->add_key('tagid', XMLDB_KEY_FOREIGN, array('tagid'), 'tag', array('id'));

        // Conditionally launch create table for ddtaquiz_slot_tags.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ddtaquiz savepoint reached.
        upgrade_mod_savepoint(true, 2018040800, 'ddtaquiz');
    }

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
