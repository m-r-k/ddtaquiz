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
 * Privacy provider tests.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\local\metadata\collection;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\writer;
use mod_ddtaquiz\privacy\provider;
use mod_ddtaquiz\privacy\helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/tests/privacy_helper.php');

/**
 * Privacy provider tests class.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    use core_question_privacy_helper;

    /**
     * Test that a user who has no data gets no contexts
     */
    public function test_get_contexts_for_userid_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $contextlist = provider::get_contexts_for_userid($USER->id);
        $this->assertEmpty($contextlist);
    }

    /**
     * The export function should handle an empty contextlist properly.
     */
    public function test_export_user_data_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($USER->id),
            'mod_ddtaquiz',
            []
        );

        provider::export_user_data($approvedcontextlist);
        $this->assertDebuggingNotCalled();

        // No data should have been exported.
        $writer = \core_privacy\local\request\writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data_in_any_context());
    }

    /**
     * The delete function should handle an empty contextlist properly.
     */
    public function test_delete_data_for_user_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($USER->id),
            'mod_ddtaquiz',
            []
        );

        provider::delete_data_for_user($approvedcontextlist);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Export + Delete ddtaquiz data for a user who has made a single attempt.
     */
    public function test_user_with_data() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        // Make a ddtaquiz with an override.
        $this->setUser();
        $ddtaquiz = $this->create_test_ddtaquiz($course);
        $DB->insert_record('ddtaquiz_overrides', [
                'ddtaquiz' => $ddtaquiz->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the ddtaquiz.
        list($ddtaquizobj, $quba, $attemptobj) = $this->attempt_ddtaquiz($ddtaquiz, $user);
        $this->attempt_ddtaquiz($ddtaquiz, $otheruser);
        $context = $ddtaquizobj->get_context();

        // Fetch the contexts - only one context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Perform the export and check the data.
        $this->setUser($user);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_ddtaquiz',
            $contextlist->get_contextids()
        );
        provider::export_user_data($approvedcontextlist);

        // Ensure that the ddtaquiz data was exported correctly.
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $ddtaquizdata = $writer->get_data([]);
        $this->assertEquals($ddtaquizobj->get_ddtaquiz_name(), $ddtaquizdata->name);

        // Every module has an intro.
        $this->assertTrue(isset($ddtaquizdata->intro));

        // Fetch the attempt data.
        $attempt = $attemptobj->get_attempt();
        $attemptsubcontext = [
            get_string('attempts', 'mod_ddtaquiz'),
            $attempt->attempt,
        ];
        $attemptdata = writer::with_context($context)->get_data($attemptsubcontext);

        $attempt = $attemptobj->get_attempt();
        $this->assertTrue(isset($attemptdata->state));
        $this->assertEquals(\ddtaquiz_attempt::state_name($attemptobj->get_state()), $attemptdata->state);
        $this->assertTrue(isset($attemptdata->timestart));
        $this->assertTrue(isset($attemptdata->timefinish));
        $this->assertTrue(isset($attemptdata->timemodified));
        $this->assertFalse(isset($attemptdata->timemodifiedoffline));
        $this->assertFalse(isset($attemptdata->timecheckstate));

        $this->assertTrue(isset($attemptdata->grade));
        $this->assertEquals(100.00, $attemptdata->grade->grade);

        // Check that the exported question attempts are correct.
        $attemptsubcontext = helper::get_ddtaquiz_attempt_subcontext($attemptobj->get_attempt(), $user);
        $this->assert_question_attempt_exported(
            $context,
            $attemptsubcontext,
            \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid()),
            ddtaquiz_get_review_options($ddtaquiz, $attemptobj->get_attempt(), $context),
            $user
        );

        // Delete the data and check it is removed.
        $this->setUser();
        provider::delete_data_for_user($approvedcontextlist);
        $this->expectException(\dml_missing_record_exception::class);
        \ddtaquiz_attempt::create($attemptobj->get_ddtaquizid());
    }

    /**
     * Export + Delete ddtaquiz data for a user who has made a single attempt.
     */
    public function test_user_with_preview() {
        global $DB;
        $this->resetAfterTest(true);

        // Make a ddtaquiz.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $ddtaquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_ddtaquiz');

        $ddtaquiz = $ddtaquizgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
            ]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        ddtaquiz_add_ddtaquiz_question($saq->id, $ddtaquiz);
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        ddtaquiz_add_ddtaquiz_question($numq->id, $ddtaquiz);

        // Run as the user and make an attempt on the ddtaquiz.
        $this->setUser($user);
        $starttime = time();
        $ddtaquizobj = ddtaquiz::create($ddtaquiz->id, $user->id);
        $context = $ddtaquizobj->get_context();

        $quba = question_engine::make_questions_usage_by_activity('mod_ddtaquiz', $ddtaquizobj->get_context());
        $quba->set_preferred_behaviour($ddtaquizobj->get_ddtaquiz()->preferredbehaviour);

        // Start the attempt.
        $attempt = ddtaquiz_create_attempt($ddtaquizobj, 1, false, $starttime, true, $user->id);
        ddtaquiz_start_new_attempt($ddtaquizobj, $quba, $attempt, 1, $starttime);
        ddtaquiz_attempt_save_started($ddtaquizobj, $quba, $attempt);

        // Answer the questions.
        $attemptobj = ddtaquiz_attempt::create($attempt->id);

        $tosubmit = [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ];

        $attemptobj->process_submitted_actions($starttime, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = ddtaquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($starttime, false);

        // Fetch the contexts - no context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Export + Delete ddtaquiz data for a user who has made a single attempt.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        // Make a ddtaquiz with an override.
        $this->setUser();
        $ddtaquiz = $this->create_test_ddtaquiz($course);
        $DB->insert_record('ddtaquiz_overrides', [
                'ddtaquiz' => $ddtaquiz->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the ddtaquiz.
        list($ddtaquizobj, $quba, $attemptobj) = $this->attempt_ddtaquiz($ddtaquiz, $user);
        list($ddtaquizobj, $quba, $attemptobj) = $this->attempt_ddtaquiz($ddtaquiz, $otheruser);

        // Create another ddtaquiz and questions, and repeat the data insertion.
        $this->setUser();
        $otherddtaquiz = $this->create_test_ddtaquiz($course);
        $DB->insert_record('ddtaquiz_overrides', [
                'ddtaquiz' => $otherddtaquiz->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the ddtaquiz.
        list($otherddtaquizobj, $otherquba, $otherattemptobj) = $this->attempt_ddtaquiz($otherddtaquiz, $user);
        list($otherddtaquizobj, $otherquba, $otherattemptobj) = $this->attempt_ddtaquiz($otherddtaquiz, $otheruser);

        // Delete all data for all users in the context under test.
        $this->setUser();
        $context = $ddtaquizobj->get_context();
        provider::delete_data_for_all_users_in_context($context);

        // The ddtaquiz attempt should have been deleted from this ddtaquiz.
        $this->assertCount(0, $DB->get_records('ddtaquiz_attempts', ['ddtaquiz' => $ddtaquizobj->get_ddtaquizid()]));
        $this->assertCount(0, $DB->get_records('ddtaquiz_overrides', ['ddtaquiz' => $ddtaquizobj->get_ddtaquizid()]));
        $this->assertCount(0, $DB->get_records('question_attempts', ['questionusageid' => $quba->get_id()]));

        // But not for the other ddtaquiz.
        $this->assertNotCount(0, $DB->get_records('ddtaquiz_attempts', ['ddtaquiz' => $otherddtaquizobj->get_ddtaquizid()]));
        $this->assertNotCount(0, $DB->get_records('ddtaquiz_overrides', ['ddtaquiz' => $otherddtaquizobj->get_ddtaquizid()]));
        $this->assertNotCount(0, $DB->get_records('question_attempts', ['questionusageid' => $otherquba->get_id()]));
    }

    /**
     * Export + Delete ddtaquiz data for a user who has made a single attempt.
     */
    public function test_wrong_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Make a choice.
        $this->setUser();
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_choice');
        $choice = $plugingenerator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('choice', $choice->id);
        $context = \context_module::instance($cm->id);

        // Fetch the contexts - no context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        // Perform the export and check the data.
        $this->setUser($user);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_ddtaquiz',
            [$context->id]
        );
        provider::export_user_data($approvedcontextlist);

        // Ensure that nothing was exported.
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data_in_any_context());

        $this->setUser();

        $dbwrites = $DB->perf_get_writes();

        // Perform a deletion with the approved contextlist containing an incorrect context.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_ddtaquiz',
            [$context->id]
        );
        provider::delete_data_for_user($approvedcontextlist);
        $this->assertEquals($dbwrites, $DB->perf_get_writes());
        $this->assertDebuggingNotCalled();

        // Perform a deletion of all data in the context.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEquals($dbwrites, $DB->perf_get_writes());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Create a test ddtaquiz for the specified course.
     *
     * @param   \stdClass $course
     * @return  array
     */
    protected function create_test_ddtaquiz($course) {
        global $DB;

        $ddtaquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_ddtaquiz');

        $ddtaquiz = $ddtaquizgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
            ]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        ddtaquiz_add_ddtaquiz_question($saq->id, $ddtaquiz);
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        ddtaquiz_add_ddtaquiz_question($numq->id, $ddtaquiz);

        return $ddtaquiz;
    }

    /**
     * Answer questions for a ddtaquiz + user.
     *
     * @param   \stdClass   $ddtaquiz
     * @param   \stdClass   $user
     * @return  array
     */
    protected function attempt_ddtaquiz($ddtaquiz, $user) {
        $this->setUser($user);

        $starttime = time();
        $ddtaquizobj = ddtaquiz::create($ddtaquiz->id, $user->id);
        $context = $ddtaquizobj->get_context();

        $quba = question_engine::make_questions_usage_by_activity('mod_ddtaquiz', $ddtaquizobj->get_context());
        $quba->set_preferred_behaviour($ddtaquizobj->get_ddtaquiz()->preferredbehaviour);

        // Start the attempt.
        $attempt = ddtaquiz_create_attempt($ddtaquizobj, 1, false, $starttime, false, $user->id);
        ddtaquiz_start_new_attempt($ddtaquizobj, $quba, $attempt, 1, $starttime);
        ddtaquiz_attempt_save_started($ddtaquizobj, $quba, $attempt);

        // Answer the questions.
        $attemptobj = ddtaquiz_attempt::create($attempt->id);

        $tosubmit = [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ];

        $attemptobj->process_submitted_actions($starttime, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = ddtaquiz_attempt::create($attempt->id);
        $attemptobj->process_finish($starttime, false);

        $this->setUser();

        return [$ddtaquizobj, $quba, $attemptobj];
    }
}
