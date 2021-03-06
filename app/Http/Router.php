<?php 

namespace App\Http;

use \Closure;
use \Exception;
use \ReflectionFunction;

class Router{

    /**
    * URL completa do projeto (raiz)
    * @var string
    */
    private $url = '';

    /**
    * Prefixo de todas as rotas
    * @var string
    */
    private $prefix = '';

    /**
    * Índice de rotas
    * @var array
    */
    private $routes = [];

    /**
    * Instância de Request
    * @var Request
    */
    private $request = '';

    /**
    * Método responsável por iniciar a classe
    * @param string $url
    */
    public function __construct($url){
        $this->request  = new Request();
        $this->url      = $url;        
        $this->setPrefix();
    }

    /**
    * Método reponsável por definir o prefixo das rotas    
    */
    private function setPrefix(){
        //informações da url atual
        $parseURL = parse_url($this->url);
        
        //define o prefixo
        $this->prefix = $parseURL['path'] ?? '';
    }

    /**
    * Método responsável por adicionar uma rota na classe
    * @param string $method
    * @param string $route
    * @param array $params
    */
    public function addRoute($method, $route, $params = []){
        //validação dos parâmtros
        foreach ($params as $key => $value) {
            if($value instanceof Closure){
                $params['controller'] = $value;
                unset($params[$key]);
                continue;
            }
        }

        //variáveis da rota
        $params['variables'] = [];

        //padrão de validacao de variáveis das rotas
        $patternVariable = '/{(.*?)}/';
        if (preg_match_all($patternVariable, $route, $matches)){
            $route = preg_replace($patternVariable, '(.*?)', $route);
            $params['variables'] = $matches[1];
        }

        //padrao de validacao da url
        $patternRoute = '/^'.str_replace('/','\/',$route).'$/';

        //adiciona a rota dentro da classe
        $this->routes[$patternRoute][$method] = $params;
    }

    /**
    * Método responsável por definir uma rota de GET
    * @param string $route
    * @param array $params
    */
    public function get($route, $params = []){
        return $this->addRoute('GET', $route, $params);
    }

    /**
    * Método responsável por definir uma rota de POST
    * @param string $route
    * @param array $params
    */
    public function post($route, $params = []){
        return $this->addRoute('POST', $route, $params);
    }

    /**
    * Método responsável por definir uma rota de PUT
    * @param string $route
    * @param array $params
    */
    public function put($route, $params = []){
        return $this->addRoute('PUT', $route, $params);
    }

    /**
    * Método responsável por definir uma rota de DELETE
    * @param string $route
    * @param array $params
    */
    public function delete($route, $params = []){
        return $this->addRoute('DELETE', $route, $params);
    }

    /**
    * Método responsável por a URI desconsiderando o prefixo
    * @return string
    */
    private function getUri(){
        // uri da resquest
        $uri = $this->request->getUri();
        
        $xUri = strlen($this->prefix) ? explode($this->prefix, $uri) : [$uri];
        
        //retornar uri sem prefix
        return end($xUri);        
    }

    /**
    * Método responsável por retornar os  dados da rota atual
    * @return array
    */
    private function getRoute(){
        // uri
        $uri = $this->getUri();

        //method
        $httpMethod = $this->request->getHttpMethod();

        //valida as rotas
        foreach ($this->routes as $patternRoute => $methods) {
            //verifica se a rota bate com o padão
            if (preg_match($patternRoute, $uri, $matches)){
                if (isset($methods[$httpMethod])){
                    //removo a primeira posicao
                    unset($matches[0]);

                    // chaves da variáveis
                    $keys = $methods[$httpMethod]['variables'];
                    $methods[$httpMethod]['variables'] = array_combine($keys, $matches);
                    $methods[$httpMethod]['variables']['request'] = $this->request;

                    // retorno dos parâmetros da rota
                    return $methods[$httpMethod];
                }

                throw new Exception("Método não permitido", 405);
            }
        }

        // url nao encontrada
        throw new Exception("URL não encontrada", 405);
        
    }

    /**
    * Método por executar a rota atual
    * @return Response
    */
    public function run() {
        try {
            //obtém a rota atual
            $route = $this->getRoute();
                        
            //verifica o controlador 
            if (!isset($route['controller'])) {
                throw new Exception("A URL não pôde ser processada",500);                
            }

            $args = [];

            $reflection = new ReflectionFunction($route['controller']);
            foreach ($reflection->getParameters() as $parameter ) {
                $name = $parameter->getName();
                $args[$name] = $route['variables'][$name] ?? '';
            }
            //retorna a execucao da funçao
            return call_user_func_array($route['controller'], $args);

        } catch (Exception $e) {
            return new Response($e->getCode(), $e->getMessage());
        }
    }

}