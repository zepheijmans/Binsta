<?php

function displayTemplate(string $templatePath, array $templateData = null)
{
    $loader = new \Twig\Loader\FilesystemLoader('../views');
    $twig = new \Twig\Environment($loader);
    if (!empty($_SESSION['user'])) {
        $templateData['user'] = $_SESSION['user'];
    }
    return $twig->display($templatePath, $templateData);
}

function error(int $errorNumber, string $errorMessage)
{
    http_response_code($errorNumber);
    return displayTemplate("error.twig", ['error_message' => $errorMessage]);
}

function loginError(int $errorNumber, string $errorMessage, array $fields = null)
{
    http_response_code($errorNumber);
    return displayTemplate("user/login.twig", ['error_message' => $errorMessage, 'fields' => $fields]);
}


function registerError(int $errorNumber, string $errorMessage, array $fields = null)
{
    http_response_code($errorNumber);
    return displayTemplate("user/register.twig", ['error_message' => $errorMessage, 'fields' => $fields]);
}

function callMethod($controller, $method, $id)
{
    if (method_exists($controller, $method)) {
        $controller->$method($id);
    } else {
        error('404', 'This method does not exist');
    }
}
