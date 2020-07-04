<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Client;
use Firebase\JWT\JWT;

require '../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = 'localhost';
$config['db']['user']   = 'postgres';
$config['db']['pass']   = 'dev#';
$config['db']['dbname'] = 'exampleapp';

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler('../logs/app.log');
    $logger->pushHandler($file_handler);
    return $logger;
};

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO('pgsql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

/**
 * Token do JWT
 */
$container['secretkey'] = "ensopadodemariscos";

/**
 * Auth básica do JWT
 * Whitelist - Bloqueia tudo, e só libera os itens dentro do "passthrough"
 */
$app->add(new \Slim\Middleware\JwtAuthentication([
    "secure" => false,
    "regexp" => "/(.*)/", //Regex para encontrar o Token nos Headers - Livre
    "header" => "X-Token", //O Header que vai conter o token
    "path" => "/", //Vamos cobrir toda a API a partir do /
    "passthrough" => ["/auth"], //Vamos adicionar a exceção de cobertura a rota /auth
    "realm" => "Protected",
    "secret" => $container['secretkey'] //Nosso secretkey criado
]));

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write(json_encode(array("status" => "200", "message" => "Bem-vindo!")));

    return $response;
});

$app->get('/dolar', function (Request $request, Response $response, array $args) {
    $client = new GuzzleHttp\Client(['base_uri' => 'https://economia.awesomeapi.com.br/json']);
    $res = $client->request('GET', '/all/USD-BRL');
    
    //Se a API retornou a cotacao do dolar responde com os dados, senao retorna msg de erro
    if($res->getStatusCode() == 200){
        $return = $response->withJson(json_decode($res->getBody()), 200)->withHeader('Content-type', 'application/json');
    }else{
        $return = $response->withJson(json_decode('{"message": "Desculpe, não foi possível recuperar o valor do dólar nesse momento."}'), 500)->withHeader('Content-type', 'application/json');
    }

    return $return;
});

/**
 * HTTP Auth - Autenticação minimalista para retornar um JWT
 */
$app->get('/auth', function (Request $request, Response $response) use ($app) {

    $key = $this->get("secretkey");

    $token = array(
        "user" => "@jdson7",
        "name" => "Jadson Freitas",
        "github" => "https://github.com/jdson7"
    );

    $jwt = JWT::encode($token, $key);

    return $response->withJson(["auth-jwt" => $jwt], 200)->withHeader('Content-type', 'application/json');
});

$app->run();