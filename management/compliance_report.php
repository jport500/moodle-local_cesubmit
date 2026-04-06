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

/**
 * CE Compliance admin report page.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

require(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/cesubmit:viewreport', $context);

$PAGE->set_url('/local/cesubmit/management/compliance_report.php');
$PAGE->set_context($context);

$title = get_string('compliancereport', 'local_cesubmit');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($title);

$report = \core_reportbuilder\system_report_factory::create(
    \local_cesubmit\reportbuilder\local\systemreports\ce_compliance::class,
    $context
);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Send reminders button.
$remindersurl = new core\url('/local/cesubmit/management/send_reminders.php');
echo html_writer::tag('div',
    html_writer::link($remindersurl, get_string('sendreminders', 'local_cesubmit'), ['class' => 'btn btn-primary mb-3']),
    ['class' => 'mb-2']
);

echo $report->output();
echo $OUTPUT->footer();
