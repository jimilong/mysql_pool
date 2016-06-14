<?php

$table = new swoole_table(1024);
$table->column('fd', swoole_table::TYPE_INT);
$table->column('from_id', swoole_table::TYPE_INT);
$table->create();

$serv = new swoole_server("127.0.0.1", 9501);
//将table保存在serv对象上
$serv->table = $table;

//设置异步任务的工作进程数量
$serv->set(array(
    'worker_num' => 4,
    'task_worker_num' => 2,
    'max_request' => 10000,
    'dispatch_mode' => 3,
    'task_ipc_mode' => 2
));


$serv->on('receive', function($serv, $fd, $from_id, $data) {
    //投递异步任务
    $data = unserialize($data);

    //$task_id = $serv->task($data);
    $taskProcess = [];
    if (isset($data['worker_id'])) {
        /**/
        if ($data['event'] == 'beginTransaction') {
            //记录占用task进程
            $key = $data['worker_id'];
            $serv->table->set($key, array('from_id' => 9, 'fd' => 9));
        }

        if ($data['event'] == 'rollBack') {
            //删除占用task进程
            $key = $data['worker_id'];
            $serv->table->del($key);
        }

        if ($data['event'] == 'commit') {
            //删除占用task进程
            $key = $data['worker_id'];
            $serv->table->del($key);
        }
        /**/
        $task_id = $serv->task($data, $data['worker_id']);

        $key = $serv->worker_id.'_'.$task_id;  //$task_id和$from_id组合起来才是全局唯一的，不同的worker进程投递的任务ID可能会有相同
        $serv->table->set($key, array('from_id' => $from_id, 'fd' => $fd));
    } else {
        $taskProcess = [];
        for($i=0; $i<$serv->setting['task_worker_num']; $i++) {
            $taskProcess[$i] = $i;
        }
        foreach ($serv->table as $k => $v) {
            if (in_array($k, $taskProcess)) {
                unset($taskProcess[$k]);
            }
        }
        //存在空余task进程
        if ($taskProcess) {
            $to_task_worker_id = array_rand($taskProcess, 1);

            /**/
            if ($data['event'] == 'beginTransaction') {
                //记录占用task进程
                $key = $to_task_worker_id;
                $serv->table->set($key, array('from_id' => 9, 'fd' => 9));
            }

            if ($data['event'] == 'rollBack') {
                //删除占用task进程
                $key = $to_task_worker_id;
                $serv->table->del($key);
            }

            if ($data['event'] == 'commit') {
                //删除占用task进程
                $key = $to_task_worker_id;
                $serv->table->del($key);
            }
            /**/

            $task_id = $serv->task($data, $to_task_worker_id);

            $key = $serv->worker_id.'_'.$task_id;  //$task_id和$from_id组合起来才是全局唯一的，不同的worker进程投递的任务ID可能会有相同
            $serv->table->set($key, array('from_id' => $from_id, 'fd' => $fd));
        } else {
            $task_id = '';
        }
    }

    print_r($taskProcess);
    echo "Dispath AsyncTask: id=$task_id\n";
});

//处理异步任务
$serv->on('task', function ($serv, $task_id, $from_id, $data) {
    static $link = null;
    if ($link == null)
    {
        $link = new PDO('mysql:host=127.0.0.1;dbname=test', 'root', 123456);
        if (!$link)
        {
            $link = null;
            return array("data" => '', 'error' => "connect database failed.");
        }
    }

    $task_worker_id = $serv->worker_id - $serv->setting['worker_num'];

    if ($data['event'] == 'select') {
        $result = $link->query($data['sql']);
        if (!$result)
        {
            return array("data" => '', 'error' => "query error");
        }
        $data = $result->fetchAll();
    }

    if ($data['event'] == 'insert' || $data['event'] == 'update' || $data['event'] == 'delete') {
        //$data = $data['sql'];
        $link->exec($data['sql']);
    }

    if ($data['event'] == 'beginTransaction') {
        $link->beginTransaction();
    }

    if ($data['event'] == 'rollBack') {
        $link->rollBack();
    }

    if ($data['event'] == 'commit') {
        $link->commit();
    }

    $key = $from_id.'_'.$task_id;

    $fd = $serv->table->get($key);
    $serv->send($fd['fd'], $task_worker_id);

    $data['key'] = $key;

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