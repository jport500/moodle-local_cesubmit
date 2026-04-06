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
 * My CE Record dashboard.
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

$userid = optional_param('userid', 0, PARAM_INT);

require_login();
if (isguestuser()) {
    redirect(new core\url('/'));
}

$currenturl = new core\url('/local/cesubmit/my/index.php');

if ($userid) {
    $currenturl->param('userid', $userid);
} else {
    $userid = $USER->id;
}
$PAGE->set_url($currenturl);

$usercontext = context_user::instance($userid);
$PAGE->set_context($usercontext);

$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
if (isguestuser($user)) {
    redirect(new core\url('/'));
}

if ($userid != $USER->id) {
    require_capability('tool/mutrain:viewusercredits', $usercontext);
}

$title = get_string('mydashboard', 'local_cesubmit');

$PAGE->navigation->extend_for_user($user);
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('profile'), new core\url('/user/profile.php', ['id' => $user->id]));
$PAGE->navbar->add($title);

/** @var \local_cesubmit\output\my\renderer $renderer */
$renderer = $PAGE->get_renderer('local_cesubmit', 'my');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $renderer->render_dashboard($userid);
echo $OUTPUT->footer();
