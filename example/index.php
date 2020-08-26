<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title>ConfigMGR test</title>
    <?php
    require_once(__DIR__ . "\includes\config.inc.php");
    ?>
</head>
<body>

<pre>
<?php
use ConfigMGR\ConfigurationManager as ConfigurationManager;

$cfgmgr = ConfigurationManager::getInstance();
$cfgmgr->get_constants_table();
$cfgmgr->get_variables_table()
?>
</pre>
</body>