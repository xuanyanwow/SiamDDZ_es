<?php
/**
 * 游戏房间的接口
 * User: Siam
 * Date: 2020/5/28
 * Time: 16:51
 */

namespace App\HttpController;


use EasySwoole\FastCache\Cache;

class Room extends Base
{
    public function getInfo()
    {
        // 房间id
        $roomId      = $this->request()->getRequestParam("roomId");
        $roomActorId = Cache::getInstance()->get("room_{$roomId}");
        if (!$roomActorId) {
            $this->response()->write("房间不存在");
            return;
        }
        $this->response()->write('ok');
    }
}