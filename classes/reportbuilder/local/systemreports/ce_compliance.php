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

namespace local_cesubmit\reportbuilder\local\systemreports;

use local_cesubmit\reportbuilder\local\entities\cecompliance;
use core_reportbuilder\system_report;
use core_reportbuilder\local\helpers\database;
use lang_string;

/**
 * CE compliance admin report.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ce_compliance extends system_report {

    #[\Override]
    protected function initialise(): void {
        $entity = new cecompliance();
        $pa = $entity->get_table_alias('tool_muprog_allocation');
        $u = $entity->get_table_alias('user');
        $f = $entity->get_table_alias('tool_mutrain_framework');
        $pi = $entity->get_table_alias('tool_muprog_item');
        $mc = $entity->get_table_alias('tool_mutrain_credit');

        $this->set_main_table('tool_muprog_allocation', $pa);
        $this->add_entity($entity);

        $this->add_base_fields("{$pa}.id, {$pa}.userid, {$pa}.programid");

        $this->add_join("JOIN {user} {$u} ON {$u}.id = {$pa}.userid");
        $this->add_join("JOIN {tool_muprog_item} {$pi} ON {$pi}.programid = {$pa}.programid AND {$pi}.creditframeworkid IS NOT NULL");
        $this->add_join("JOIN {tool_mutrain_framework} {$f} ON {$f}.id = {$pi}.creditframeworkid AND {$f}.archived = 0");
        $this->add_join("LEFT JOIN {tool_mutrain_credit} {$mc} ON {$mc}.frameworkid = {$f}.id AND {$mc}.userid = {$pa}.userid");

        // Base conditions.
        $where = "{$pa}.archived = 0 AND {$u}.deleted = 0 AND {$u}.suspended = 0";

        // Optional framework filter from parameters.
        $params = [];
        $parameters = $this->get_parameters();
        if (!empty($parameters['frameworkid'])) {
            $paramname = database::generate_param_name();
            $where .= " AND {$f}.id = :{$paramname}";
            $params[$paramname] = $parameters['frameworkid'];
        }

        $this->add_base_condition_sql($where, $params);

        $this->add_columns();
        $this->add_filters();

        $this->set_initial_sort_column('cecompliance:earned', SORT_ASC);
        $this->set_downloadable(true);
        $this->set_default_no_results_notice(new lang_string('nocredits', 'local_cesubmit'));
    }

    #[\Override]
    protected function can_view(): bool {
        return isloggedin() && !isguestuser()
            && has_capability('local/cesubmit:viewreport', $this->get_context());
    }

    /**
     * Adds the columns to display.
     */
    public function add_columns(): void {
        $columns = [
            'cecompliance:userfullname',
            'cecompliance:username',
            'cecompliance:framework',
            'cecompliance:earned',
            'cecompliance:required',
            'cecompliance:gap',
            'cecompliance:status',
            'cecompliance:deadline',
        ];
        $this->add_columns_from_entities($columns);
    }

    /**
     * Adds the filters.
     */
    protected function add_filters(): void {
        $filters = [
            'cecompliance:status',
            'cecompliance:framework',
            'cecompliance:deadline',
            'cecompliance:username',
        ];
        $this->add_filters_from_entities($filters);
    }
}
