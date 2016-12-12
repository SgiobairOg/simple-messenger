<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

//Configure details of the RabbitMQ service
define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;


//Symphony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Messenger handlers
$messenger = $app['controllers_factory'];
$messenger->get('/', function() use($app) {
  $url = parse_url(getenv('CLOUDAMQP_URL'));
  $app['monolog']->addDebug('fetching message.');
  
  //Connect to the queue
  $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
  $channel = $conn->channel();

  $exchange = 'amq.direct';
  $queue = 'basic_get_queue';
  $channel->queue_declare($queue, false, true, false, false);
  $channel->exchange_declare($exchange, 'direct', true, true, false);
  $channel->queue_bind($queue, $exchange);
  
  $app['monolog']->addDebug('Waiting for messages');
  
  $deliver = function($msg) {
    echo ' [X] ', $msg->body, '\n';
  };
  
  //Retrive message from queue
  $channel->basic_consume($queue, '', false, true, false, false, $deliver);
  
  while(count($channel->callbacks)) {
    $channel->wait();
  }
  
  //Disconnect from the queue
  $channel->close();
  $conn->close();
  
  return new Response('Listening...', 201);
});

$messenger->get('/send/test/{message}', function($message) use($app) {
  $url = parse_url(getenv('CLOUDAMQP_URL'));
  $app['monolog']->addDebug('sending message.');
  
  //Connect to the queue
  $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
  $channel = $conn->channel();

  $exchange = 'amq.direct';
  $queue = 'basic_get_queue';
  $channel->queue_declare($queue, false, true, false, false);
  $channel->exchange_declare($exchange, 'direct', true, true, false);
  $channel->queue_bind($queue, $exchange);
  
  //Send message to the queue
  $msg_body = $message;
  $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
  $channel->basic_publish($msg, $exchange);
  
  //Disconnect from the queue
  $channel->close();
  $conn->close();
  
  return new Response('Message sent!', 201);
});

$messenger->post('/send', function(Request $request) use($app) {
  $url = parse_url(getenv('CLOUDAMQP_URL'));
  $app['monolog']->addDebug('sending message.');
  
  //Connect to the queue
  $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
  $channel = $conn->channel();

  $exchange = 'amq.direct';
  $queue = 'basic_get_queue';
  $channel->queue_declare($queue, false, true, false, false);
  $channel->exchange_declare($exchange, 'direct', true, true, false);
  $channel->queue_bind($queue, $exchange);
  
  //Send message to the queue
  $msg_body = $request->get('message');
  $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
  $channel->basic_publish($msg, $exchange);
  
  //Disconnect from the queue
  $channel->close();
  $conn->close();
  
  return new Response('Message sent!', 201);
});

$app->mount('/messenger', $messenger);
$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});

$app->run();
