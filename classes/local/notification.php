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

namespace local_cesubmit\local;

use stdClass;

/**
 * CE compliance notification helper.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification {

    /**
     * Send at-risk compliance reminder to a user.
     *
     * @param stdClass $user
     * @param stdClass $framework Object with name and requiredcredits
     * @param float $earned Credits earned so far
     * @param int $timeend Deadline timestamp (0 = no deadline)
     * @param int $sentby Userid who triggered the send (0 = system)
     * @return bool True if sent successfully
     */
    public static function send_atrisk(
        stdClass $user,
        stdClass $framework,
        float $earned,
        int $timeend,
        int $sentby = 0
    ): bool {
        $required = (float)$framework->requiredcredits;
        $gap = max(0.0, $required - $earned);

        $a = new stdClass();
        $a->userfullname = fullname($user);
        $a->frameworkname = format_string($framework->name);
        $a->creditsearned = format_float($earned, 1);
        $a->creditsrequired = format_float($required, 1);
        $a->creditsgap = format_float($gap, 1);
        $a->deadline = $timeend > 0
            ? userdate($timeend, get_string('strftimedate', 'langconfig'))
            : get_string('nodeadline', 'local_cesubmit');
        $a->siteurl = (string)(new \core\url('/'));

        // Category breakdown.
        $a->categorybreakdown = '';
        if (!empty($framework->id)) {
            $windowstart = 0;
            if (!empty($framework->windowdays) && (int)$framework->windowdays > 0) {
                $windowstart = time() - ((int)$framework->windowdays * DAYSECS);
            }
            $categorydetail = \tool_mutrain\api::get_category_compliance_detail(
                (int)$user->id, (int)$framework->id, $windowstart
            );
            if (!empty($categorydetail)) {
                $lines = "\n\nCategory breakdown:\n";
                foreach ($categorydetail as $cd) {
                    $status = $cd['compliant']
                        ? 'OK'
                        : 'need ' . format_float($cd['gap'], 1) . ' more';
                    $lines .= sprintf(
                        "  %-20s %s / %s  %s\n",
                        $cd['categoryname'] . ':',
                        format_float($cd['earned'], 1),
                        format_float($cd['mincredits'], 1),
                        $status
                    );
                }
                $a->categorybreakdown = $lines;
            }
        }

        $subject = get_string('atrisk_subject', 'local_cesubmit', $a);
        $body = get_string('atrisk_body', 'local_cesubmit', $a);
        $bodyhtml = text_to_html($body, false, false, true);

        $message = new \core\message\message();
        $message->notification = 1;
        $message->component = 'local_cesubmit';
        $message->name = 'atrisk_notification';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = $subject;
        $message->contexturl = (new \core\url('/local/cesubmit/my/index.php'))->out(false);
        $message->contexturlname = get_string('mydashboard', 'local_cesubmit');

        return (bool)message_send($message);
    }
}
