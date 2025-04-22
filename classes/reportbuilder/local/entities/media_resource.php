<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace tool_mediatime\reportbuilder\local\entities;

use context;
use context_helper;
use core_collator;
use core_filetypes;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\filters\{boolean_select, date, filesize, select, text};
use core_reportbuilder\local\report\{column, filter};

/**
 * Media Time resource entity
 *
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_resource extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'tool_mediatime',
            'context',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('mediaresource', 'tool_mediatime');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $resourcesalias = $this->get_table_alias('tool_mediatime');
        $contextalias = $this->get_table_alias('context');

        // Name.
        $columns[] = (new column(
            'name',
            new lang_string('resourcename', 'tool_mediatime'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$resourcesalias}.name")
            ->set_is_sortable(true);

        // Name with link column.
        $columns[] = (new column(
            'namewithlink',
            new lang_string('resourcenamewithlink', 'tool_mediatime'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$resourcesalias}.name, {$resourcesalias}.contextid, {$resourcesalias}.id")
            ->add_callback(static function (?string $name, stdClass $resource): string {
                if (empty($resource->id)) {
                    return '';
                }
                $id = $resource->id;
                $url = new moodle_url('/admin/tool/mediatime', ['id' => $id]);
                $context = \context::instance_by_id($resource->contextid);
                return html_writer::link(
                    $url,
                    format_string($name, true, ['context' => $context])
                );
            })
            ->set_is_sortable(true);

        // Type.
        $columns[] = (new column(
            'type',
            new lang_string('type', 'core_repository'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$resourcesalias}.source")
            ->set_is_sortable(true)
            ->add_callback(static function ($source): string {
                global $CFG;

                return get_string('pluginname', "mediatimesrc_$source");
            });

        // Time created.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$resourcesalias}.timecreated")
            ->add_callback([format::class, 'userdate'])
            ->set_is_sortable(true);

        // Time modified.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$resourcesalias}.timemodified")
            ->add_callback([format::class, 'userdate'])
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $resourcesalias = $this->get_table_alias('tool_mediatime');

        // Name.
        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('resourcename', 'tool_mediatime'),
            $this->get_entity_name(),
            "{$resourcesalias}.name"
        ))
            ->add_joins($this->get_joins());

        // Source.
        $filters[] = (new filter(
            text::class,
            'source',
            new lang_string('type', 'core_repository'),
            $this->get_entity_name(),
            "{$resourcesalias}.source"
        ))
            ->add_joins($this->get_joins());

        // Time created.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$resourcesalias}.timecreated"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ]);

        // Time modified.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$resourcesalias}.timemodified"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ]);

        return $filters;
    }
}
