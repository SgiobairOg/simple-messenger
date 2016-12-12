<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

//Configure details of the RabbitMQ service
define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
$url = parse_url(getenv('CLOUDAMQP_URL'));

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
$messenger->get('/emit/test', function() use($app) {
  $app['monolog']->addDebug('fetching message.');
  
  echo 'Emit test';
  
  return new Response('Test complete', 201);
});

$messenger->get('/consume/test', function($message) use($app) {
  $app['monolog']->addDebug('sending message.');
  
  echo 'Consume test';
  
  return new Response('Test complete', 201);
});

$app->mount('/messenger', $messenger);
$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});

$app->run();
