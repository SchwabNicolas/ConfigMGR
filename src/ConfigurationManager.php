<?php

namespace ConfigMGR;

use ConfigMGR\Exceptions\FileNotReadableException;
use ConfigMGR\Exceptions\JsonNotValidException;
use ConfigMGR\Exceptions\SelfReferenceException;
use ConfigMGR\StringInterpolationFormatter;

class ConfigurationManager
{
    private string $path;
    private array $constants_list = array();
    private array $variables_list = array();
    private array $config = array();
    private array $computed_values = array();
    private bool $loaded = false;
    private static ?ConfigurationManager $instance = null;

    public static string $open_markup = "{";
    public static string $close_markup = "}";

    public static string $open_string_interpolation = "{%";
    public static string $close_string_interpolation = "%}";

    /**
     * Private ConfigurationManager constructor.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function __construct()
    {
    }

    /**
     * Prevents ConfigurationManager from being cloned.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function __clone()
    {
    }

    /**
     * Prevents ConfigurationManager from being unserialized.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function __wakeup()
    {
    }

    /**
     * Gets an instance of ConfigurationManager.
     * @return ConfigurationManager|null ConfigurationManager instance.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public static function getInstance()
    {
        if (static::$instance === null)
            static::$instance = new static();

        return static::$instance;
    }

    /**
     * Sets the path of the configuration manager.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public static function set_path($path)
    {
        $cfgmgr = ConfigurationManager::getInstance();
        $cfgmgr->path = $path;
    }

    /**
     * Open configuration from a JSON file.
     * @return mixed the deserialized JSON file
     * @throws JsonNotValidException|FileNotReadableException
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function load_json_config()
    {
        if (!is_readable($this->path)) {
            throw new FileNotReadableException();
        }
        $config_file = fopen($this->path, "r");
        $file_content = fread($config_file, filesize($this->path));
        fclose($config_file);
        $json = json_decode($file_content);
        if ($json === null) {
            throw new JsonNotValidException();
        }
        return $json;
    }

    /**
     * Load configuration from a JSON file.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function load_config()
    {
        if (isset($this->path)) {
            $config = $this->load_json_config();

            $constants = null;
            $variables = null;

            if (isset($config->configmgr) && !empty($config->configmgr))
                $this->load_configMGR_config($config->configmgr);
            if (isset($config->constants) && !empty($config->constants))
                $constants = $this->load_constants_from_object($config->constants);
            if (isset($config->variables) && !empty($config->variables))
                $variables = $this->load_variables_from_object($config->variables);

            $this->config = array_merge($constants, $variables);

            $this->replace_markups();
            $this->match_constants_with_config_names($constants);
            $this->match_variables_with_config_names($variables);

            $this->define_constants();

            $loaded = true;
        }
    }

    /**
     * Load ConfigMGR configuration.
     * @param $config array configuration
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function load_configMGR_config($config)
    {
        if (isset($config->open_markup)
            && !ctype_space($config->open_markup)
            && !$config->open_markup == "") {
            $this::$open_markup = $config->open_markup;
        }
        if (isset($config->close_markup)
            && !ctype_space($config->close_markup)
            && !$config->close_markup == "") {
            $this::$close_markup = $config->close_markup;
        }

        if (isset($config->open_string_interpolation)
            && !ctype_space($config->open_string_interpolation)
            && !$config->open_string_interpolation == "") {
            $this::$open_string_interpolation = $config->open_string_interpolation;
        }
        if (isset($config->close_string_interpolation)
            && !ctype_space($config->close_string_interpolation)
            && !$config->close_string_interpolation == "") {
            $this::$close_string_interpolation = $config->close_string_interpolation;
        }
    }

    /**
     * Load constants from an object (dictionary-like)
     * @param $cfg_constants object constants
     * @return array constants
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function load_constants_from_object($cfg_constants)
    {
        $constants = array();
        foreach ($cfg_constants as $constant => $value) {
            $constants[$constant] = $value;
        }
        return $constants;
    }

    /**
     * Load variables from an object (dictionary-like)
     * @param $cfg_variables object variables
     * @return array variables
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function load_variables_from_object($cfg_variables)
    {
        $variables = array();
        foreach ($cfg_variables as $variable => $value) {
            $variables[$variable] = $value;
        }
        return $variables;
    }

    /**
     * Define constants.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function define_constants()
    {
        foreach ($this->constants_list as $name => $value) {
            define($name, $value);
        }
    }

    /**
     * Matches the keys in the computed values with the keys in constants list array.
     * @param $constants object loaded keys
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function match_constants_with_config_names($constants)
    {
        foreach ($constants as $name => $value) {
            $this->constants_list[$name] = $this->computed_values[$name];
        }
    }

    /**
     * Matches the keys in the computed values with the keys in variables list array.
     * @param $variables object loaded keys
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function match_variables_with_config_names($variables)
    {
        foreach ($variables as $name => $value) {
            $this->variables_list[$name] = $this->computed_values[$name];
        }
    }

    /**
     * Replace markups in the configuration array.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function replace_markups()
    {
        foreach ($this->config as $name => $value) {
            $value = $this->crawl($name, $value);
            $this->add_computed_value($name, $value);
        }
    }

    /**
     * Crawls through different kinds of objects.
     * @param $name string name of the origin object
     * @param $val mixed value of the currently crawled through object.
     * @return mixed|string|string[]
     * @throws SelfReferenceException
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function crawl($name, $val)
    {
        if (gettype($val) == "string") {
            $val = $this->replace_value($name, $val);
        } else if (gettype($val) == "array") {
            $val = $this->array_crawl($name, $val);
        } else if (gettype($val) == "object") {
            $val = $this->object_crawl($name, $val);
        }
        return $val;
    }

    /**
     * Replace markup by a value.
     * @param $name string key name
     * @param $value mixed key value
     * @return mixed|string|string[] new key value
     * @throws SelfReferenceException
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function replace_value($name, $value)
    {
        if (StringInterpolationFormatter::has_string_interpolation($value)) {
            $extracted_name = StringInterpolationFormatter::extract_name_from_string_interpolation($value);
            if ($extracted_name == $name) {
                throw new SelfReferenceException();
            }
            if (defined($extracted_name)) {
                $value = defined($extracted_name) ? constant($extracted_name) : $this->computed_values[$extracted_name];
                $value = StringInterpolationFormatter::replace_markup($extracted_name, $value, constant($extracted_name));
            } else if (isset($this->computed_values[$extracted_name]) && $this->computed_values[$extracted_name]) {
                $value = StringInterpolationFormatter::replace_markup($extracted_name, $value, $this->computed_values[$extracted_name]);
                if (StringInterpolationFormatter::has_string_interpolation($value)) {
                    $value = $this->replace_value($name, $value);
                }
            } else {
                $this->replace_value($extracted_name, $this->get_value_from_key_name($extracted_name));
            }
        }

        return $value;
    }

    /**
     * Crawls recursively through arrays.
     * @param $name string key name
     * @param $value mixed initial value
     * @return mixed final value
     * @throws SelfReferenceException
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function array_crawl($name, $value)
    {
        foreach ($value as $key => $val) {
            $value[$key] = $this->crawl($name, $val);
        }
        return $value;
    }

    /**
     * Crawls recursively through objects.
     * @param $name string key name
     * @param $value mixed initial value
     * @return mixed final value
     * @throws SelfReferenceException
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function object_crawl($name, $value)
    {
        foreach ($value as $key => $val) {
            $value->$key = $this->crawl($name, $val);
        }
        return $value;
    }

    /**
     * Add a key in computed values array.
     * @param $name string name of the key
     * @param $value mixed value of the key
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function add_computed_value($name, $value)
    {
        $this->computed_values[$name] = $value;
    }

    /**
     * Gets a value from a key name.
     * @param $name string key name
     * @return mixed value
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function get_value_from_key_name($name)
    {
        return $this->config[$name];
    }

    /**
     * Gets the configuration constants.
     * @return array list of constant-value pairs
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function get_constants()
    {
        $constants = array();
        foreach ($this->constants_list as $constant => $name) {
            $constants[$constant] = constant($constant);
        }
        return $constants;
    }

    /**
     * Gets the configuration variables.
     * @return array list of variables
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function get_variables()
    {
        return $this->variables_list;
    }

    /**
     * Gets a variable value by name.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function get_variable($name)
    {
        return $this->variables_list[$name];
    }

    /**
     * Sets a variable value by name
     * @param $name string the key name
     * @param $new_value mixed the key value
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function set_variable($name, $new_value)
    {
        $this->variables_list[$name] = $new_value;
    }

    /**
     * Gets a constant value by name.
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function get_constant($name)
    {
        return constant($this->constants_list[$name]);
    }

    /**
     * Loaded getter.
     * @return bool loaded
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function is_loaded()
    {
        return $this->loaded;
    }

    /**
     * DEBUG
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function get_constants_table()
    {
        print '<table>';
        print '<th>Constant name</th>';
        print '<th>Constant value</th>';
        print '<th>Constant type</th>';
        foreach ($this->constants_list as $constant => $value) {
            print '<tr>';
            print '<td style="border: 2px solid black; padding: 10px;">';
            print $constant;
            print '</td>';
            print '<td style="border: 2px solid black; padding: 10px;">';
            print_r(constant($constant));
            print '</td>';
            print '<td style="border: 2px solid black; padding: 10px;">';
            print gettype(constant($constant));
            print '</td>';
            print '</tr>';
        }
        print '</table>';
    }

    /**
     * DEBUG
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function get_variables_table()
    {
        print '<table>';
        print '<th>Variable name</th>';
        print '<th>Variable value</th>';
        print '<th>Variable type</th>';
        foreach ($this->variables_list as $variable => $value) {
            print '<tr>';
            print '<td style="border: 2px solid black; padding: 10px;">';
            print $variable;
            print '</td>';
            print '<td style="border: 2px solid black; padding: 10px;">';
            print_r($value);
            print '</td>';
            print '<td style="border: 2px solid black; padding: 10px;">';
            print gettype($value);
            print '</td>';
            print '</tr>';
        }
        print '</table>';
    }
}