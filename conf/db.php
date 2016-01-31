<?php
return array(
    'guid_db' => 'firegit',
    'guid_table' => 'c_guid',
    'db_pool' => array(
        'master' => array(
            'ip' => '192.168.3.101',
            'port' => 13507,
            'charset' => 'utf8',
            'user' => 'root',
            'pass' => 'HapN',
        ),
        'slave' => array(
            'ip' => '192.168.3.102',
            'port' => 13507,
            'charset' => 'utf8',
            'user' => 'root',
            'pass' => 'HapN',
        ),
    ),
    'dbs' => array(
        'firegit' => array(
            'master' => 'master',
            'slave' => array('slave')
        ),
    )
);