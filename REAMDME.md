# Sima 斗地主 服务端部分

主要逻辑说明：

Websocket只可以操作PlayerActor 这是权限原则

## 顺序说明

Websocket (Services 判断是否合法操作)

-> PlayerActor(只做转发)

-> RoomActor(通知其他成员改变属性)

-> PlayerActor(改变属性 或者失败提示)

-> Websocket 回复

-> UI做动画