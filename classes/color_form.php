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
 * TODO: comment
 *
 * @package    tool_genmobilecss
 * @copyright  2020 Alison of Sheesania
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_genmobilecss;

use \Sabberworm\CSS\Parser;
use \Sabberworm\CSS\Value\Color;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir.'/formslib.php');

class color_form extends \moodleform {
    private $colors = array();

    public function __construct(string $css = null) {
        $cache = \cache::make('tool_genmobilecss', 'colors');

        if(is_null($css)) {
            $this->colors = $cache->get('colors');
        } else {
            $cssparser = new Parser($css);
            $cssdoc = $cssparser->parse();
            foreach($cssdoc->getAllRuleSets() as $ruleset) {
                foreach($ruleset->getRules() as $rule) {
                    $value = $rule->getValue();
                    if($value instanceof Color) {
                        $color = (string) $value;
                        if (!array_key_exists($color, $this->colors)) {
                            $this->colors[$color] = new color_info();
                        }
                        $this->colors[$color]->usedcount++;
                    }
                }
            }
            uasort($this->colors, function($a, $b)
            {
                return $b->usedcount - $a->usedcount;
            });
            $cache->set('colors', $this->colors);
        }
        parent::__construct();
    }

    public function definition() {
        global $PAGE;
        $PAGE->requires->js_call_amd('tool_genmobilecss/colorpicker', 'init');
        
        $mform = $this->_form;
        $this->add_action_buttons(false, get_string('colorformsubmit', 'tool_genmobilecss'));
        $mform->addElement('static', 'intro', '', get_string('colorformdesc', 'tool_genmobilecss'));

        $mform->addElement('textarea', 'customcss', get_string('customcsslabel', 'tool_genmobilecss'),
            array('rows'=>'6', 'cols'=>'50', 'style'=>'font-family: monospace;'));
        $existingcustomcss = $this->get_existing_custom_css();
        $mform->setDefault('customcss', $existingcustomcss);

        foreach($this->colors as $colorname => $colorinfo) {
            $mform->addElement('text', $colorname, $colorname, array('class'=>'colorpicker-text'));
            $mform->setType($colorname, PARAM_TEXT);
            $mform->addElement('static', 'description-' . $colorname, '',
                    $colorinfo->usedcount . " " . get_string('uses', 'tool_genmobilecss'));

            $previewgroup = array();
            $previewgroup[] =& $mform->createElement('html', $this->get_color_preview_div($colorname, False));
            $previewgroup[] =& $mform->createElement('html', '<div id="convert-message-' . substr($colorname, 1) . '" ' .
                'style="height: 30px; 10px; margin-right: 10px; margin-bottom: 35px; display: none;">' .
                get_string('willbeconvertedto', 'tool_genmobilecss') . '</div>');
            $previewgroup[] =& $mform->createElement('html', $this->get_color_preview_div($colorname, True));
            $mform->addGroup($previewgroup, 'preview-' . $colorname, '', '', false);
        }

        $mform->addElement('hidden', 'step', '3');
        $mform->setType('step', PARAM_INT);
        $this->add_action_buttons(false, get_string('colorformsubmit', 'tool_genmobilecss'));
    }

    private function get_existing_custom_css() {
        $css_file_manager = new css_file_manager();
        $css = $css_file_manager->get_file_contents();
        if (empty($css)) {
            return '';
        }
        $with_beginning_trimmed = explode('\* START ADDLCSS *\\', $css)[1];
        $with_end_trimmed = explode("\n\* END ADDLCSS *\\", $with_beginning_trimmed)[0];
        return $with_end_trimmed;
    }

    private function get_color_preview_div(string $color, bool $is_new_color_preview) {
        $id = $is_new_color_preview ? 'id="new-color-preview-' . substr($color, 1) . '"' : '';
        $hidden = $is_new_color_preview ? 'display: none;' : '';
        return '<div ' . $id . ' style="background-color: ' . $color . '; ' .
            'width: 30px; height: 30px; margin-right: 10px; margin-bottom: 35px; outline: solid; ' . $hidden .
            '"></div>';
    }
}

class color_info {
    public $usedcount = 0;
}