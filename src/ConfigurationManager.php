<?php

class ConfigurationManager
{
    private $path;
    private $constants_list = array();
    private $variables_list = array();
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
     * Remplace les balises de constante par des constantes.
     * @param $string : la chaîne de caractères dans laquelle il faut remplacer les constantes
     * @return string formaté avec les constantes
     * @throws Exception Undefined constant
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     * @example Si la constante PATH est définie comme "C:/temp" : la valeur de la constante {PATH}/exemple deviendra C:/temp/exemple.
     */
    private function replace_with_constant_value($string)
    {
        $pos1 = strpos($string, '{');
        $pos2 = strpos($string, '}');
        $length = $pos2 - $pos1 + 1; // String position starts at 0

        if ($length > 1) {
            $constant = substr($string, $pos1 + 1, $length - 2);
            if (defined($constant)) {
                $string = substr_replace($string, constant($constant), $pos1, $length);
                $string = $this->replace_with_constant_value($string);
            } else {
                throw new Exception('Constant used before being defined : {$constant}');
            }
        }
        return $string;
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

                if (isset($config->constants)) {
                    $this->constants_list = $this->load_constants($config->constants);
                }

                if (isset($config->variables)) {
                    $this->config = $this->load_variables($config->variables);
                }
            } catch (Exception $e) {
                return false;
            }
        }
    }

    /**
     * Loads the constants.
     * @param $cfg_constants array configuration constants from JSON file
     * @return array with every configuration constants.
     * @throws Exception
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function load_constants($cfg_constants)
    {
        $constants = array();
        foreach ($cfg_constants as $constant_name => $value) {
            if (gettype($value) == "string")
                $value = $this->replace_with_constant_value($value);
            if (!DEFINE($constant_name, $value))
                throw new Exception("The constant could not be defined.");
            $constants[] = $constant_name;
        }
        return $constants;
    }

    /**
     * Loads the variables.
     * @param $cfg_variables array configuration variables from JSON file
     * @return mixed configuration variables array
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    private function load_variables($cfg_variables) {
        $variables = array();
        foreach ($cfg_variables as $variable => $value) {
            $variables[$variable] = $value;
        }
        return $variables;
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
        foreach ($this->constants_list as $constant) {
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
        foreach ($this->constants_list as $constant) {
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
        foreach ($this->config as $variable => $value) {
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