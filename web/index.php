<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;


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

//Test Emitter
$messenger->get('/emit/test', function() use($app) {
  
  // Log
  $app['monolog']->addDebug('Sending message.');
  
  //Configure details of the RabbitMQ service
  define('AMQP_DEBUG', true);
  use PhpAmqpLib\Connection\AMQPConnection;
  use PhpAmqpLib\Message\AMQPMessage;
  
  //Get cloudAMQP url from server
  $url = parse_url(getenv('CLOUDAMQP_URL'));
  
  
  //Connect to the queue
  $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
  $channel = $conn->channel();

  //Declare exchange
  $exchange = 'amq.fanout';
  $channel->exchange_declare($exchange, 'fanout', false, false, false);
  
  $data = implode( ' ', array_slice($argv,1));
  if(empty($data)) $data = "info: Hello World!";
  $msg = new AMQPMessage($data);
  
  $channel->basic_publish($msg, $exchange);
  
  echo " [X] Sent: ", $data, "\n";
  
  //Disconnect from the queue
  $channel->close();
  $conn->close();
  
  return new Response('Message Sent!', 201);
});

//Test consumer
$messenger->get('/consume/test', function() use($app) {
  $app['monolog']->addDebug('fetching message.');
  
  //Configure details of the RabbitMQ service
  define('AMQP_DEBUG', true);
  use PhpAmqpLib\Connection\AMQPConnection;
  use PhpAmqpLib\Message\AMQPMessage;
  
  //Get cloudAMQP url from server
  $url = parse_url(getenv('CLOUDAMQP_URL'));
  
  
  //Connect to the queue
  $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
  $channel = $conn->channel();
  
  //Declare exchange
  $exchange = 'amq.fanout';
  $channel->exchange_declare($exchange, 'fanout', false, false, false);
  
  list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);
  
  $channel->queue_bind($queue_name, $exchange);
  
  echo '  [X] Waiting for logs.', '\n';
  
  $callback = function( $message ) {
    echo '    [X] ', $message->body, '\n';
  };
  
  $channel->basic_consume($queue_name, '', false, true, false, false, $callback);
  
  while(count($channel->callbacks)) {
    $channel->wait();
  }
  
  $channel->close();
  $connection->close();
  
  return new Response('Listening...', 201);
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
