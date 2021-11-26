let vue_app = new Vue({
    el: '#app',
    data: function() {
        return {
            check_card_list: [],
            card_list: [],

            path:"ws://43.255.28.204:9504/?user_id="+getQueryVariable("userId"),
            socket:null,
            roomId:null,
            userId:null,
            log_list:"",

            player_info_list:[
                {
                    user_id: "1",
                    role:"",
                    card_number:13,
                },
                {
                    user_id: "2",
                    role:"",
                    card_number:0,
                },
                {
                    user_id: "3",
                    role:"",
                    card_number:0,
                }
            ],
            now_player: "1",
            end_time:32,
            room_status:"wait_start",
            can_pass:true,

            // 房间信息
            multiple:1,
            rich_card_list:[],
        };
    },
    mounted :function(){
        // 弹框 输入用户名和房间号  连接websocket  加载房间信息
        this.roomId = getQueryVariable("roomId") + "";
        this.userId = getQueryVariable("userId") + "";
        this.init();
    },
    destroyed () {
        // 销毁监听
        this.socket.onclose = this.close
    },
    methods:{
        init:function(){
            // 实例化socket
            this.socket = new WebSocket(this.path)
            // 监听socket连接
            this.socket.onopen = this.event_open
            // 监听socket错误信息
            this.socket.onerror = this.event_error
            // 监听socket消息
            this.socket.onmessage = this.event_message
        },
        event_open: function () {
            this.join_room();
        },
        event_error: function () {
            console.log("连接错误")
        },
        event_message: function (msg) {
            this.log_list = this.log_list + msg.data+"\n\n";
            // 解析，调用控制器来完成
            let temp = JSON.parse(msg.data);
            if(Array.isArray(temp) === false) {
                temp = [temp]
            }
            for (let i = 0; i < temp.length; i++) {
                message_parser(temp[i]);
            }
        },
        send: function (params) {
            this.socket.send(params)
        },
        close: function () {
            console.log("socket已经关闭")
        },
        join_room()
        {
            this.send(pack('game','joinRoom',{
                roomId:this.roomId
            }));
        },
        tick_end_time(time)
        {
            return time;
        },
        do_call_rich(result)
        {
            // 暂停倒计时
            _tick_clear();
            this.send(pack('game','call_rich',{
                result:result,
                roomId:this.roomId
            }));
        },
        do_pass_card()
        {
            this.send(pack('game','pass_card',{
                roomId:this.roomId
            }));
        },
        do_use_card()
        {
            this.send(pack('game','use_card',{
                roomId:this.roomId,
                card_list: JSON.stringify(this.check_card_list)
            }));
        },
        show_card(card)
        {
            return "<img src='https://z3.ax1x.com/2021/11/26/oVMxVs.png'/>" + card;
        }
    }

})