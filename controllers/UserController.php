<?php

class UserController extends BaseController
{
    private const MIN_PASSWORD_LENGTH = 6;
    private const MAX_PASSWORD_LENGTH = 32;

    private function redirectAuthorized()
    {
        if (isset($_SESSION['user'])) {
            return header('Location: /');
        }
    }

    public function login()
    {
        $this->redirectAuthorized();
        displayTemplate("user/login.twig", []);
    }

    public function loginPost()
    {
        $this->redirectAuthorized();

        $fields = [];

        // List of fields which should be saved
        $saveFields = [
            'email' => true
        ];

        // Populate fields list
        foreach ($_POST as $fieldName => $fieldValue) {
            $fields[$fieldName] = [
                'valid' => false,
                'save' => $saveFields[$fieldName] ?? false,
                'value' => $fieldValue
            ];
        }

        // Validate fields
        if (empty($fields['email']['value']) || empty($fields['password']['value'])) {
            foreach ($fields as $name => $field) {
                if (!empty($field['value'])) {
                    $fields[$name]['valid'] = true;
                }
            }

            return loginError(403, "All fields are required.", $fields);
        }

        // Validate user
        $user = $this->findOneBean('users', 'email', $fields['email']['value']);

        if (!$user) {
            return loginError(403, "Invalid email or password.", $fields);
        }

        if (!password_verify($fields['password']['value'], $user['password'])) {
            return loginError(403, "Invalid email or password.", $fields);
        }

        // Authorize user
        $_SESSION['user'] = array(
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'avatar' => $this->getAvatarUrl($user['avatar'])
        );

        header('Location: /');
    }

    public function logout()
    {
        $_SESSION['user'] = null;
        header('Location: /');
    }

    public function register()
    {
        $this->redirectAuthorized();
        displayTemplate("user/register.twig", []);
    }

    public function registerPost()
    {
        $this->redirectAuthorized();

        $fields = [];

        // List of fields which should be saved
        $saveFields = [
            'email' => true,
            'username' => true
        ];

        // Populate fields list
        foreach ($_POST as $fieldName => $fieldValue) {
            $fields[$fieldName] = [
                'valid' => false,
                'save' => $saveFields[$fieldName] ?? false,
                'value' => $fieldValue
            ];
        }

        // Validate fields
        if (empty($_POST['email']) || empty($_POST['username']) || empty($_POST['password']) || empty($_POST['confirmPassword'])) {
            return registerError(404, "All fields are required.", $fields);
        }

        // Validate email
        $user = $this->findOneBean('users', 'email', $fields['email']['value']);

        if (!filter_var($fields['email']['value'], FILTER_VALIDATE_EMAIL)) {
            return registerError(404, "Invalid email format", $fields);
        }

        if ($user) {
            return registerError(404, "Email already in use", $fields);
        }

        // Validate username
        if (!preg_match("#^[a-zA-Z0-9äöüÄÖÜ]+$#", $fields['username']['value'])) {
            return registerError(404, "Username should only contain numbers and letters", $fields);
        }

        $user = $this->findOneBean('users', 'username', $fields['username']['value']);

        if ($user) {
            return registerError(404, "Username already in use", $fields);
        }

        // Validate confirmation password
        if ($_POST['password'] != $_POST['confirmPassword']) {
            return registerError(404, "Passwords did not match", $fields);
        }

        if (!strlen($_POST['password']) < $this::MIN_PASSWORD_LENGTH) {
            return registerError(404, "Password must be at least " . $this::MIN_PASSWORD_LENGTH . " characters", $fields);
        }

        if (!strlen($_POST['confirmPassword']) > $this::MAX_PASSWORD_LENGTH) {
            return registerError(404, "Password should not exceed " . $this::MAX_PASSWORD_LENGTH . " characters", $fields);
        }

        // Create user
        $id = $this->insertBean('users', [
            'email' => $fields['email']['value'],
            'username' => $fields['username']['value'],
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'date_joined' => time()
        ]);

        $_SESSION['user'] = array(
            'id' => $id,
            'username' => $fields['username']['value'],
            'email' => $fields['email']['value'],
            'avatar' => $this::DEFAULT_AVATAR
        );

        header('Location: /');
    }

    public function profile($id = null)
    {
        $this->authorizeUser();

        if (!$id) {
            $id = $_SESSION['user']['id']; // Fallback to self
        }

        $user = $this->findOneBean('users', 'id', $id);
        if (!$user) {
            return error(404, 'User does not exist');
        }

        $posts = $this->findBeans('posts', 'author', $id);

        $user['avatar'] = $this->getAvatar($id);
        $user['banner'] = $this->getBannerUrl($user['banner']);

        $user['following'] = json_decode($user['following'], true);
        $user['followers'] = json_decode($user['followers'], true);

        displayTemplate("user/profile.twig", [
            'profile' => $user,
            'count_posts' => count($posts),
            'count_following' => count($user['following']),
            'count_followers' => count($user['followers']),
            'follower' => in_array($_SESSION['user']['id'], $user['following']),
            'follow' => in_array($_SESSION['user']['id'], $user['followers'])
        ]);
    }

    public function settings()
    {
        $this->authorizeUser();

        $user = $this->findOneBean('users', 'id', $_SESSION['user']['id']);
        if (!$user) {
            return error(404, 'User does not exist');
        }
        $user['settings'] = json_decode($user['settings'], true);
        $user['banner'] = $this->getBannerUrl($user['banner']);
        displayTemplate("user/settings.twig", ['me' => $user]);
    }

    public function settingsPost()
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        $postData = array();

        foreach ($_POST as $key => $value) {
            $postData[$key] = $value;
        }

        if (strlen($postData['bio']) > 1000) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Bio exceeds 1000 characters.'));
            exit();
        }

        $this->updateBean('users', $_SESSION['user']['id'], $postData);
        echo json_encode(array('status' => 'success'));
    }

    private $allowedFileTypes = [
        "image/gif",
        "image/jpeg",
        "image/pjpeg",
        "image/png"
    ];

    private function uploadImage(array $image, string $dirName)
    {
        if (filesize($image["tmp_name"]) <= 0) {
            header("Content-Type: application/json");
            echo json_encode(array('status' => 'error', 'error_message' => 'Uploaded image has no contents.'));
            exit();
        }

        if (!in_array($image['type'], $this->allowedFileTypes)) {
            header("Content-Type: application/json");
            echo json_encode(array('status' => 'error', 'error_message' => 'Uploaded file is not of allowed type.'));
            exit();
        }

        $fileName = $_SESSION['user']['id'] . "_" . $image["name"];
        move_uploaded_file($image["tmp_name"], dirname(__DIR__) . "/public/uploads/" . $dirName . "/" . $fileName . "");
        return $fileName;
    }

    public function updateAvatar()
    {
        $this->authorizeUser();

        $image = $_FILES['picture-upload'];

        if (!isset($image)) {
            return http_response_code(500);
        }

        $avatar = $this->uploadImage($image, "avatars");
        $this->updateBean('users', $_SESSION['user']['id'], ['avatar' => $avatar]);
        $_SESSION['user']['avatar'] = '/uploads/avatars/' . $avatar;

        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success'));
    }

    public function updateBanner()
    {
        $this->authorizeUser();

        $image = $_FILES['picture-upload'];

        if (!isset($image)) {
            return http_response_code(500);
        }

        $banner = $this->uploadImage($image, "banners");
        $this->updateBean('users', $_SESSION['user']['id'], ['banner' => $banner]);

        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success'));
    }

    public function updatePasswordPost()
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        if (!isset($_POST['password']) || !isset($_POST['newPassword']) || !isset($_POST['confirmPassword'])) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Fill in all the fields.'));
            exit();
        }

        $password = $this->findOneBean('users', 'id', $_SESSION['user']['id'])['password'];
        if (!password_verify($_POST['password'], $password)) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Invalid password.'));
            exit();
        }

        if ($_POST['newPassword'] != $_POST['confirmPassword']) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Passwords did not match.'));
            exit();
        }

        if (!strlen($_POST['newPassword']) < $this::MIN_PASSWORD_LENGTH) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Password must be at least ' . $this::MIN_PASSWORD_LENGTH . ' characters.'));
            exit();
        }

        if (!strlen($_POST['confirmPassword']) > $this::MAX_PASSWORD_LENGTH) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Password should not exceed ' . $this::MAX_PASSWORD_LENGTH . ' characters.'));
            exit();
        }

        $this->updateBean('users', $_SESSION['user']['id'], ['password' => password_hash($_POST['newPassword'], PASSWORD_DEFAULT)]);
        echo json_encode(array('status' => 'success'));
    }

    public function updateEmailPost()
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        if (!isset($_POST['email']) || !isset($_POST['confirmEmail'])) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Fill in all the fields.'));
            exit();
        }

        if ($_POST['email'] != $_POST['confirmEmail']) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Emails did not match.'));
            exit();
        }

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Invalid email format.'));
            exit();
        }

        $this->updateBean('users', $_SESSION['user']['id'], ['email' => $_POST['email']]);
        echo json_encode(array('status' => 'success'));
    }

    public function follow($id)
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        if (!$id) {
            return http_response_code(500);
        }

        if ($id == $_SESSION['user']['id']) {
            echo json_encode(array('status' => 'error', 'error_message' => 'You cannot follow yourself.'));
            exit();
        }

        $target = $this->findOneBean('users', 'id', $id);

        if (!$target) {
            echo json_encode(array('status' => 'error', 'error_message' => 'User does not exist.'));
            exit();
        }

        $followers = json_decode($target['followers'], true);
        $shouldFollow = false;
        $key = array_search($_SESSION['user']['id'], $followers);

        if ($key !== false) {
            unset($followers[$key]);
        } else {
            array_push($followers, $_SESSION['user']['id']);
            $shouldFollow = true;
        }
        $this->updateBean('users', $id, ['followers' => json_encode($followers)]);

        $user = $this->findOneBean('users', 'id', $_SESSION['user']['id']);
        if (!$user) {
            return http_response_code(500);
        }

        $following = json_decode($user['following'], true);
        $key = array_search($id, $following);

        if ($shouldFollow) {
            array_push($following, $id);
        } elseif ($key !== false) {
            unset($following[$key]);
        }

        $this->updateBean('users', $_SESSION['user']['id'], ['following' => json_encode($following)]);

        echo json_encode(array('status' => 'success', 'follow' => $shouldFollow));
    }

    private function getFollowList($id, $queryFindString)
    {
        $users = json_decode($this->getFromBean('users', $queryFindString, 'id', $id)[$queryFindString], true);
        if (!$users) {
            return [];
        }

        $list = [];
        foreach ($users as $index => $userID) {
            $user = $this->findOneBean('users', 'id', $userID);
            if (!$user) {
                continue;
            }

            $list[$userID] = [
                'id' => $userID,
                'username' => $user['username'],
                'avatar' => $this->getAvatarUrl($user['avatar'])
            ];
        }

        return $list;
    }

    public function getFollowing($id)
    {
        $this->authorizeUser();
        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success', 'list' => $this->getFollowList($id, 'following')));
    }

    public function getFollowers($id)
    {
        $this->authorizeUser();
        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success', 'list' => $this->getFollowList($id, 'followers')));
    }

    public function getComments($id)
    {
        $this->authorizeUser();

        if (!isset($_POST['offset']) || !isset($_POST['amount'])) {
            return http_response_code(500);
        }

        $comments = $this->getFromBean('users', 'comments', 'id', $id)['comments'];
        if (!$comments) {
            return http_response_code(500);
        }

        $comments = json_decode($comments, true);
        $comments = array_reverse($comments);
        $comments = array_splice($comments, $_POST['offset'], $_POST['amount']);

        $comments_meta = $this->getCommentsMeta($comments);

        header("Content-Type: application/json");
        echo json_encode(array('status' => 'success', 'comments' => $comments, 'comments_meta' => $comments_meta));
    }

    public function postComment()
    {
        $this->authorizeUser();

        header("Content-Type: application/json");

        if (!isset($_POST['id']) || !isset($_POST['comment'])) {
            return http_response_code(500);
        }

        if (empty($_POST['comment'])) {
            echo json_encode(array('status' => 'error', 'error_message' => 'Comment is empty.'));
            exit();
        }

        $comments = $this->getFromBean('users', 'comments', 'id', $_POST['id'])['comments'];
        if (!$comments) {
            return http_response_code(500);
        }
        $comments = json_decode($comments, true);

        $comment = [];
        $comment['author'] = $_SESSION['user']['id'];
        $comment['comment'] = htmlspecialchars($_POST['comment']);
        $comment['date_posted'] = time();

        array_push($comments, $comment);

        $this->updateBean('users', $_POST['id'], ['comments' => json_encode($comments)]);

        echo json_encode(array('status' => 'success'));
    }
}
