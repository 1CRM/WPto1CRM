<?php

/*
Plugin Name: send contact form 7 form to 1crm
Plugin URI: http://1crm-system.de/
Description: This plugin sends the data from a form that contains an acceptance-1crm field to the lead capture of a 1crm system
Author: Björn Rafreider
Version: 0.1
Author URI: http://www.visual4.de/
 */

class v4_post_cf7_form_to_1crm
{
    static $instance;
    var $settings = array();
    var $prefix = 'v4lc_';

    public function __construct()
    {
        $this->settings = $this->getSettingsObject();
        add_action('admin_init', array($this, 'save_settings'));
        add_action("wpcf7_before_send_mail", array(&$this, 'wpcf7_before_send_mail'));
        add_action('admin_menu', array($this, 'menu'));

        if($this->get_setting('lc_uri') == '') $this->activate();

    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function activate()
    {
        $this->add_setting('lc_uri', "http://demo.infoathand.net/leadCapture.php");
        $this->add_setting('campaign_id', "");
        $this->add_setting('assigned_user_id', "");

    }

    public function wpcf7_before_send_mail(WPCF7_ContactForm $wpcf7)
    {

        $postData = $wpcf7->posted_data;
        if ($postData['acceptance-1crm'] == true) {

            $post = array();
            foreach ($postData as $key => $value){
                $post[$key] = $value;
            }
            $post['campaign_id'] = $this->get_setting('campaign_id');
            $post['assigned_user_id'] = $this->get_setting('assigned_user_id');

            $postStringArray = array();
            foreach ($post as $key => $value) {
                $postStringArray[] = $key . '=' . urlencode($value);
            }
            $postString = implode('&', $postStringArray);

            $url = $this->get_setting('lc_uri');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, count($postStringArray));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

            $return = curl_exec($ch);

            curl_close($ch);
            $wpcf7->posted_data['beschreibung'] .= "\n\n" . print_r($return, 1);
        }


    }

    function menu()
    {
        add_options_page("CRM Lead Capture", "CRM Lead Capture", 'manage_options', "v4-1crm-cf7", array($this, 'admin_page'));
    }

    public function admin_page()
    {
        include 'v4_post_cf7_form_to_1crm_admin.php';
    }

    function get_field_name($setting, $type = 'string')
    {
        return "{$this->prefix}setting[$setting][$type]";
    }

    function saved_admin_notice()
    {
        echo '<div class="updated">
	       <p>1CRM Lead Capture settings have been saved.</p>
	    </div>';


    }

    function add_setting($option = false, $newvalue)
    {
        if ($option === false) return false;

        if (!isset($this->settings[$option])) {
            return $this->set_setting($option, $newvalue);
        } else return false;
    }

    function set_setting($option = false, $newvalue)
    {
        if ($option === false) return false;

        $this->settings = $this->getSettingsObject($this->prefix);
        $this->settings[$option] = $newvalue;
        return $this->set_settings_obj($this->settings);
    }

    function set_settings_obj($newobj)
    {
        return update_option("{$this->prefix}settings", $newobj);
    }

    protected function getSettingsObject()
    {
        return get_option("{$this->prefix}settings", false);
    }

    function get_setting($option = false)
    {
        if ($option === false || !isset($this->settings[$option])) return false;

        return apply_filters($this->prefix . 'get_setting', $this->settings[$option], $option);
    }

    function save_settings()
    {
        if (isset($_REQUEST["{$this->prefix}setting"]) && check_admin_referer('save_v4lc_settings', 'save_the_v4lc')) {

            $new_settings = $_REQUEST["{$this->prefix}setting"];

            foreach ($new_settings as $setting_name => $setting_value) {
                foreach ($setting_value as $type => $value) {
                    if ($type == "array") {
                        $this->set_setting($setting_name, explode(";", $value));
                    } else {
                        $this->set_setting($setting_name, $value);
                    }
                }
            }

            add_action('admin_notices', array($this, 'saved_admin_notice'));
        }
    }

}

if (!function_exists('str_true')) {
    /**
     * Evaluates natural language strings to boolean equivalent
     *
     * Used primarily for handling boolean text provided in shopp() tag options.
     * All values defined as true will return true, anything else is false.
     *
     * Boolean values will be passed through.
     *
     * Replaces the 1.0-1.1 value_is_true()
     *
     * @author Jonathan Davis
     * @since 1.2
     *
     * @param string $string The natural language value
     * @param array $istrue A list strings that are true
     * @return boolean The boolean value of the provided text
     **/
    function str_true($string, $istrue = array('yes', 'y', 'true', '1', 'on', 'open'))
    {
        if (is_array($string)) return false;
        if (is_bool($string)) return $string;
        return in_array(strtolower($string), $istrue);
    }


}

$v4ContactForm = v4_post_cf7_form_to_1crm::getInstance();
