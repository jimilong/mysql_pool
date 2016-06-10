<?php
/*$serv = new swoole_http_server("127.0.0.1", 9500);
$serv->set(array(
    'worker_num' => 3,
    'task_worker_num' => 4, //database connection pool
    /*'db_uri' => 'mysql:host=127.0.0.1;dbname=test',
    'db_user' => 'root',
    'db_passwd' => '123456',
));
function my_onRequest_sync($req, $resp)
{
    global $serv;
    $result = $serv->taskwait("show tables");
    if ($result !== false)
    {
        $resp->end(var_export($result['data'], true));
        return;
    }
    else
    {
        $resp->status(500);
        $resp->end("Server Error, Timeout\n");
    }
}
function my_onTask($serv, $task_id, $from_id, $sql)
{
    static $link = null;
    if ($link == null)
    {
        $link = new PDO('mysql:host=127.0.0.1;dbname=test', 'root', 123456);;
        if (!$link)
        {
            $link = null;
            return array("data" => '', 'error' => "connect database failed.");
        }
    }
    $result = $link->query($sql);
    if (!$result)
    {
        return array("data" => '', 'error' => "query error");
    }
    $data = $result->fetchAll();
    return array("data" => $data);
}
function my_onFinish($serv, $data)
{
    echo "AsyncTask Finish:Connect.PID=" . posix_getpid() . PHP_EOL;
}
$serv->on('request', 'my_onRequest_sync');
$serv->on('task', 'my_onTask');
$serv->on('finish', 'my_onFinish');
$serv->start();*/

/*$serv = new swoole_server("127.0.0.1", 9501);

//设置异步任务的工作进程数量
$serv->set(array('worker_num' => 3, 'task_worker_num' => 4));

$serv->on('receive', function($serv, $fd, $from_id, $data) {
    //投递异步任务
    $task_id = $serv->task($data);
    echo "Dispath AsyncTask: id=$task_id\n";
});

//处理异步任务
$serv->on('task', function ($serv, $task_id, $from_id, $data) {
    echo "New AsyncTask[id=$task_id]".PHP_EOL;
    //返回任务执行的结果
    $serv->finish("$data -> OK");
});

//处理异步任务的结果
$serv->on('finish', function ($serv, $task_id, $data) {
    echo "AsyncTask[$task_id] Finish: $data".PHP_EOL;
});

$serv->start();*/

$serv = new swoole_server("127.0.0.1", 9501);

//设置异步任务的工作进程数量
$serv->set(array('worker_num' => 3, 'task_worker_num' => 4));

$serv->on('receive', function($serv, $fd, $from_id, $data) {
    //投递异步任务
    $mem = new Memcached();
    //链接一台memcahe服务
    $mem->addServer('127.0.0.1','11211');
    $mem->add('fdd', $fd);
    $task_id = $serv->task($data);
    echo "Dispath AsyncTask: id=$task_id\n";
});

//处理异步任务
$serv->on('task', function ($serv, $task_id, $from_id, $data) {
    static $link = null;
    if ($link == null)
    {
        $link = new PDO('mysql:host=127.0.0.1;dbname=test', 'root', 123456);;
        if (!$link)
        {
            $link = null;
            return array("data" => '', 'error' => "connect database failed.");
        }
    }
    $result = $link->query($data);
    if (!$result)
    {
        return array("data" => '', 'error' => "query error");
    }
    $data = $result->fetchAll();

    $mem = new Memcached();
    //链接一台memcahe服务
    $mem->addServer('127.0.0.1','11211');
    $fdd = $mem->get('fdd');
    $serv->send($fdd, json_encode($data));

    //返回任务执行的结果
    return array("data" => $data);

    //$serv->finish($data);
});

//处理异步任务的结果
$serv->on('finish', function ($serv, $task_id, $data) {
    //echo "AsyncTask[$task_id] Finish: $data".PHP_EOL;
    print_r(['data' => $data]);
});

$serv->start();