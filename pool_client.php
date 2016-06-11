<?php

//以下是客户端代码
$client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC); //同步阻塞
$client->connect('127.0.0.1', 9501);
$client->send(serialize(['sql' => '', 'event' => 'beginTransaction']));
$data[1] = $client->recv();
$client->send(serialize(['sql' => "insert user (id,name) values (9, 'long9')", 'event' => 'insert', 'worker_id' => $data[1]]));
$data[2] = $client->recv();
$client->send(serialize(['sql' => "", 'event' => 'commit', 'worker_id' => $data[2]]));
$data[3] = $client->recv();


print_r($data);