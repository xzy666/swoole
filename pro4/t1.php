<?php

////mysql动态连接池
///
class SqlActive
{

	function __construct()
	{
		$this->run();
	}

	public function run()
	{
		$server = new swoole_server('127.0.0.1', 9888);
		$server->set([
			'worker_num' => 100, //工作进程数量
			'task_worker_num' => 10 //mysql连接池数量
		]);

		$server->on('receive', [$this, 'onReceive']);
		$server->on('task', [$this, 'onTask']);
		$server->on('finish', [$this, 'onFinish']);
		$server->start();
	}

	public function onReceive($server, $fd, $from_id, $data)
	{
		echo "receive msg from {$fd}\n";
		$sql = "{$data}";
		$result = $server->taskwait($sql);
		if ($result) {
			list($status, $db) = explode(":", $result, 2);
			if ($status == 'OK') {
				$server->send($fd, json_encode($db));
			} else {
				$server->send($fd, 'error');

			}
			return;
		} else {
			$server->send($fd, "eeeeeerror");
		}

	}

	public function onTask($server, $task_id, $from_id, $data)
	{
		static $link = null;
		if ($link == null) {
			$link = mysqli_connect("127.0.0.1", "root", "root", "test");
			if (!$link&& mysqli_ping($link)) {
				$link =null;
				$server->finish("eeeeer:" . mysqli_error($link));
				return;
			}
		}
		$result = $link->query($data);
		if (!$result) {
			$server->finish("errrr" . mysqli_error($link));
		}
		$data = $result->fetch_all(MYSQL_ASSOC);
		$server->finish("OK:" . serialize($data));
	}

	public function onFinish()
	{
		echo "AsyncTask Finish:Connect.PID=" . posix_getpid() . PHP_EOL;
	}
}

new SqlActive();