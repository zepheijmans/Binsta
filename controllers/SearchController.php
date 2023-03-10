<?php

class SearchController extends BaseController
{
    public function index($search)
    {
        $this->authorizeUser();

        if (!$search) {
            $users = $this->getBeans('users');
        } else {
            $users = $this->findFromBeansLike('users', 'id, username, fullname, bio, avatar, banner, following, followers, date_joined', 'username', $search);
        }

        foreach ($users as $index => $user) {
            if ($user['id'] == $_SESSION['user']['id']) {
                unset($users[$index]);
                continue;
            }

            $users[$index]['followers'] = json_decode($user['followers'], true);
            $users[$index]['following'] = json_decode($user['following'], true);
            $users[$index]['follow'] = in_array($_SESSION['user']['id'], $users[$index]['followers']);
            $users[$index]['followedby'] = in_array($_SESSION['user']['id'], $users[$index]['following']);
            $users[$index]['banner'] = $this->getBannerUrl($user['banner']);
            $users[$index]['banner_is_custom'] = $users[$index]['banner'] != $this::DEFAULT_BANNER;
        }

        displayTemplate('search.twig', ['users' => $users]);
    }
}
