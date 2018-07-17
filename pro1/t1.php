<?php

///////服务端
///
///
$serv = new swoole_server("0.0.0.0", 9501);

$serv->set(array(
	'worker_num' => 4,
	'task_worker_num' => 4,
	'daemonize' => false,
	'max_request' => 10000,
	'backlog' => 128,
	'dispatch_mode' => 2,
));
$serv->on("start", function ($server) {
	echo "Swoole server is started \n";
});

$serv->on("connect",function ($server,$fd,$from_id){
	echo "swoole fd {$fd}client is connecting \n";
});
$serv->on("receive",function ($server,$fd,$from_id,$data){
	echo "get msg from client {$fd}--{$data} \n";
	$data = [
		'task' => 'task_1',
		'params' =>$data,
		'fd'=>$fd
	];
	$server->task(json_encode($data));
	echo "fuck async";
});
$serv->on("close",function ($server,$fd,$from_id){
	echo "swoole fd {$fd}client is closing \n";
});
$serv->on("task",function ($server,$task_id,$from_id,$data){
	echo "task: {$task_id} is from {$from_id} \n";
	$data = json_decode($data,true);
	echo "fd : {$data['fd']}";
	sleep(5);
	$server->send($data['fd'],'this is task');
	return "task over";
});
$serv->on("finish",function ($server,$task_id,$data){
	echo "task {$task_id} is finished \n";
	echo "result : $data";
});
$serv->start();