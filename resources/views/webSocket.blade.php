<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>公共聊天室</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toastr.css') }}">
    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/toastr.js') }}"></script>
    <script src="{{ asset('js/emoji_jQuery.min.js') }}"></script>
</head>

<body>
    <div class="container mt-3">
        <div class="row">
            <div class="col-md-9 text-center">
                <h4>聊天室</h4>
            </div>
            <div class="offset-md-1 col-md-2 text-center">
                <h4>在线成员</h4>
            </div>
        </div>

        <div class="row" style="height: 450px;">
            <div class="col-md-9 border border-primary rounded chat" style="height: 450px; overflow: auto">
                <div class="mt-3"></div>

                

            </div>
            <div class="offset-md-1 col-md-2 border border-success rounded" style="height: 450px; overflow: auto">
                <div id="list-example" class="list-group text-center">
                    <div class="mt-3"></div>
                    <!-- <a class="list-group-item list-group-item-action text-primary he">Item 1</a>
                    <a class="list-group-item list-group-item-action text-primary he">Item 2</a>
                    <a class="list-group-item list-group-item-action text-success me">Item 3</a> -->

                </div>
            </div>
        </div>


        <div class="row">
            <div class="col-md-9">
                <form method="get" action="">

                    <div class="input-group mt-3">

                        <div class="btn-group" role="group">
                            <button id="btnGroupDrop1" type="button" fd="0" class="btn btn-outline-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            全部
                            </button>
                            <div class="dropdown-menu" aria-labelledby="btnGroupDrop1" id="list">
                                <a class="dropdown-item sel" href="javascript:;" fd="0">全部</a>
                                <a class="dropdown-item sel" href="javascript:;" fd="1">用户1</a>
                                <a class="dropdown-item sel" href="javascript:;" fd="2">用户2</a>
                            </div>
                        </div>

                        <textarea class="form-control" id="message" rows="2" style="resize:none"></textarea>

                        <div class="input-group-append">
                            <button class="btn btn-outline-info" type="button" id="emoji">表情</button>
                            <button class="btn btn-outline-success" type="button" id="button-addon2">发送</button>
                            
                        </div>
                    </div>
                    <input type="hidden" name="fd" id="fd">
                </form>
            </div>
        </div>

        <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">请登录后进入聊天室</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="form-group">
                                <label for="username" class="col-form-label">账号：</label>
                                <input type="text" class="form-control" id="username" value="lstink">
                            </div>
                            <div class="form-group">
                                <label for="pwd" class="col-form-label">密码：</label>
                                <input type="password" class="form-control" id="pwd" value="123456">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                        <button type="button" class="btn btn-primary sub">登陆</button>
                    </div>
                </div>
            </div>
        </div>


    </div>

</body>

</html>
<script>
    $(function() {
        //弹窗配置
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "positionClass": "toast-top-right",
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "1000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        }

        //表情配置
        $.Lemoji({
            emojiInput: '#message',
            emojiBtn: '#emoji',
            position: 'LEFTTOP',
            length: 8,
            emojis: {
                qq: {path: '/static/images/qq/', code: ':', name: 'QQ表情'},
                tieba: {path: '/static/images/tieba', code: ';', name: "贴吧表情"},
                emoji: {path: '/static/images/emoji', code: ',', name: 'Emoji表情'}
            }
        });


        var wsServer = '{{ env('CHAT_HOST') }}';
        var websocket = new WebSocket(wsServer);
        websocket.onopen = function(evt) {
            console.log("Connected to WebSocket server.");
        };

        websocket.onclose = function(evt) {
            console.log("Disconnected");
        };

        websocket.onmessage = function(evt) {
            var data = JSON.parse(evt.data);
            // console.log(evt.data);
            switch (data.action) {
                case 'login':
                    doLogin(data);
                    break;
                case 'userList':
                    userList(data);
                    break;
                case 'fd':
                    fd(data);
                    break;
                case 'out':
                    out(data);
                    break;
                case 'chatOnPublic':
                    chatOnPublic(data);
                    break;
                case 'chatOnPrivate':
                    chatOnPrivate(data);
                    break;
            
                default:
                    break;
            }
            // console.log(data.action);
            // console.log('Retrieved data from server: ' + evt.data);
        };

        websocket.onerror = function(evt, e) {
            toastr.error('网络已断开，请重新登录');
            // console.log('Error occured: ' + evt.data);
        };
        //发送数据
        $('#button-addon2').click(function() {
            sendMessage();
        });

        //回车键的处理
        $('#message').bind('keypress',function(){
            if (event.keyCode == '13') {
                sendMessage();
                return false;
            }else{
                return true;
            }
        });

        //聊天信息的发送
        function sendMessage()
        {
            //获取文本框内的值
            var value = $('#message').val();
            //非空判断
            if (value == '') {
                return false;
            }
            //获取当前用户的fd
            var fd = $('#fd').val();
            //获取发送对象的fd
            var to_fd = $('#btnGroupDrop1').attr('fd');
            if (to_fd == 0) {
                var json = '{"message":"'+value+'","fd":"'+fd+'","action":"chatOnPublic"}';
            }else{
                var json = '{"message":"'+value+'","fd":"'+fd+'","action":"chatOnPrivate","to_fd":"'+to_fd+'"}';
            }
            websocket.send(json);
        }

        //展示聊天记录
        function showMessageType(type,data)
        {
            if (type == 'me') {
                //判断是否为私密聊天
                if (data.type == 'private') {
                    var content = '<figcaption class="figure-caption text-right ml-1"><span>'+data.user.username+'</span> <span class="text-warning">发送给<span class="text-danger">'+data.user.username+'</span>的私密消息</span> '+data.time+'</figcaption><div class="alert alert-success w-75 offset-md-3" role="alert">'+data.data+'</div>';
                }else{
                    var content = '<figcaption class="figure-caption text-right ml-1"><span>'+data.user.username+'</span> '+data.time+'</figcaption><div class="alert alert-success w-75 offset-md-3" role="alert">'+data.data+'</div>';
                }
            }else{
                //判断是否为私密聊天
                if (data.type == 'private') {
                    //私密聊天
                    var content = '<figcaption class="figure-caption text-left ml-1"><span>'+data.user.username+'</span> <span class="text-warning">您收到一条<span class="text-danger">'+data.user.username+'</span>的私密消息</span> '+data.time+'</figcaption><div class="alert alert-primary w-75" role="alert">'+data.data+'</div>';
                }else{
                    var content = '<figcaption class="figure-caption text-left ml-1"><span>'+data.user.username+'</span> '+data.time+'</figcaption><div class="alert alert-primary w-75" role="alert">'+data.data+'</div>';
                }
            }
            //表情解析
            content = $.emojiParse({
                content: content,
                emojis: [{type: 'qq', path: '/static/images/qq/', code: ':'}, {
                    path: '/static/images/tieba/',
                    code: ';',
                    type: 'tieba'
                }, {path: '/static/images/emoji/', code: ',', type: 'emoji'}]
            });
            $('.chat').append(content);
            //滚动到底部
            $(".chat").scrollTop($(".chat")[0].scrollHeight);
            //清空内容
            $('#message').val('')
        }

        var login = false;
        if (!login) {
            $('#exampleModal').modal('show');
        }

        //未登录强制登陆
        $('#exampleModal').on('hide.bs.modal', function(event) {
            if (!login) {
                return false;
            }
        })

        //提交的点击事件
        $('.sub').click(function(){
            var username = $('#username').val();
            var pwd = $('#pwd').val();
            if (username == '' || pwd == '') {
                return ;
            }
            var json = '{"username":"'+username+'","pwd":"'+pwd+'","action":"login"}';
            //发送数据
            websocket.send(json);
            
        });

        

        //处理登陆方法
        function doLogin(data)
        {
            if (data.code == 200) {
                login = true;
                $('#exampleModal').modal('hide');
                $('.me').text(data.data);
            }else{
                toastr.error(data.msg);
            }
            
        }
        //处理用户列表
        function userList(data)
        {
            //获取当前用户的fd
            var fd = $('#fd').val();
            refreshUserList(data);
            //弹出登录用户
            if (fd != data.user.fd) {
                toastr.info(data.user.username+'已上线');
            }

        }
        //获取用户fd
        function fd(data)
        {
            if (data.code == 200) {
                $('#fd').val(data.user.fd);
            }
        }
        //退出登录
        function out(data)
        {
            // console.log(data);
            if(data.code == 200){
                //获取当前用户的fd
                var fd = $('#fd').val();
                refreshUserList(data);
                if (fd != data.user.fd) {
                    toastr.info(data.user.username+'已下线');
                }
            }
        }
        //刷新用户列表
        function refreshUserList(data)
        {
            //获取当前用户的fd
            var fd = $('#fd').val();
            var content = '<div class="mt-3"></div>';
            //将用户展示到用户列表
            for (var i in data.data) {
                if (i == fd) {
                    content += '<a class="list-group-item list-group-item-action text-success me" href="javascript:;" fd="'+i+'">'+data.data[i]+'</a>';
                }else{
                    content += '<a class="list-group-item list-group-item-action text-primary he" href="javascript:;" fd="'+i+'">'+data.data[i]+'</a>';
                }
            }
            //刷新列表
            $('#list-example').empty().append(content);
            //刷新用户列表
            refreshUserListBySelect(data)
        }

        //刷新用户列表--聊天室
        function refreshUserListBySelect(data)
        {
            //获取当前用户的fd
            var fd = $('#fd').val();
            var content = '<a class="dropdown-item sel" href="javascript:;" fd="0">全部</a>';
            //将用户展示到用户列表
            for (var i in data.data) {
                if (i != fd) {
                    content += '<a class="dropdown-item sel" href="javascript:;" fd="'+i+'">'+data.data[i]+'</a>';
                }
            }
            //刷新列表
            $('#list').empty().append(content);
        }

        //公共聊天室
        function chatOnPublic(data){
            if (data.code == 200) {
                //获取当前用户的fd
                var fd = $('#fd').val();
                if (fd == data.user.fd) {
                    var type = 'me';
                }else{
                    var type = 'he';
                }
                //显示聊天记录
                showMessageType(type,data);
            }
        }

        //私有聊天室
        function chatOnPrivate(data)
        {
            if (data.code == 200) {
                //获取当前用户的fd
                var fd = $('#fd').val();
                if (fd == data.user.fd) {
                    var type = 'me';
                }else{
                    var type = 'he';
                }
                //显示聊天记录
                showMessageType(type,data);
            }
        }
        //选择聊天对象
        $(document).on('click','.sel',function(){
            var to_fd = $(this).attr('fd');
            var to_name = $(this).text();
            $('#btnGroupDrop1').attr('fd',to_fd).text(to_name);
        });


        
    });
</script>