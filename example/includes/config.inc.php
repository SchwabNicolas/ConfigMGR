<?php

$path = __DIR__ . "\..\config.json";
$configuration_manager = ConfigurationManager::getInstance();
$configuration_manager->set_path($path);

$configuration_manager->load_config();

$constants = $configuration_manager->get_constants();

?>