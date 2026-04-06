<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon
// phpcs:disable moodle.Files.LineLength.TooLong

/**
 * Send CE compliance reminder notifications to at-risk members.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $USER */

require(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/cesubmit:viewreport', $context);

$confirm = optional_param('confirm', 0, PARAM_INT);
$selected = optional_param_array('selected', [], PARAM_RAW);

$pageurl = new core\url('/local/cesubmit/management/send_reminders.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$title = get_string('sendreminders', 'local_cesubmit');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$reporturl = new core\url('/local/cesubmit/management/compliance_report.php');
$PAGE->navbar->add(get_string('compliancereport', 'local_cesubmit'), $reporturl);
$PAGE->navbar->add($title);

// Query at-risk members.
$sql = "SELECT CONCAT(u.id, '-', f.id) AS uniqid,
               u.id AS userid,
               u.firstname, u.lastname, u.email,
               f.id AS frameworkid,
               f.name AS frameworkname,
               f.requiredcredits,
               COALESCE(mc.credits, 0) AS earned,
               pa.timeend
          FROM {tool_mutrain_framework} f
          JOIN {tool_muprog_item} pi ON pi.creditframeworkid = f.id
          JOIN {tool_muprog_allocation} pa ON pa.programid = pi.programid AND pa.archived = 0
          JOIN {user} u ON u.id = pa.userid AND u.deleted = 0 AND u.suspended = 0
     LEFT JOIN {tool_mutrain_credit} mc ON mc.frameworkid = f.id AND mc.userid = pa.userid
         WHERE f.archived = 0 AND COALESCE(mc.credits, 0) < f.requiredcredits
      ORDER BY pa.timeend ASC, earned ASC";
$atrisk = $DB->get_records_sql($sql);

// Handle form submission.
$sentcount = 0;
$notice = '';
if ($confirm && confirm_sesskey() && !empty($selected)) {
    // Build lookup of at-risk records by compound key.
    $lookup = [];
    foreach ($atrisk as $row) {
        $lookup[$row->userid . '-' . $row->frameworkid] = $row;
    }

    foreach ($selected as $key) {
        $key = clean_param($key, PARAM_RAW);
        if (!isset($lookup[$key])) {
            continue;
        }
        $row = $lookup[$key];
        $user = core_user::get_user($row->userid, '*', MUST_EXIST);
        $fw = $DB->get_record('tool_mutrain_framework', ['id' => $row->frameworkid], '*', MUST_EXIST);
        $sent = \local_cesubmit\local\notification::send_atrisk(
            $user,
            $fw,
            (float)$row->earned,
            (int)$row->timeend,
            (int)$USER->id
        );
        if ($sent) {
            $sentcount++;
        }
    }
    $notice = get_string('reminderssent', 'local_cesubmit', $sentcount);
    // Re-query to refresh data.
    $atrisk = $DB->get_records_sql($sql);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if ($notice) {
    echo $OUTPUT->notification($notice, \core\output\notification::NOTIFY_SUCCESS);
}

if (empty($atrisk)) {
    echo html_writer::tag('p', get_string('allmembersontrack', 'local_cesubmit'), ['class' => 'alert alert-success']);
    echo html_writer::link($reporturl, get_string('compliancereport', 'local_cesubmit'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die;
}

$now = time();

echo html_writer::tag('p', get_string('atriskcount', 'local_cesubmit', count($atrisk)));

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Select all / deselect all.
echo html_writer::tag('p',
    html_writer::link('#', get_string('selectall'), [
        'onclick' => "document.querySelectorAll('input[name=\"selected[]\"]').forEach(function(cb){cb.checked=true});return false;",
    ])
    . ' / '
    . html_writer::link('#', get_string('deselectall'), [
        'onclick' => "document.querySelectorAll('input[name=\"selected[]\"]').forEach(function(cb){cb.checked=false});return false;",
    ]),
    ['class' => 'small']
);

$table = new html_table();
$table->head = [
    '',
    get_string('fullname'),
    get_string('email'),
    get_string('cecompliance', 'local_cesubmit'),
    get_string('creditsearned', 'local_cesubmit'),
    get_string('creditsgap', 'local_cesubmit'),
    get_string('deadline', 'local_cesubmit'),
    get_string('status'),
];
$table->attributes['class'] = 'admintable generaltable';

foreach ($atrisk as $row) {
    $tr = new html_table_row();
    $key = $row->userid . '-' . $row->frameworkid;

    $checkbox = html_writer::checkbox('selected[]', $key, false, '', ['id' => 'sel_' . $key]);
    $tr->cells[] = $checkbox;
    $tr->cells[] = fullname($row);
    $tr->cells[] = s($row->email);
    $tr->cells[] = s($row->frameworkname);
    $tr->cells[] = format_float($row->earned, 1) . ' / ' . format_float($row->requiredcredits, 1);
    $tr->cells[] = format_float(max(0, $row->requiredcredits - $row->earned), 1);

    if ($row->timeend) {
        $tr->cells[] = userdate($row->timeend, get_string('strftimedate', 'langconfig'));
    } else {
        $tr->cells[] = get_string('nodeadline', 'local_cesubmit');
    }

    if ($row->timeend > 0 && $row->timeend < $now) {
        $tr->cells[] = html_writer::span(get_string('status_overdue', 'local_cesubmit'), 'badge badge-danger');
    } else {
        $tr->cells[] = html_writer::span(get_string('status_inprogress', 'local_cesubmit'), 'badge badge-warning');
    }

    $table->data[] = $tr;
}

echo html_writer::table($table);

echo html_writer::tag('div',
    html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('sendreminders', 'local_cesubmit'),
        'class' => 'btn btn-primary mr-2',
    ])
    . html_writer::link($reporturl, get_string('compliancereport', 'local_cesubmit'), ['class' => 'btn btn-secondary']),
    ['class' => 'buttons mt-3']
);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
