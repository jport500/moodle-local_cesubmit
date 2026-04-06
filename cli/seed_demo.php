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
 * ACPP demo data seeder.
 *
 * Idempotent: deletes previous demo data (ACPP-DEMO- prefix) and rebuilds.
 *
 * Usage: php local/cesubmit/cli/seed_demo.php
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
global $DB, $CFG;

$syscontext = context_system::instance();

// ============================================================
// RESET — Delete all previous demo data
// ============================================================
echo "[RESET] Deleting previous demo data...\n";

// Remove cesubmit block instances for demo users and system default.
$demousersforblocks = $DB->get_records_select('user', "idnumber LIKE 'ACPP-DEMO-USER-%'");
foreach ($demousersforblocks as $u) {
    $ctx = context_user::instance($u->id);
    $DB->delete_records('block_instances', ['blockname' => 'cesubmit', 'parentcontextid' => $ctx->id]);
}
$DB->delete_records('block_instances', ['blockname' => 'cesubmit', 'parentcontextid' => $syscontext->id]);
echo "[RESET] Removed cesubmit block instances\n";

// Category rules.
$DB->delete_records_select(
    'tool_mutrain_framework_category',
    "frameworkid IN (SELECT id FROM {tool_mutrain_framework} WHERE idnumber LIKE 'ACPP-DEMO-FW-%')"
);
echo "[RESET] Removed framework category rules\n";

// Ledger entries from demo seeder.
$demoframeworks = $DB->get_records_select('tool_mutrain_framework', "idnumber LIKE 'ACPP-DEMO-FW-%'");
foreach ($demoframeworks as $fw) {
    $DB->delete_records('tool_mutrain_ledger', ['frameworkid' => $fw->id]);
    $DB->delete_records('tool_mutrain_credit', ['frameworkid' => $fw->id]);
}

// Certification assignments + certifications.
$democerts = $DB->get_records_select('tool_mucertify_certification', "idnumber LIKE 'ACPP-DEMO-CERT-%'");
foreach ($democerts as $cert) {
    $DB->delete_records('tool_mucertify_period', ['certificationid' => $cert->id]);
    $DB->delete_records('tool_mucertify_assignment', ['certificationid' => $cert->id]);
    $DB->delete_records('tool_mucertify_source', ['certificationid' => $cert->id]);
    $DB->delete_records('tool_mucertify_certification', ['id' => $cert->id]);
}

// Program allocations + items + programs.
$demoprogs = $DB->get_records_select('tool_muprog_program', "idnumber LIKE 'ACPP-DEMO-PROG-%'");
foreach ($demoprogs as $prog) {
    $DB->delete_records('tool_muprog_allocation', ['programid' => $prog->id]);
    $DB->delete_records('tool_muprog_completion', ['allocationid' => 0]); // Safety.
    // Delete completions for allocations of this program.
    $allocationids = $DB->get_fieldset_select('tool_muprog_allocation', 'id', "programid = ?", [$prog->id]);
    if ($allocationids) {
        [$insql, $params] = $DB->get_in_or_equal($allocationids);
        $DB->delete_records_select('tool_muprog_completion', "allocationid $insql", $params);
    }
    $DB->delete_records('tool_muprog_item', ['programid' => $prog->id]);
    $DB->delete_records('tool_muprog_source', ['programid' => $prog->id]);
    $DB->delete_records('tool_muprog_program', ['id' => $prog->id]);
}

// Frameworks.
foreach ($demoframeworks as $fw) {
    $DB->delete_records('tool_mutrain_field', ['frameworkid' => $fw->id]);
    $DB->delete_records('tool_mutrain_framework', ['id' => $fw->id]);
}

// Demo courses (delete_course handles all cascade: modules, enrolments, context, etc.).
require_once($CFG->dirroot . '/course/lib.php');
$democourses = $DB->get_records_select('course', "shortname LIKE 'ACPP-DEMO-COURSE-%'");
foreach ($democourses as $dc) {
    delete_course($dc->id, false);
}

// Users.
$demousers = $DB->get_records_select('user', "idnumber LIKE 'ACPP-DEMO-USER-%'");
foreach ($demousers as $u) {
    $DB->delete_records('user', ['id' => $u->id]);
}

// Categories.
$democats = $DB->get_records_select('course_categories', "idnumber LIKE 'ACPP-DEMO-CAT-%'", null, 'id DESC');
foreach ($democats as $cat) {
    $DB->delete_records('course_categories', ['id' => $cat->id]);
}

echo "[RESET] Done.\n\n";

// ============================================================
// CATEGORIES
// ============================================================
$catdata = [
    ['fullname' => 'ACPP Fixed Cycle Demo',   'idnumber' => 'ACPP-DEMO-CAT-FIXED'],
    ['fullname' => 'ACPP Rolling Cycle Demo',  'idnumber' => 'ACPP-DEMO-CAT-ROLLING'],
    ['fullname' => 'ACPP Anniversary Demo',    'idnumber' => 'ACPP-DEMO-CAT-ANNIV'],
];
$categories = [];
foreach ($catdata as $cd) {
    $cat = core_course_category::create((object)[
        'name' => $cd['fullname'],
        'idnumber' => $cd['idnumber'],
        'parent' => 0,
    ]);
    $categories[$cd['idnumber']] = $cat;
    echo "[CAT] Created: {$cd['fullname']} (id={$cat->id})\n";
}

// ============================================================
// CREDIT FRAMEWORKS
// ============================================================
$fwdata = [
    ['name' => 'ACPP CE Credits (Fixed)',   'idnumber' => 'ACPP-DEMO-FW-FIXED',   'requiredcredits' => '30'],
    ['name' => 'ACPP CE Credits (Rolling)', 'idnumber' => 'ACPP-DEMO-FW-ROLLING', 'requiredcredits' => '30'],
    ['name' => 'ACPP CE Credits (Anniv)',   'idnumber' => 'ACPP-DEMO-FW-ANNIV',   'requiredcredits' => '30'],
];
$frameworks = [];
foreach ($fwdata as $fd) {
    $fw = \tool_mutrain\local\framework::create([
        'name' => $fd['name'],
        'idnumber' => $fd['idnumber'],
        'requiredcredits' => $fd['requiredcredits'],
        'contextid' => $syscontext->id,
        'publicaccess' => 1,
    ]);
    $frameworks[$fd['idnumber']] = $fw;
    echo "[FW] Created: {$fd['name']} (id={$fw->id})\n";
}

// Set rolling window for the rolling framework.
$DB->set_field('tool_mutrain_framework', 'windowdays', 730, ['id' => $frameworks['ACPP-DEMO-FW-ROLLING']->id]);
$frameworks['ACPP-DEMO-FW-ROLLING']->windowdays = 730;
echo "[FW] Set windowdays=730 on ACPP-DEMO-FW-ROLLING\n";

// Enable proration on the fixed framework.
$DB->set_field('tool_mutrain_framework', 'cycledays', 730, ['id' => $frameworks['ACPP-DEMO-FW-FIXED']->id]);
$DB->set_field('tool_mutrain_framework', 'proratejoins', 1, ['id' => $frameworks['ACPP-DEMO-FW-FIXED']->id]);
echo "[FW] Enabled proration on ACPP-DEMO-FW-FIXED (cycledays=730, proratejoins=1)\n";

// Category rules for rolling framework (demonstrates enforcement).
$rollingfw = $frameworks['ACPP-DEMO-FW-ROLLING'];
$categoryrules = [
    ['categoryname' => 'Ethics',  'mincredits' => 2.0, 'sortorder' => 1],
    ['categoryname' => 'General', 'mincredits' => 5.0, 'sortorder' => 2],
];
foreach ($categoryrules as $rule) {
    $DB->insert_record('tool_mutrain_framework_category', (object)[
        'frameworkid' => $rollingfw->id,
        'categoryname' => $rule['categoryname'],
        'mincredits' => $rule['mincredits'],
        'sortorder' => $rule['sortorder'],
    ]);
    echo "[CATEGORY] Added rule: {$rule['categoryname']} min={$rule['mincredits']} credits on rolling framework\n";
}

// ============================================================
// PROGRAMS
// ============================================================
$enddate = strtotime('2026-12-31 23:59:59');
$progdata = [
    [
        'fullname' => 'ACPP 2025-2026 CE Program (Fixed)',
        'idnumber' => 'ACPP-DEMO-PROG-FIXED',
        'fw_key'   => 'ACPP-DEMO-FW-FIXED',
    ],
    [
        'fullname' => 'ACPP Rolling CE Program (24 months)',
        'idnumber' => 'ACPP-DEMO-PROG-ROLLING',
        'fw_key'   => 'ACPP-DEMO-FW-ROLLING',
    ],
    [
        'fullname' => 'ACPP Anniversary CE Program',
        'idnumber' => 'ACPP-DEMO-PROG-ANNIV',
        'fw_key'   => 'ACPP-DEMO-FW-ANNIV',
    ],
];
$programs = [];
foreach ($progdata as $pd) {
    $program = \tool_muprog\local\program::create((object)[
        'contextid' => $syscontext->id,
        'fullname' => $pd['fullname'],
        'idnumber' => $pd['idnumber'],
        'publicaccess' => 1,
        'startdate' => ['type' => 'allocation'],
        'duedate' => ['type' => 'notset'],
        'enddate' => ['type' => 'date', 'date' => $enddate],
    ]);
    $programs[$pd['idnumber']] = $program;
    echo "[PROG] Created: {$pd['fullname']} (id={$program->id})\n";

    // Enable manual source.
    \tool_muprog\local\source\base::update_source((object)[
        'enable' => 1,
        'programid' => $program->id,
        'type' => 'manual',
    ]);

    // Add credit framework item.
    $fw = $frameworks[$pd['fw_key']];
    $top = \tool_muprog\local\program::load_content((int)$program->id);
    $top->append_credits($top, (int)$fw->id);
    echo "  [ITEM] Added credits item: {$fw->name}\n";
}

// ============================================================
// CERTIFICATIONS
// ============================================================
$certdata = [
    [
        'fullname' => 'ACPP Certified Practitioner (Fixed)',
        'idnumber' => 'ACPP-DEMO-CERT-FIXED',
        'prog_key' => 'ACPP-DEMO-PROG-FIXED',
    ],
    [
        'fullname' => 'ACPP Certified Practitioner (Rolling)',
        'idnumber' => 'ACPP-DEMO-CERT-ROLLING',
        'prog_key' => 'ACPP-DEMO-PROG-ROLLING',
    ],
    [
        'fullname' => 'ACPP Certified Practitioner (Anniversary)',
        'idnumber' => 'ACPP-DEMO-CERT-ANNIV',
        'prog_key' => 'ACPP-DEMO-PROG-ANNIV',
    ],
];
$certifications = [];
foreach ($certdata as $cd) {
    $prog = $programs[$cd['prog_key']];
    $cert = \tool_mucertify\local\certification::create((object)[
        'contextid' => $syscontext->id,
        'fullname' => $cd['fullname'],
        'idnumber' => $cd['idnumber'],
        'publicaccess' => 1,
        'programid1' => (int)$prog->id,
    ]);
    $certifications[$cd['idnumber']] = $cert;
    echo "[CERT] Created: {$cd['fullname']} (id={$cert->id})\n";

    // Enable manual source.
    \tool_mucertify\local\source\base::update_source((object)[
        'enable' => 1,
        'certificationid' => $cert->id,
        'type' => 'manual',
    ]);
}

// ============================================================
// USERS
// ============================================================
$userdata = [
    // Fixed cycle.
    ['username' => 'acpp.fixed.compliant',  'first' => 'Alex',   'last' => 'Chen (Fixed - Compliant)',  'email' => 'acpp.fixed.compliant@acpp-demo.invalid',  'idnumber' => 'ACPP-DEMO-USER-FC', 'cycle' => 'FIXED',   'persona' => 'compliant'],
    ['username' => 'acpp.fixed.atrisk',     'first' => 'Morgan', 'last' => 'Riley (Fixed - At Risk)',   'email' => 'acpp.fixed.atrisk@acpp-demo.invalid',     'idnumber' => 'ACPP-DEMO-USER-FA', 'cycle' => 'FIXED',   'persona' => 'atrisk'],
    ['username' => 'acpp.fixed.lapsed',     'first' => 'Jordan', 'last' => 'Park (Fixed - Lapsed)',     'email' => 'acpp.fixed.lapsed@acpp-demo.invalid',     'idnumber' => 'ACPP-DEMO-USER-FL', 'cycle' => 'FIXED',   'persona' => 'lapsed'],
    ['username' => 'acpp.fixed.midcycle',   'first' => 'Sam',    'last' => 'Torres (Fixed - Mid-Cycle)','email' => 'acpp.fixed.midcycle@acpp-demo.invalid',   'idnumber' => 'ACPP-DEMO-USER-FM', 'cycle' => 'FIXED',   'persona' => 'midcycle'],
    // Rolling cycle.
    ['username' => 'acpp.rolling.compliant','first' => 'Taylor', 'last' => 'Kim (Rolling - Compliant)', 'email' => 'acpp.rolling.compliant@acpp-demo.invalid', 'idnumber' => 'ACPP-DEMO-USER-RC', 'cycle' => 'ROLLING', 'persona' => 'compliant'],
    ['username' => 'acpp.rolling.atrisk',   'first' => 'Casey',  'last' => 'Lee (Rolling - At Risk)',   'email' => 'acpp.rolling.atrisk@acpp-demo.invalid',   'idnumber' => 'ACPP-DEMO-USER-RA', 'cycle' => 'ROLLING', 'persona' => 'atrisk'],
    ['username' => 'acpp.rolling.lapsed',   'first' => 'Riley',  'last' => 'Cho (Rolling - Lapsed)',    'email' => 'acpp.rolling.lapsed@acpp-demo.invalid',   'idnumber' => 'ACPP-DEMO-USER-RL', 'cycle' => 'ROLLING', 'persona' => 'lapsed'],
    // Anniversary cycle.
    ['username' => 'acpp.anniv.compliant',  'first' => 'Jamie',  'last' => 'Patel (Anniv - Compliant)', 'email' => 'acpp.anniv.compliant@acpp-demo.invalid',  'idnumber' => 'ACPP-DEMO-USER-AC', 'cycle' => 'ANNIV',   'persona' => 'compliant'],
    ['username' => 'acpp.anniv.atrisk',     'first' => 'Drew',   'last' => 'Ngo (Anniv - At Risk)',     'email' => 'acpp.anniv.atrisk@acpp-demo.invalid',     'idnumber' => 'ACPP-DEMO-USER-AA', 'cycle' => 'ANNIV',   'persona' => 'atrisk'],
    ['username' => 'acpp.anniv.lapsed',     'first' => 'Reese',  'last' => 'Santos (Anniv - Lapsed)',   'email' => 'acpp.anniv.lapsed@acpp-demo.invalid',     'idnumber' => 'ACPP-DEMO-USER-AL', 'cycle' => 'ANNIV',   'persona' => 'lapsed'],
];

$password = hash_internal_user_password('ACPPdemo2026!');
$users = [];
foreach ($userdata as $ud) {
    $user = (object)[
        'username'  => $ud['username'],
        'password'  => $password,
        'firstname' => $ud['first'],
        'lastname'  => $ud['last'],
        'email'     => $ud['email'],
        'idnumber'  => $ud['idnumber'],
        'confirmed' => 1,
        'auth'      => 'manual',
        'lang'      => 'en',
        'country'   => 'US',
        'timezone'  => 'America/Chicago',
        'mnethostid' => $CFG->mnet_localhost_id,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $user->id = $DB->insert_record('user', $user);
    $ud['id'] = $user->id;
    $users[] = $ud;
    echo "[USER] Created: {$ud['username']} (id={$user->id})\n";
}

// ============================================================
// PROGRAM ALLOCATIONS
// ============================================================
echo "\n";
$cycletoprog = [
    'FIXED'   => 'ACPP-DEMO-PROG-FIXED',
    'ROLLING' => 'ACPP-DEMO-PROG-ROLLING',
    'ANNIV'   => 'ACPP-DEMO-PROG-ANNIV',
];
$cycletocert = [
    'FIXED'   => 'ACPP-DEMO-CERT-FIXED',
    'ROLLING' => 'ACPP-DEMO-CERT-ROLLING',
    'ANNIV'   => 'ACPP-DEMO-CERT-ANNIV',
];
$cycletofw = [
    'FIXED'   => 'ACPP-DEMO-FW-FIXED',
    'ROLLING' => 'ACPP-DEMO-FW-ROLLING',
    'ANNIV'   => 'ACPP-DEMO-FW-ANNIV',
];

foreach ($users as &$ud) {
    $progkey = $cycletoprog[$ud['cycle']];
    $program = $programs[$progkey];
    $source = $DB->get_record('tool_muprog_source', ['type' => 'manual', 'programid' => $program->id], '*', MUST_EXIST);

    if ($ud['persona'] === 'lapsed') {
        $dateoverrides = [
            'timestart' => strtotime('2023-01-01'),
            'timeend' => strtotime('2024-12-31 23:59:59'),
        ];
    } else if ($ud['persona'] === 'midcycle') {
        $dateoverrides = [
            'timestart' => strtotime('2026-01-01'),
            'timeend' => strtotime('2026-12-31 23:59:59'),
        ];
    } else {
        $dateoverrides = [
            'timestart' => strtotime('2025-01-01'),
            'timeend' => strtotime('2026-12-31 23:59:59'),
        ];
    }

    $allocationids = \tool_muprog\local\source\manual::allocate_users(
        (int)$program->id, (int)$source->id, [$ud['id']], $dateoverrides
    );
    echo "[ALLOC] Allocated {$ud['username']} to {$progkey}\n";
}
unset($ud);

// ============================================================
// CERTIFICATION ASSIGNMENTS
// ============================================================
echo "\n";
foreach ($users as &$ud) {
    $certkey = $cycletocert[$ud['cycle']];
    $cert = $certifications[$certkey];
    $source = $DB->get_record('tool_mucertify_source', ['type' => 'manual', 'certificationid' => $cert->id], '*', MUST_EXIST);

    \tool_mucertify\local\source\manual::assign_users(
        (int)$cert->id, (int)$source->id, [$ud['id']]
    );
    echo "[CERTASSIGN] Assigned {$ud['username']} to {$certkey}\n";
}
unset($ud);

// ============================================================
// LEDGER CREDITS
// ============================================================
echo "\n";

$compliant_credits = [
    ['credits' => 6.0, 'activity' => 'Annual Conference Attendance',        'provider' => 'ACPP National',         'time' => '2025-03-15', 'type' => 'General'],
    ['credits' => 4.0, 'activity' => 'Ethics in Practice Workshop',         'provider' => 'State Board',           'time' => '2025-05-20', 'type' => 'Ethics'],
    ['credits' => 5.0, 'activity' => 'Online CE Module: Risk Management',   'provider' => 'ACPP Online',           'time' => '2025-07-10', 'type' => 'General'],
    ['credits' => 6.0, 'activity' => 'Regional Symposium',                  'provider' => 'ACPP Midwest Chapter',  'time' => '2025-09-18', 'type' => 'General'],
    ['credits' => 4.0, 'activity' => 'Webinar Series: Emerging Trends',     'provider' => 'ProLearn Inc',          'time' => '2025-11-05', 'type' => 'General'],
    ['credits' => 7.0, 'activity' => 'Self-Study: New Practice Guidelines', 'provider' => 'ACPP',                  'time' => '2026-01-20', 'type' => 'General'],
];

$atrisk_credits = [
    ['credits' => 6.0, 'activity' => 'Annual Conference Attendance',    'provider' => 'ACPP National', 'time' => '2025-03-15', 'type' => 'General'],
    ['credits' => 4.0, 'activity' => 'Ethics in Practice Workshop',     'provider' => 'State Board',   'time' => '2025-05-20', 'type' => 'Ethics'],
    ['credits' => 4.0, 'activity' => 'Online CE Module: Fundamentals',  'provider' => 'ACPP Online',   'time' => '2025-08-12', 'type' => 'General'],
];

$lapsed_credits = [
    ['credits' => 5.0, 'activity' => 'Annual Conference Attendance', 'provider' => 'ACPP National', 'time' => '2023-04-10', 'type' => 'General'],
    ['credits' => 3.0, 'activity' => 'Ethics Fundamentals',          'provider' => 'State Board',   'time' => '2023-09-15', 'type' => 'Ethics'],
];

// Rolling lapsed credits use 2022 dates — outside the 24-month (730-day) rolling window.
$rolling_lapsed_credits = [
    ['credits' => 5.0, 'activity' => 'Annual Conference Attendance', 'provider' => 'ACPP National', 'time' => '2022-04-10', 'type' => 'General'],
    ['credits' => 3.0, 'activity' => 'Ethics Fundamentals',          'provider' => 'State Board',   'time' => '2022-09-15', 'type' => 'Ethics'],
];

$midcycle_credits = [
    ['credits' => 16.0, 'activity' => 'Regional Conference', 'provider' => 'ACPP National', 'time' => '2026-02-15', 'type' => 'General'],
];

$persona_credits = [
    'compliant' => $compliant_credits,
    'atrisk'    => $atrisk_credits,
    'lapsed'    => $lapsed_credits,
    'midcycle'  => $midcycle_credits,
];

$sourceseq = 0;
foreach ($users as $ud) {
    $fwkey = $cycletofw[$ud['cycle']];
    $fw = $frameworks[$fwkey];
    // Use rolling-specific lapsed credits for the rolling cycle.
    if ($ud['cycle'] === 'ROLLING' && $ud['persona'] === 'lapsed') {
        $entries = $rolling_lapsed_credits;
    } else {
        $entries = $persona_credits[$ud['persona']];
    }
    $total = 0.0;

    foreach ($entries as $entry) {
        $sourceseq++;
        \tool_mutrain\api::post_credit(
            $ud['id'],
            (int)$fw->id,
            $entry['credits'],
            'demo_seeder',
            $sourceseq,
            strtotime($entry['time']),
            [
                'activityname' => $entry['activity'],
                'provider' => $entry['provider'],
                'credittype' => $entry['type'],
            ]
        );
        $total += $entry['credits'];
    }
    echo "[CREDITS] Posted " . format_float($total, 1) . " credits for {$ud['username']}\n";
}

// ============================================================
// SYNC CREDITS
// ============================================================
echo "\n";
foreach ($frameworks as $fwkey => $fw) {
    \tool_mutrain\local\framework::sync_credits(null, (int)$fw->id);
    echo "[SYNC] Synced framework {$fwkey}\n";
}

// ============================================================
// COURSES, ENROLMENTS & ASSIGNMENTS
// ============================================================
echo "\n";
require_once($CFG->dirroot . '/lib/enrollib.php');

$cycletocourse = [
    'FIXED'   => 'ACPP-DEMO-COURSE-FIXED',
    'ROLLING' => 'ACPP-DEMO-COURSE-ROLLING',
    'ANNIV'   => 'ACPP-DEMO-COURSE-ANNIV',
];
$cycletocoursedata = [
    'FIXED'   => ['shortname' => 'ACPP-DEMO-COURSE-FIXED',   'fullname' => 'ACPP CE Credit Submissions (Fixed Cycle)',       'catkey' => 'ACPP-DEMO-CAT-FIXED'],
    'ROLLING' => ['shortname' => 'ACPP-DEMO-COURSE-ROLLING', 'fullname' => 'ACPP CE Credit Submissions (Rolling Cycle)',     'catkey' => 'ACPP-DEMO-CAT-ROLLING'],
    'ANNIV'   => ['shortname' => 'ACPP-DEMO-COURSE-ANNIV',   'fullname' => 'ACPP CE Credit Submissions (Anniversary Cycle)', 'catkey' => 'ACPP-DEMO-CAT-ANNIV'],
];

$enrolplugin = enrol_get_plugin('manual');
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$assignmodule = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);
$courses = [];

foreach (['FIXED', 'ROLLING', 'ANNIV'] as $cycle) {
    $cd = $cycletocoursedata[$cycle];
    $cat = $categories[$cd['catkey']];

    // Create course.
    $coursedata = (object)[
        'shortname' => $cd['shortname'],
        'fullname' => $cd['fullname'],
        'category' => $cat->id,
        'format' => 'topics',
        'visible' => 1,
        'numsections' => 1,
    ];
    $course = create_course($coursedata);
    $courses[$cycle] = $course;
    echo "[COURSE] Created: {$cd['fullname']} (id={$course->id})\n";

    // Enrol the 3 users for this cycle.
    $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    if (!$enrolinstance) {
        $enrolid = $enrolplugin->add_instance($course);
        $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
    }
    $enrolled = 0;
    foreach ($users as $ud) {
        if ($ud['cycle'] === $cycle) {
            $enrolplugin->enrol_user($enrolinstance, $ud['id'], $studentrole->id);
            $enrolled++;
        }
    }
    echo "[ENROL] Enrolled {$enrolled} users in course id={$course->id}\n";

    // Create assignment.
    $assign = (object)[
        'course' => $course->id,
        'name' => 'Submit External CE Credits',
        'intro' => '<p>Use this activity to submit CE credits earned outside of this platform. Upload your certificate of completion and provide details of the activity.</p>',
        'introformat' => FORMAT_HTML,
        'alwaysshowdescription' => 1,
        'nosubmissions' => 0,
        'submissiondrafts' => 0,
        'sendnotifications' => 0,
        'sendlatenotifications' => 0,
        'duedate' => 0,
        'allowsubmissionsfromdate' => 0,
        'grade' => 100,
        'timemodified' => time(),
        'requiresubmissionstatement' => 0,
        'completionsubmit' => 0,
        'cutoffdate' => 0,
        'gradingduedate' => 0,
        'teamsubmission' => 0,
        'requireallteammemberssubmit' => 0,
        'teamsubmissiongroupingid' => 0,
        'blindmarking' => 0,
        'hidegrader' => 0,
        'revealidentities' => 0,
        'attemptreopenmethod' => 'none',
        'maxattempts' => -1,
        'markingworkflow' => 0,
        'markingallocation' => 0,
        'markinganonymous' => 0,
        'sendstudentnotifications' => 1,
        'preventsubmissionnotingroup' => 0,
    ];
    $assign->id = $DB->insert_record('assign', $assign);

    // Create course module.
    $cm = (object)[
        'course' => $course->id,
        'module' => $assignmodule->id,
        'instance' => $assign->id,
        'section' => 0,
        'added' => time(),
        'visible' => 1,
    ];
    $cm->id = $DB->insert_record('course_modules', $cm);
    course_add_cm_to_section($course->id, $cm->id, 1);
    context_module::instance($cm->id);
    echo "[ASSIGN] Created: Submit External CE Credits (id={$assign->id}) in course id={$course->id}\n";

    // Configure assignsubmission_mutrain plugin.
    $fwkey = $cycletofw[$cycle];
    $fw = $frameworks[$fwkey];
    $pluginconfigs = [
        ['subtype' => 'assignsubmission', 'plugin' => 'mutrain', 'name' => 'enabled', 'value' => '1'],
        ['subtype' => 'assignsubmission', 'plugin' => 'mutrain', 'name' => 'frameworkid', 'value' => (string)$fw->id],
        ['subtype' => 'assignsubmission', 'plugin' => 'mutrain', 'name' => 'credittypes', 'value' => 'General,Ethics,Clinical,Technical'],
        ['subtype' => 'assignsubmission', 'plugin' => 'mutrain', 'name' => 'maxhours', 'value' => '20'],
        ['subtype' => 'assignsubmission', 'plugin' => 'file',    'name' => 'enabled', 'value' => '0'],
        ['subtype' => 'assignsubmission', 'plugin' => 'onlinetext', 'name' => 'enabled', 'value' => '0'],
    ];
    foreach ($pluginconfigs as $pc) {
        $pc['assignment'] = $assign->id;
        $DB->insert_record('assign_plugin_config', (object)$pc);
    }
    echo "[CONFIG] Configured assignsubmission_mutrain: frameworkid={$fw->id}, credittypes=General,Ethics,Clinical,Technical\n";
}

// ============================================================
// DASHBOARD BLOCK PLACEMENT
// ============================================================
echo "\n";

$syscontextid = $syscontext->id;

// Step 1: Add cesubmit to the system default dashboard (subpage '2').
if (!$DB->record_exists('block_instances', [
    'blockname' => 'cesubmit',
    'parentcontextid' => $syscontextid,
    'pagetypepattern' => 'my-index',
    'subpagepattern' => '2',
])) {
    $maxweight = $DB->get_field_sql(
        "SELECT COALESCE(MAX(defaultweight), -1)
           FROM {block_instances}
          WHERE pagetypepattern = 'my-index'
            AND parentcontextid = :ctxid
            AND subpagepattern = '2'",
        ['ctxid' => $syscontextid]
    );
    $newweight = (int)$maxweight + 1;
    $DB->insert_record('block_instances', (object)[
        'blockname' => 'cesubmit',
        'parentcontextid' => $syscontextid,
        'showinsubcontexts' => 0,
        'requiredbytheme' => 0,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => '2',
        'defaultregion' => 'side-post',
        'defaultweight' => $newweight,
        'configdata' => null,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    echo "[BLOCK] Added cesubmit to system default dashboard (region=side-post, weight={$newweight})\n";
} else {
    echo "[BLOCK] cesubmit already on system default dashboard\n";
}

// Step 2: Add cesubmit to each demo user's personal dashboard.
$userblockcount = 0;
foreach ($users as $ud) {
    $userctx = context_user::instance($ud['id']);

    // Find user's personal dashboard subpage.
    $subpage = $DB->get_field_sql(
        "SELECT subpagepattern
           FROM {block_instances}
          WHERE pagetypepattern = 'my-index'
            AND parentcontextid = :ctxid
            AND blockname != 'cesubmit'
          LIMIT 1",
        ['ctxid' => $userctx->id]
    );

    if (!$subpage) {
        // User has no custom dashboard — system default covers them.
        continue;
    }

    if ($DB->record_exists('block_instances', [
        'blockname' => 'cesubmit',
        'parentcontextid' => $userctx->id,
        'pagetypepattern' => 'my-index',
    ])) {
        $userblockcount++;
        continue;
    }

    $DB->insert_record('block_instances', (object)[
        'blockname' => 'cesubmit',
        'parentcontextid' => $userctx->id,
        'showinsubcontexts' => 0,
        'requiredbytheme' => 0,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => $subpage,
        'defaultregion' => 'side-post',
        'defaultweight' => 0,
        'configdata' => null,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    $userblockcount++;
}
echo "[BLOCK] Added cesubmit to {$userblockcount} user dashboards\n";

// ============================================================
// VERIFICATION
// ============================================================
echo "\n";
echo str_pad('Username', 28) . str_pad('Cycle', 10) . str_pad('Persona', 12)
   . str_pad('Ledger', 10) . str_pad('Cache', 10) . str_pad('Req', 8) . "Compliant\n";
echo str_repeat('-', 88) . "\n";

foreach ($users as $ud) {
    $fwkey = $cycletofw[$ud['cycle']];
    $fw = $frameworks[$fwkey];

    $ledgertotal = \tool_mutrain\api::get_user_total($ud['id'], (int)$fw->id);

    $cacherec = $DB->get_record('tool_mutrain_credit', [
        'frameworkid' => $fw->id,
        'userid' => $ud['id'],
    ]);
    $cachetotal = $cacherec ? (float)$cacherec->credits : 0.0;
    $required = (float)$fw->requiredcredits;
    $compliant = $cachetotal >= $required ? 'Y' : 'N';

    echo str_pad($ud['username'], 28)
       . str_pad($ud['cycle'], 10)
       . str_pad($ud['persona'], 12)
       . str_pad(format_float($ledgertotal, 1), 10)
       . str_pad(format_float($cachetotal, 1), 10)
       . str_pad(format_float($required, 0), 8)
       . $compliant . "\n";
}

echo "\n[DONE] Demo data seeded successfully.\n";
