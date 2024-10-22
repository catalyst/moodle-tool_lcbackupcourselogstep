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
 * Trigger test for end date delay trigger.
 *
 * @package    tool_lcbackupcourselogstep
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lcbackupcourselogstep\tests;

use tool_lifecycle\action;
use tool_lifecycle\local\entity\trigger_subplugin;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\processor;
use tool_lifecycle\settings_type;

/**
 * Trigger test for start date delay trigger.
 *
 * @package    tool_lcbackupcourselogstep
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_test extends \advanced_testcase {

    /**
     * Icon of the manual trigger.
     * @var string
     */
    const MANUAL_TRIGGER1_ICON = 't/up';

    /**
     * Display name of the manual trigger.
     * @var string
     */
    const MANUAL_TRIGGER1_DISPLAYNAME = 'Up';

    /**
     * Capability of the manual trigger.
     * @var string
     */
    const MANUAL_TRIGGER1_CAPABILITY = 'moodle/course:manageactivities';

    /**
     * Instances of the triggers under test.
     * @var trigger_subplugin
     */
    private $trigger;

    /**
     * Instance of the course under test.
     * @var \stdClass
     */
    private $course;

    /**
     * Set up the test case.
     *
     * @return void
     */
    public function setUp(): void {
        global $USER, $DB;

        // We do not need a sesskey check in these tests.
        $USER->ignoresesskey = true;

        // Create manual workflow.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_lifecycle');
        $triggersettings = new \stdClass();
        $triggersettings->icon = self::MANUAL_TRIGGER1_ICON;
        $triggersettings->displayname = self::MANUAL_TRIGGER1_DISPLAYNAME;
        $triggersettings->capability = self::MANUAL_TRIGGER1_CAPABILITY;
        $manualworkflow = $generator->create_manual_workflow($triggersettings);

        // Trigger.
        $this->trigger = trigger_manager::get_triggers_for_workflow($manualworkflow->id)[0];

        // Step.
        $step = $generator->create_step("instance1", "tool_lcbackupcourselogstep", $manualworkflow->id);
        settings_manager::save_settings($step->id, settings_type::STEP, "tool_lcbackupcourselogstep",
            ["fileformat" => "csv"]
        );

        // Course.
        $this->course = $this->getDataGenerator()->create_course();

        // Activate the workflow.
        workflow_manager::handle_action(action::WORKFLOW_ACTIVATE, $manualworkflow->id);
    }

    /**
     * Test that the notification step creates a log file.
     * @covers \tool_lcbackupcourselogstep\step::process_course
     * @return void
     */
    public function test_notification_step() {
        global $DB;

        $this->resetAfterTest();

        // Run trigger.
        process_manager::manually_trigger_process($this->course->id, $this->trigger->id);

        // Run processor.
        $processor = new processor();
        $processor->process_courses();

        // Check that the log file is created.
        $contextid = \context_system::instance()->id;
        $sql = "contextid = :contextid
               AND component = :component
               AND filearea = :filearea
               AND filepath = :filepath
               AND filename <> :filename";
        $file = $DB->get_record_select('files', $sql, [
                'contextid' => $contextid,
                'component' => 'tool_lcbackupcourselogstep',
                'filearea' => 'course_log',
                'filepath' => '/',
                'filename' => '.',
            ]
        );

        // File existence.
        $this->assertNotEmpty($file);

        // Check file name pattern.
        $this->assertThat($file->filename, $this->logicalAnd(
            $this->isType('string'),
            $this->matchesRegularExpression(
                '/^course_log_' . $this->course->shortname . '_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/'
            )
        ));
    }

}

