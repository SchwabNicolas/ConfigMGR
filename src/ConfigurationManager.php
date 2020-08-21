<?php

class ConfigurationManager
{
    private string $path;
    private array $constants_list = array();
    private array $variables_list = array();
    private array $config = array();
    private array $computed_values = array();
    private static ?ConfigurationManager $instance = null;

    // TODO Add phpDoc

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
     * Charge la configuration du projet depuis un fichier JSON.
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
     * Charge la configuration du projet depuis un fichier JSON et la transfère dans les constantes.
     * @return false if an error occurred
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public function load_config()
    {
        if (isset($this->path)) {

            try {
                $config = $this->load_json_config();

                $constants = $this->load_constants($config->constants);
                $variables = $this->load_variables($config->variables);

                $this->config = array_merge($constants, $variables);

                $this->replace_markups();

                $this->match_constants_with_config_names($constants);
                $this->match_variables_with_config_names($variables);

                $this->define_constants();
            } catch (Exception $e) {
                return false;
            }
        }
    }

    private function load_constants($cfg_constants)
    {
        $constants = array();
        foreach ($cfg_constants as $constant => $value) {
            $constants[$constant] = $value;
        }
        return $constants;
    }

    private function load_variables($cfg_variables)
    {
        $variables = array();
        foreach ($cfg_variables as $variable => $value) {
            $variables[$variable] = $value;
        }
        return $variables;
    }

    private function define_constants()
    {
        foreach ($this->constants_list as $name => $value) {
            define($name, $value);
        }
    }

    private function get_value_from_config_name($name)
    {
        return $this->config[$name];
    }

    private function match_constants_with_config_names($constants)
    {
        foreach ($constants as $name => $value) {
            $this->constants_list[$name] = $this->computed_values[$name];
        }
    }

    private function match_variables_with_config_names($variables)
    {
        foreach ($variables as $name => $value) {
            $this->variables_list[$name] = $this->computed_values[$name];
        }
    }

    private function replace_markups()
    {
        foreach ($this->config as $name => $value) {
            if (gettype($value) == "string") {
                $this->replace_value($name, $value);
            } else {
                $this->add_computed_value($name, $value);
            }
        }
    }

    private function add_computed_value($name, $value)
    {
        $this->computed_values[$name] = $value;
    }

    private function extract_name_from_markup($string)
    {
        if(preg_match("/{(.*?)}/", $string, $matches))
            return str_replace(["{", "}"], "", $matches[0]);
        return "";
    }

    // TODO : Finish the system
    private function replace_value($name, $value)
    {
        if ($this->has_markup($value)) {
            $extracted_name = $this->extract_name_from_markup($value);

            if ($this->computed_values[$extracted_name] && isset($this->computed_values[$extracted_name])) {
                $value = $this->replace_markup($extracted_name, $value);
            } else {
                $this->replace_value($extracted_name, $this->get_value_from_config_name($extracted_name));
            }
        }

        $this->computed_values[$name] = $value;
        return $value;
    }

    private function replace_markup($extracted_name, $string)
    {
        $to_replace = "{" . $extracted_name . "}";

        $value = $this->computed_values[$extracted_name];
        $replaced_string = str_replace($to_replace, $value, $string);

        if ($this->has_markup($replaced_string)) {
            $this->replace_markup($this->extract_name_from_markup($replaced_string), $replaced_string);
        }

        return $replaced_string;
    }

    private function has_markup($string)
    {
        return preg_match('/{(.*?)}/', $string);
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