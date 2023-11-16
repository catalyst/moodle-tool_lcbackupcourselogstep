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
 * Displays backed up logs
 *
 * @package tool_lcbackupcourselogstep
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_lcbackupcourselogstep\lifecycle\log_table;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$PAGE->set_context(context_system::instance());
require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('tool_lcbackupcourselogstep_logs');

$PAGE->set_url(new \moodle_url('/admin/tool/lcbackupcourselogstep/logs.php'));
$PAGE->set_title(get_string('courselogs', 'tool_lcbackupcourselogstep'));
$PAGE->set_heading(get_string('courselogs', 'tool_lcbackupcourselogstep'));

// Download.
$action = optional_param('action', null, PARAM_ALPHANUMEXT);

if ($action) {
    global $DB;
    require_sesskey();

    // Retrieve the file.
    $id = required_param('id', PARAM_INT);
    $fs = get_file_storage();
    $file = $fs->get_file_by_id($id);

    // Check file existence.
    if (!$file) {
        throw new coding_exception("File with id $id not found");
    }

    // Check file component.
    if ($file->get_component() != 'tool_lcbackupcourselogstep') {
        throw new coding_exception("File with id $id is not a backup file");
    }

    // Perform the action.
    if ($action == 'download') {
        // Download the file.
        send_stored_file($file, 0, 0, true);
    } else {
        throw new coding_exception("action must be 'download'");
    }
}

// Reuse course filter from Lifecycle plugin in.
$mform = new \tool_lifecycle\local\form\form_courses_filter();

// Cache handling. Reuse cache definition from Lifecycle plugin.
$cache = cache::make('tool_lifecycle', 'mformdata');
if ($mform->is_cancelled()) {
    $cache->delete('logbackups_filter');
    redirect($PAGE->url);
} else if ($data = $mform->get_data()) {
    $cache->set('logbackups_filter', $data);
} else {
    $data = $cache->get('logbackups_filter');
    if ($data) {
        $mform->set_data($data);
    }
}
echo $OUTPUT->header();
$mform->display();

// Show log table with default paging: 100 items/page.
$table = new log_table($data);
$table->define_baseurl($PAGE->url);
$table->out(100, false);

echo $OUTPUT->footer();
