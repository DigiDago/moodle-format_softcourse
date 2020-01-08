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
 * This file contains main class for the course format SoftCourse
 *
 * @since     Moodle 2.0
 * @package   format_softcourse
 * @copyright 2019 Pimenko <contact@pimenko.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/topics/lib.php');

/**
 * Main class for the Soft Course format
 *
 * @package    format_softcourse
 * @copyright  2019 Pimenko <contact@pimenko.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_softcourse extends format_topics {


    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));
        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                $sectionno = $sr;
            } else {
                $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-' . $sectionno);
            }
        }
        return $url;
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Soft Course format uses the following options:
     * - coursedisplay
     * - hideallsections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                    'hideallsections' => [
                            'default' => 0,
                            'type' => PARAM_INT
                    ],
                    'hidesectionzero' => [
                            'default' => 0,
                            'type' => PARAM_INT
                    ],

                    'introduction' => [
                            'default' => '',
                            'type' => PARAM_RAW
                    ]
            ];
        }
        if ($foreditform) {
            $optionsedit = [
                    'hideallsections' => [
                            'label' => new lang_string('hideallsections', "format_softcourse"),
                            'help' => 'hideallsections',
                            'help_component' => 'format_softcourse',
                            'element_type' => 'select',
                            'element_attributes' => [
                                    [
                                            0 => new lang_string('hideallsectionsno', "format_softcourse"),
                                            1 => new lang_string('hideallsectionsyes', "format_softcourse")
                                    ]
                            ]
                    ],
                    'hidesectionzero' => [
                            'label' => new lang_string('hidesectionzero', "format_softcourse"),
                            'help' => 'hidesectionzero',
                            'help_component' => 'format_softcourse',
                            'element_type' => 'select',
                            'element_attributes' => [
                                    [
                                            0 => new lang_string('hidesectionzerono', "format_softcourse"),
                                            1 => new lang_string('hidesectionzeroyes', "format_softcourse")
                                    ]
                            ]
                    ],
                    'introduction' => [
                            'label' => new lang_string('introduction', "format_softcourse"),
                            'help' => 'introduction',
                            'help_component' => 'format_softcourse',
                            'element_type' => 'editor',
                            'maxfiles' => EDITOR_UNLIMITED_FILES
                    ]
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $optionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        // Put the old value of format option introduction in the editor.
        if (isset($this->get_format_options()['introduction'])) {
            $element = $mform->getElement('introduction');
            $element->setValue(['text' => $this->get_format_options()['introduction']]);
            $element->setMaxfiles(EDITOR_UNLIMITED_FILES);
        }

        return $elements;
    }

    /**
     * Prepares values of course or section format options before storing them in DB
     *
     * If an option has invalid value it is not returned
     *
     * @param array $rawdata associative array of the proposed course/section format options
     * @param int|null $sectionid null if it is course format option
     * @return array array of options that have valid values
     */
    protected function validate_format_options(array $rawdata, int $sectionid = null) : array {
        if (!$sectionid) {
            $allformatoptions = $this->course_format_options(true);
        } else {
            $allformatoptions = $this->section_format_options(true);
        }
        $data = array_intersect_key($rawdata, $allformatoptions);
        foreach ($data as $key => $value) {
            $option = $allformatoptions[$key] + ['type' => PARAM_RAW, 'element_type' => null, 'element_attributes' => [[]]];
            if ($option['element_type'][0] == 'editor') {
                $data[$key] = clean_param($value['text'], $option['type']);
            } else {
                $data[$key] = clean_param($value, $option['type']);
            }

            if ($option['element_type'] === 'select' && !array_key_exists($data[$key], $option['element_attributes'][0])) {
                // Value invalid for select element, skip.
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }

        // Managing of image in the introduction.
        if (isset($data['introduction']) &&  $introductiondraftid = file_get_submitted_draft_itemid('introduction')) {
            $context = context_course::instance($this->courseid);
            $options = array('subdirs' => false);

            // Retrieve the image in the draftfilearea and put it into the introduction filearea of the plugin.
            $data['introduction']['text'] = file_save_draft_area_files($introductiondraftid, $context->id,
                    'format_softcourse', 'introduction', time(), null, $data['introduction']['text']);
            $data['introduction']['text'] = file_rewrite_pluginfile_urls($data['introduction']['text'], 'pluginfile.php',
                    $context->id, 'format_softcourse', 'introduction',  time());
        }

        return $this->update_format_options($data);
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_softcourse_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'softcourse'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Softcourse plugin function function
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @return bool
 */
function format_softcourse_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($filearea == 'sectionimage' || $filearea == 'introduction') {
        $relativepath = implode('/', $args);
        $contextid = $context->id;
        $fullpath = "/$contextid/format_softcourse/$filearea/$relativepath";
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash(sha1($fullpath));
        if ($file) {
            send_stored_file($file, $lifetime, 0, $forcedownload, $options);
            return true;
        }
    }
}