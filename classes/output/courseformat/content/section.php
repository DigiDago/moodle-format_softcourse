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

/**
 * Contains the default section controls output class.
 *
 * @package   format_softcourse
 * @copyright Sylvain | Pimenko 2021 <contact@pimneko.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_softcourse\output\courseformat\content;

use core_courseformat\base as course_format;
use context_course;
use completion_info;
use core_courseformat\output\local\content\section as section_base;
use stdClass;

/**
 * Base class to render a course section.
 *
 * @package   format_softcourse
 * @copyright Sylvain | Pimenko 2021 <contact@pimneko.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {

    /** @var course_format the course format */
    protected $format;

    public function export_for_template(\renderer_base $output): stdClass {
        // Review this part.
        $format = $this->format;

        $data = parent::export_for_template($output);
        $data->start_url = null;

        $course = $format->get_course();
        $modinfo = get_fast_modinfo($course);

        $completioninfo = new completion_info($course);
        $context = context_course::instance($course->id);

        // If section have no mods we skip.
        if (!$data->cmlist->cms) {
            $data->skip = true;
            return $data;
        }

        // Prepare some cm_info we will need further.
        foreach ($data->cmlist->cms as $key => $cm) {
            $cm = $modinfo->get_cm($cm->cmitem->id);
            $idsection = $cm->get_section_info()->section;
            // Hide the section 0 if the course format option is set to "Hide the section 0".
            if (!($idsection == 0 && $format->get_format_options()['hidesectionzero'] == 1)) {
                $info = $modinfo->get_section_info_all()[$idsection];
                if ($data) {
                    $data->idsection = $idsection;
                    $data->name = $info->name;
                    $summary = new stdClass();
                    $summary->summarytext = $info->summary;
                    $data->summary = $summary;
                    $data->uservisible = $info->uservisible;
                    $data->visible = $info->visible;
                    $data->available = $info->available;
                }
                $data->cmlist->cms[$key]->cminfo = $cm;
            } else {
                $data->skip = true;
                return $data;
            }
        }

        // We check case were section are hidden.
        // We check case were section have only one hidden activity.
        if ($data->visible == 0 || $data->uservisible == false || $data->available == false) {
            $data->skip = true;
            return $data;
        } else if (count($data->cmlist->cms) == 1
            && ($data->cmlist->cms[0]->cminfo->visible == 0
                || $data->cmlist->cms[0]->cminfo->visibleoncoursepage == 0
                || $data->cmlist->cms[0]->cminfo->uservisible == false || $data->cmlist->cms[0]->cminfo->available == false)
        ) {
            $data->skip = true;
            return $data;
        }
        $data->name = format_string(
            $data->name,
            true,
            array('context' => context_course::instance($course->id))
        );
        $data->courseid = $course->id;
        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        $data->summary->summarytext = format_text($data->summary->summarytext, 1, $options);
        $data->start = get_string('startcourse', 'format_softcourse');
        $data->countactivitiestooltip = get_string('countactivities', 'format_softcourse');
        $data->countactivities = 0;

        // Check capability to edit/delete softcourse section picture.
        if (has_capability('moodle/course:update', $context)) {
            $data->update_img = get_string('update_img', 'format_softcourse');
            $data->delete_img = get_string('delete_img', 'format_softcourse');
        }

        // Render the iamge section.
        $fs = get_file_storage();
        $file = $fs->get_area_files($context->id, 'format_softcourse', 'sectionimage', $data->num,
            "itemid, filepath, filename", false);

        if ($file) {
            $data->urlimg = \moodle_url::make_pluginfile_url(
                end($file)->get_contextid(),
                end($file)->get_component(),
                end($file)->get_filearea(),
                end($file)->get_itemid(),
                end($file)->get_filepath(),
                end($file)->get_filename()
            );
        }

        $nbcomplete = 0;
        $nbcompletion = 0;
        $data->first_cm_url = '';
        $data->countactivities = 0;
        // Get completion of cms.
        foreach ($data->cmlist->cms as $cm) {
            if ($cm->cminfo->available && ($cm->cminfo->uservisible && !$cm->cminfo->is_stealth() && $cm->cminfo->modname != 'label'
                    || !empty($cm->url)) && $data->first_cm_url == '') {
                if ($cm->cminfo->modname == 'resource') {
                    $cm->cminfo->url->param('forceview', 1);
                }
                if ($data->start_url == null) {
                    $data->start_url = $cm->cminfo->url->out(false);
                }
                $data->first_cm_url = $cm->cminfo->url->out(false);
            }
            if ($cm->cminfo->completion > 0) {
                $nbcompletion++;
            }
            $nbcomplete += $completioninfo->get_data($cm->cminfo, true)->completionstate;
            if ($cm->cminfo->deletioninprogress == 0 && $cm->cminfo->visible == 1
                && $cm->cminfo->modname != "label" && $cm->cminfo->visibleoncoursepage == 1
                && $cm->cminfo->uservisible == true
                && $cm->cminfo->available == true) {
                $data->countactivities += 1;
            }
        }
        // Count the percent of cm complete.
        if ($nbcompletion != 0) {
            $data->progression = get_string('progression', 'format_softcourse');
            $percentcomplete = $nbcomplete * 100 / $nbcompletion;
            $data->progression_percent = intval($percentcomplete);
        }

        if ($data->start_url == null) {
            $data->disabledStart = 'true';
        }

        return $data;
    }
}
