<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings for format_softcourse.
 *
 * @package    format_softcourse
 * @copyright  2026 Pimenko <contact@pimenko.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Linear navigation: sticky "Previous" / "Next" footer on activity pages (Moodle 5.3+).
// The feature (and its core strings) does not exist before Moodle 5.3, so only add the setting when available.
if (!class_exists(\core_courseformat\local\linearnavigationsettings::class)) {
    $settings = null;
    return;
}

if ($ADMIN->fulltree) {
    if (class_exists(\core_courseformat\local\linearnavigationsettings::class)) {
        $options = [
            1 => get_string('yes'),
            0 => get_string('no'),
        ];
        $settings->add(new admin_setting_configselect(
            'format_softcourse/' . \core_courseformat\local\linearnavigationsettings::SETTING_ENABLE_LINEAR_NAV,
            new lang_string('linearnavigationsettings', 'core_courseformat'),
            new lang_string('linearnavigationsettings_help', 'core_courseformat'),
            1,
            $options,
        ));
    }
}
