<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();

require_once __DIR__.'/config.php';

if ($app['debug']) {
  ini_set('error_reporting', E_ALL);
  ini_set('display_errors', 'On');
}

$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__.'/'
));
$app->register(new Silex\Provider\SessionServiceProvider());

// the landing page
$app->get('/', function() use($app) {
    return $app['twig']->render('index.html.twig', array(
        'deped_connect_url' => $app['deped_oauth2']->getAuthorizationUri()
    ));
})->bind('homepage');


$app->get('/logout', function() use($app) {
    $app['session']->remove('user');

    return $app->redirect('/');
})->bind('logout');


// the controller called by oauth2 after authorization
$app->get($app['deped.oauth2.callback_uri'] , function() use($app) {
  $request = $app['request_stack']->getCurrentRequest();
  $code = $request->query->get('code');
  $state = $request->query->get('state');

  // get access_token
  $app['deped_oauth2']->requestAccessToken($code, $state);

  $context = 0;
  if ($state) {
    $context = json_decode(base64_decode($state), true);
  }

  // retrieve user information, a bearer header that contains access_token is attached behind the scene
  // which allows the api security to authorize the request
  $user = $app['deped_oauth2']->request('api/user/me?context='.$context);

  // we simply store the payload in session which also indicates that the user is loggedin
  $app['session']->set('user', json_decode($user, true));

  // go back to homepage
  return $app->redirect('/');
})->bind('oauth2_callback');

// the oauth2 service initializer
$app['deped_oauth2'] = function () use($app) {
  $credential = new OAuth\Common\Consumer\Credentials(
      $app['deped.oauth2.client_id'],
      $app['deped.oauth2.client_secret'],
      $app['url_generator']->generate('oauth2_callback', array(), true)
  );
  $client = new OAuth\Common\Http\Client\StreamClient;
  $session = new OAuth\Common\Storage\SymfonySession($app['session']);
  $apiUri = new OAuth\Common\Http\Uri\Uri($app['deped.api.base_url']);

  require_once __DIR__.'/DepEdOAuth2Service.php';

  return new DepEdOAuth2Service($credential, $client, $session, array(),
    $app['deped.oauth2.base_url'], $apiUri);
};

$app->run();
