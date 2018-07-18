<?php

/////mysql动态连接池
///

class MysqlActive
{

	//利用process 来完成动态连接池
	private $mysql_list = [];         // sql池对象数组
	private $mysql_use = [];          // 进程占用标记数组
	private $min_mysql_num = 3;        // 进程池最小值
	private $max_mysql_num = 6;        // 进程池最大值
	private $current_num;               // 当前进程数

	function __construct()
	{
		$this->run();
	}

	public function run()
	{
		$server = new swoole_server("0.0.0.0", 9777);

		$server->set(array(
			'worker_num' => 4,
			'daemonize' => true,
			'backlog' => 128,
			'task_worker_num' => 8
		));


		$server->on('start', [$this, 'onStart']);
		$server->on('connect', [$this, 'onConnect']);
		$server->on('receive', [$this, 'onReceive']);
		$server->on('closed', [$this, 'onClosed']);
		$server->start();
	}


	public function onStart()
	{
		$this->current_num = $this->min_mysql_num;
		echo "server start!";
		//创建 多个mysql连接
		for ($i = 0; $i < $this->min_mysql_num; $i++) {
			$process = new swoole_process(array($this, 'task_run'), false, 2);
			$pid = $process->start();
			$this->mysql_list[$pid] = $process;
			$this->mysql_use[$pid] = 0;
		}

		foreach ($this->mysql_list as $pid => $process) {
			swoole_event_add($this->process, function ($pipe) use ($process, $pid) {
				$info = $process->read();
				if ($info) {
					$this->mysql_use[$pid] = 0;
					echo $info;
				}
			});
		}
	}

	public function onConnect($serv, $fd)
	{
		echo "server get a connect from fd:{$fd}";
	}

	public function onReceive($serv, $fd, $from_id, $data)
	{

		$sql = $data;
		$flag = true;
		//找有没有空闲的mysql连接
		foreach ($this->mysql_use as $pid => $used) {

			//有相关的未用连接
			if ($used === 0) {
				$flag = false;
				$this->mysql_list[$pid]->write($sql);
				$this->mysql_use[$pid] = 1;
			}
		}

		//没有找到相关的连接 并且还能再增加连接数
		if ($flag && $this->current_num < $this->max_mysql_num) {
			//增加一个进程
			$process = new swoole_process([$this, 'task_run'], false, 2);
			$pid = $process->start();
			$this->mysql_list[$pid] = $process;
			$this->mysql_use = 0;

			swoole_event_add($process, function ($pipe) use ($process, $pid) {
				$info = $process->read();
				if ($info) {
					$this->mysql_use[$pid] = 0;
					echo $info;
				}
			});

			$this->mysql_list[$pid]->write($sql);
			$this->mysql_use[$pid] = 1;
		}

		//如果mysql达到最大的连接数量
		if ($this->current_num == $this->max_mysql_num) {
			echo "数据库繁忙！";
		}
	}


	//开启数据库连接 并且保证连接的稳定性 ping||断线重连
	public function task_run($worker)
	{
		$link = mysqli_connect("127.0.0.1", "root", "root", "test");

		// 注册监听管道的事件，接收任务
		swoole_event_add($worker->pipe, function ($pipe) use ($worker, $link) {
			$data = $worker->read();
			var_dump($worker->pid . ": " . $data);
			if ($data == 'exit') {
				// 收到退出指令，关闭子进程
				$worker->exit();
				exit;
			}
			// 模拟任务执行过程

			if (!$link && mysqli_ping($link)) { //断线重连
				$link = mysqli_connect("127.0.0.1", "root", "root", "test");
			}
			$result = $link->query($data);
			$data = $result->fetch_all(MYSQL_ASSOC);

			// 执行完成，通知父进程
			$worker->write(json_encode($data));
		});
	}
}