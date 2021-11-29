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
            now_player: '',
            end_time:32,
            room_status:"wait_prepare",
            can_pass:true,

            // 房间信息
            multiple:1,
            rich_card_list:[],
            player_use_card_map:{},
        };
    },
    mounted :function(){
        // 弹框 输入用户名和房间号  连接websocket  加载房间信息
        this.roomId = getQueryVariable("roomId") + "";
        this.userId = getQueryVariable("userId") + "";
        this.now_player = this.userId;
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
        do_prepare(result)
        {
            this.send(pack('game','do_prepare',{
                result:result,
                roomId:this.roomId
            }));
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
            return "" + card;
        },

        _auto_poker_card_style(i,card)
        {
            // 总共20个位置，智能居中，如果18张牌，则空1个牌   16张牌，则空2个牌
            // (20 - pokerLenth) / 2   20-18 / 2 = 1

            let left_i = (Math.floor((20-this.card_list.length) / 2) + i ) * 42
            let return_data = {
                left: left_i+'px'
            };
            // 判断是否要弹出
            let check_card_list_temp = this.check_card_list;
            if (in_array(card,check_card_list_temp, true)){
                return_data.bottom = '25px'
            }
            return return_data
        },
        _check_porker_card(card){
            // 交换这个牌的位置
            let check_card_list_temp = this.check_card_list;
            let has = in_array(card,check_card_list_temp, true);
            if (!has){
                check_card_list_temp.push(card);
            }else{
                check_card_list_temp.splice(parseInt(has), 1);
            }
        },
        _get_avtor(role)
        {
            if (!!role && role === "地主"){
                return "/static/dizhu.png";
            }
            return "/static/nonmin.jpg";
        },
        _render_poker(card){
            // 根据第一个字母 返回img
            let i = card.slice(0,1);
            let number = card.substr(1);
            if (parseInt(number) === 11){
                number = "J";
            }
            if (parseInt(number) === 12){
                number = "Q";
            }
            if (parseInt(number) === 13){
                number = "K";
            }
            if (parseInt(number) === 14){
                number = "A";
            }
            if (parseInt(number) === 15){
                number = "2";
            }

            if (parseInt(number) === 16){
                if (i === "H") return  `<img class="poker_icon" src='/static/small_gui.png'/>`;
                if (i === "C") return  `<img class="poker_icon" src='/static/big_gui.png'/>`;
            }


            let i_img = `<img class="poker_icon" src='/static/${i}.png'/>`;

            return i_img + number;
        }
    },
    computed:{
        // 玩家自己是一号位，则左边是三号，右边是二号
        // 玩家自己是二号位，则左边是一号，右边是三号
        // 玩家自己是三号位，则左边是二号，右边是一号
        self_player()
        {
            return this.player_info_list[this.self_player_index]
        },
        self_player_index(){
            let user_id_array = [];
            for (const key in this.player_info_list) {
                let player = this.player_info_list[key];
                user_id_array.push(player.user_id);
            }

            return user_id_array.indexOf(this.userId);
        },
        left_player()
        {
            switch (this.self_player_index){
                case 0:
                    if (!!this.player_info_list[2]) return this.player_info_list[2];
                    return false;
                case 1:
                    if (!!this.player_info_list[0]) return this.player_info_list[0];
                    return false;
                case 2:
                    if (!!this.player_info_list[1]) return this.player_info_list[1];
                    return false;
            }
            return false;
        },
        right_player()
        {
            switch (this.self_player_index){
                case 0:
                    if (!!this.player_info_list[1]) return this.player_info_list[1];
                    return false;
                case 1:
                    if (!!this.player_info_list[2]) return this.player_info_list[2];
                    return false;
                case 2:
                    if (!!this.player_info_list[0]) return this.player_info_list[0];
                    return false;
            }
            return false;
        },
    }

})