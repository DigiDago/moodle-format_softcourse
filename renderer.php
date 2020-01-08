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
 * Renderer for outputting the Soft Course format.
 *
 * @package format_softcourse
 * @copyright 2018 Pimenko <contact@pimneko.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.5
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for softcourse format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_softcourse_renderer extends format_section_renderer_base {

    /**
     * @var stdClass Course
     */
    private $course;

    /**
     * @var stdClass Course format
     */
    private $courseformat = null;

    /**
     * @var stdClass Mod info
     */
    private $modinfo = null;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     * @throws moodle_exception
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->course = $page->course;
        $this->courseformat = course_get_format($this->course);
        $this->modinfo = get_fast_modinfo($this->course);

        // Since format_softcourse_renderer::section_edit_controls() only displays
        // the 'Set current section' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'softcourse'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $markedthissection = get_string('markedthistopic');
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => array('class' => '', 'alt' => $markedthissection),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markedthissection,
                        'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $markthissection = get_string('markedthistopic');
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => array('class' => '', 'alt' => $markthissection),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markthissection,
                        'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     * @throws moodle_exception
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        echo $this->course_introduction();
    }

    /**
     * Output the html for a multiple section page.
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @throws moodle_exception
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;
        $context = context_course::instance($this->course->id);

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            parent::print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
        } else {
            echo $this->course_introduction();
            if ($this->courseformat->get_format_options()['hideallsections'] == 0) {
                echo $this->course_sections();
            }
        }
    }

    /**
     *  Return le template rendered of the introduction of the course
     *
     * @throws moodle_exception
     */
    public function course_introduction() {
        $template = new stdClass();
        $template->start_url = "";

        foreach ($this->modinfo->get_cms() as $cm) {
            if ($cm->uservisible && !$cm->is_stealth() || !empty($cm->url)) {
                $template->start_url = $cm->url;
                break;
            }
        }
        if ($template->start_url == "") {
            $template->disabledStart = "disabled";
        }

        // Get the introduction.
        $summary = $this->courseformat->get_format_options()['introduction'];

        // Rewrite url of files (like pictures).
        $context = context_course::instance($this->course->id);

        $summarytext = file_rewrite_pluginfile_urls($summary, 'pluginfile.php',
            $context->id, 'course', 'introduction', 0);

        $summarytext = $summary;
        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        $template->summary = format_text($summarytext, 1, $options);

        // Button Start.
        $template->start = get_string('startcourse', 'format_softcourse');

        return $this->render_from_template('format_softcourse/introduction', $template);
    }

    /**
     *  Return le template rendered of the sections of the course
     *
     * @throws moodle_exception
     */
    public function course_sections() {
        global $COURSE;

        $template = $template = new stdClass();
        $sections = [];
        $sectionmods = [];
        $completioninfo = new completion_info($COURSE);
        $context = context_course::instance($this->course->id);

        // Assiociation tabs : section[id_of_section] = cm.
        foreach ($this->modinfo->get_cms() as $cm) {
            $idsection = $cm->get_section_info()->section;

            // Hide the section 0 if the course format option is set to "Hide the section 0".
            if (!($idsection == 0 && $this->courseformat->get_format_options()['hidesectionzero'] == 1)) {
                $info = $this->modinfo->get_section_info_all()[$idsection];
                if (!isset($sectionmods[$idsection])) {
                    $sectionmods[$idsection] = new stdClass();
                    $sectionmods[$idsection]->cm = [];
                    $sectionmods[$idsection]->id = $idsection;
                    $sectionmods[$idsection]->name = $info->name;
                    $sectionmods[$idsection]->summary = $info->summary;
                }
                $sectionmods[$idsection]->cm[] = $cm;
            }
        }

        // Put tabs into a tabs readable by mustache.
        foreach ($sectionmods as $section) {
            $s = new stdClass();
            $s->name = $section->name;
            $s->id = $section->id;
            $s->courseid = $this->course->id;
            $s->summary = $section->summary;
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
                if (($cm->uservisible && !$cm->is_stealth() || !empty($cm->url)) && $s->first_cm_url == '') {
                    $s->first_cm_url = $cm->url;
                }
                if ($cm->completion > 0) {
                    $nbcompletion++;
                }
                $nbcomplete += $completioninfo->get_data($cm, true)->completionstate;
                if ($cm->deletioninprogress == 0 && $cm->visible == 1 && $cm->modname != "label") {
                    $s->countactivities += 1;
                }
            }

            // Count the percent of cm complete.
            if ($nbcompletion != 0) {
                $s->progression = get_string('progression', 'format_softcourse');
                $percentcomplete = $nbcomplete * 100 / $nbcompletion;
                $s->progression_percent = intval($percentcomplete);
            }

            $sections[] = $s;
        }

        $template->sections = $sections;

        return $this->render_from_template('format_softcourse/sections', $template);
    }
}
