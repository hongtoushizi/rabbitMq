<?php

require_once '../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->queue_declare('rpc_queue', false, false, false, false);

function makeRequest ($param = array()) {
    $url = "http://localhost/question_api.php?s=/question/help";    //请求的URL地址
    $query = http_build_query($param);
    $options = array(
        'http' => array(
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                "Content-Length: " . strlen($query) . "\r\n" .
                "User-Agent:MyAgent/1.0\r\n",
            'method' => "POST",
            'content' => $query,
        ),
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return  $result;
};


echo " [x] Awaiting RPC requests\n";
$callback = function ($req) {
    $n = intval($req->body);
    $response_info = makeRequest();
    $msg = new AMQPMessage(
        $response_info,
        array('correlation_id' => $req->get('correlation_id'),
            'content_type' => 'application/json'
            )
    );

    $req->delivery_info['channel']->basic_publish(
        $msg, '', $req->get('reply_to'));
    $req->delivery_info['channel']->basic_ack(
        $req->delivery_info['delivery_tag']);
};



$channel->basic_qos(null, 1, null);
$channel->basic_consume('rpc_queue', '', false, false, false, false, $callback);

while (count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();

?>
