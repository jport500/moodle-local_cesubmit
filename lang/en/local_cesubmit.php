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
 * CE Credit Submission language strings.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'CE Credit Submission';
$string['mydashboard'] = 'My CE Record';
$string['categoryrequirements'] = 'Category Requirements';
$string['cecompliance'] = 'CE Compliance';
$string['creditsprogress'] = '{$a->earned} of {$a->required} CE credits earned';
$string['status_compliant'] = 'Compliant';
$string['status_inprogress'] = 'In Progress';
$string['status_overdue'] = 'Overdue';
$string['status_subperiodalert'] = 'Sub-period Alert';
$string['nocredits'] = 'No CE credits recorded yet';
$string['nocertifications'] = 'No certifications assigned';
$string['credithistory'] = 'Credit History';
$string['date'] = 'Date';
$string['activity'] = 'Activity';
$string['provider'] = 'Provider';
$string['credittype'] = 'Credit Type';
$string['hours'] = 'Hours';
$string['certifieduntil'] = 'Valid until';
$string['daysremaining'] = '{$a} days remaining';
$string['viewmycerecord'] = 'View my CE record';
$string['compliancereport'] = 'CE Compliance Report';
$string['creditsgap'] = 'Credit gap';
$string['creditsearned'] = 'Credits earned';
$string['creditsrequired'] = 'Credits required';
$string['deadline'] = 'Deadline';
$string['certificationstatus'] = 'Certification Status';
$string['creditprogress'] = 'Credit Progress';
$string['overdue'] = 'Overdue';
$string['nodeadline'] = 'No deadline set';
$string['morecreditsneeded'] = '{$a} more credits needed';
$string['sendreminders'] = 'Send CE reminders';
$string['atriskcount'] = '{$a} members are behind on their CE requirements.';
$string['allmembersontrack'] = 'All members are on track with their CE requirements.';
$string['reminderssent'] = '{$a} reminder(s) sent successfully.';
$string['atrisk_subject'] = 'CE Compliance Reminder: {$a->frameworkname}';
$string['atrisk_body'] = 'Dear {$a->userfullname},

This is a reminder that you currently have {$a->creditsearned} of {$a->creditsrequired} CE credits required for {$a->frameworkname}.

You still need {$a->creditsgap} more credits to reach compliance.{$a->categorybreakdown}

Deadline: {$a->deadline}

Please log in to view your CE record and submit any outstanding credits:
{$a->siteurl}local/cesubmit/my/index.php';
$string['messageprovider:atrisk_notification'] = 'CE compliance at-risk reminder';
$string['privacy:metadata'] = 'The CE Credit Submission plugin does not store any personal data. Credit data is stored by tool_mutrain.';
