<?php

/**
 * Created by PhpStorm.
 * User: xizy
 * Date: 2018/7/7
 * Time: 下午10:42
 */
class Server
{
	private $serv;
	private $test;

	public function __construct()
	{
		$this->serv = new swoole_server("0.0.0.0", 9501);
		$this->serv->set([
			'worker_num' => 8,
			'daemonize' => false,
			'max_request' => 2,
			'dispatch_mode' => 2,
			'task_worker_num' => 8,
		]);
		$this->serv->on('Start', [$this, 'onStart']);
		$this->serv->on('Connect', [$this, 'onConnect']);
		$this->serv->on('Receive', [$this, 'onReceive']);
		$this->serv->on('Close', [$this, 'onClose']);

		//callback
		$this->serv->on('Task', [$this, 'onTask']);
		$this->serv->on('Finish', [$this, 'onFinish']);
		$this->serv->start();
	}


	public function onStart($serv)
	{
		echo 'onStart';
	}

	public function onConnect($serv, $fd, $from_id)
	{
		echo 'onConnect' . $fd.'\n';
	}

	public function onReceive(swoole_server $serv, $fd, $from_id, $data)
	{
		echo 'onReceive ' . $fd.'\n';
		echo '----------'.'\n';
		echo 'data ' . $data.'\n';

		$data = [
			'task' => 'task_1',
			'params' => $data,
			'fd' => $fd,
		];
		$serv->task(json_encode($data));
	}

	public function onClose($serv, $fd)
	{
		echo 'onClose' . $fd.'\n';
	}

	public function onTask($serv, $task_id, $from_id, $data)
	{
		echo "this task id :{$task_id} is from worker id: {$from_id}".'\n';
		$data = json_decode($data);
		var_dump($data);

		$serv->send($data['fd'],"task 323123123");
		return 0;
	}

	public function onFinish($serv, $task_id, $data)
	{
		echo "task id:{$task_id} finish".'\n';
		echo "data {$data}".'\n';
	}
}

new Server();