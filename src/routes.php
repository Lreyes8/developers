<?php

use Slim\Http\Request as Request;
use Slim\Http\Response as Response;

use Zend\Soap\AutoDiscover;
use Zend\Soap\Server as SoapServer;


$container = $app->getContainer();
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('../templates', [
        'cache' => '../var/cache',
        'auto_reload' => true,
        'debug' => true,
    ]);

    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new \Slim\Views\TwigExtension($container['router'], $basePath));

    return $view;
};

$app->group('/employee', function () {

    $this->get('/', function (Request $request, Response $response) {
        return $this->view->render($response, 'index.html.twig');
    })->setName('employee_index');

    $this->get('/{id}', function ($request, $response, $args) {

        $parents = json_decode(file_get_contents('employees.json'), true);
        $searched = ['id' => $args['id']];
        $employee = null;

        foreach ($parents as $key => $value) {
            $exists = true;
            foreach ($searched as $skey => $svalue) {
                $exists = ($exists && IsSet($parents[$key][$skey]) && $parents[$key][$skey] == $svalue);
            }
            if ($exists) {
                $employee = $value;
            }
        }

        return $this->view->render($response, 'show.html.twig', [
            'employee' => $employee
        ]);
    })->setName('employee_detail');

});

$app->group('/service', function () {

    $this->get('/employee/search', function (Request $request, Response $response, $args) {

        $employee = [];

        if ($request->getParam('email')) {
            $data = json_decode(file_get_contents('employees.json'), true);
            $searched = ['email' => trim($request->getParam('email'))];

            foreach ($data as $key => $value) {
                $exists = true;
                foreach ($searched as $skey => $svalue) {
                    $exists = ($exists && IsSet($data[$key][$skey]) && $data[$key][$skey] == $svalue);
                }
                if ($exists) {
                    $employee[] = $value;
                }
            }
        }

        $newResponse = $response
            ->withStatus(200)
            ->withHeader('Content-type', 'application/json')
            ->write(json_encode($employee));

        return $newResponse;

    })->setName('employee_searchbyemail');

    $this->post('/employee/salary.xml', function (Request $request, Response $response, $args) {

        $start = tofloat($request->getParam('start'));
        $end = tofloat($request->getParam('end'));

        $employee = [];

        $parents = json_decode(file_get_contents('employees.json'), true);

        foreach ($parents as $key => $value) {

            if (
                $start <= tofloat($parents[$key]['salary']) &&
                $end >= tofloat($parents[$key]['salary'])
            ) {
                $employee[] = $value;
            }
        }

        $newResponse = $response->withHeader('Content-type', 'application/xml');

        return $this->view->render($newResponse, 'salary.xml.twig', array('employees' => $employee));
    })->setName('employee_salary_filter');

});

function tofloat($num)
{
    $dotPos = strrpos($num, '.');
    $commaPos = strrpos($num, ',');
    $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
        ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

    if (!$sep) {
        return floatval(preg_replace("/[^0-9]/", "", $num));
    }

    return floatval(
        preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
        preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num)))
    );
}