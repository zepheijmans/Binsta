<?php

session_start();

require_once '../vendor/autoload.php';

$id = $_GET['id'] ?? null;
$query = $_GET['q'] ?? 'post';
$query = explode("/", $query);

$method = $query[1] ?? 'index';
$controller = ucfirst($query[0]) . 'Controller';

if (class_exists($controller)) {
    $pageController = new $controller();

    callMethod($pageController, $method, $id ?? $query[2] ?? null);
} else {
    error(404, "Controller " . $controller . " not found");
    exit();
}
