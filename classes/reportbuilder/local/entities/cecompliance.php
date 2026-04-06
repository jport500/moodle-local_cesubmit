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

namespace local_cesubmit\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;

/**
 * CE compliance entity.
 *
 * @package    local_cesubmit
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cecompliance extends base {
    #[\Override]
    protected function get_default_tables(): array {
        return [
            'tool_muprog_allocation',
            'user',
            'tool_mutrain_framework',
            'tool_muprog_item',
            'tool_mutrain_credit',
        ];
    }

    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('cecompliance', 'local_cesubmit');
    }

    #[\Override]
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $pa = $this->get_table_alias('tool_muprog_allocation');
        $u = $this->get_table_alias('user');
        $f = $this->get_table_alias('tool_mutrain_framework');
        $mc = $this->get_table_alias('tool_mutrain_credit');

        $dateformat = get_string('strftimedatetimeshort');
        $columns = [];

        // 1. User full name (linked to user credits page).
        $columns[] = (new column(
            'userfullname',
            new lang_string('fullname'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$u}.firstname, {$u}.lastname, {$u}.id AS linkuserid, {$f}.id AS linkframeworkid")
            ->set_is_sortable(true)
            ->set_callback(static function (?string $value, \stdClass $row): string {
                if (empty($row->firstname)) {
                    return '';
                }
                $name = fullname($row);
                if (!empty($row->linkuserid) && !empty($row->linkframeworkid)) {
                    $url = new \core\url('/admin/tool/mutrain/management/user_credits.php', [
                        'userid' => $row->linkuserid,
                        'frameworkid' => $row->linkframeworkid,
                    ]);
                    $name = \html_writer::link($url, $name);
                }
                return $name;
            });

        // 2. Username.
        $columns[] = (new column(
            'username',
            new lang_string('username'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$u}.username")
            ->set_is_sortable(true);

        // 3. Framework name.
        $columns[] = (new column(
            'framework',
            new lang_string('cecompliance', 'local_cesubmit'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$f}.name AS frameworkname")
            ->set_is_sortable(true)
            ->set_callback(static function (?string $value, \stdClass $row): string {
                return format_string($row->frameworkname ?? '');
            });

        // 4. Credits earned.
        $columns[] = (new column(
            'earned',
            new lang_string('creditsearned', 'local_cesubmit'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("COALESCE({$mc}.credits, 0)", 'earned')
            ->set_is_sortable(true)
            ->set_callback(static function ($value, \stdClass $row): string {
                return format_float((float)($row->earned ?? 0), 1);
            });

        // 5. Credits required.
        $columns[] = (new column(
            'required',
            new lang_string('creditsrequired', 'local_cesubmit'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$f}.requiredcredits")
            ->set_is_sortable(true)
            ->set_callback(static function ($value, \stdClass $row): string {
                return format_float((float)($row->requiredcredits ?? 0), 1);
            });

        // 6. Credit gap.
        $columns[] = (new column(
            'gap',
            new lang_string('creditsgap', 'local_cesubmit'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("CASE WHEN ({$f}.requiredcredits - COALESCE({$mc}.credits, 0)) > 0
                              THEN ({$f}.requiredcredits - COALESCE({$mc}.credits, 0))
                              ELSE 0 END", 'gap')
            ->set_is_sortable(true)
            ->set_callback(static function ($value, \stdClass $row): string {
                $gap = (float)($row->gap ?? 0);
                return $gap > 0 ? format_float($gap, 1) : '-';
            });

        // 7. Status.
        $columns[] = (new column(
            'status',
            new lang_string('status'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("COALESCE({$mc}.credits, 0)", 'statuscredits')
            ->add_field("{$f}.requiredcredits", 'statusrequired')
            ->add_field("{$pa}.timeend", 'statustimeend')
            ->set_is_sortable(false)
            ->set_callback(static function ($value, \stdClass $row): string {
                $now = time();
                $earned = (float)$row->statuscredits;
                $required = (float)$row->statusrequired;
                $timeend = (int)$row->statustimeend;
                if ($earned >= $required) {
                    return \html_writer::span(
                        get_string('status_compliant', 'local_cesubmit'),
                        'badge badge-success'
                    );
                } else if ($timeend > 0 && $timeend < $now) {
                    return \html_writer::span(
                        get_string('status_overdue', 'local_cesubmit'),
                        'badge badge-danger'
                    );
                } else {
                    return \html_writer::span(
                        get_string('status_inprogress', 'local_cesubmit'),
                        'badge badge-warning'
                    );
                }
            });

        // 8. Deadline.
        $columns[] = (new column(
            'deadline',
            new lang_string('deadline', 'local_cesubmit'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$pa}.timeend")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], $dateformat);

        // 9. Completed.
        $columns[] = (new column(
            'timecompleted',
            new lang_string('completed', 'completion'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$pa}.timecompleted")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], $dateformat);

        return $columns;
    }

    /**
     * Return list of all available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $pa = $this->get_table_alias('tool_muprog_allocation');
        $u = $this->get_table_alias('user');
        $f = $this->get_table_alias('tool_mutrain_framework');
        $mc = $this->get_table_alias('tool_mutrain_credit');
        $now = time();

        $filters = [];

        // 1. Status filter.
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status'),
            $this->get_entity_name(),
            "CASE
                WHEN COALESCE({$mc}.credits, 0) >= {$f}.requiredcredits THEN 'compliant'
                WHEN {$pa}.timeend > 0 AND {$pa}.timeend < {$now} THEN 'overdue'
                ELSE 'inprogress'
            END"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                'compliant' => get_string('status_compliant', 'local_cesubmit'),
                'inprogress' => get_string('status_inprogress', 'local_cesubmit'),
                'overdue' => get_string('status_overdue', 'local_cesubmit'),
            ]);

        // 2. Framework name filter.
        $filters[] = (new filter(
            text::class,
            'framework',
            new lang_string('cecompliance', 'local_cesubmit'),
            $this->get_entity_name(),
            "{$f}.name"
        ))
            ->add_joins($this->get_joins());

        // 3. Deadline filter.
        $filters[] = (new filter(
            date::class,
            'deadline',
            new lang_string('deadline', 'local_cesubmit'),
            $this->get_entity_name(),
            "{$pa}.timeend"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_NOT_EMPTY,
                date::DATE_EMPTY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ]);

        // 4. Username filter.
        $filters[] = (new filter(
            text::class,
            'username',
            new lang_string('username'),
            $this->get_entity_name(),
            "{$u}.username"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
