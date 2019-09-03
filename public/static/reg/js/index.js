$(function (){
    var dialog = window.YDUI.dialog;
    //切换页面
    /*function cutPage(page){
        if(page == 'pageA'){
            $('#pageA').css('display','none');
            $('#area').css('display','none');
            $('#pageA').css('display','block');
        }else if(page == 'pageB'){
            $('#pageA').css('display','none');
            $('#area').css('display','none');
            $('#pageB').css('display','block');
        }else if(page == 'area'){
            $('#pageA').css('display','none');
            $('#pageB').css('display','none');
            $('#area').css('display','block');
        }
    }*/
    //跳转到第一个页面
    /*$('.topagea').click(function(){
        cutPage('pageA');
    })*/

    //选择区号
    $('#gjm').click(function(){
        console.log('测试选择区号');
        return null;
        cutPage('area');
        if($('.area-main').children().length == 0){
            dialog.loading.open();
            //ajax发送
            $.ajax({
                type: "post",
                dataType: "json",
                url: mem_url + 'user/index/getMobileArea',
                data:{
                    lang:'cn',
                    project:'WSEC'
                },
                success: function(res) {
                    console.log(res)
                    dialog.loading.close();
                if(res.code == 1){
                    let info = res.data;
                    let dom = '';
                    info.forEach(function(item,index){
                        dom += `<div class="code-box">
                                    <div class="code-name">${item.name}</div>
                                    <div class="code-key">${item.num}</div>
                                </div>`;
                    })
                    $('.area-main').append(dom);
                }
                
                
                },
                error: function(res) {
                    
                }
            }); 
            
        }
        $(document).on('click','.code-box',function(){
            var code = $(this).find('.code-key').text();
            cutPage('pageA');
            $('.gjm-code').text(code)
        })


    })

    //发送验证码
    var $getCode = $('#J_GetCode');
    $getCode.sendCode({
        disClass: 'btn-disabled', // 禁用按钮样式【必填】
        secs: 60, // 倒计时时长 [可选，默认：60秒]
        run: false,// 是否初始化自动运行 [可选，默认：false]
        runStr: '- {%s} s',// 倒计时显示文本 [可选，默认：58秒后重新获取]
        resetStr: 'Get Code'// 倒计时结束后按钮显示文本 [可选，默认：重新获取验证码]
    });
    $getCode.on('click', function () {
        var phone = $('.phone').val();
        var area = $('.gjm-code').text();
        if(phone === ''){
            dialog.toast('手机号为空！', 'error', 1000);
            return;
        }
        if(area === '86'){
            area = '1086'
        }else{
            area = '00' + area
        }
        
        var $this = $(this);
        dialog.loading.open('发送中...');
        $.ajax({
            type: "post",
            dataType: "json",
            url: zing_url + 'user/user/sendMsg',
            data:{
                mobile: phone,
                num: area,
                type:'reg',
            },
            success: function(res) {
                console.log(res)
                dialog.loading.close();
                $this.sendCode('start');
                if(res.code == 1){
                    dialog.toast(res.msg, 'success', 1500);
                }else{
                    dialog.toast(res.msg, 'error', 1500);
                }
            
            },
            error: function(res) {
                dialog.toast('发送失败', 'error', 1500);
            }
        });
    });

    //点击下一步
    $('.next-step').click(function(){
        let mobile = $('.phone').val(),
        code = $('.input-code').val(),
        password = $('.password').val(),
        rePassword = $('.rePassword').val(),
        invitation = $('.invitation').val();
      
        if(mobile == ''){
            dialog.toast('手机号为空', 'error', 1000);
            return;
        }
        if(code == ''){
            dialog.toast('请输入验证码', 'error', 1000);
            return;
        }
        if(password == ''){
            dialog.toast('请输入密码', 'error', 1000);
            return;
        }
        
        if(rePassword == ''){
            dialog.toast('请再次确认密码', 'error', 1000);
            return;
        }
        if(password !== rePassword){
            dialog.toast('两次密码不同', 'error', 1000);
            return;
        }
        if(invitation == ''){
            dialog.toast('请填写邀请人', 'error', 1000);
            return;
        }
        cutPage('pageB');

    })


    //勾选用户协议
    $('.dagou').click(function(){
        var that = $(this),
        sign = $(this).attr('data-sign');
        if(sign == '1'){
            that.css({
                'background':'none'
                
            }).attr('data-sign','0');
        }else{
            that.css({
                'background':'url(/static/reg/image/gou.png)',
                'background-size':'contain',
            }).attr('data-sign','1');
        }
    })

    //提交
    $('.submit').click(function(){
        let trade_pwd = $('.trade_pwd').val(),
        re_trade_pwd = $('.re_trade_pwd').val(),
        gou = $('.dagou').attr('data-sign');
        let mobile = $('.phone').val(),
        code = $('.input-code').val(),
        password = $('.password').val(),
        rePassword = $('.rePassword').val(),
        invitation = $('.invitation').val();
      
        if(mobile == ''){
            dialog.toast('手机号为空', 'error', 1000);
            return;
        }
        if(code == ''){
            dialog.toast('请输入验证码', 'error', 1000);
            return;
        }
        if(password == ''){
            dialog.toast('请输入密码', 'error', 1000);
            return;
        }
        
        if(rePassword == ''){
            dialog.toast('请再次确认密码', 'error', 1000);
            return;
        }
        if(password !== rePassword){
            dialog.toast('两次密码不同', 'error', 1000);
            return;
        }
        if(invitation == ''){
            dialog.toast('请填写邀请人', 'error', 1000);
            return;
        }
        if(trade_pwd == ''){
            dialog.toast('请输入交易密码', 'error', 1000);
            return;
        }
        if(re_trade_pwd == ''){
            dialog.toast('请确认交易密码', 'error', 1000);
            return;
        }
        if(re_trade_pwd !== trade_pwd){
            dialog.toast('两次交易密码不同', 'error', 1000);
            return;
        }
        if(gou != 1){
            dialog.toast('请同意', 'error', 1000);
            return;
        }
        
        let signinfo = {
            mobile: $('.phone').val(),
            password:$('.password').val(),
            rePassword:$('.rePassword').val(),
            project:'WSEC',
            code:$('.input-code').val(),
            pid:$('.invitation').val(),
            lang:'cn',
            trade_pwd:trade_pwd,
            re_trade_pwd:re_trade_pwd,
        }
        
        //提交信息
        dialog.loading.open('正在注册');
        $.ajax({
            type: "post",
            dataType: "json",
            url: mem_url + 'user/index/register',
            data:signinfo,
            success: function(res) {
                if(res.code == 1){
                    dialog.loading.close();
                    dialog.toast('注册成功！正在跳转下载页', 'success', 1000,function(){
                        window.location.href = 'http://120.78.141.142:10002/WSEC.apk'
                    });
                }else{
                    dialog.loading.close();
                    dialog.toast(res.msg, 'error', 1000);
                }
            },
            error: function(res) {
                dialog.toast('注册失败', 'error', 1500);
            }
        });
})




})