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




//$meta = '/CREATE\ TABLE\ `([\w\d_]+)`[\s\S]+?ENGINE=(\w+)/mu';
//preg_match($meta, $a, $match);
//
//$table = [
//    'name' => $match[1],
//    'engine' => $match[2],
//];
//
////$fields = '/\s+`([\w\d_]+)`\ (\w+\(\d+\)|\w+)(?: (unsigned))?.+?(NULL|NOT NULL|(?:NOT NULL )?DEFAULT\ .+?)(?:(?: ?(AUTO_INCREMENT)?)|(?: COMMENT \'([\s\S]+?)\')),/u';
////$fields = '/\s+`([\w\d_]+)`\ (\w+)(?:\((\d+)\))?(?: (unsigned))?.+?(NULL|NOT NULL|(?:NOT NULL )?DEFAULT\ .+?)(?:(?: ?(AUTO_INCREMENT)?)|(?: COMMENT \'([\s\S]+?)\')),/u';
//$fields = '/\s+`([\w\d_]+)`\ (\w+)(?:\((\d+)\))?(?: (unsigned))?'
//    // ignore collate info, match value attribute
//    . '.+?(NULL|NOT\ NULL|(?:NOT\ NULL\ )?DEFAULT\ .+?)'
//    // match auto_increment or comment
//    . '(?:\ (AUTO_INCREMENT)|\ COMMENT \'([\s\S]+?)\')?,/u';
//preg_match_all($fields, $a, $matches);
//
//print_r($matches);exit;
//$result = [];
//for ($i = 0; $i < sizeof($matches[0]); $i++) {
//    $result[$i] = [
//        'field' => $matches[1][$i],
//        'type' => $matches[2][$i],
//        'unsigned' => !!$matches[3][$i],
//        'auto_increment' => !!$matches[5][$i],
//        'comment' => $matches[6][$i]
//    ];
//    $null = $matches[4][$i];
//    if ($null == 'DEFAULT NULL') {
//        $result[$i]['nullable'] = 1;
//    } else if (substr($null, 0, 8) == 'DEFAULT ') {
//        $result[$i]['nullable'] = 0;
//        $v = substr($null, 8);
//        if ($v == 'NULL ON UPDATE CURRENT_TIMESTAMP') {
//            $result[$i]['default'] = "knex.raw('NULL ON UPDATE CURRENT_TIMESTAMP')";
//        } else if ($v == 'CURRENT_TIMESTAMP') {
//            $result[$i]['default'] = "knex.raw('CURRENT_TIMESTAMP')";
//        }
//    } else if ($null == 'NOT NULL') {
//        $result[$i]['nullable'] = 0;
//    } else if ($null == 'NOT NULL DEFAULT CURRENT_TIMESTAMP') {
//        $result[$i]['nullable'] = 0;
//        $result[$i]['default'] = "knex.raw('CURRENT_TIMESTAMP')";
//    }
//}
//
//
//
//}
//
//$table['fields'] = $result;
//
//print_r($table);
//
//
