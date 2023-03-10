<?php

require_once 'vendor/autoload.php';

use RedBeanPHP\R as R;

// Drop tables if they exist
R::exec('DROP TABLE IF EXISTS users');
R::exec('DROP TABLE IF EXISTS posts');

$users = [
    [
        'email' => 'admin@email.com',
        'username' => 'admin',
        'fullname' => 'John Doe',
        'bio' => '🦄',
        'password' => 'admin'
    ],
    [
        'email' => 'dev@email.com',
        'username' => 'dev',
        'fullname' => 'Jane Doe',
        'bio' => 'Sample biography',
        'password' => 'dev'
    ],
    [
        'email' => 'cat@email.com',
        'username' => 'cat',
        'fullname' => 'Cat',
        'bio' => '🥛Milk',
        'avatar' => 'cat.jpg',
        'banner' => 'cat.jpg',
        'fullname' => 'Cat',
        'password' => 'cat'
    ],
];

// Create users
foreach ($users as $user) {
    $newUser = R::dispense('users');
    $newUser->email = $user['email'];
    $newUser->username = $user['username'];
    $newUser->fullname = $user['fullname'];
    $newUser->bio = $user['bio'];
    $newUser->avatar = $user['avatar'] ?? '';
    $newUser->banner = $user['banner'] ?? '';
    $newUser->password = password_hash($user['password'], PASSWORD_DEFAULT);
    $newUser->settings = '[]';
    $newUser->following = $user['following'] ?? '[]';
    $newUser->followers = $user['followers'] ?? '[]';
    $newUser->forks = '[]';
    $newUser->comments = '[]';
    $newUser->date_joined = time();
    R::store($newUser);
    echo "Created user " . $user['username'] . ".\n";
}

// Create dummy post
$post = R::dispense('posts');
$post->author = 1;
$post->theme = "rainbow";
$post->content = "<div>Hello World</div>";
$post->language = "html";
$post->caption = "🦄 Hello World";
$post->likes = "[]";
$post->comments = "[]";
$post->date_created = time();
R::store($post);
echo "Dummy post created.\n";
