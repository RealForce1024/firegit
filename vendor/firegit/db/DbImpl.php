<?php


/**
 * Db操作公用实现类
 * @file DbImpl.php
 **/

namespace firegit\db;

final class DbImpl
{
    private $dbimpl;

    private $dbname;
    private $foundRows = 0;
    private $table = null;
    private $object = null;
    private $where = array();
    private $between = null;
    private $like = null;
    private $field = null;
    private $order = null;
    private $group = null;
    private $asc = true;
    private $start = -1;
    private $limit = 0;
    private $save = null;
    private $unique_f = null;
    private $uniqId = null;
    private $time = null;
    private $t_split = null;
    private $t_split_method = null;
    private $allIn = null;
    private $followedSql = null;
    private $followedResult = null;
    //是否需要getLastInsertId
    private $autoinc = false;

    // 遇到冲突则报错
    const MODE_DUPLICATE_ERROR = 0;
    // 遇到冲突则更新
    const MODE_DUPLICATE_UPDATE = 1;
    // 遇到冲突则忽略
    const MODE_DUPLICATE_IGNORE = 2;

    // 执行一条单独的记录
    const MODE_RECORD_SINGLE = 1;
    // 执行多条语句
    const MODE_RECORD_BATCH = 2;

    function __construct($dbname, $gdpconf)
    {
        $this->dbname = $dbname;
        $this->dbimpl = new MysqlDb($dbname, $gdpconf);
    }

    private function initDbBase()
    {
        $this->table = null;
        $this->object = null;
        $this->where = array();
        $this->between = null;
        $this->like = null;
        $this->field = null;
        $this->order = null;
        $this->allIn = null;
        $this->asc = true;
        $this->start = -1;
        $this->limit = 0;
        $this->save = null;
        $this->unique_f = null;
        $this->t_split = null;
        $this->t_split_method = null;
        $this->group = null;

        $this->followedSql = null;
    }

    private function checkDbTable()
    {
        if (empty($this->table)) {
            throw new \Exception("db.LibDbTableEmpty");
        }
    }

    /**
     * 设置是否强制查询主库.
     * @param $force boolean
     * @return $this
     */
    public function forceMaster($force = true)
    {
        $this->dbimpl->forceMaster($force);
        return $this;
    }

    /**
     * 根据分表规则获取分表名称
     * @param mixed $value 分表字段值
     * @param array $split_method 分表方式 array(split_mod=>value);
     * @return string
     * @throws \Exception
     */
    public function getSplitTable($value, $split_method)
    {
        if (empty($value)) {
            return $this->table;
        }
        if (empty($split_method) || !is_array($split_method)) {
            throw new \Exception("db.SplitMethodNotSet");
        }
        foreach ($split_method as $split => $split_value) {
            break;
        }
        switch ($split) {
            case DbContext::MOD_SPLIT:
                if ($split_value == 10 || $split_value == 100 || $split_value == 100) {
                    //防止value过大没法除
                    $v = substr('' . $value, -(strlen($split_value) - 1));
                    if ($v === false) {
                        $subfix = intval($value);
                    } else {
                        $subfix = intval($v);
                    }
                } else {
                    $subfix = $value % $split_value;
                }
                break;
            case DbContext::DIV_SPLIT:
                $subfix = round($value / $split_value);
                break;
            case DbContext::YEAR_SPLIT:
                $subfix = date('Y', $value);
                break;
            case DbContext::MONTH_SPLIT:
                $subfix = date('Ym', $value);
                break;
            case DbContext::DAY_SPLIT:
                $subfix = date('Ymd', $value);
                break;
            default:
                throw new \Exception('db.NotSupportTableSplit');
        }
        return $this->table . $subfix;
    }

    /**
     * 根据当前的DB设置获取查询的SQL语句
     * @param boolean $is_calc_found_rows 否则计算所有影响行数。default: false
     * @return string SQL语句
     * @throws \Exception
     */
    public function getPrepare($is_calc_found_rows = false)
    {
        $table = $this->getSplitTable($this->t_split, $this->t_split_method);
        if (empty($this->allIn) &&
            empty($this->where) && empty($this->between) &&
            empty($this->like) && $this->start == -1
        ) {
            //不支持不设置in where between like limit的查询，方式代码的失误导致查询了一个很大的表
            throw new \Exception('db.MysqlGetBothInAndWhereNotExist');
        }
        if (empty($this->field)) {
            $fields = '*';
        } else {
            $fields = implode(',', $this->field);
        }
        if (!$is_calc_found_rows) {
            $sql = 'SELECT ' . $fields . ' FROM ' . $this->normalField($this->dbname) . '.' . $this->normalField($table);
        } else {
            $sql = 'SELECT SQL_CALC_FOUND_ROWS ' . $fields . ' FROM ' . $this->normalField($this->dbname) . '.' . $this->normalField($table);
        }
        return $this->concatSql($sql, $this->buildWhere(true));
    }

    /**
     * 连接sql语句
     * @param $sql
     * @param $where
     * @return array|string
     */
    private function concatSql($sql, $where)
    {
        if (is_array($where)) {
            $sql .= array_shift($where);
            $sql = array($sql, $where);
        } else {
            $sql .= $where;
        }
        return $sql;
    }

    /**
     * 将字段变成以`开头的，防止和关键字冲突
     * @param $field
     * @return string
     */
    private function normalField($field)
    {
        if ($field[0] == '`' || strpos($field, ' ') || strpos($field, '.')) {
            return $field;
        } else {
            return '`' . $field . '`';
        }
    }

    /**
     * 获取更新操作需要的SQL语句
     * @param string $action 更新操作。目前支持：insert/update/delete/insertIgnore/insertOrUpdate
     * @param array $options
     * @return SQL语句
     * @throws \Exception
     */
    public function genUpdateSql($action, $options = array())
    {
        $sql = '';
        $table = $this->getSplitTable($this->t_split, $this->t_split_method);
        switch ($action) {
            case 'insert':
                //insert系列的
                if (empty($this->save)) {
                    //如果都没数据可以插入的，那还能怎样？
                    throw new \Exception('db.EmptySaveBody');
                }

                if ($options['duplicate'] == self::MODE_DUPLICATE_IGNORE) {
                    $sql .= 'INSERT IGNORE INTO ';
                } else {
                    $sql .= 'INSERT INTO ';
                }
                $sql .= $this->normalField($this->dbname) . "." . $this->normalField($table);

                if ($options['record'] == self::MODE_RECORD_BATCH) {
                    $arr_values = array_values($this->save);
                    $arr_fields = array_map(array($this, 'normalField'), array_keys($arr_values[0]));
                    $arr_escape_values = array();
                    foreach ($arr_values as $key => $values) {
                        $arr_escape_values[] = $this->dbimpl->escapeValues($values);
                    }
                } else {
                    $arr_fields = array_map(array($this, 'normalField'), array_keys($this->save));
                    $arr_escape_values = $arr_values = array_values($this->save);
                    foreach ($arr_escape_values as &$value) {
                        if (!$this->rawData($value)) {
                            $value = $this->dbimpl->escapeValue($value);
                        }
                    }
                }
                $sql .= '(' . implode(',', $arr_fields) . ') ';
                if ($options['record'] == self::MODE_RECORD_BATCH) {
                    $sql .= ' VALUES';
                    $valuesArr = array();
                    foreach ($arr_escape_values as $key => $values) {
                        $valuesArr[] = '(' . implode(',', $values) . ')';
                    }
                    $sql .= implode(',', $valuesArr);
                } else {
                    $sql .= ' VALUES(' . implode(',', $arr_escape_values) . ')';
                }

                if ($options['duplicate'] == self::MODE_DUPLICATE_UPDATE) {
                    //拼上update部分
                    $str = '';
                    for ($i = 0; isset($arr_fields[$i]); $i++) {
                        $str .= $arr_fields[$i] . '=VALUES(' . $arr_fields[$i] . '),';
                    }
                    //删除最后一个逗号,
                    $str = substr($str, 0, -1);
                    $sql .= ' ON DUPLICATE KEY UPDATE ' . $str;
                }
                return $sql;
            case 'update':
                if ($options['record'] == self::MODE_RECORD_SINGLE) {
                    if (empty($this->save)) {
                        //没有数据更新，也没法干了
                        throw new \Exception('db.EmptySaveBody');
                    }
                    $sql .= 'UPDATE ' . $this->normalField($this->dbname) . "." . $this->normalField($table) . ' SET ';
                    /* 设置需要更新的字段 */
                    foreach ($this->save as $field => $value) {
                        $field = $this->normalField($field);
                        if (!$this->rawData($value)) {
                            $sql .= $field . '=' . $this->dbimpl->escapeValue($value) . ',';
                        } else {
                            $sql .= $field . '=' . $value . ',';
                        }
                    }
                    if (!empty($this->save)) {
                        $sql = substr($sql, 0, -1);
                    }
                    $where = $this->buildWhere(false);
                    if (!$where) {
                        throw new \Exception('db.UpdateAllTableError');
                    }
                    return $this->concatSql($sql, $where);
                } else {
                    if ($options['record'] == self::MODE_RECORD_BATCH && !empty($options['update_key'])) {
                        $update_key = $options['update_key'];
                        $_update_key = $this->normalField($update_key);

                        if (empty($this->save)) {
                            //没有数据更新，也没法干了
                            throw new \Exception('db.EmptySaveBody');
                        }
                        $sql .= 'UPDATE ' . $this->normalField($this->dbname) . "." . $this->normalField($table) . ' SET ';
                        /* 设置需要更新的字段 */
                        $where_in = $save = array();

                        foreach ($this->save as $k => $v) {
                            if (!is_string($v[$update_key]) && !is_int($v[$update_key])) {
                                throw new \Exception('db.UpdateKeyError');
                            }
                            foreach ($v as $field => $value) {
                                if ($field != $update_key) {
                                    $t = is_string($v[$update_key]) ? $this->dbimpl->escapeValue($v[$update_key]) : $v[$update_key];
                                    $save[$field][$k][$update_key] = $t;
                                    $save[$field][$k]['value'] = $value;
                                    $where_in[] = $t;
                                }
                            }
                        }

                        foreach ($save as $k => $v) {
                            $sql .= $this->normalField($k) . " = CASE";
                            foreach ($v as $key => $val) {
                                if (!$this->rawData($val['value'])) {
                                    $sql .= " WHEN $_update_key = " . $val[$update_key] . " THEN " . $this->dbimpl->escapeValue($val['value']);
                                } else {
                                    $sql .= " WHEN $_update_key = " . $val[$update_key] . " THEN '" . $val['value'] . "'";
                                }
                            }
                            $sql .= ' END,';
                        }

                        if (!empty($save)) {
                            $sql = substr($sql, 0, -1);
                        }
                        $where = $this->buildWhere(false);
                        if (!$where_in) {
                            throw new \Exception('db.UpdateAllTableError');
                        }
                        $args = array();
                        if (is_array($where)) {
                            $args = $where;
                            $where = array_shift($where);
                        }
                        $sql = $sql . (empty($where) ? ' WHERE ' : $where . ' AND ') . $_update_key . " IN (" . implode(',',
                                $where_in) . ")";
                        if (!empty($args)) {
                            return array($sql, $args);
                        }
                        return $sql;
                    }
                }
                break;
            case 'delete':
                $sql .= 'DELETE FROM ' . $this->normalField($this->dbname) . "." . $this->normalField($table);
                $where = $this->buildWhere(false);
                if (!$where) {
                    throw new \Exception('db.DeleteAllTableError');
                }
                return $this->concatSql($sql, $where);
            default:
                throw new \Exception('db.UnknownUpdateMethod');

        }
    }

    private function rawData(&$value)
    {
        if (!is_string($value)) {
            return false;
        }
        $prefix = substr($value, 0, 2);
        $useRaw = false;
        switch ($prefix) {
            case '0b':
                if (preg_match('{^0b[01]+$}', $value)) {
                    $useRaw = true;
                }
                break;
            case '0x':
                if (preg_match('{^0x[0-9a-fA-F]+$}', $value)) {
                    $useRaw = true;
                    $value = strtolower($value);
                }
                break;
        }
        return $useRaw;
    }

    /**
     * 构建查询语句
     * @param bool|是否用来select查询 $forselect
     * @param bool|是否用来计数 $forCount
     * @return string
     * @throws \Exception
     */
    private function buildWhere($forselect = true, $forCount = false)
    {
        $sql = '';
        $args = array();
        if (!empty($this->allIn)) {
            $allIn = array();
            foreach ($this->allIn as $field => $inArr) {
                $in_v = $this->dbimpl->escapeValues($inArr);
                $allIn[] = sprintf('%s IN (%s)', $field, implode(',', $in_v));
            }
            $sql .= '(' . implode(') AND (', $allIn) . ')';
        }
        if (!empty($this->where) && is_array($this->where)) {
            if (!empty($sql)) {
                $sql .= ' AND ';
            }
            foreach ($this->where as $key => $value) {
                if (is_array($value) && ($len = count($value)) == 2 && isset($value['cause']) && isset($value['args'])) {
                    $args = array_merge($args, $value['args']);
                    $value = $value['cause'];
                }

                if (is_array($value)) {
                    /* range > and < */
                    if (count($value) != 2) {
                        //where条件里也支持exp表达式
                        $sql .= " $key=" . $this->dbimpl->escapeValue($value) . ' AND ';
                        continue;
                    }
                    if (is_null($value[0]) && is_null($value[1])) {//如果范围条件全是null
                        throw new \Exception("db.ArgWhereRangeBothNull");
                    }
                    if (isset($value [0])) {
                        $sql .= " $key>" . $this->dbimpl->escapeValue($value [0]);
                    }
                    if (isset($value [1])) {
                        $sql .= isset($value [0]) ? ' AND ' : '';
                        $sql .= " $key<" . $this->dbimpl->escapeValue($value [1]);
                    }
                } else {
                    if (is_null($value)) {
                        $sql .= " $key is NULL";
                    } else {
                        if (is_int($key)) {
                            $sql .= $value;
                        } else {
                            $sql .= " $key=" . $this->dbimpl->escapeValue($value);
                        }
                    }
                }
                $sql .= ' AND ';
            } //end of foreach
            //去掉最后的 AND
            $sql = substr($sql, 0, -4);
        } elseif (!empty($this->where)) {
            if (!empty($sql)) {
                $sql .= ' AND ';
            }
            $sql .= $this->where;
        }
        if (!empty($this->between)) {
            if (!empty($sql)) {
                $sql .= ' AND ';
            }
            $sql .= ' ' . $this->between[0] . ' between ';
            $sql .= $this->dbimpl->escapeValue($this->between[1]) . ' and ';
            $sql .= $this->dbimpl->escapeValue($this->between[2]);
        }
        if (!empty($this->like)) {
            if (!empty($sql)) {
                $sql .= ' AND ';
            }
            $sql .= ' ' . $this->like[0] . ' like ' . $this->dbimpl->escapeValue($this->like[1]);
        }
        if ($sql) {
            $sql = ' WHERE ' . $sql;
        }
        if (!$forCount) {
            if (!empty($this->group)) {
                $sql .= ' GROUP BY ' . $this->group;
            }
            if (!empty($this->order) && ($forselect || $this->limit > 0)) {
                $sql .= " ORDER BY {$this->order}";
                //如果指定asc为null，则可以自己指定排序
                $sql = $sql . (($this->asc === null) ? '' : ($this->asc ? ' ASC' : ' DESC'));
            }
            if ($forselect) {
                if ($this->start >= 0) {
                    $sql .= ' LIMIT ' . $this->start . ', ' . $this->limit;
                }
            } else {
                if ($this->limit > 0) {
                    $sql .= ' LIMIT ' . $this->limit;
                }
            }
        }
        if (!empty($args)) {
            $ret = array($sql);
            $ret = array_merge($ret, $args);
            return $ret;
        }
        return $sql;
    }

    private function prepareText(&$arr_body)
    {
        if (!$arr_body) {
            return;
        }
        $arr_text = array();
        foreach ($arr_body as $key => $value) {
            if ('r' == $key [0] && '_' == $key [2]) {
                if (is_int($value)) {
                    continue;
                }
                /* v字段的处理 只提交非数值型的请求 */
                /* 判断是否为空,需注意'0'的情况 */
                if ((empty($value) && '0' !== $value) || "\0" === $value) {
                    /* 如果为空串,msg_id置为0 */
                    $arr_body[$key] = 0;
                } else {
                    if ('a' == $key [1]) {
                        /* ra_ means store array,indeed a pack will be stored */
                        if (!is_array($value)) {
                            throw new \Exception("db.SystaVaField: ra_ field NotArray ");
                        }
                        $pack = json_encode($value, JSON_UNESCAPED_UNICODE);
                        $arr_text [$key] = $pack;
                    } else {
                        $arr_text [$key] = $value;
                    } // if ra
                } //if is_string
            }
        } /* foreach */
        foreach ($arr_text as $key => $value) {
            $ret_id = Db::commitText($value);
            $arr_body [$key] = $ret_id;
        }
    }

    private function prepareGuid(&$arr_body, $mode = self::MODE_RECORD_SINGLE)
    {
        if (empty($this->unique_f) || empty($arr_body)) {
            return;
        }
        if ($mode == self::MODE_RECORD_SINGLE) {
            foreach ($this->unique_f as $uniq) {
                if (!empty($arr_body[$uniq])) {
                    //有id就不生成了
                    continue;
                }
                $guid = Db::newGUID($uniq);
                $arr_body[$uniq] = $guid;
                //保留最后那个
                $this->uniqId = $guid;
            }
        } else {
            if ($mode == self::MODE_RECORD_BATCH) {
                $count = count($arr_body);
                $first = $arr_body[0];
                foreach ($this->unique_f as $uniq) {
                    if (!empty($first[$uniq])) {
                        continue;
                    }
                    $guid = Db::newGUID($uniq, $count);
                    foreach ($arr_body as $key => $body) {
                        $arr_body[$key][$uniq] = $guid;
                        $guid++;
                    }
                    $this->uniqId = $guid;
                }

            }
        }
    }

    /**
     * 检查是否是只读模式
     */
    public function checkReadOnly()
    {
        if (DbContext::$readOnly) {
            throw new \Exception("db.DbAllowReadonly");
        }
    }

    /**
     * 提交文本。详情参考: Db::commitText($texts);
     */
    public function commitText($arr_text)
    {
        return Db::commitText($arr_text);
    }

    /**
     * 查询文本 Db::queryText($textid);
     */
    public function queryText($arr_signid)
    {
        return Db::queryText($arr_signid);
    }

    /**
     * 在执行各种DB操作(insert/update/delete等)后，需要尾随执行的一个SQL语句
     * 比如执行select found_rows(); select last_insert_id()等等。
     * 通常这个功能用于：执行一次数据操作，然后需要调用一个查询马上获取这个操作的结果。并且这两次查询需要在同一连接中
     * @param string $sql sql语句
     * @return $this
     */
    public function follow($sql)
    {
        $this->followedSql = $sql;
        return $this;
    }

    /**
     * 设置auto increament字段。
     * 设置此字段后，在insert之后可以通过调用getLastInsertId接口获取字段值
     * 此接口不同于unique接口功能，unique是通过guid分配一个全局id，而此接口是使用数据库自增列.
     * @param string $field 字段名称
     * @return $this
     */
    public function autoInc($field)
    {
        $this->follow('SELECT LAST_INSERT_ID() as LID');
        $this->autoinc = true;
        return $this;
    }

    /**
     * 获取最后生成的guid值。
     * 这个值为提交数据前为unique字段分配的值，跟数据库中的auto_increament是不一样的含义.
     * 获取一次后值会被清空，不能多次获取.
     * 如果没有设置unique而使用了autoInc，则会返回autoInc值
     */
    public function getLastInsertId()
    {
        if ($this->uniqId) {
            $unique = $this->uniqId;
            $this->uniqId = null;
            return $unique;
        } elseif ($this->autoinc) {
            $ret = $this->followedResult;
            $this->followedResult = null;
            $this->autoinc = false;
            $vs = array_values($ret['fields'][0]);
            return intval($vs[0]);
        }
    }

    /**
     * 获取查询语句受影响的函数。
     * 这个函数结合is_calc_found_rows模式来使用，只用当使用了这种模式来查询
     * 才能通过此函数获取到相应的值
     */
    public function getFoundRows()
    {
        $rows = $this->foundRows;
        $this->foundRows = 0;
        return $rows;
    }

    function beginTx()
    {
        $this->dbimpl->beginTx();
    }

    function commit()
    {
        $this->dbimpl->commit();
    }

    function rollback()
    {
        $this->dbimpl->rollback();
    }

    /*
     * 设置表名
     */
    public function table($table)
    {
        $this->initDbBase();
        $this->table = $table;
        return $this;
    }

    /*
     * 设置对象名
     */
    public function object($class)
    {
        $this->initDbBase();
        $this->object = new $class();
        $this->table = $this->object->table;
        return $this;
    }

    /**
     * 设置where条件
     * @param $arr_where array(key=>value)或者"key=value"
     *     如果$arr_where是字符串，允许使用?来做变量替换，在后面传入和?个数一样的参数即可
     *     例如：->where('a=? and b=?', 3, 'admin')
     *        ：->where('a=? and b=?', array(3, 'admin'))
     * @return $this
     */
    public function where($arr_where)
    {
        $this->checkDbTable();
        if (is_string($this->where)) {
            $this->where = array($this->where);
        }
        if (is_array($arr_where)) {
            foreach ($arr_where as $key => $value) {
                if (is_int($key)) {
                    $this->where[] = $value;
                } else {
                    $this->where[$key] = $value;
                }
            }
        } elseif (is_string($arr_where)) {
            if (($num = func_num_args()) > 1) {
                // 参数必须以数组的形式传入
                if (!is_array(func_get_arg(1))) {
                    throw new \Exception('db.argsMustBeArray');
                }
                $args = func_get_arg(1);
                $this->where[] = array('cause' => $arr_where, 'args' => $args);
            } else {
                $this->where[] = $arr_where;
            }
        }
        return $this;
    }

    /**
     * 设置sql的between子句
     * @param string $field
     * @param int $min
     * @param int $max
     * @return $this
     */
    public function between($field, $min, $max)
    {
        $this->checkDbTable();
        $this->between = array($field, $min, $max);
        return $this;
    }

    /**
     * 设置sql的like子句, like '%value%'
     */
    public function like($field, $value)
    {
        $this->like = array($field, $value);
        return $this;
    }

    /**
     * 设置in条件，前一个参数为in字段名，仅支持一个字符串；后一个字段为取值数组
     * @param $in 字段名
     * @param $arr_in_value 字段的数组
     * @return $this
     * @throws \Exception
     */
    public function in($in, $arr_in_value)
    {
        $this->checkDbTable();
        if (empty($in) || empty($arr_in_value) || !is_array($arr_in_value)) {
            throw new \Exception("db.InParam:$in " . print_r($arr_in_value, true) . "Error");
        }
        $this->allIn[$in] = $arr_in_value;
        return $this;
    }

    /**
     * 设置查询order条件。
     * @param $order 排序设置，可以是字段名，可以可以是整个order子句
     *   例如: "id asc,time desc"
     * @param bool $asc 升序还是降序 true/false，如果设置为null,是认为$order参数为排序子句
     * @return $this
     * @throws \Exception
     */
    public function order($order, $asc = true)
    {
        $this->checkDbTable();
        $this->order = $order;
        $this->asc = $asc;
        return $this;
    }

    /**
     * 设置查询的group by条件
     * @param  string $fields 字段名，可以是多个
     * @return $this
     */
    public function group($fields)
    {
        $this->checkDbTable();
        $this->group = $fields;
        return $this;
    }

    /**
     * 设置查询field，支持数组或字符串
     * @param string|array $arr_field 如果第一个参数为字符串，则会通过func_get_args获取所有参数
     * @return $this
     */
    public function field($arr_field)
    {
        $this->checkDbTable();
        if (!is_array($arr_field)) {
            $arr_field = func_get_args();
        }
        $this->field = $arr_field;
        return $this;
    }

    /**
     * 设置查询limit,必须整数
     * @param $start
     * @param $limit
     * @return $this
     * @throws \Exception db.LimitParam $start<=-1且$limit小于0
     */
    public function limit($start, $limit)
    {
        $this->checkDbTable();
        $start = intval($start);
        $limit = intval($limit);
        if ($start <= -1 && $limit <= 0) {
            throw new \Exception("db.LimitParam:$start Error");
        }
        $this->start = $start;
        $this->limit = $limit;
        return $this;
    }

    /**
     * @deprecated This function is deprecated, pls use saveBody instead.
     */
    public function save($arr_save)
    {
        return $this->saveBody($arr_save);
    }

    /**
     * 设置更新字段数组
     * @param $arr_save array  key=>value格式
     * @return $this
     * @throws \Exception db.SaveBodyParam 参数不是数组
     */
    public function saveBody($arr_save)
    {
        $this->checkDbTable();
        if (!is_array($arr_save)) {
            throw new \Exception("db.SaveBodyParam:$arr_save NotArray");
        }
        $this->save = $arr_save;
        return $this;
    }

    private function parseField($fields)
    {
        if (is_string($fields)) {
            return array($fields);
        }

        if (is_array($fields)) {
            //有点坑爹，外面可能传field=>0的方式，也可能传array(field1,field2)的方式
            //都是为了兼容性啊
            if (isset($fields[0])) {
                return $fields;
            } else {
                return array_keys($fields);
            }
        }
        throw new \Exception('db.FieldNotSupport');
    }

    /**
     * 设置需要分配guid的字段
     * @param string $field
     * @return $this
     */
    public function unique($field)
    {
        $this->checkDbTable();
        $this->unique_f = $this->parseField($field);
        return $this;
    }

    /**
     * 设置分表属性和分库方法
     * @param mixed $split_value 分表字段值
     * @param $split 分表设置
     * @return $this
     * @throws \Exception
     */
    public function tsplit($split_value, $split)
    {
        $this->checkDbTable();
        if (!empty($split_value)) {
            if (is_array($split_value)) {
                throw new \Exception("db.MysqlTableSplitValueMustNotBeArray");
            }
            if (is_null($split) || !is_array($split) || 0 == count($split)) {
                throw new \Exception("db.MysqlTableSplitMethodNotSet");
            }
            $this->t_split = $split_value;
            $this->t_split_method = $split;
        }
        return $this;
    }


    private function executeUpdate($action, $options = array())
    {
        $this->checkReadOnly();
        //主要处理文本字段，全局id和时间字段
        $this->prepareGuid($this->save, $options['record']);
        $this->prepareText($this->save);
        $sql = $this->genUpdateSql($action, $options);
        if ($this->followedSql) {
            $sql = array(
                array(
                    array($sql),
                    array($this->followedSql)
                )
            );
        }
        $res = $this->_query($sql);
        if ($this->followedSql) {
            //保存第二条SQL的结果
            $this->followedResult = $res[1];
        }
        //这个格式的返回值主要为了兼容以前systa的返回值
        $ret = array();
        if ($this->time) {
            foreach ($this->time as $key) {
                $ret[$key] = $this->save[$key];
            }
        }
        if ($this->unique_f) {
            if ($options['record'] == self::MODE_RECORD_SINGLE) {
                $arr = $this->save;
            } else {
                $arr = $this->save[0];
            }
            foreach ($this->unique_f as $key) {
                $ret[$key] = $arr[$key];
            }
        }
        if (isset($res[0]['affected_rows'])) {
            $ret['affected_rows'] = $res[0]['affected_rows'];
        }

        $this->initDbBase();
        return $ret;
    }

    /**
     * 执行插入
     */
    public function insert()
    {
        return $this->executeUpdate('insert', array(
            'record' => self::MODE_RECORD_SINGLE,
            'duplicate' => self::MODE_DUPLICATE_ERROR,
        ));
    }

    /**
     * 执行更新
     */
    public function update()
    {
        return $this->executeUpdate('update', array(
            'record' => self::MODE_RECORD_SINGLE,
            'duplicate' => self::MODE_DUPLICATE_ERROR,
        ));
    }

    /**
     * 执行插入。INSERT IGNORE INTO模式
     */
    public function insertIgnore()
    {
        return $this->executeUpdate('insert', array(
            'record' => self::MODE_RECORD_SINGLE,
            'duplicate' => self::MODE_DUPLICATE_IGNORE,
        ));
    }

    /**
     * 执行插入。INSERT ON DUPLICATE KEY UPDATE 模式
     */
    public function insertOrUpdate()
    {
        return $this->executeUpdate('insert', array(
            'record' => self::MODE_RECORD_SINGLE,
            'duplicate' => self::MODE_DUPLICATE_UPDATE,
        ));
    }

    /**
     * 执行批量插入。
     * 要求saveBody中的数据多一个维度
     * @param int $mode 0 普通模式 1 如果有冲突则更新 2 如果有冲突则忽略
     * @return array
     */
    public function insertBatch($mode = self::MODE_DUPLICATE_ERROR)
    {
        return $this->executeUpdate('insert', array(
            'record' => self::MODE_RECORD_BATCH,
            'duplicate' => $mode,
        ));
    }

    /**
     * 执行批量修改
     * 要求saveBody中的数据多一个维度
     * @param string $updateKey 指定saveBody中的主键，此主键将作为主要判断条件。如果不传递此参数，则和普通update效果一致
     * @example saveBody中的数据格式：
     * array(
     *     array(
     *         'field1' => 1,
     *         'field2' => 2,
     *     ),
     *     array(
     *         'field1' => 2,
     *         'field2' => 4,
     *     ),
     * )
     * $update_key的值是'field1', 那么将执行以下SQL语句:
     * UPDATE `tablename` SET `field2` = CASE
     *     WHEN `field1` = 1 THEN 2
     *     WHEN `field1` = 2 THEN 4
     * END
     * WHERE `field1` IN (1,2)
     *
     * @return bool
     */
    public function updateBatch($updateKey = null)
    {
        if (!is_string($updateKey)) {
            return $this->executeUpdate('update', array(
                'record' => self::MODE_RECORD_SINGLE,
            ));
        }

        return $this->executeUpdate('update', array(
            'record' => self::MODE_RECORD_BATCH,
            'update_key' => $updateKey
        ));
    }

    /**
     * 执行删除功能
     */
    public function delete()
    {
        return $this->executeUpdate('delete', array('record' => self::MODE_RECORD_SINGLE));
    }

    /**
     * 执行sql查询，支持批量的sql
     * @param $sql string|array, 查询信息，支持如下几种格式的查询:
     *  queryBySql('select...');
     *  queryBySql('select...? ?',$arg1,$arg2);
     *  queryBySql(array(
     *      array('select...? ?',$arg1,$arg2),
     *      array('select ..?',$arg1)
     *  ));
     * @return 如果执行的是批量查询 则返回多个结果记录，否则返回单个结果集.
     */
    public function queryBySql($sql)
    {
        $results = $this->_query(func_get_args());
        $this->formatData($results);
        if (is_array($sql)) {
            //返回一个结果集列表
            return $results;
        } elseif (empty($results[0])) {
            //没有查询到结果
            return array();
        }
        $result = $results[0];
        if (isset($result['found_rows'])) {
            $this->foundRows = intval($result['found_rows']);
        } elseif (isset($result['affected_rows'])) {
            //更新操作返回影响行数就可以了
            return intval($result['affected_rows']);
        }
        return $result['fields'];
    }

    /**
     * select for update功能
     * @param bool $calc_found_rows
     * @return array
     */
    public function getForUpdate($calc_found_rows = false)
    {
        return $this->get($calc_found_rows, true);
    }

    /**
     * 获取一个单一的对象
     * @return array|boolean
     */
    public function getOne()
    {
        $rows = $this->get();
        if ($rows) {
            return array_shift($rows);
        }
        return false;
    }

    /**
     * 执行一个常规的查询操作.查询参数就是通过where in limit order等接口所设置的
     * @param boolean|int $calc_found_rows 是否统计受影响行数，默认通过另外查询一次计算得出，要使用SQL_CALC_FOUND_ROWS得明确使用2
     * @param boolean $forupdate 是否使用“select for update”功能
     * @return array
     */
    public function get($calc_found_rows = false, $forupdate = false)
    {
        $this->foundRows = 0;
        $use_sql_calc = $calc_found_rows === 2;
        $sql = $this->getPrepare($use_sql_calc);
        if ($forupdate) {
            $sql .= ' FOR UPDATE';
        }
        $results = $this->_query($sql);
        $this->formatData($results);

        if ($use_sql_calc) {
            $this->foundRows = $results[0]['found_rows'];
        } else {
            if ($calc_found_rows) {
                // 有结果或者不是从第一个记录查起，才需要获取数目
                if (!empty($results) || $this->limit > 0) {
                    $this->foundRows = $this->getCount();
                }
            }
        }
        $result = $results[0]['fields'];
        if (empty($this->object)) {
            $this->initDbBase();
            return $result;
        } else {
            $arr_object = array();
            foreach ($result as $arr_res_item) {
                $object_res = clone $this->object;
                $object_res->arrValue = $arr_res_item;
                $object_res->__hit__ = true;
                $arr_object [] = $object_res;
            }
            $this->initDbBase();
            return $arr_object;
        }
    }

    private function formatData(&$ret)
    {
        $arrId = array();
        foreach ($ret as &$result) {
            foreach ($result['fields'] as &$row) {
                $hasv = false;
                foreach ($row as $key => $tid) {
                    if (isset($key[2]) && 'r' == $key[0] && '_' == $key[2]) {
                        if ($tid) {
                            if (is_numeric($tid)) {
                                //如果id>0需要去查询内容
                                $arrId[] = $tid;
                            }
                        } else {
                            $row[$key] = '';
                            $row['_' . $key] = 0;
                        }
                        $hasv = true;
                    }
                }
                //记录里面没有v字段，只需要扫描一行记录就知道是否包含v字段，如果第一行没有其他行肯定没有了
                if (!$hasv) {
                    break;
                }
            }
        }
        if (!$arrId) {
            return;
        }
        $arrText = Db::queryText($arrId);
        foreach ($ret as &$result) {
            foreach ($result['fields'] as &$row) {
                $hasv = false;
                foreach ($row as $key => $tid) {
                    if (isset($key[2]) && 'r' == $key[0] && '_' == $key[2]) {
                        if (is_numeric($tid) && $tid) {
                            //r字段
                            if (isset($arrText[$tid])) {
                                $tmpv = $arrText[$tid];
                                if ('a' == $key[1]) {
                                    //ra_字段
                                    $row[$key] = json_decode($tmpv, true);
                                } else {
                                    $row[$key] = $tmpv;
                                }
                            } else {
                                //存在文本id但是没有查询到内容，可能把文本数据丢了
                                $row[$key] = '';
                                //打一个日志
                                if (DbContext::$logFunc) {
                                    call_user_func(DbContext::$logFunc, 'db.MissTextData text_id=' . $tid);
                                }
                            }
                            //把id保存下来
                            $row['_' . $key] = $tid;
                        } //if ($tid)
                        $hasv = true;
                    }
                } //foreach($row as $key=>$tid)

                //记录里面没有v字段
                if (!$hasv) {
                    break;
                }
            }//foreach($result['fields'] as &$row)
        } //foreach($ret as &$result)

    }

    /**
     * 查询出结果总数。
     * @return int
     * @throws \Exception
     */
    public function getCount()
    {
        $table = $this->getSplitTable($this->t_split, $this->t_split_method);
        $sql = 'SELECT COUNT(1) AS CNT FROM ' . $this->dbname . '.' . $table;
        //为了别拼limit语句了
        $this->start = -1;
        $sql = $this->concatSql($sql, $this->buildWhere(true, true));
        $ret = $this->_query($sql);
        $this->initDbBase();
        return intval($ret[0]['fields'][0]['CNT']);
    }

    /**
     * 检查是否存在
     * @return bool
     * @throws \Exception
     */
    public function getExist()
    {
        $table = $this->getSplitTable($this->t_split, $this->t_split_method);
        $sql = 'SELECT 1 FROM ' . $this->dbname . '.' . $table;
        $this->start = 0;
        $this->limit = 1;
        $sql = $this->concatSql($sql, $this->buildWhere(true, true));
        $ret = $this->_query($sql);
        $this->initDbBase();
        return !empty($ret[0]['fields'][0]);
    }

    /**
     * 执行查询
     * @param string $sql
     */
    private function _query($sql)
    {
        if (DbContext::$longQueryTime == 0) {
            return $this->dbimpl->query($sql);
        }
        $start = microtime(true);
        $ret = $this->dbimpl->query($sql);
        $offset = (microtime(true) - $start) * 1000;
        if ($offset > DbContext::$longQueryTime && DbContext::$logFunc) {
            call_user_func(DbContext::$logFunc,
                'db.longquery cost:' . sprintf('%.3f', $offset) . 'ms sql:' . (!is_string($sql) ? var_export($sql,
                    true) : $sql));
        }

        return $ret;
    }
}