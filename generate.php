<?php
require_once './MysqlDefinitionToMigration.php';

for ($i = 1; $i < sizeof($_SERVER['argv']); $i++) {
    $sqlFile = $_SERVER['argv'][$i];
    if (file_exists($sqlFile)) {
        $pattern = '/CREATE TABLE [\s\S]+?;/mu';
        preg_match_all($pattern, file_get_contents($sqlFile), $matches);
        foreach ($matches[0] as $match) {
            $m = new MysqlDefinitionToMigration($match);
            $m->write2file();
        }
    } else {
        echo $sqlFile . " is not exist.";
    }
}
