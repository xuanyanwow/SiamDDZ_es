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

    /**
     * 获取房间状态
     * @Param(name="roomId",from={GET,POST},notEmpty="不能为空")
     */
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

    /**
     * 加入房间
     * @Param(name="roomId",from={GET,POST},notEmpty="不能为空")
     */
    public function join()
    {

    }

    // ***************** 以下接口，从用户信息中获取所在房间信息

    /**
     * 退出房间
     */
    public function quit()
    {

    }

    /**
     * 准备开始
     */
    public function preStart()
    {

    }

    /**
     * 取消准备开始
     */
    public function cancelStart()
    {

    }

    /**
     * 叫地主操作
     * @Param(name="result",from={GET,POST},notEmpty="不能为空")
     */
    public function callRich()
    {

    }

    /**
     * 出牌操作
     * @Param(name="cards",from={GET,POST},notEmpty="不能为空")
     */
    public function sendCard()
    {

    }
}