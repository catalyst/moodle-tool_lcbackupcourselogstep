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
 * This file handles database upgrades for tool_lcbackupcourselogstep.
 *
 * @package   tool_lcbackupcourselogstep
 * @copyright 2024 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles database upgrades for tool_lcbackupcourselogstep.
 *
 * @param int $oldversion The version of the plugin being upgraded from.
 * @return bool True on success, false on failure.
 */
function xmldb_tool_lcbackupcourselogstep_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2024102000) {

        $table = new xmldb_table('tool_lcbackupcourselogstep_metadata');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('oldcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fileid_fk', XMLDB_KEY_FOREIGN, ['fileid'], 'files', ['id']);

        if (!$DB->get_manager()->table_exists($table)) {
            $DB->get_manager()->create_table($table);
        }

        $sql1 = "
            INSERT INTO {tool_lcbackupcourselogstep_metadata} (shortname, fullname, oldcourseid, fileid, timecreated)
            SELECT crs.shortname,
                   crs.fullname,
                   crs.id AS oldcourseid,
                   f.id AS fileid,
                   f.timecreated
              FROM {files} f
              JOIN {context} ctx ON ctx.id = f.contextid
              JOIN {course} crs ON ctx.instanceid = crs.id
             WHERE ctx.contextlevel = :contextlevel
               AND f.component = :component
               AND f.filearea = :filearea
               AND f.filesize > 0
        ";
        $DB->execute($sql1, [
            'contextlevel' => CONTEXT_COURSE,
            'component' => 'tool_lcbackupcourselogstep',
            'filearea' => 'course_log',
        ]);

        $sql2 = "
            UPDATE {files}
               SET contextid = :contextid
             WHERE id IN (
                    SELECT f.id
                      FROM {files} f
                      JOIN {context} ctx ON ctx.id = f.contextid
                     WHERE ctx.contextlevel = :contextlevel
                       AND f.component = :component
                       AND f.filearea = :filearea
                   )
        ";
        $DB->execute($sql2, [
            'contextid' => \context_system::instance()->id,
            'contextlevel' => CONTEXT_COURSE,
            'component' => 'tool_lcbackupcourselogstep',
            'filearea' => 'course_log',
        ]);

        upgrade_plugin_savepoint(true, 2024102000, 'tool', 'lcbackupcourselogstep');
    }

    return true;
}
