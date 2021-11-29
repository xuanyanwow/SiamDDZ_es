function getQueryVariable(variable)
{
    let query = window.location.search.substring(1);
    let vars = query.split("&");
    for (let i=0;i<vars.length;i++) {
        let pair = vars[i].split("=");
        if(pair[0] === variable){return pair[1];}
    }
    return false;
}

function pack(controller,action,content){
    return JSON.stringify({
        class:controller,
        action:action,
        content:content,
    });
}
function array_dive(aArr,bArr){ //第一个数组减去第二个数组
    if(bArr.length===0){return aArr}
    let diff=[];
    let str=bArr.join("&quot;&quot;");
    for(let e in aArr){
        if(str.indexOf(aArr[e])===-1){
            diff.push(aArr[e]);
        }
    }
    return diff;
}
function in_array(needle,array,bool){
    if(typeof needle=="string"||typeof needle=="number"){
        for(let i in array){
            if(needle===array[i]){
                if(bool){
                    return i;
                }
                return true;
            }
        }
        return false;
    }
}