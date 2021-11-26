<?php


namespace App\Repository;


use EasySwoole\FastCache\Cache;

class UserConnectInfoMap
{
    public static function user_set_fd_actor_id($user_id, $fd, $actor_id)
    {
        $cache = Cache::getInstance();
        $cache->set("player_{$user_id}_actor", $actor_id);
        $cache->set("player_{$user_id}_fd", $fd);

        $cache->set("fd_user_{$fd}", $user_id);
        $cache->set("fd_actor_{$fd}", $actor_id);
    }

    /**
     * user_id获取fd
     * @param $user_id
     * @return mixed|null
     */
    public static function user_get_fd($user_id)
    {
        return Cache::getInstance()->get("player_{$user_id}_fd");
    }

    /**
     * user_id获取actor_id
     * @param $user_id
     * @return mixed|null
     */
    public static function user_get_actor($user_id)
    {
        return Cache::getInstance()->get("player_{$user_id}_actor");
    }

    /**
     * fd获取玩家user_id
     * @param $fd
     * @return mixed|null
     */
    public static function fd_get_user($fd)
    {
        return Cache::getInstance()->get("fd_user_{$fd}");
    }

    /**
     * fd获取玩家actorId
     * @param $fd
     * @return mixed|null
     */
    public static function fd_get_actor($fd)
    {
        return Cache::getInstance()->get("fd_actor_{$fd}");
    }

    /**
     * 用户ID数组转换为FD数组
     * @param $user_list
     * @return array
     */
    public static function userList_to_fdList($user_list)
    {
        $return = [];
        foreach ($user_list as $user_id){
            $return[] = static::user_get_fd($user_id);
        }
        return $return;
    }


}