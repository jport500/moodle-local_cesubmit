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

namespace local_cesubmit;

/**
 * Event observer for CE credit posting on assignment grading.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {

    /**
     * Handle submission graded event.
     *
     * When an assignment submission is graded with a passing grade,
     * post CE credits to the tool_mutrain ledger if the assignment
     * has the mutrain submission plugin enabled and configured.
     *
     * @param \mod_assign\event\submission_graded $event
     * @return void
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event): void {
        global $CFG, $DB, $USER;

        try {
            // a. Get the grade record.
            $grade = $event->get_record_snapshot('assign_grades', $event->objectid);

            // b. Bail if grade is not positive (ungraded or zero).
            if ($grade->grade <= 0) {
                return;
            }

            // c. Load the assign instance.
            require_once($CFG->dirroot . '/mod/assign/locallib.php');

            $cm = get_coursemodule_from_instance('assign', $grade->assignment, $event->courseid, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $assign = new \assign($context, $cm, get_course($event->courseid));

            // d. Check mutrain submission plugin is enabled.
            $plugin = $assign->get_submission_plugin_by_type('mutrain');
            if (!$plugin || !$plugin->is_enabled()) {
                return;
            }

            // e. Get framework id from plugin config.
            $frameworkid = (int)$plugin->get_config('frameworkid');
            if (!$frameworkid) {
                return;
            }

            // f. Get student submission for this attempt.
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $grade->assignment,
                'userid' => $grade->userid,
                'attemptnumber' => $grade->attemptnumber,
            ]);
            if (!$submission) {
                return;
            }

            // g. Get CE submission data.
            $cedata = $DB->get_record('assignsubmission_mutrain', [
                'assignment' => $grade->assignment,
                'submission' => $submission->id,
            ]);
            if (!$cedata) {
                return;
            }

            // h. Prevent duplicate posting.
            if (\tool_mutrain\api::credit_exists(
                $grade->userid,
                $frameworkid,
                'external_submission',
                (int)$submission->id
            )) {
                return;
            }

            // i. Post the credit.
            \tool_mutrain\api::post_credit(
                $grade->userid,
                $frameworkid,
                (float)$cedata->hoursclaimed,
                'external_submission',
                (int)$submission->id,
                (int)$cedata->dateofactivity,
                [
                    'activityname' => $cedata->activityname,
                    'provider' => $cedata->provider,
                    'credittype' => $cedata->credittype,
                    'gradedby' => $grade->grader,
                ],
                (int)$USER->id
            );

            // j. Sync the tool_mutrain_credit cache so dashboards see the update.
            \tool_mutrain\local\framework::sync_credits(
                (int)$submission->userid,
                (int)$frameworkid
            );
        } catch (\Throwable $e) {
            debugging('local_cesubmit: Error posting CE credit: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
