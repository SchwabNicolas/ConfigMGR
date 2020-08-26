<?php

namespace ConfigMGR;

class ConfigurationManager
{
    private string $path;
    private array $constants_list = array();
    private array $variables_list = array();
    private array $config = array();
    private array $computed_values = array();
    private bool $loaded = false;
    private static ?ConfigurationManager $instance = null;

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
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function load_json_config()
    {
        $config_file = fopen($this->path, "r");
        $file_content = fread($config_file, filesize($this->path));
        fclose($config_file);
        return json_decode($file_content);
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

            $constants = $this->load_constants_from_object($config->constants);
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
            if (gettype($value) == "string") {
                $value = $this->replace_value($name, $value);
            }
            $this->add_computed_value($name, $value);
        }
    }

    /** Add a key in computed values array.
     * @param $name string name of the key
     * @param $value mixed value of the key
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function add_computed_value($name, $value)
    {
        $this->computed_values[$name] = $value;
    }

    /** Extract the first markup name of a string.
     * @param $string string string
     * @return string|string[] markup
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function extract_name_from_markup($string)
    {
        if (preg_match("/{(.*?)}/", $string, $matches))
            return str_replace(["{", "}"], "", $matches[0]);
        return "";
    }

    /** Replace markup by a value.
     * @param $name string key name
     * @param $value mixed key value
     * @return mixed|string|string[] new key value
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function replace_value($name, $value)
    {
        if ($this->has_markup($value)) {
            $extracted_name = $this->extract_name_from_markup($value);

            if (defined($extracted_name)) {
                $value = $this->replace_markup($extracted_name, $value);
            } else if ($this->computed_values[$extracted_name] && isset($this->computed_values[$extracted_name])) {
                $value = $this->replace_markup($extracted_name, $value);
                if ($this->has_markup($value)) {
                    $value = $this->replace_value($name, $value);
                }
            } else {
                $this->replace_value($extracted_name, $this->get_value_from_key_name($extracted_name));
            }
        }

        $this->computed_values[$name] = $value;
        return $value;
    }

    /** Replace markups in a key by the value of another or a constant.
     * @param $extracted_name string name extracted from markup
     * @param $string string value
     * @return string|string[] new value
     */
    private function replace_markup($extracted_name, $string)
    {
        $to_replace = "{" . $extracted_name . "}";
        $value = defined($extracted_name) ? constant($extracted_name) : $this->computed_values[$extracted_name];

        $replaced_string = str_replace($to_replace, $value, $string);

        if ($this->has_markup($replaced_string)) {
            $this->replace_markup($this->extract_name_from_markup($replaced_string), $replaced_string);
        }


        return $replaced_string;
    }

    /**
     * Return the number of markups if the string has markup.
     * @param $string string string
     * @return false|int number of markups
     */
    private function has_markup($string)
    {
        return preg_match('/{(.*?)}/', $string);
    }

    /** Gets a value from a key name.
     * @param $name string key name
     * @return mixed value
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
     * Loaded getter.
     * @return bool loaded
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function is_loaded() {
        return $this->loaded;
    }

    /**
     * DEBUG
     *
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
     *
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