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

namespace tool_lcbackupcourselogstep\lifecycle;

global $CFG;
require_once($CFG->dirroot . '/admin/tool/lifecycle/step/lib.php');

use admin_externalpage;
use core\dataformat;
use moodle_url;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;
use tool_lifecycle\step\instance_setting;
use tool_lifecycle\step\libbase;

defined('MOODLE_INTERNAL') || die();

class step extends libbase {
    public function get_subpluginname()
    {
        return 'tool_lcbackupcourselogstep';
    }

    public function get_plugin_description() {
        return "Backup course log step plugin";
    }

    public function process_course($processid, $instanceid, $course)
    {
        global $DB;

        // Get all logs for the course with related courses and user details.
        $sql = "SELECT log.*, c.shortname as courseshortname, c.fullname as coursefullname,
                    u1.firstname as userfirstname, u1.lastname as userlastname, 
                    u2.firstname as realuserfirstname, u2.lastname as realuserlastname,
                    u3.firstname as relateduserfirstname, u3.lastname as relateduserlastname 
                  FROM {logstore_standard_log} as log
                  LEFT JOIN {course} as c ON log.courseid = c.id
                  LEFT JOIN {user} as u1 ON log.userid = u1.id
                  LEFT JOIN {user} as u2 ON log.realuserid = u2.id
                  LEFT JOIN {user} as u3 ON log.relateduserid = u3.id
                 WHERE courseid = :courseid";

        // Get all logs for the course.
        $logs = $DB->get_recordset_sql($sql, ['courseid' => $course->id]);

        // Headers for the CSV file.
        $columns = array_keys($DB->get_columns('logstore_standard_log'));

        // Additional columns to identify course, users.
        $columns = array_merge($columns, [
            'courseshortname',
            'coursefullname',
            'userfirstname',
            'userlastname',
            'realuserfirstname',
            'realuserlastname',
            'relateduserfirstname',
            'relateduserlastname',
        ]);

        // File format.
        $fileformat = settings_manager::get_settings($instanceid, settings_type::STEP)['fileformat'];

        // File name.
        $filename = 'course_log_' . $course->shortname . '_' . date('Y-m-d_H-i-s');

        // Prepare file record.
        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_lcbackupcourselogstep',
            'filearea' => 'course_log',
            'itemid' => $instanceid,
            'filepath' => "/",
            'filename' => $filename,
        ];

        // Delete existing file.
        $fs = \get_file_storage();
        $file = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
            $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename'] . $fileformat);
        if ($file) {
            $file->delete();
        }

        // Write data to file.
        $newfile = dataformat::write_data_to_filearea($filerecord, $fileformat, $columns, $logs);

        $DB->insert_record('tool_lcbackupcourselogstep_metadata', [
            'shortname' => $course->shortname,
            'fullname' => $course->fullname,
            'oldcourseid' => $course->id,
            'fileid' => $newfile->get_id(),
            'timecreated' => time()
        ]);

        // Proceed.
        return step_response::proceed();
    }

    public function instance_settings() {
        return [
            new instance_setting('fileformat', PARAM_TEXT, true),
        ];
    }

    public function extend_add_instance_form_definition($mform) {
        $fileformatoptions = [
            'csv' => 'CSV',
            'json' => 'JSON',
        ];

        // Check if XMLWriter is available.
        // Available data formats are installed under 'dataformat' folder.
        // We need to install https://moodle.org/plugins/dataformat_xml
        $classname = 'dataformat_xml\writer';
        if (class_exists($classname)) {
            $fileformatoptions['xml'] = 'XML';
        }

        $mform->addElement('select', 'fileformat',
            get_string('fileformat', 'tool_lcbackupcourselogstep'),
            $fileformatoptions
        );
        $mform->setType('fileformat', PARAM_TEXT);
        $mform->setDefault('fileformat', 'csv');

    }

    public function get_plugin_settings()
    {
        global $ADMIN;
        // Page to show the logs.
        $ADMIN->add('lifecycle_category', new admin_externalpage('tool_lcbackupcourselogstep_logs',
            get_string('courselogs', 'tool_lcbackupcourselogstep'),
            new moodle_url('/admin/tool/lcbackupcourselogstep/logs.php')));

    }

}
