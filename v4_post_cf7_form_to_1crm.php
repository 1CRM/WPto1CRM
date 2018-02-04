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

		add_filter( 'wpcf7_contact_form_properties', array($this, 'assign_form'), 10, 2 );
		add_filter( 'wpcf7_validate_text',  array($this, 'validate'), 10, 2);
		add_filter( 'wpcf7_form_elements',  array($this, 'add_elements'), 10, 1);
		add_filter( 'request', array($this, 'process_request_vars'), 10, 1 );

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
		set_time_limit(60);
        $postData = $wpcf7->posted_data;

		$post = array();
		foreach ($postData as $key => $value){
			$post[$key] = $value;
		}
		$post['campaign_id'] = $this->get_setting('campaign_id');
		$post['assigned_user_id'] = $this->get_setting('assigned_user_id');

		$override = $wpcf7->additional_setting( 'onecrm_override', false);
		if (count($override)) {
			foreach ($override as $s) {
				$parts = explode('|', $s, 2);
				if (count($parts) == 2) {
					$post[$parts[0]] = $parts[1];
				}
			}
		}

		$postStringArray = array();
		foreach ($post as $key => $value) {
			if (is_array($value)) {
				reset($value);
				$value = current($value);
			}
			$postStringArray[] = $key . '=' . urlencode($value);
		}
		$postString = implode('&', $postStringArray);

		$url = $wpcf7->additional_setting( 'onecrm_url', 1);
		if (count($url))
			$url = $url[0];
		else
			$url = $this->get_setting('lc_uri');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, count($postStringArray));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		$return = curl_exec($ch);

		curl_close($ch);
		$return_field = $wpcf7->additional_setting( 'onecrm_return_field', 1);
		if (count($return_field))
			$wpcf7->posted_data[$return_field[0]] .= "\n\n" . print_r($return, 1);

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

	function validate($result, $tag)
	{
		$tag = new WPCF7_Shortcode( $tag );
		$name = $tag->name;
		$validators = $this->form->additional_setting('onecrm_validate_' . $name, false);
		if (!empty($validators)) {
			foreach ($validators as $v) {
				$v = explode('|', $v, 2);
				if (count($v) == 1) {
					$message = 'Invalid value';
					$re = $v[0];
				} else {
					list($message, $re) = $v;
				}
				$value = isset( $_POST[$name] ) ? stripslashes( strtr( (string) $_POST[$name], "\n", " " ) ) : '';
				if (strlen($value) || empty($result['reason'][$name])) {
					if (!@preg_match($re, $value)) {
						$result['valid'] = false;
						$result['reason'][$name] = $message;
					}
				}
			}
		}
		return $result;
	}

	function assign_form($props, $form)
	{
		$this->form = $form;
		return $props;
	}

	function add_elements($html)
	{
		foreach ($this->partner_params as $k => $v) {
			$html .= "<input type=\"hidden\" name=\"$k\" value=\"$v\" />";
		}
		return $html;
	}

	public function process_request_vars($vars) {
		$request_uri = $_SERVER['REQUEST_URI'];
		$parsed = parse_url($request_uri);
		$source_number = $partner_number = null;
		if (!empty($parsed['query'])) {
			$parts = explode('&', $parsed['query']);
			foreach ($parts as $part) {
				if (preg_match('/^(\d+)|(\d+_)|(\d+_\d+)|(_\d+)$/', $part)) {
					$numbers = explode('_', $part);
					if (count($numbers) == 2)
						list($partner_number, $source_number) = $numbers;
					else {
						$partner_number = $numbers[0];
						$source_number = null;
					}
				}
			}
		}
		if (empty($partner_number))
			if (!empty($_COOKIE[OCRM_EX1]))
				$partner_number = $_COOKIE[OCRM_EX1];
		if (empty($source_number))
			if (!empty($_COOKIE[OCRM_EX2]))
				$source_number = $_COOKIE[OCRM_EX2];
		$partner_number = (int)$partner_number;
		$source_number = (int)$source_number;

		$params = array();
		if (!empty($partner_number)) {
			$params['_ex1'] = $partner_number;
			setcookie (OCRM_EX1, $partner_number, time() + 365 * 24 * 60 * 60, COOKIEPATH, 'www.1crm.com'); 
		}
		if (!empty($source_number)) {
			$params['_ex2'] = $source_number;
			setcookie (OCRM_EX2, $source_number, time() + 365 * 24 * 60 * 60, COOKIEPATH, 'www.1crm.com'); 
		}

		$this->partner_params = $params;
		return $vars;
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
if (!defined('OCRM_EX1'))
	define('OCRM_EX1', '_1crm_ex1');
if (!defined('OCRM_EX2'))
	define('OCRM_EX2', '_1crm_ex2');

