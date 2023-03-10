<?php

class PostController extends BaseController
{
    public function index($id)
    {
        $this->authorizeUser();
        displayTemplate('post/feed.twig', ['user_id' => $id]);
    }

    public function getPosts()
    {
        $this->authorizeUser();

        if (!isset($_POST['offset']) || !isset($_POST['amount'])) {
            return http_response_code(500);
        }

        header("Content-Type: application/json");
        $posts = $this->getBeansFromTo('posts', 'date_created', $_POST['offset'], $_POST['amount']);
        foreach ($posts as &$post) {
            $author = $this->getBeanById("users", $post['author']);
            $post['author'] = [];
            $post['author']['id'] = $author['id'];
            $post['author']['username'] = $author['username'];
            $post['author']['avatar'] = $this->getAvatarUrl($author['avatar']);

            $likes = json_decode($post['likes'], true);
            $likes_count = 0;
            foreach ($likes as $like) {
                if (json_decode($like, true)) {
                    $likes_count++;
                }
            }
            $post['likes_count'] = $likes_count;
            $post['like_status'] = isset($likes[$_SESSION['user']['id']]) ? intval(json_decode($likes[$_SESSION['user']['id']], true)) : false;
            $comments = json_decode($post['comments'], true);
            $comments_count = count($comments);
            $post['comments_count'] = $comments_count;
        }
        echo json_encode(array('status' => 'success', 'posts' => $posts));
    }

    public function create()
    {
        $this->authorizeUser();
        displayTemplate('post/create.twig', []);
    }

    public function createPost()
    {
        $this->authorizeUser();

        header("Content-Type: application/json");
        if (isset($_SESSION['user']['post_timeout']) && time() < $_SESSION['user']['post_timeout']) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Please wait before creating another post.'));
        }

        $_SESSION['user']['post_timeout'] = time() + 5;

        if (!isset($_POST['content'])) {
            return http_response_code(500);
        }

        $bean = $this->insertBean('posts', [
            'author' => $_SESSION['user']['id'],
            'theme' => isset($_POST['theme']) ? $_POST['theme'] : 'default',
            'language' => isset($_POST['language']) ? $_POST['language'] : null,
            'content' => $_POST['content'],
            'caption' => isset($_POST['caption']) ? $_POST['caption'] : null,
            'likes' => "[]",
            'comments' => "[]",
            'date_created' => time()
        ]);

        echo json_encode(array('status' => $bean ? 'success' : 'error'));
    }

    public function likePost()
    {
        $this->authorizeUser();

        if (!isset($_POST['id']) || !isset($_POST['status'])) {
            return http_response_code(500);
        }

        $post = $this->getBeanById('posts', $_POST['id']);
        $likes = json_decode($post['likes'], true);
        $likes[$_SESSION['user']['id']] = $_POST['status'];
        $this->updateBean('posts', $_POST['id'], ['likes' => json_encode($likes)]);

        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success'));
    }

    public function commentPost($id)
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        if (!isset($_POST['comment'])) {
            return http_response_code(500);
        }

        if (empty($_POST['comment'])) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Comment is empty.'));
            exit();
        }

        $post = $this->getBeanById('posts', $id);
        if (!$post) {
            return http_response_code(500);
        }

        $comments = json_decode($post['comments'], true);

        $comment = [];
        $comment['author'] = $_SESSION['user']['id'];
        $comment['comment'] = htmlspecialchars($_POST['comment']);
        $comment['date_posted'] = time();

        array_push($comments, $comment);

        $this->updateBean('posts', $id, ['comments' => json_encode($comments)]);

        echo json_encode(array('status' => 'success'));
    }

    public function getComments($id)
    {
        $this->authorizeUser();

        if (!isset($_POST['offset']) || !isset($_POST['amount'])) {
            return http_response_code(500);
        }

        $post = $this->getBeanById('posts', $id);
        if (!$post) {
            return http_response_code(500);
        }

        $comments = json_decode($post['comments'], true);
        $comments = array_reverse($comments);
        $comments = array_splice($comments, $_POST['offset'], $_POST['amount']);

        $comments_meta = $this->getCommentsMeta($comments);

        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success', 'comments' => $comments, 'comments_meta' => $comments_meta));
    }

    public function getLikes($id)
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        $post = $this->getBeanById('posts', $id);
        if (!$post) {
            return http_response_code(500);
        }

        $likes = json_decode($post['likes'], true);

        $likes_meta = [];
        foreach ($likes as $userID => $active) {
            $user = $this->getBeanById('users', $userID);
            if (!$user) {
                continue;
            }
            $likes_meta[$userID] = [];
            $likes_meta[$userID]['username'] = $user['username'];
            $likes_meta[$userID]['avatar'] = $this->getAvatarUrl($user['avatar']);
        }
        echo json_encode(array('status' => 'success', 'likes' => $likes, 'likes_meta' => $likes_meta));
    }

    public function forks()
    {
        $this->authorizeUser();
        $forks = json_decode($this->getFromBean('users', 'forks', 'id', $_SESSION['user']['id'])['forks'], true);
        displayTemplate('post/forks.twig', ['forks' => $forks]);
    }

    public function forkPost()
    {
        $this->authorizeUser();

        if (!isset($_POST['id'])) {
            return http_response_code(500);
        }

        $post = $this->getBeanById('posts', $_POST['id']);
        if (!$post) {
            return http_response_code(500);
        }

        $forks = $this->getFromBean('users', 'forks', 'id', $_SESSION['user']['id'])['forks'];
        if (!$forks) {
            return http_response_code(500);
        }
        $forks = json_decode($forks, true);

        $author = $this->getFromBean('users', 'username', 'id', $post['author']);
        if (!$author) {
            return http_response_code(500);
        }

        if (isset($forks[$post['id']])) {
            header("Content-Type: application/json");
            echo json_encode(array('status' => 'error', 'error_message' => 'You have already forked this post.'));
            return;
        }

        $forks[$post['id']] = [
            'id' => $post['id'],
            'author' => $post['author'],
            'author_username' => $author['username'],
            'theme' => $post['theme'],
            'language' => $post['language'],
            'content' => $post['content'],
            'date_created' => $post['date_created'],
            'date_forked' => time()
        ];

        $this->updateBean('users', $_SESSION['user']['id'], ['forks' => json_encode($forks)]);
        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success'));
    }

    public function forkDelete()
    {
        $this->authorizeUser();

        if (!isset($_POST['id'])) {
            return http_response_code(500);
        }

        $forks = $this->getFromBean('users', 'forks', 'id', $_SESSION['user']['id'])['forks'];
        if (!$forks) {
            return http_response_code(500);
        }
        $forks = json_decode($forks, true);

        if (!isset($forks[$_POST['id']])) {
            header("Content-Type: application/json");
            echo json_encode(array('status' => 'error', 'error_message' => 'Fork does not exist.'));
            return;
        }

        unset($forks[$_POST['id']]);
        $this->updateBean('users', $_SESSION['user']['id'], ['forks' => json_encode($forks)]);
        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success'));
    }
}
