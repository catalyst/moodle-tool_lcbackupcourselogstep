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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/tablelib.php');

class log_table extends \table_sql
{

    /**
     * @var array "cached" lang strings
     */
    private $strings;

    /**
     * Constructor for delayed_courses_table.
     *
     * @throws \coding_exception
     */
    public function __construct($filterdata = [])
    {
        global $DB;

        parent::__construct('tool_lcbackupcourselogstep-log');

        // Action buttons string
        $this->strings = [
            'download' => get_string('download')
        ];

        // Build the SQL.
        $fields = 'f.id as id,
                   md.oldcourseid as courseid, md.shortname as courseshortname, md.fullname as coursefullname,
                   f.filename as filename, f.filesize as filesize, f.timecreated as createdat';
        $from = '{files} f
                 JOIN {context} c ON c.id = f.contextid
                 LEFT JOIN {tool_lcbackupcourselogstep_metadata} md ON f.id = md.fileid';         

        $where = ["f.component = :component AND filename <> '.'"];
        $params = ['component' => 'tool_lcbackupcourselogstep'];

        // Filtering.
        if ($filterdata) {
            if ($filterdata->shortname) {
                $where[] = $DB->sql_like('md.shortname', ':shortname', false, false);
                $params['shortname'] = '%' . $DB->sql_like_escape($filterdata->shortname) . '%';
            }

            if ($filterdata->fullname) {
                $where[] = $DB->sql_like('md.fullname', ':fullname', false, false);
                $params['fullname'] = '%' . $DB->sql_like_escape($filterdata->fullname) . '%';
            }

            if ($filterdata->courseid) {
                $where[] = 'md.oldcourseid = :courseid';
                $params['courseid'] = $filterdata->courseid;
            }
        }

        $where = join(" AND ", $where);

        $this->set_sql($fields, $from, $where, $params);


        // Columns.
        $this->define_columns([
            'courseid',
            'courseshortname',
            'coursefullname',
            'filename',
            'filesize',
            'createdat',
            'actions',
        ]);

        $this->define_headers([
            get_string('course_id_header', 'tool_lcbackupcourselogstep'),
            get_string('course_shortname_header', 'tool_lcbackupcourselogstep'),
            get_string('course_fullname_header', 'tool_lcbackupcourselogstep'),
            get_string('filename_header', 'tool_lcbackupcourselogstep'),
            get_string('filesize_header', 'tool_lcbackupcoursestep'),
            get_string('createdat_header', 'tool_lcbackupcourselogstep'),
            get_string('actions_header', 'tool_lcbackupcourselogstep'),
        ]);

        // Set default sorting.
        $this->sortable(true, 'createdat', SORT_DESC);
        $this->sortable(true, 'filesize', SORT_DESC);
        $this->collapsible(true);
        $this->initialbars(true);
        $this->set_attribute('class', 'admintable generaltable');
    }

    /**
     * Define download action.
     *
     * @param $row
     * @return bool|string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions($row)
    {
        global $OUTPUT;

        $actionmenu = new \action_menu();
        $actionmenu->add_primary_action(
            new \action_menu_link_primary(
                new \moodle_url('', ['action' => 'download', 'id' => $row->id, 'sesskey' => sesskey()]),
                new \pix_icon('t/download', $this->strings['download']),
                $this->strings['download']
            )
        );

        return $OUTPUT->render($actionmenu);
    }

    /**
     * Time when the file is created, displayed in user-friendly format.
     *
     * @param $row
     * @return string
     * @throws \coding_exception
     */
    public function col_createdat($row)
    {
        return userdate($row->createdat);
    }

    /**
     * Display size in a user-friendly format.
     *
     * @param $row
     * @return string
     */
    public function col_filesize($row) {
        return display_size($row->filesize);
    }

}
