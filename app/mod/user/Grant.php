<?php
namespace firegit\app\mod\user;

final class Grant
{
    private $sessionMask = 'KX3v8S01r/psessionwxDnz3SFNqJqUXkjF/gE/X78s=';

    /**
     * 通过cookie获取用户信息
     * @param string $cookieValue
     * @return array
     *  <code>array(
     *  'user_id',
     *  'username',
     *  )</code>
     */
    function getUserFromCookie($cookieValue)
    {
        $info = $this->unpackSession($cookieValue);
        if (!$info) {
            return false;
        }
        try {
            @list($uname, $expire) = $info;
        } catch (\Exception $ex) {
            return false;
        }

        if ($expire < time()) {
            return false;
        }

        return array(
            'username' => $uname,
        );
    }

    /**
     * 数据掩码
     * @param string $data
     * @return string  掩码
     */
    private function _mask($data)
    {
        $md5 = md5($this->sessionMask, true);
        $len = strlen($data);

        $result = '';

        $i = 0;
        while ($i < $len) {
            $j = 0;
            while ($i < $len && $j < 16) {
                $result .= $data[$i] ^ $md5[$j];

                $i++;
                $j++;
            }
        }
        return $result;
    }

    /**
     * 加密登录信息
     * @param string $uname 用户昵称
     * @param  bool [$iskeep=false] 是否记住登录
     * @throws user.u_uidIllegal    用户ID非法
     * @throws user.u_unameIllegal    用户名非法
     * @return string  加密后的session
     */
    public function packSession($uname, $isKeep = false)
    {
        $expire = $isKeep ? 24 * 3600 * 21 : 1 * 3600;
        $arr = array(
            $uname,
            time() + $expire
        );

        $str = json_encode($arr, JSON_UNESCAPED_UNICODE);
        $str .= "&" . md5($str);
        return base64_encode($this->_mask($str));
    }

    /**
     * 解密登录cookie
     * */
    private function unpackSession($session)
    {
        $data_session = base64_decode($session);
        $data_session = $this->_mask($data_session);

        $arr = explode('&', $data_session);

        if (count($arr) != 2) {
            trigger_error('user\'s session data error of UserImpl.unpackSession', E_USER_WARNING);
            return false;
        }
        if (md5($arr[0]) !== $arr[1]) {
            trigger_error('user\'s session data checked failed', E_USER_WARNING);
            return false;
        }

        $data = @json_decode($arr[0], true);
        if (!$data) {
            trigger_error('user\'s session data illegal', E_USER_WARNING);
            return false;
        }

        return $data;
    }
}