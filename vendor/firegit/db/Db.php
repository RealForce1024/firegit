<?php

/**
 * Db操作类
 * @copyright
 */

namespace firegit\db;


final class DbContext
{
    static $db_pool;
    static $dbconf;
    //分表设置，配置格式为
    // array(table=>array(field,array(method=>arg)));
    //
    static $splits;
    static $guidDB;
    static $guidTable;
    static $readOnly = false;
    static $logFunc;
    static $testMode = false;
    static $defaultDB;
    // 长查询时间
    static $longQueryTime = 0;
    //分库或分表方法
    //ID取模
    const MOD_SPLIT = 1;
    //ID区间分表
    const DIV_SPLIT = 2;
    //按月分表
    const MONTH_SPLIT = 3;
    //按年分表
    const YEAR_SPLIT = 4;
    //按填分表
    const DAY_SPLIT = 5;
}


final class Db
{
    //在这里定义就是为了兼容
    //分库或分表方法
    //ID取模
    const MOD_SPLIT = 1;
    //ID区间分表
    const DIV_SPLIT = 2;
    //按月分表
    const MONTH_SPLIT = 3;
    //按年分表
    const YEAR_SPLIT = 4;
    //按填分表
    const DAY_SPLIT = 5;

    static $dbs = array();
    static $mongo_dbs = array();
    static $txDbs = array();

    /**
     * 设置整个DB库的只读模式。
     * @param $readonly boolean 如果设置为true，则只能查询，不能提交
     */
    static function setReadOnly($readonly = false)
    {
        DbContext::$readOnly = $readonly;
    }

    /**
     * 配置初始化函数
     * @param $conf array 配置项如下：
     *    guid_db 可选
     *    guid_table 可选
     *    splits 可选
     *    log_func 可选
     *    test_mode 可选
     *    db_pool=>array(
     *    'db11'=>array(
     *        'ip'=>'ip',
     *        'port'=>3306,
     *        'user'=>'user',
     *     'pass'=>'pass',
     *        'charset'=>'charset'
     *     ),
     *    'db2'=>xxx
     *    ....
     * ),
     * 'dbs'=>array(
     *    'dbname'=>'db1',
     *  'dbname'=>array('master'=>'db1','slave'=>array('db2','db3'))
     * )
     * @throws \Exception
     */
    static function init($conf)
    {
        //check db conf format
        foreach ($conf['dbs'] as $db => $dbconf) {
            if (is_string($dbconf)) {
                if (!isset($conf['db_pool'][$dbconf])) {
                    throw new \Exception('db.ConfError ' . $dbconf . ' no such pool in db_pool');
                }
            } else {
                if (!isset($dbconf['master']) || !isset($dbconf['slave'])) {
                    throw new \Exception('db.ConfError missing master|slave conf ' . $db);
                }
                $master = $dbconf['master'];
                $slaves = $dbconf['slave'];
                if (!isset($conf['db_pool'][$master])) {
                    throw new \Exception('db.ConfError ' . $master . ' no such pool in db_pool');
                }
                foreach ($slaves as $slave) {
                    if (!isset($conf['db_pool'][$slave])) {
                        throw new \Exception('db.ConfError ' . $slave . ' no such pool in db_pool');
                    }
                }
            }
        }


        DbContext::$db_pool = $conf['db_pool'];
        DBContext::$dbconf = $conf['dbs'];

        DbContext::$guidDB = empty($conf['guid_db']) ? null : $conf['guid_db'];
        DbContext::$guidTable = empty($conf['guid_table']) ? null : $conf['guid_table'];

        DbContext::$defaultDB = empty($conf['default_db']) ? null : $conf['default_db'];

        //转换成小写的，免得因为大小写问题比较不成功
        DbContext::$splits = empty($conf['splits']) ? array() : $conf['splits'];

        DbContext::$logFunc = empty($conf['log_func']) ? null : $conf['log_func'];
        DbContext::$testMode = !empty($conf['test_mode']);

        DbContext::$longQueryTime = empty($conf['long_query_time']) ? 0 : $conf['long_query_time'];
    }

    /**
     * 获取一个DB实例对象,目前实现为DbImpl
     * 没法实现复用db，外面get的使用自己节约点用
     * 别任意浪费
     * @param $db_name string
     * @return DbImpl
     * @throws \Exception
     */
    static function get($db_name = null)
    {
        if (empty($db_name)) {
            $db_name = DbContext::$defaultDB;
        }
        $db_name = strtolower($db_name);
        if (!empty(TxScope::$txDbs[$db_name]) &&
            !empty(self::$txDbs[$db_name])
        ) {
            //如果对db_name启用了事务，则复用db对象
            return self::$txDbs[$db_name];
        }
        if (!isset(DBContext::$dbconf[$db_name])) {
            throw new \Exception('db.ConfError no db conf ' . $db_name);
        }
        $conf = array();
        if (is_string(DBContext::$dbconf[$db_name])) {
            //db_name只配了一个地址
            $poolname = DBContext::$dbconf[$db_name];
            //从db_pool里把ip/port/user/pass这些取出来
            $conf['master'] = DBContext::$db_pool[$poolname];
        } else {
            //db_name配置了主从结构
            $poolconf = DBContext::$dbconf[$db_name];
            $mastername = $poolconf['master'];
            $conf['master'] = DBContext::$db_pool[$mastername];
            foreach ($poolconf['slave'] as $slave) {
                //从db_pool里把ip/port/user/pass这些取出来
                $conf['slave'][] = DBContext::$db_pool[$slave];
            }
        }
        $db = new DbImpl($db_name, $conf);
        self::$dbs[$db_name][] = $db;

        if (!empty(TxScope::$txDbs[$db_name])) {
            //启用db_name事务的话就保留一下连接
            self::$txDbs[$db_name] = $db;
        }

        return $db;
    }

    static function close()
    {
        foreach (self::$txDbs as $dbname => $db) {
            $db->rollback();
        }
        self::$txDbs = array();
        foreach (self::$dbs as $dbname => $arrdb) {
            foreach ($arrdb as $db) {
                $db->rollback();
            }
        }
        self::$dbs = array();
    }

    /**
     * 新分配一个全局id，返回分配到的id
     * @param string $name 全局id名称，这个名称必须在全局数据库中的表种已经创建好
     * @param int $count 分配id的个数。
     * @return int 无论$count多大，总会返回比当前id大1的id
     * @throws \Exception
     */
    static function newGUID($name, $count = 1)
    {
        if (empty(DbContext::$guidDB)) {
            throw new \Exception('db.GUIDError not support');
        }
        //guid分配和db无关，这里借用一下textdb
        $db = self::get(DbContext::$guidDB);
        $count = intval($count);
        if ($count < 1) {
            throw new \Exception("db.guid error count");
        }
        if (DbContext::$logFunc) {
            $log = '[GUID][NAME:' . $name . '][COUNT:' . $count . ']';
            call_user_func(DbContext::$logFunc, $log);
        }
        if (DbContext::$testMode) {
            return 1;
        }
        $db->forceMaster(true);
        $changeRows = $db->queryBySql('UPDATE ' . DbContext::$guidDB . '.' . DbContext::$guidTable . ' set guid_value = LAST_INSERT_ID(guid_value+?) where guid_name = ?',
            $count, $name);
        if (!$changeRows) {
            throw new \Exception('db.newGuid error guid_name:' . $name);
        }
        $res = $db->queryBySql('SELECT LAST_INSERT_ID() as ID');
        $lastId = intval($res[0]['ID']);
        return $lastId - $count + 1;
    }

    /**
     * 将一段文本转化为64位整数
     * @param string $s
     * @return int
     */
    static function create_sign64($s)
    {
        $hash = md5($s, true);
        $high = substr($hash, 0, 8);
        $low = substr($hash, 8, 8);
        $sign = $high ^ $low;
        $sign1 = hexdec(bin2hex(substr($sign, 0, 4)));
        $sign2 = hexdec(bin2hex(substr($sign, 4, 4)));
        return ($sign1 << 32) | $sign2;
    }
}