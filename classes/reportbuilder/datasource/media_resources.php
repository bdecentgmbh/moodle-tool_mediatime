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

namespace tool_mediatime\reportbuilder\datasource;

use core\reportbuilder\local\entities\context;
use tool_mediatime\reportbuilder\local\entities\media_resource;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\boolean_select;

/**
 * Files datasource
 *
 * @package     tool_mediatime
 * @copyright   2024 bdecent gmbh <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_resources extends datasource {
    /**
     * Return user friendly name of the report source
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('pluginname', 'tool_mediatime');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $resourceentity = new media_resource();
        $resourcesalias = $resourceentity->get_table_alias('tool_mediatime');

        $this->set_main_table('tool_mediatime', $resourcesalias);
        $this->add_entity($resourceentity);

        // Join the context entity.
        $contextentity = new context();
        $contextalias = $contextentity->get_table_alias('context');
        $this->add_entity($contextentity
            ->add_join("LEFT JOIN {context} {$contextalias} ON {$contextalias}.id = {$resourcesalias}.contextid"));

        // Join the user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$resourcesalias}.usermodified"));

        // Add report elements from each of the entities we added to the report.
        $this->add_all_from_entities();
    }

    /**
     * Return the columns that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'context:name',
            'user:fullname',
            'media_resource:name',
            'media_resource:timecreated',
        ];
    }

    /**
     * Return the column sorting that will be added to the report upon creation
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'context:name' => SORT_ASC,
            'media_resource:timecreated' => SORT_ASC,
        ];
    }

    /**
     * Return the filters that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'media_resource:timecreated',
        ];
    }

    /**
     * Return the conditions that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }

    /**
     * Return the condition values that will be set for the report upon creation
     *
     * @return array
     */
    public function get_default_condition_values(): array {
        return [];
    }
}
