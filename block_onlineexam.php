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
 * Plugin "Exams (evaexam)"
 *
 * @package    block_onlineexam
 * @copyright  2018 Soon Systems GmbH on behalf of evasys GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Onlineexam block.
 *
 * @package    block_onlineexam
 * @copyright  2018 Soon Systems GmbH on behalf of evasys GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_onlineexam extends block_base {

    /** @var boolean Indicates whether the DEBUG mode is set or not */
    private $debugmode;

    /** @var boolean Indicates whether the block is minimally configured or not */
    private $isconfigured;

    /** @var String Error string */
    private $error;

    /** @var String Type of connection - LTI or SOAP */
    private $connectiontype;

    /** @var String Path to .wsdl used for SOAP */
    private $wsdl;

    /** @var Int Id of the current user */
    private $moodleuserid;

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {

        $this->title = get_string('pluginname', 'block_onlineexam');

        // Block settings.
        $config = get_config("block_onlineexam");

        if (isset($config) && !empty($config)) {

            // Get block title from block setting.
            if (!empty($config->blocktitle)) {
                $this->title = format_string($config->blocktitle);
            }

            $this->connectiontype = (!empty($config->connectiontype)) ? $config->connectiontype : '';
            $this->debugmode = (!empty($config->exam_debug)) ? $config->exam_debug : false;

            // Session information.
            global $USER;
            if ($this->moodleuserid = $USER->id) {

                if ($this->connectiontype == 'SOAP') {

                    $this->wsdl = $config->exam_server;

                    // Quick check of SOAP settings.
                    if (empty($config->exam_server) || empty($config->exam_login) || empty($config->exam_user) ||
                            empty($config->exam_pwd)) {
                        $this->isconfigured = false;
                        $this->error = get_string('error_soap_settings_error', 'block_onlineexam');

                        $errorinfo = '';
                        if (empty($config->exam_server)) {
                            $errorinfo .= get_string('error_exam_server_missing', 'block_onlineexam').'<br>';
                        }
                        if (empty($config->exam_login)) {
                            $errorinfo .= get_string('error_exam_login_missing', 'block_onlineexam').'<br>';
                        }
                        if (empty($config->exam_user)) {
                            $errorinfo .= get_string('error_exam_user_missing', 'block_onlineexam').'<br>';
                        }
                        if (empty($config->exam_pwd)) {
                            $errorinfo .= get_string('error_exam_pwd_missing', 'block_onlineexam').'<br>';
                        }

                        $context = context_system::instance();
                        if (has_capability('block/onlineexam:view_debugdetails', $context)) {
                            if (!empty($errorinfo)) {
                                $this->error .= "<br>".$errorinfo;
                            }
                        }
                    } else {
                        $this->isconfigured = true;
                    }

                    // Parse wsdlnamespace from the wsdl url.
                    preg_match('/\/([^\/]+\.wsdl)$/', $this->wsdl, $matches);

                    if (count($matches) == 2) {
                        $this->isconfigured = true;
                    } else {
                        $this->isconfigured = false;
                        $this->error = "WSDL namespace parse error";
                    }
                } else if ($this->connectiontype == 'LTI') {
                    // Quick check of some LTI settings.
                    if (empty($config->lti_url) || empty($config->lti_password) || empty($config->lti_learnermapping)) {
                        $this->isconfigured = false;
                        $this->error = get_string('error_lti_settings_error', 'block_onlineexam');

                        $errorinfo = '';
                        if (empty($config->lti_url)) {
                            $errorinfo .= get_string('error_lti_url_missing', 'block_onlineexam').'<br>';
                        }
                        if (empty($config->lti_password)) {
                            $errorinfo .= get_string('error_lti_password_missing', 'block_onlineexam').'<br>';
                        }
                        if (empty($config->lti_learnermapping)) {
                            $errorinfo .= get_string('error_lti_learnermapping_missing', 'block_onlineexam').'<br>';
                        }

                        $context = context_system::instance();
                        if (has_capability('block/onlineexam:view_debugdetails', $context)) {
                            if (!empty($errorinfo)) {
                                $this->error .= "<br>".$errorinfo;
                            }
                        }

                    } else {
                        $this->isconfigured = true;
                    }
                }
            } else {
                $this->isconfigured = false;
                $this->error = get_string('error_userid_not_found', 'block_onlineexam');
            }
        } else {
            $this->error = get_string('error_config_not_accessible', 'block_onlineexam');
            $this->isconfigured = false;
        }
    }

    /**
     * Display the block content.
     *
     * @return void
     */
    public function get_content() {
        global $CFG, $USER;

        // Block settings.
        $config = get_config("block_onlineexam");

        $context = context_system::instance();
        if (! has_capability('block/onlineexam:view', $context)) {
            $this->content = null;
            return $this->content;
        }

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        if ($this->moodleuserid && $this->isconfigured) {

            $context = $this->page->context;
            $course = $this->page->course;
            $urlparams = 'ctxid='.$context->id.'&cid='.$course->id;
            $url = $CFG->wwwroot.'/blocks/onlineexam/show_exams.php?'.$urlparams;

            if ($config->show_spinner) {
                $this->content->text .= '<div id="block_onlineexam_exams_content" class="block_onlineexam_is-loading">';
                $this->content->text .= '<div class="block_onlineexam_exams_loading">'.
                        '<div class="block_onlineexam_lds-spinner"><div></div><div></div><div></div><div></div><div></div><div>'.
                        '</div><div></div><div></div><div></div><div></div><div></div><div></div></div></div>';
            } else {
                $this->content->text .= '<div id="block_onlineexam_exams_content">';
            }

            if ($config->connectiontype == 'LTI') {
                $connectionclass = 'block_onlineexam_lti';
            } else if ($config->connectiontype == 'SOAP') {
                $connectionclass = 'block_onlineexam_soap';
            }

            if ($config->presentation == 'brief') {
                $presentationclass = 'block_onlineexam_compact';
            } else if ($config->presentation == 'detailed') {
                $presentationclass = 'block_onlineexam_detailed';
            }

            // Testing reveals that the iframe requires the permissions "allow-same-origin allow-scripts",
            // hence the sandbox attribute can not be used.
            $this->content->text .= '<iframe id="block_onlineexam_contentframe" '.
                    'class="'.$connectionclass.' '.$presentationclass.'" src="'.$url.'"></iframe>';

            // If we are showing detailed mode.
            if ($config->presentation == 'detailed') {
                // If enabled, add the 'All exams' button as directly visible.
                if ($config->offer_zoom == true) {
                    $this->content->text .= '<div class="block_onlineexam_allexams"><button class="btn btn-secondary" ' .
                            'onClick="event.stopPropagation(); '.
                            'document.getElementById(\'block_onlineexam_exams_content\').click();">' .
                            get_string('allexams', 'block_onlineexam') . '</button></div>';

                    // Otherwise, add the 'Zoom exam list' button as hidden.
                } else {
                    $this->content->text .= '<div class="block_onlineexam_allexams"><button class="btn btn-secondary" ' .
                            'onClick="event.stopPropagation(); '.
                            'document.getElementById(\'block_onlineexam_exams_content\').click();">' .
                            get_string('zoomexamlist', 'block_onlineexam') . '</button></div>';
                    $this->page->requires->css('/blocks/onlineexam/style/block_onlineexam_offerzoom.css');
                }
            }

            $this->content->text .= '</div>';

            if ($config->exam_popupinfo_title != '') {
                $popupinfotitle = format_string($config->exam_popupinfo_title);
            } else {
                $popupinfotitle = get_string('setting_exam_popupinfo_title_default', 'block_onlineexam');
            }
            if ($config->exam_popupinfo_content != '') {
                $popupinfocontent = format_text($config->exam_popupinfo_content);
            } else {
                $popupinfocontent = get_string('setting_exam_popupinfo_content_default', 'block_onlineexam');
            }

            $this->page->requires->js_call_amd('block_onlineexam/modal-zoom', 'init',
                    array($popupinfotitle, $popupinfocontent, $USER->currentlogin));
            $this->page->requires->css('/blocks/onlineexam/style/block_onlineexam_modal-zoom.css');

            if ($config->show_spinner) {
                $this->page->requires->js_call_amd('block_onlineexam/spinner', 'init');
                $this->page->requires->css('/blocks/onlineexam/style/block_onlineexam_spinner.css');
            }

            if ($config->exam_hide_empty) {
                $this->page->requires->css('/blocks/onlineexam/style/block_onlineexam_hide.css');
            }
        }

        if (!empty($this->error)) {
            $this->content->text = get_string('error_occured', 'block_onlineexam', $this->error);
        }

        return $this->content;
    }

    /**
     * Returns true which means that the block has a settings.php file.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Returns false which means that only one instance is allowed.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Returns false which means that the header should be shown.
     *
     * @return bool
     */
    public function hide_header() {
        return false;
    }

    /**
     * Returns the locations where the block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my-index' => true, 'my' => true, 'site-index' => true, 'course-view' => true);
    }

    /**
     * Returns the class $title var value.
     *
     * Intentionally doesn't check if a title is set.
     *
     * @return string $this->title
     */
    public function get_title() {
        return $this->title;
    }
}
