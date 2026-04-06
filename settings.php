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
 * CE Credit Submission admin settings.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'local_cesubmit_compliance_report',
    get_string('compliancereport', 'local_cesubmit'),
    new core\url('/local/cesubmit/management/compliance_report.php'),
    'local/cesubmit:viewreport'
));

$ADMIN->add('reports', new admin_externalpage(
    'local_cesubmit_send_reminders',
    get_string('sendreminders', 'local_cesubmit'),
    new core\url('/local/cesubmit/management/send_reminders.php'),
    'local/cesubmit:viewreport'
));
