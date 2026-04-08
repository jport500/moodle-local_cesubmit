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

namespace local_cesubmit\output\my;

/**
 * Renderer for the CE compliance dashboard.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Build framework progress data for a user.
     *
     * @param int $userid
     * @return array [frameworks data array, has_any bool]
     */
    private function get_frameworks_data(int $userid): array {
        global $DB;

        $now = time();
        $dateformat = get_string('strftimedate', 'langconfig');

        // Get frameworks with credits and allocation deadlines.
        $sql = "SELECT f.id, f.name, f.requiredcredits, f.windowdays,
                       COALESCE(mc.credits, 0) AS earned,
                       mc.proratedcredits,
                       pa.timeend, pa.timedue
                  FROM {tool_mutrain_framework} f
                  JOIN {tool_muprog_item} pi ON pi.creditframeworkid = f.id
                  JOIN {tool_muprog_allocation} pa ON pa.programid = pi.programid
                       AND pa.userid = :userid2 AND pa.archived = 0
             LEFT JOIN {tool_mutrain_credit} mc ON mc.frameworkid = f.id AND mc.userid = :userid1
                 WHERE f.archived = 0
              GROUP BY f.id, f.name, f.requiredcredits, f.windowdays, mc.credits, mc.proratedcredits, pa.timeend, pa.timedue
              ORDER BY f.id ASC";
        $rows = $DB->get_records_sql($sql, ['userid1' => $userid, 'userid2' => $userid]);

        // Also pick up frameworks with ledger credits but no program allocation.
        $sql2 = "SELECT DISTINCT f.id, f.name, f.requiredcredits, f.windowdays,
                        COALESCE(mc.credits, 0) AS earned,
                        0 AS timeend, 0 AS timedue
                   FROM {tool_mutrain_framework} f
                   JOIN {tool_mutrain_ledger} ml ON ml.frameworkid = f.id AND ml.userid = :userid2 AND ml.revokedtime IS NULL
              LEFT JOIN {tool_mutrain_credit} mc ON mc.frameworkid = f.id AND mc.userid = :userid1
                  WHERE f.archived = 0 AND f.id NOT IN (
                        SELECT pi2.creditframeworkid
                          FROM {tool_muprog_item} pi2
                          JOIN {tool_muprog_allocation} pa2 ON pa2.programid = pi2.programid
                               AND pa2.userid = :userid3 AND pa2.archived = 0
                         WHERE pi2.creditframeworkid IS NOT NULL
                  )
               ORDER BY f.id ASC";
        $extra = $DB->get_records_sql($sql2, ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid]);
        foreach ($extra as $row) {
            if (!isset($rows[$row->id])) {
                $rows[$row->id] = $row;
            }
        }

        $fwdata = [];
        foreach ($rows as $fw) {
            $earned = (float)$fw->earned;
            $proratedcredits = (isset($fw->proratedcredits) && $fw->proratedcredits !== null)
                ? (float)$fw->proratedcredits : null;
            $required = $proratedcredits ?? (float)$fw->requiredcredits;
            $isprorated = $proratedcredits !== null && $proratedcredits < (float)$fw->requiredcredits;
            $timeend = (int)$fw->timeend;
            $pct = $required > 0 ? min(100, round(($earned / $required) * 100)) : 0;
            $compliant = $earned >= $required;
            $gap = max(0.0, $required - $earned);
            $isoverdue = $timeend > 0 && $timeend < $now;

            // Category compliance.
            $windowstart = 0;
            if (isset($fw->windowdays) && (int)$fw->windowdays > 0) {
                $windowstart = $now - ((int)$fw->windowdays * DAYSECS);
            }
            $categorydetail = \tool_mutrain\api::get_category_compliance_detail(
                $userid, (int)$fw->id, $windowstart
            );
            $categorydata = [];
            $allcategorycompliant = true;
            foreach ($categorydetail as $cd) {
                $catpct = $cd['mincredits'] > 0
                    ? min(100, round(($cd['earned'] / $cd['mincredits']) * 100))
                    : 100;
                if (!$cd['compliant']) {
                    $allcategorycompliant = false;
                }
                $categorydata[] = [
                    'categoryname' => $cd['categoryname'],
                    'mincredits' => format_float($cd['mincredits'], 1),
                    'earned' => format_float($cd['earned'], 1),
                    'compliant' => $cd['compliant'],
                    'notcompliant' => !$cd['compliant'],
                    'gap' => format_float($cd['gap'], 1),
                    'hascategorygap' => $cd['gap'] > 0,
                    'categorygaptext' => $cd['gap'] > 0
                        ? format_float($cd['gap'], 1) . ' more needed'
                        : '',
                    'pct' => $catpct,
                    'pctwidth' => $catpct,
                ];
            }

            // Override compliance if category requirements are not met.
            if ($compliant && !$allcategorycompliant) {
                $compliant = false;
            }

            if ($compliant) {
                $status = 'compliant';
            } else if ($isoverdue) {
                $status = 'overdue';
            } else {
                $status = 'inprogress';
            }

            // Compute days remaining.
            $daysrem = null;
            if ($timeend > 0 && $timeend > $now) {
                $daysrem = (int)ceil(($timeend - $now) / DAYSECS);
            }

            // Sub-period compliance.
            $subperioddetail = \tool_mutrain\api::get_user_subperiod_detail(
                $userid, (int)$fw->id
            );
            $subperiods = [];
            $hassubperiods = false;
            $anysubperiodalert = false;
            if (!empty($subperioddetail)) {
                $hassubperiods = true;
                foreach ($subperioddetail as $spd) {
                    $spcats = [];
                    foreach ($spd->categories as $cat) {
                        $spcats[] = [
                            'categoryname' => $cat->categoryname,
                            'mincredits'   => format_float($cat->mincredits, 1),
                            'earned'       => format_float($cat->earned, 1),
                            'pass'         => $cat->pass,
                            'notpass'      => !$cat->pass,
                        ];
                    }
                    $subperiods[] = [
                        'name'        => $spd->subperiod->name,
                        'windowstart' => userdate($spd->windowstart, '%Y-%m-%d'),
                        'windowend'   => userdate($spd->windowend, '%Y-%m-%d'),
                        'isclosed'    => $spd->isclosed,
                        'totalearned' => format_float($spd->totalearned, 1),
                        'requiredcredits' => (float)$spd->subperiod->requiredcredits > 0
                            ? format_float((float)$spd->subperiod->requiredcredits, 1)
                            : null,
                        'hasrequiredcredits' => (float)$spd->subperiod->requiredcredits > 0,
                        'pass'        => $spd->pass,
                        'notpass'     => !$spd->pass,
                        'alert'       => $spd->alert,
                        'categories'  => $spcats,
                        'hasspcats'   => !empty($spcats),
                    ];
                    if ($spd->alert) {
                        $anysubperiodalert = true;
                    }
                }
            }

            $fwdata[] = [
                'id' => (int)$fw->id,
                'hassubperiods' => $hassubperiods,
                'subperiods' => $subperiods,
                'anysubperiodalert' => $anysubperiodalert,
                'name' => $fw->name,
                'earned' => format_float($earned, 1),
                'required' => format_float($required, 0),
                'percentage' => $pct,
                'status' => $status,
                'status_compliant' => $status === 'compliant',
                'status_inprogress' => $status === 'inprogress',
                'status_overdue' => $status === 'overdue',
                'statuslabel' => get_string('status_' . $status, 'local_cesubmit'),
                'deadline' => $timeend > 0 ? userdate($timeend, $dateformat) : get_string('nodeadline', 'local_cesubmit'),
                'hasdeadline' => $timeend > 0,
                'daysremaining' => $daysrem,
                'hasdaysremaining' => $daysrem !== null,
                'daysremainingtext' => $daysrem !== null ? get_string('daysremaining', 'local_cesubmit', $daysrem) : '',
                'days_green' => $daysrem !== null && $daysrem > 180,
                'days_amber' => $daysrem !== null && $daysrem >= 60 && $daysrem <= 180,
                'days_red' => $daysrem !== null && $daysrem < 60,
                'isoverdue' => $isoverdue,
                'creditsgap' => format_float($gap, 1),
                'hascreditsgap' => $gap > 0,
                'creditsgaptext' => $gap > 0 ? get_string('morecreditsneeded', 'local_cesubmit', format_float($gap, 1)) : '',
                'hascategories' => !empty($categorydata),
                'categories' => $categorydata,
                'allcategorycompliant' => $allcategorycompliant,
                'hascategorywarning' => !empty($categorydata) && !$allcategorycompliant,
                'isprorated' => $isprorated,
                'proratedcredits' => $isprorated ? format_float($proratedcredits, 1) : null,
                'fullrequired' => format_float((float)$fw->requiredcredits, 1),
            ];
        }

        return [$fwdata, !empty($fwdata)];
    }

    /**
     * Render block content for the sidebar block.
     *
     * @param int $userid
     * @return string HTML
     */
    public function render_block_content(int $userid): string {
        [$frameworks, $hasframeworks] = $this->get_frameworks_data($userid);

        $dashboardurl = new \core\url('/local/cesubmit/my/index.php');

        $context = [
            'frameworks' => $frameworks,
            'hasframeworks' => $hasframeworks,
            'dashboardurl' => $dashboardurl->out(false),
            'viewlinktext' => get_string('viewmycerecord', 'local_cesubmit'),
            'nocreditstext' => get_string('nocredits', 'local_cesubmit'),
        ];

        return $this->render_from_template('local_cesubmit/my/block_content', $context);
    }

    /**
     * Render the full dashboard page.
     *
     * @param int $userid
     * @return string HTML
     */
    public function render_dashboard(int $userid): string {
        global $DB;

        $now = time();

        // Section 1: Certification status.
        $certifications = [];
        $sql = "SELECT ca.*, c.fullname AS certname
                  FROM {tool_mucertify_assignment} ca
                  JOIN {tool_mucertify_certification} c ON c.id = ca.certificationid
                 WHERE ca.userid = :userid AND ca.archived = 0
              ORDER BY c.fullname ASC";
        $certassigns = $DB->get_records_sql($sql, ['userid' => $userid]);
        $maxreasonableexpiry = strtotime('+20 years');
        foreach ($certassigns as $ca) {
            $expiry = $ca->timecertifieduntil ? (int)$ca->timecertifieduntil : 0;
            $certified = !empty($ca->timecertifiedfrom);

            // Filter out far-future placeholder dates.
            $expiryisreal = $expiry > 0 && $expiry < $maxreasonableexpiry;

            if ($certified && $expiry > $now) {
                $status = 'compliant';
                $daysrem = $expiryisreal ? (int)ceil(($expiry - $now) / DAYSECS) : 0;
            } else if ($expiry > 0 && $expiry < $now) {
                $status = 'overdue';
                $daysrem = 0;
            } else {
                $status = 'inprogress';
                $daysrem = 0;
            }

            $certifications[] = [
                'name' => $ca->certname,
                'status' => $status,
                'status_compliant' => $status === 'compliant',
                'status_inprogress' => $status === 'inprogress',
                'status_overdue' => $status === 'overdue',
                'statuslabel' => get_string('status_' . $status, 'local_cesubmit'),
                'validuntil' => $expiryisreal ? userdate($expiry, get_string('strftimedate', 'langconfig')) : '',
                'hasexpiry' => $expiryisreal,
                'daysremaining' => $daysrem > 0 ? get_string('daysremaining', 'local_cesubmit', $daysrem) : '',
                'hasdaysremaining' => $daysrem > 0,
            ];
        }

        // Section 2: Framework progress.
        [$frameworks, $hasframeworks] = $this->get_frameworks_data($userid);

        // Section 3: Credit history.
        $transcript = \tool_mutrain\api::get_user_full_transcript($userid, true);
        $historybyfw = [];
        foreach ($frameworks as $fwd) {
            $fwid = $fwd['id'];
            $entries = $transcript[$fwid] ?? [];
            $rows = [];
            foreach ($entries as $entry) {
                $evidence = $entry->evidencejson ? json_decode($entry->evidencejson, true) : [];
                $revoked = $entry->revokedtime !== null;
                $rows[] = [
                    'date' => userdate((int)$entry->timecredited, get_string('strftimedate', 'langconfig')),
                    'activity' => $evidence['activityname'] ?? $entry->sourcetype,
                    'provider' => $evidence['provider'] ?? '',
                    'credittype' => $evidence['credittype'] ?? '',
                    'hours' => format_float((float)$entry->credits, 1),
                    'revoked' => $revoked,
                ];
            }
            $historybyfw[] = [
                'frameworkname' => $fwd['name'],
                'entries' => $rows,
                'hasentries' => !empty($rows),
            ];
        }

        $context = [
            'certifications' => $certifications,
            'hascertifications' => !empty($certifications),
            'nocertificationstext' => get_string('nocertifications', 'local_cesubmit'),
            'frameworks' => $frameworks,
            'hasframeworks' => $hasframeworks,
            'nocreditstext' => get_string('nocredits', 'local_cesubmit'),
            'historybyfw' => $historybyfw,
            'credithistorylabel' => get_string('credithistory', 'local_cesubmit'),
            'datelabel' => get_string('date', 'local_cesubmit'),
            'activitylabel' => get_string('activity', 'local_cesubmit'),
            'providerlabel' => get_string('provider', 'local_cesubmit'),
            'credittypelabel' => get_string('credittype', 'local_cesubmit'),
            'hourslabel' => get_string('hours', 'local_cesubmit'),
        ];

        return $this->render_from_template('local_cesubmit/my/dashboard', $context);
    }
}
