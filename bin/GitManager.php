<?php
/**
 * 此脚本用来实现将权限提升到root，跨帐号执行某些git操作
 */
require_once dirname(__DIR__) . '/vendor/firegit/autoload.php';

file_put_contents(LOG_ROOT.'command', var_export($argv, true));
exec('whoami', $outputs);
$user = $outputs[0];
if ($user != 'root') {
    $args = $argv;
    array_shift($args);
    $cmd = __DIR__ . "/chother php " . __FILE__ . ' ' . implode(' ', $args);
    exit(system($cmd));
}
function showHelp()
{
    echo <<<HELP
php GitManager.php
    --group     git的分组名
    --name      git的名称
    --action    git的操作，包括
        * init      初始化一个新的git项目
        * newBranch 新建一个分支，
            args参数
            * orig 原始分支
            * dest 新分支名称
    -d          传入的参数，格式为 -d a=b -d c=d
    -h          显示本帮助文档

HELP;
}

$opts = getopt('hd:', array(
    'group:',
    'name:',
    'action:',
));

if (isset($opts['h']) || !isset($opts['group']) || !isset($opts['name']) || !isset($opts['action'])) {
    showHelp();
    exit(200);
}

if (isset($opts['d'])) {
    if (!is_array($opts['d'])) {
        $opts['d'] = array($opts['d']);
    }
    foreach ($opts['d'] as $line) {
        $arr = explode(':', $line);
        $opts['args'][$arr[0]] = isset($arr[1]) ? $arr[1] : null;
    }
    unset($opts['d']);
}

$mng = new \firegit\git\Manager();
$method = $opts['action'];
if (!method_exists($mng, $method)) {
    throw new \Exception('manager.actionNotFound action=' . $method);
}
$refMethod = new ReflectionMethod('\firegit\git\Manager', $method);
$params = $refMethod->getParameters();
$ps = array();

foreach ($params as $param) {
    $name = $param->getName();
    if ($name == 'group') {
        $ps[] = $opts['group'];
    } elseif ($name == 'name') {
        $ps[] = $opts['name'];
    } elseif (isset($opts['args'][$name])) {
        $ps[] = $opts['args'][$name];
    } elseif ($param->isDefaultValueAvailable()) {
        $ps[] = $param->getDefaultValue();
    } else {
        $ps[] = null;
    }
}
file_put_contents(LOG_ROOT.'ps', var_export($ps, true));
$code = call_user_func_array(array($mng, $method), $ps);
if ($code < 255) {
    exit($code);
}
exit(255);