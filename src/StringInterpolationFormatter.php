<?php


namespace ConfigMGR;


trait StringInterpolationFormatter
{
    /**
     * Return the number of markups if the string has markup.
     * @param $string string string
     * @return false|int number of markups
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public static function has_string_interpolation($string)
    {
        return preg_match("/" . ConfigurationManager::$open_string_interpolation . "(.*?)" . ConfigurationManager::$close_markup . "/", $string);
    }

    /**
     * Extract the first markup name of a string.
     * @param $string string string
     * @return string|string[] markup
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public static function extract_name_from_string_interpolation($string)
    {
        if (preg_match("/" . ConfigurationManager::$open_string_interpolation . "(.*?)" . ConfigurationManager::$close_string_interpolation . "/", $string, $matches))
            return str_replace([ConfigurationManager::$open_string_interpolation, ConfigurationManager::$close_string_interpolation], "", $matches[0]);
        return "";
    }

    /**
     * Replace markups in a key by the value of another or a constant.
     * @param $extracted_name string name extracted from markup
     * @param $content string value
     * @param $value
     * @return string|string[] new value
     * @author Nicolas Schwab
     * @email nicolas.schwab@ceff.ch
     */
    public static function replace_markup($extracted_name, $content, $value)
    {
        $to_replace = ConfigurationManager::$open_string_interpolation . $extracted_name . ConfigurationManager::$close_string_interpolation;

        $replaced_string = str_replace($to_replace, $value, $content);

        if (StringInterpolationFormatter::has_string_interpolation($replaced_string)) {
            StringInterpolationFormatter::replace_markup(StringInterpolationFormatter::extract_name_from_string_interpolation($replaced_string), $replaced_string, $value);
        }

        return $replaced_string;
    }
}