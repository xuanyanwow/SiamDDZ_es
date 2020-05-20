# 客户端->服务端

加入房间
{"class":"Game","action":"joinRoom","content":{"roomId":1}}

退出房间
{"class":"Game","action":"quitRoom","content":{"roomId":1}}

叫地主
{"class":"Game","action":"callLandLoad","content":{"result":true}}

{"class":"Game","action":"callLandLoad","content":{"result":false}}

出牌

过牌

# 服务端->客户端

游戏开始
{"class":"game","action":"start","data":null}

发牌
{"class":"game","action":"send_card","data":["H3","H5","C6","D6","S6","D7","C9","D10","H10","C11","C13","D13","H13","H14","S14","D15","W17"]}

通知叫地主，isMe，轮到谁叫
{"class":"game","action":"call_landload","data":{"isMe":false}}