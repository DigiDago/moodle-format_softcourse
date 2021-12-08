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
        $format = $this->format;

        $data = parent::export_for_template($output);

        $course = $format->get_course();
        $modinfo = get_fast_modinfo($course);

        $sectionmods = [];

        $completioninfo = new completion_info($course);
        $context = context_course::instance($course->id);

        // If section have no mods we skip.
        if (!$data->cmlist->cms) {
            $data->skip = true;
        }

        // Assiociation tabs : section[id_of_section] = cm.
        foreach ($data->cmlist->cms as $cm) {
            $cm = $modinfo->get_cm($cm->cmitem->id);
            $idsection = $cm->get_section_info()->section;

            // Hide the section 0 if the course format option is set to "Hide the section 0".
            if (!($idsection == 0 && $format->get_format_options()['hidesectionzero'] == 1)) {
                $info = $modinfo->get_section_info_all()[$idsection];
                if (!isset($sectionmods[$idsection])) {
                    $sectionmods[$idsection] = new stdClass();
                    $sectionmods[$idsection]->cm = [];
                    $sectionmods[$idsection]->id = $idsection;
                    $sectionmods[$idsection]->name = $info->name;
                    $summary = new stdClass();
                    $summary->summarytext = $info->summary;
                    $sectionmods[$idsection]->summary = $summary;
                    $sectionmods[$idsection]->uservisible = $info->uservisible;
                    $sectionmods[$idsection]->visible = $info->visible;
                    $sectionmods[$idsection]->available = $info->available;
                }
                $sectionmods[$idsection]->cm[] = $cm;
            }
        }
        // Put tabs into a tabs readable by mustache.

        foreach ($sectionmods as $section) {
            // We check case were section are hidden.
            // We check case were section have only one hidden activity.
            if ($section->visible == 0 || $section->uservisible == false || $section->available == false) {
                $data->skip = true;
            } else if (count($section->cm) == 1
                && ($section->cm[0]->visible == 0
                    || $section->cm[0]->visibleoncoursepage == 0
                    || $section->cm[0]->uservisible == false || $section->cm[0]->available == false)
            ) {
                $data->skip = true;
            }
            $s = $data;
            $s->name = format_string(
                $section->name,
                true,
                array('context' => context_course::instance($course->id))
            );
            $s->courseid = $course->id;
            $options = new stdClass();
            $options->noclean = true;
            $options->overflowdiv = true;
            $s->summary->summarytext = format_text($section->summary->summarytext, 1, $options);
            $s->start = get_string('startcourse', 'format_softcourse');
            $s->countactivitiestooltip = get_string('countactivities', 'format_softcourse');
            $s->countactivities = 0;

            if (has_capability('moodle/course:update', $context)) {
                $s->update_img = get_string('update_img', 'format_softcourse');
                $s->delete_img = get_string('delete_img', 'format_softcourse');
            }

            // Render the iamge section.
            $fs = get_file_storage();
            $file = $fs->get_area_files($context->id, 'format_softcourse', 'sectionimage', $s->id,
                "itemid, filepath, filename", false);
            if ($file) {
                $s->urlimg = \moodle_url::make_pluginfile_url(
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

            $s->first_cm_url = '';
            $s->countactivities = 0;
            // Get completion of cms.
            foreach ($section->cm as $cm) {
                if ($cm->available && ($cm->uservisible && !$cm->is_stealth() && $cm->modname != 'label'
                        || !empty($cm->url)) && $s->first_cm_url == '') {
                    if ($cm->modname == 'resource') {
                        $cm->url->param('forceview', 1);
                    }
                    $s->first_cm_url = $cm->url->out(false);
                }
                if ($cm->completion > 0) {
                    $nbcompletion++;
                }
                $nbcomplete += $completioninfo->get_data($cm, true)->completionstate;
                if ($cm->deletioninprogress == 0 && $cm->visible == 1
                    && $cm->modname != "label" && $cm->visibleoncoursepage == 1
                    && $cm->uservisible == true
                    && $cm->available == true) {
                    $s->countactivities += 1;
                }
            }
            // Count the percent of cm complete.
            if ($nbcompletion != 0) {
                $s->progression = get_string('progression', 'format_softcourse');
                $percentcomplete = $nbcomplete * 100 / $nbcompletion;
                $s->progression_percent = intval($percentcomplete);
            }
            $s->hasmodules = ($s->countactivities > 0);
            if ($s->hasmodules) {
                $data = $s;
            }
        }

        return $data;
    }
}
