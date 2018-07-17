<?php


/////客户端


$client = new stream_socket_client('tcp://127.0.0.1:9501',$errno,$errstr,30);
function read() {
	global $client;
	$buf = stream_socket_recvfrom($client,1024);
	if (!$buf){
		echo "server closed";
		swoole_event_del($client);
	}
	echo "recv ：{$buf}";
	fwrite(STDOUT,"ENTER:");
}

function write(){
	global $client;
	echo "on write";
}

function input(){
	global $client;
	$msg = trim(fgets(STDIN));
	if ($msg = 'exit') {
		swoole_event_exit();
		exit();
	}
	swoole_event_write($client,$msg);
	fwrite(STDOUT,"ENTER:");

}

swoole_event_add($client,'read','write');
swoole_event_add(STDIN,'input');
fwrite(STDOUT,"ENTER:");
