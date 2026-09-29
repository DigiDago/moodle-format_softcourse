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

    /**
     * Exports data for rendering in a template.
     *
     * @param \renderer_base $output The renderer for output
     * @return stdClass The data object for template rendering
     */
    public function export_for_template(\renderer_base $output): stdClass {
        // Keep a local reference to the format.
        $format = $this->format;

        $data = parent::export_for_template($output);
        $data->start_url = null;

        $course = $format->get_course();
        $modinfo = get_fast_modinfo($course);

        $completioninfo = new completion_info($course);
        $context = context_course::instance($course->id);

        // If the section has no CMs or the list is not set/empty, skip and optionally inject the "add section" control.
        if (!isset($data->cmlist) || empty($data->cmlist) || !$data->cmlist->cms) {
            $data->skip = true;
            if (!$this->format->get_sectionnum()) {
                $addsectionclass = $format->get_output_classname('content\\addsection');
                $addsection = new $addsectionclass($format);
                $data->numsections = $addsection->export_for_template($output);
                $data->insertafter = true;
            }
            return $data;
        }

        // Single pass: initialize section fields once, attach cminfo, and compute completion in the same loop.
        $hidesectionzero = (int) ($format->get_format_options()['hidesectionzero'] ?? 0) === 1;
        $sectionfieldsset = false;

        $nbcomplete = 0;
        $nbcompletion = 0;
        $data->first_cm_url = '';
        $data->countactivities = 0;

        foreach ($data->cmlist->cms as $key => $cmwrapper) {
            $cminfo = $modinfo->get_cm($cmwrapper->cmitem->id);
            $idsection = $cminfo->get_section_info()->section;

            // Skip when section 0 must be hidden.
            if ($idsection === 0 && $hidesectionzero) {
                $data->skip = true;
                continue;
            }

            // Initialize section-level data only once (avoid overwriting on subsequent iterations).
            if (!$sectionfieldsset) {
                $info = $modinfo->get_section_info($idsection);
                $data->idsection = $idsection;
                $data->name = $info->name ?? null;
                $data->summary = (object) [ 'summarytext' => $info->summary ?? '' ];
                $data->uservisible = (bool) ($info->uservisible ?? false);
                $data->visible = (int) ($info->visible ?? 0);
                $data->available = (bool) ($info->available ?? false);
                $data->skip = false;
                $sectionfieldsset = true;
            }

            // Attach cminfo so templates and later logic can access full CM data.
            $data->cmlist->cms[$key]->cminfo = $cminfo;

            // Compute completion and counters.
            if ($cminfo->modname === 'subsection') {
                // Handle "subsection" by iterating its child CMs.
                $sectionid = $cminfo->get_custom_data()['sectionid'] ?? null;
                if ($sectionid) {
                    $sectionnum = get_fast_modinfo($course->id)->get_section_info_by_id($sectionid);
                    $sectionmods = $sectionnum ? $sectionnum->get_sequence_cm_infos() : [];

                    foreach ($sectionmods as $subsecmodule) {
                        // Wrap into an object exposing "cminfo" to satisfy get_completion signature.
                        [
                            $data,
                            $nbcompletion,
                            $nbcomplete
                        ] = $this->get_completion(
                            (object) [ 'cminfo' => $subsecmodule ],
                            $data,
                            $completioninfo,
                            $nbcompletion,
                            $nbcomplete,
                        );
                    }
                }
            } else {
                // Regular CM completion computation.
                [
                    $data,
                    $nbcompletion,
                    $nbcomplete
                ] = $this->get_completion(
                    (object) [ 'cminfo' => $cminfo ],
                    $data,
                    $completioninfo,
                    $nbcompletion,
                    $nbcomplete,
                );
            }
        }

        // If the section itself is hidden or not available, skip rendering it.
        if (
            (isset($data->visible) && (int) $data->visible === 0) || (isset($data->uservisible) && $data->uservisible === false) ||
            (isset($data->available) && $data->available === false)
        ) {
            $data->skip = true;
            return $data;
        }

        // If there is only one CM and it is hidden/unavailable on the course page, skip.
        if (isset($data->cmlist) && isset($data->cmlist->cms) && count($data->cmlist->cms) === 1) {
            $only = $data->cmlist->cms[0]->cminfo ?? null;
            $onlyhiddenorunavailable = ($only && isset($only->visible) && (int) $only->visible === 0) ||
                ($only && isset($only->visibleoncoursepage) && (int) $only->visibleoncoursepage === 0) ||
                ($only && isset($only->uservisible) && $only->uservisible === false) ||
                ($only && isset($only->available) && $only->available === false);
            if ($onlyhiddenorunavailable) {
                $data->skip = true;
                return $data;
            }
        }

        // New rule: skip the section if it has no visible activities at all (including inside subsections).
        // countactivities is incremented only for user-visible, available, non-label, on-course-page items.
        if ((int) $data->countactivities === 0) {
            $data->skip = true;
            return $data;
        }

        // Format the section name within the course context.
        if (isset($data->name)) {
            $data->name = format_string(
                $data->name,
                true,
                [ 'context' => context_course::instance($course->id) ],
            );
        }

        // Format the section summary.
        $data->courseid = $course->id;
        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        $data->summary->summarytext = format_text(
            $data->summary->summarytext,
            1,
            $options,
        );
        $data->countactivitiestooltip = get_string(
            'countactivities',
            'format_softcourse',
        );

        // Capability checks for section image edit/delete actions.
        if (has_capability('moodle/course:update', $context)) {
            $data->update_img = get_string(
                'update_img',
                'format_softcourse',
            );
            $data->delete_img = get_string(
                'delete_img',
                'format_softcourse',
            );
        }

        // Resolve section image URL if any.
        $fs = get_file_storage();
        $file = $fs->get_area_files(
            $context->id,
            'format_softcourse',
            'sectionimage',
            $data->id,
            "itemid, filepath, filename",
            false,
        );
        if ($file) {
            $last = end($file);
            $data->urlimg = \moodle_url::make_pluginfile_url(
                $last->get_contextid(),
                $last->get_component(),
                $last->get_filearea(),
                $last->get_itemid(),
                $last->get_filepath(),
                $last->get_filename(),
            );
        }

        // Compute progression percentage if applicable.
        if ($nbcompletion != 0) {
            $data->progression = get_string(
                'progression',
                'format_softcourse',
            );
            $percentcomplete = $nbcomplete * 100 / $nbcompletion;
            $data->progression_percent = (int) $percentcomplete;
        }

        // Disable the start button if no start URL is available.
        if ($data->start_url == null) {
            $data->disabledStart = 'true';
        }

        // Insert "add section" control on initial section (section 0).
        if (!$this->format->get_sectionnum()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->insertafter = true;
        }

        return $data;
    }

    /**
     * Calculates completion information for a course module and updates the provided data.
     *
     * @param object $cm The course module object, which may include or reference cm_info.
     * @param stdClass $data The data object containing details about activities and URLs.
     * @param \completion_info $completioninfo The completion information object.
     * @param int $nbcompletion The total number of completion items.
     * @param int $nbcomplete The number of completed items.
     * @return void
     */
    public function get_completion($cm, $data, $completioninfo, $nbcompletion, $nbcomplete) {
        // Determine if the desired information is in $cm or $cm->cminfo.
        $cminfo = null;
        if (is_object($cm)) {
            if ($cm instanceof \cm_info) {
                $cminfo = $cm;
            } else if (property_exists($cm, 'cminfo') && $cm->cminfo instanceof \cm_info) {
                $cminfo = $cm->cminfo;
            }
        }

        if ($cminfo !== null) {
            if (
                $cminfo->get_user_visible() &&
                (isset($cminfo->available) && $cminfo->available) &&
                (($cminfo->uservisible && !$cminfo->is_stealth() && $cminfo->modname != 'label') || !empty($cm->url)) &&
                $data->first_cm_url == ''
            ) {
                if ($cminfo->modname == 'resource') {
                    $cminfo->url->param(
                        'forceview',
                        1,
                    );
                }
                if ($data->start_url == null) {
                    $data->start_url = $cminfo->url->out(false);
                }
                $data->first_cm_url = $cminfo->url->out(false);
            }

            if (isset($cminfo->completion) && $cminfo->completion > 0) {
                $nbcompletion++;
            }

            if (isset($cminfo)) {
                $nbcomplete += $completioninfo->get_data(
                    $cminfo,
                    true,
                )->completionstate;

                if (
                    $cminfo->deletioninprogress == 0 && $cminfo->visible == 1 && $cminfo->modname != "label" &&
                    $cminfo->visibleoncoursepage == 1 && $cminfo->uservisible && $cminfo->available == true
                ) {
                    $data->countactivities += 1;
                }
            }
        }
        return [
            $data,
            $nbcompletion,
            $nbcomplete,
        ];
    }
}
