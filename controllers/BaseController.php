<?php

use RedBeanPHP\R as R;

class BaseController
{
    public const DEFAULT_AVATAR = '/images/user.png';
    public const DEFAULT_BANNER = '/images/cover-default.png';
    public const AVATAR_UPLOAD_PATH = '/uploads/avatars/';
    public const BANNER_UPLOAD_PATH = '/uploads/banners/';

    public function authorizeUser()
    {
        if (empty($_SESSION['user'])) {
            header('Location: /user/login');
            exit();
        }
    }

    public function getAvatar(string $id)
    {
        $avatar = $this->findOneBean('users', 'id', $id)['avatar'];
        return $avatar ? $this::AVATAR_UPLOAD_PATH . $avatar : $this::DEFAULT_AVATAR;
    }

    public function getAvatarUrl(string $image)
    {
        return $image ? $this::AVATAR_UPLOAD_PATH . $image : $this::DEFAULT_AVATAR;
    }

    public function getBannerUrl(string $image)
    {
        return $image ? $this::BANNER_UPLOAD_PATH . $image : $this::DEFAULT_BANNER;
    }

    public function getCommentsMeta(array $comments)
    {
        $comments_meta = [];
        foreach ($comments as $comment) {
            $author = $this->getBeanById('users', $comment['author']);
            if (!$author || isset($comments_meta[$author['id']])) {
                continue;
            }
            $comments_meta[$author['id']] = [];
            $comments_meta[$author['id']]['username'] = $author['username'];
            $comments_meta[$author['id']]['avatar'] = $this->getAvatarUrl($author['avatar']);
        }
        return $comments_meta;
    }

    public function getBeans(string $queryStringKey)
    {
        return R::getAll('SELECT * FROM ' . $queryStringKey);
    }

    public function findFromBeansLike(string $typeOfBean, string $querySelectString, $queryFindString, string $queryMatchString)
    {
        return R::getAll('SELECT ' . $querySelectString . ' FROM ' . $typeOfBean . ' WHERE ' . $queryFindString . ' LIKE ?', ['%' . $queryMatchString . '%']);
    }

    public function getFromBean(string $typeOfBean, string $querySelectString, $queryFindString, string $queryMatchString)
    {
        return R::getRow('SELECT ' . $querySelectString . ' FROM ' . $typeOfBean . ' WHERE ' . $queryFindString . ' = ?', [$queryMatchString]);
    }

    public function findOneBean(string $typeOfBean, string $queryColumn, string $queryStringKey)
    {
        return R::findOne($typeOfBean, $queryColumn . ' = ?', [$queryStringKey]);
    }

    public function findBeans(string $typeOfBean, string $querySelectString, string $queryStringKey)
    {
        return R::findAll($typeOfBean, 'WHERE ' . $querySelectString . ' = ?', [$queryStringKey]);
    }

    public function getBeanById(string $typeOfBean, string $queryStringKey)
    {
        if (!$queryStringKey) {
            error(404, 'No ID specified');
            exit();
        }
        $bean = R::findOne($typeOfBean, 'id = ?', [$queryStringKey]);
        return $bean ?? error(404, 'No ' . $typeOfBean . ' with ID ' . $queryStringKey . ' found');
    }

    public function insertBean(string $typeOfBean, array $queryValues)
    {
        $bean = R::dispense($typeOfBean);
        foreach ($queryValues as $index => $value) {
            $bean->$index = $value;
        }
        R::store($bean);

        return R::getInsertID();
    }

    public function updateBean(string $typeOfBean, string $queryStringKey, array $queryValues)
    {
        $bean = R::findOne($typeOfBean, 'id = ?', [$queryStringKey]);
        foreach ($queryValues as $index => $value) {
            $bean->$index = $value;
        }
        R::store($bean);
    }

    public function getBeansFromTo(string $typeOfBean, string $orderBy, int $from, int $to)
    {
        return R::findFromSQL($typeOfBean, " SELECT * FROM {$typeOfBean} ORDER BY {$orderBy} DESC LIMIT {$to} OFFSET {$from} ");
    }
}
