<?php
// This file is part of the softcourse course format
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

/**
 * format_softcourse course format.
 * Provides the information to backup grid course format
 *
 * @package    format_softcourse
 * @copyright  2026 Pimenko <contact@pimenko.com>.
 * @category   backup
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_format_softcourse_plugin extends backup_format_plugin {
    /**
     * Returns the format information to attach to section element
     */
    protected function define_course_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(
            null,
            '/course/format',
            'softcourse',
        );

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        $plugin->add_child($pluginwrapper);

        // Introduction.
        $pluginwrapper->annotate_files('format_softcourse', 'introduction', null);

        // Section image.
        $pluginwrapper->annotate_files('format_softcourse', 'sectionimage', null);

        return $plugin;
    }
}
