<?php
namespace firegit\app\ctl\user;

const OPEN_API_TOKEN = '29ee4a7083703f8cc40e8b88b73258c0';
class Controller extends \firegit\http\Controller
{
    function login_action()
    {
        $token = $this->get('token');
        if ($token) {
            $userId = $this->get('user_id');
            $username = $this->get('username');
            $time = $this->get('time');
            $token = $this->get('token');

            if (time() - $time > 60) {
                throw new Exception('auth.expired');
            }

            $vtoken = sha1(sprintf('%s,%s,%s,%s', $userId, $username, $time, OPEN_API_TOKEN));
            if ($vtoken != $token) {
                throw new Exception('auth.failed');
            }

            $user = new \firegit\app\mod\user\Grant();
            $cookieValue = $user->packSession($username);
            setcookie('fuser', $cookieValue, null, '/');

            $this->response->redirect(isset($_COOKIE['rurl']) ? $_COOKIE['rurl'] : '/');
        }

        $u = $this->get('u');
        setcookie('rurl', $u, null, '/');

        $this->setLayout('layout/common.phtml')
            ->setView('user/login.phtml');
    }

    function logout_action()
    {
        setcookie('fuser', null, 0, '/');
        $this->response->redirect('http://dev.hapn.cc/user/logout?u=http://'.$this->request->host.'/user/login');
    }
}