<?php


class MysqlDefinitionToMigration
{
    private $table;
    private $start = '    table';

    private $dict = [
        'varchar' => 'string',
        'int' => 'integer',
        'bigint' => 'bigInteger',
        'enum' => 'enu',
    ];

    public function __construct($definition) {
        $meta = '/CREATE\ TABLE\ `([\w\d_]+)`[\s\S]+?ENGINE=(\w+)(?: (?:DEFAULT CHARSET)=([\w\d]+))?(?:\ COLLATE=([\w\d_]+))?/mu';
        preg_match($meta, $definition, $match);

        $table = [
            'name' => $match[1],
            'engine' => $match[2],
            'default_charset' => isset($match[3]) ? $match[3] : '',
            'collate' => isset($match[4]) ? $match[4] : '',
        ];

        $fieldsPattern = '/\s+`([\w\d_]+)`\ (\w+)(?:\((\d+)\))?(?: (unsigned))?'
            // ignore collate info, match value attribute
            . '.+?(NULL|NOT\ NULL|(?:NOT\ NULL\ )?DEFAULT\ .+?)'
            // match auto_increment or comment
            . '(?:\ (AUTO_INCREMENT)|\ COMMENT \'([\s\S]+?)\')?,/u';

        preg_match_all($fieldsPattern, $definition, $matches);

        for ($i = 0, $j = 0; $i < sizeof($matches[0]); $i++) {
            // process increments fields
            if (!!$matches[6][$i]) {
                $field = $matches[2][$i] == 'bigint' ? 'bigIncrements' : 'increments';
                $table['increments'] = [
                    'increment_type' => $field,
                    'field_name' => $matches[1][$i]
                ];
                continue;
            }

            $_field = [
                'name' => $matches[1][$i],
                'type' => $matches[2][$i],
                'length' => $matches[3][$i],
                'unsigned' => !!$matches[4][$i],
                'comment' => $matches[7][$i]
            ];

            $valAttribute = $matches[5][$i];
            // ['nullable', 'defaultTo'] is optional
            if ($valAttribute == 'DEFAULT NULL') {
                $_field['nullable'] = 1;
            } else if (substr($valAttribute, 0, 8) == 'DEFAULT ') {
                $_field['nullable'] = 0;
                $v = substr($valAttribute, 8);
                if ($v == 'NULL ON UPDATE CURRENT_TIMESTAMP') {
                    $_field['default'] = "knex.raw('NULL ON UPDATE CURRENT_TIMESTAMP')";
                } else if ($v == 'CURRENT_TIMESTAMP') {
                    $_field['default'] = "knex.raw('CURRENT_TIMESTAMP')";
                }
            } else if ($valAttribute == 'NOT NULL') {
                $_field['nullable'] = 0;
            } else if ($valAttribute == 'NOT NULL DEFAULT CURRENT_TIMESTAMP') {
                $_field['nullable'] = 0;
                $_field['defaultTo'] = "knex.raw('CURRENT_TIMESTAMP')";
            }

            $table['fields'][$j] = $_field;
            $j++;
        }

        /**
         *   PRIMARY KEY (`id`),
        UNIQUE KEY `users_username_unique` (`username`),
        UNIQUE KEY `users_email_unique` (`email`)
         */
        $keyPattern = '/(?:(PRIMARY|UNIQUE) )?KEY\ (?:([`\w\d_]+)\ )?\(([`\w\d_,]+)\)/mu';
        preg_match_all($keyPattern, $definition, $matches);

        $keys = [];
        for ($i = 0; $i < sizeof($matches[0]); $i++) {
            if ($matches[1][$i] == 'PRIMARY' && isset($table['increments'])) {
                continue;
            }
            $keys[] = [
                'fields' => str_replace('`', '', $matches[3][$i]),
                'key_type' =>  $matches[1][$i] == '' ? 'index' : strtolower($matches[1][$i]),
                'key_name' => str_replace('`', '', $matches[2][$i]),
            ];
        }

        $table['keys'] = $keys;
        $this->table = $table;
    }

    private function getMigrationFileName() {
        return date('YmdHis') . '_create_' . $this->table['name'] . '_table.js';
    }

    private function getFieldsContent() {
        $fields = [];
        foreach($this->table['fields'] as $_f) {
            $_snippets = [$this->start];
            switch ($_f['type']) {
                case 'tinyint':
                case 'char':
                    $_snippets[] = "specificType('{$_f['name']}', '{$_f['type']}({$_f['length']})')";
                    break;
                default:
                    $type = $this->getType($_f['type']);
                    if ($_f['length'] !== '') {
                        $_snippets[] = "{$type}('{$_f['name']}', {$_f['length']})";
                    } else {
                        $_snippets[] = "{$type}('{$_f['name']}')";
                    }
            }

            if($_f['unsigned']) {
                $_snippets[] = 'unsigned()';
            }

            if(isset($_f['nullable'])) {
                if ($_f['nullable']) {
                    $_snippets[] = 'nullable()';
                } else {
                    $_snippets[] = 'notNullable()';
                }
            }

            if(isset($_f['defaultTo'])) {
                $_snippets[] = 'defaultTo(' . $_f['defaultTo'] . ')';
            }

            if($_f['comment']) {
                $_snippets[] = "comment('{$_f['comment']}')";
            }
            $fields[] = join('.', $_snippets);
        }
        return join("\n", $fields);
    }

    private function getMetaContent() {
        if (isset($this->table['default_charset'], $this->table['collate']) && $this->table['default_charset'] && $this->table['collate']) {
            $charset = $this->table['default_charset'];
            $collate = $this->table['collate'];
        } else {
            $charset = 'utf8mb4';
            $collate = 'utf8mb4_unicode_ci';
        }

        $meta = [];
        $meta[] = "{$this->start}.engine('{$this->table['engine']}')";
        $meta[] = "{$this->start}.charset('{$charset}')";
        $meta[] = "{$this->start}.collate('{$collate}')";

        return join("\n", $meta);
    }

    private function getKeysContent() {
        $keys = [];
        foreach ($this->table['keys'] as $_key) {
            $_snippets = [$this->start];
            $_snippets[] = $_key['key_type'] . '(' . $this->_getFields($_key['fields']) . ($_key['key_name'] ? ", '" . $_key['key_name'] . "'" : '') . ')';
            $keys[] = join('.', $_snippets);
        }

        if (isset($this->table['increments'])) {
            $keys[] = "{$this->start}.{$this->table['increments']['increment_type']}('{$this->table['increments']['field_name']}')";
        }
        return join("\n", $keys);
    }

    private function _getFields($fields) {
        $fieldList = preg_split('/[, ]/', $fields);
        return "'" . join("','", $fieldList) . "'";
    }

    private function getType($type) {
        if(isset($this->dict[$type])) {
            return $this->dict[$type];
        }
        return $type;
    }

    private function getPrefix() {
        return <<<code
//I only want migrations, rollbacks, and seeds to run when the NODE_ENV is specified
//in the knex seed/migrate command. Knex will error out if it is not specified.
if (!process.env.NODE_ENV) {
  throw new Error('NODE_ENV not set')
}

exports.up = function(knex, Promise) {
  return knex.schema.createTable('{$this->table['name']}', function(table) {

code;
    }

    private function getSuffix() {
        return <<<code
  })
}

exports.down = function(knex, Promise) {
  //We never want to drop tables in production
  if (process.env.NODE_ENV !== 'production') { 
    return knex.schema.dropTableIfExists('{$this->table['name']}')
  }
}

code;

    }

    public function write2file() {
        $dir = './migrations';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $filename = $dir . '/' . $this->getMigrationFileName();
        if (is_dir($dir)) {
            file_put_contents(
                $filename,
                $this->getPrefix() .
                $this->getMetaContent() . "\n\n" .
                $this->getFieldsContent() . "\n\n" .
                $this->getKeysContent() . "\n" .
                $this->getSuffix()
            );
        } else {
            echo $dir . ' not exist!';
        }
    }

}