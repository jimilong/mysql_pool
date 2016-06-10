<?php

//以下是客户端代码
$client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC); //同步阻塞
$client->connect('127.0.0.1', 9501);
$client->send("show tables");
$data = $client->recv();

print_r($data);