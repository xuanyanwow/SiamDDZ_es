function message_parser(job)
{
    let function_name = job.do;
    if (function_name!==undefined){
        console.log(function_name)
        eval(function_name+'(job.data)');
    }
}

function game_get_info(data){
    vue_app.room_status = data.room_status;
    vue_app.player_info_list = data.player_info_list
}
function do_prepare(data)
{
    console.log("玩家准备");
    if (data.user_id == vue_app.userId){
        vue_app.room_status = "wait_start";
    }

    let player_info_list = vue_app.player_info_list;
    for (let i = 0; i < player_info_list.length; i++) {
        if(player_info_list[i].user_id !== data.user_id){
            continue;
        }
        player_info_list[i].status_text = data.result ? "准备" : "";
    }
    vue_app.player_info_list = player_info_list;

}
function game_start(data){
    console.log("游戏开始")

    let player_info_list = vue_app.player_info_list;
    for (let i = 0; i < player_info_list.length; i++) {
        player_info_list[i].status_text = "";
    }
    vue_app.player_info_list = player_info_list;
}

function get_card(data)
{
    console.log("获得牌")
    console.log(data);
    vue_app.card_list = data;
}

function call_rich(data)
{
    console.log("轮到玩家叫地主")
    vue_app.room_status = "call_rich";
    vue_app.now_player = data.userId;
    _tick_time(data.endTime);
}

function change_multiple(data)
{
    console.log("倍数改变")
    console.log(data);
    vue_app.multiple = data.multiple
}

function player_index(user_id)
{
    let player_info_list = vue_app.player_info_list;
    for (let i = 0; i < player_info_list.length; i++) {
        if (player_info_list[i].user_id === user_id) return i;
    }
}

function player_pass_card(data)
{
    let player_info_list = vue_app.player_info_list;
    player_info_list[player_index(data.user_id)].status_text = "不出";
    vue_app.player_info_list = player_info_list;
}

function change_rich(data)
{
    console.log("地主角色已改变")
    let user_id = data.user_id;
    // 把客户端用户的角色分配一下
    let player_list = vue_app.player_info_list;
    for (const playerListKey in player_list) {
        let player = player_list[playerListKey];
        if (player.user_id == user_id){
            player.role = "地主";
            player.card_number =player.card_number+3;
        }else{
            player.role = "农民"
        }
        player_list[playerListKey] = player;
    }
    vue_app.player_info_list = player_list;

}

function show_rich_card(data)
{
    console.log("展示地主牌")
    vue_app.rich_card_list = data.card;
}

function change_player_use_card(data)
{
    console.log("轮到谁出牌")
    vue_app.now_player = data.user_id;
    vue_app.room_status = "use_card";
    vue_app.can_pass  = data.can_pass;
    // 把出牌映射关掉
    let player_use_card_map = vue_app.player_use_card_map;
    player_use_card_map[data.user_id] = [];
    vue_app.player_use_card_map = player_use_card_map;

    // 提示关掉
    let player_info_list = vue_app.player_info_list;
    player_info_list[player_index(data.user_id)].status_text = "";
    vue_app.player_info_list = player_info_list;


    _tick_time(data.endTime);
}
// 玩家出牌
function player_use_card(data){
    // 是自己则减去操作牌
    // 不是自己则添加记录 减去玩家剩余牌的数量
    if (data.user_id == vue_app.userId){
        let temp_card_list = vue_app.card_list;
        vue_app.card_list = array_dive(temp_card_list, data.card_array);
        vue_app.check_card_list = [];
    }

    let temp_player_list = vue_app.player_info_list;
    for (const tempPlayerListKey in temp_player_list) {
        let player = temp_player_list[tempPlayerListKey];
        if (player.user_id == data.user_id){
            player.card_number =player.card_number-data.card_array.length;
        }
        temp_player_list[tempPlayerListKey] = player;
    }
    vue_app.player_info_list = temp_player_list;

    let player_use_card_map = vue_app.player_use_card_map;
    player_use_card_map[data.user_id] = data.card_array;
    vue_app.player_use_card_map = player_use_card_map;

}

function game_notice(data){
    vue_app.$alert(data.msg, '提示', {
        confirmButtonText: '确定',
    });
}

function settle(data){
    // TODO 计分板
    vue_app.$alert(JSON.stringify(data), '游戏结束', {
        confirmButtonText: '确定',
    });
}



// 辅助函数
let tick_id;
function _tick_time(endTime)
{
    _tick_clear();
    vue_app.end_time = endTime;
    tick_id = setInterval(function(){
        if (vue_app.end_time <= 0){
            clearInterval(tick_id);
            // 超时处理
        }else{
            vue_app.end_time = vue_app.end_time-1;
        }
    }, 1000);
}
function _tick_clear()
{
    clearInterval(tick_id);
}