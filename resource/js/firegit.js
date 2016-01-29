!function ($) {
    "use strict";

    $.firegit = {
        errMap: {
            'firegit.u_notfound': '该页面不存在或者已经被删除',
            'firegit.u_login': '您尚未登录',
            'firegit.u_power': '您没有操作的权限'
        }
    };
    function path() {
        var args = arguments,
            result = []
            ;

        for (var i = 0; i < args.length; i++) {
            var arr = args[i].split(/\s+/), l = arr.length;
            arr[l - 1] = arr[l - 1].replace('@', '/static/SyntaxHighlighter/js/shBrush') + '.js';
            result.push(arr);
        }
        return result;
    };

    var prefix = '/static/SyntaxHighlighter/js/shBrush';
    SyntaxHighlighter.autoloader.apply(null, path(
        'applescript                @AppleScript',
        'actionscript3 as3          @AS3',
        'bash shell                 @Bash',
        'coldfusion cf              @ColdFusion',
        'cpp c                      @Cpp',
        'c# c-sharp csharp          @CSharp',
        'css                        @Css',
        'delphi pascal              @Delphi',
        'diff patch pas             @Diff',
        'erl erlang                 @Erlang',
        'groovy                     @Groovy',
        'java                       @Java',
        'jfx javafx                 @JavaFX',
        'js json jscript javascript @JScript',
        'perl pl                    @Perl',
        'php phtml                  @Php',
        'text plain                 @Plain',
        'py python                  @Python',
        'powershell ps posh         @PowerShell',
        'ruby rails ror rb          @Ruby',
        'sass scss                  @Sass',
        'scala                      @Scala',
        'sql                        @Sql',
        'vb vbnet                   @Vb',
        'xml xhtml xslt html        @Xml'
    ));
    //    'php phtml ' +  prefix + 'Php.js',
    //    'applescript ' + prefix + 'AppleScript.js',
    //    'actionscript3 as3 as ' + prefix + 'AS3.js',
    //    'bash shell ' + prefix + 'Bash.js',
    //    'coldfusion cf ' + prefix + 'ColdFusion.js',
    //    'cpp c ' + prefix + 'Cpp.js'
    //);

    //SyntaxHighlighter.autoloader(path(
    //    'applescript                @shBrushAppleScript.js',
    //    'actionscript3 as3          @shBrushAS3.js',
    //    'bash shell                 @shBrushBash.js',
    //    'coldfusion cf              @shBrushColdFusion.js',
    //    'cpp c                      @shBrushCpp.js',
    //    'c# c-sharp csharp          @shBrushCSharp.js',
    //    'css                        @shBrushCss.js',
    //    'delphi pascal              @shBrushDelphi.js',
    //    'diff patch pas             @shBrushDiff.js',
    //    'erl erlang                 @shBrushErlang.js',
    //    'groovy                     @shBrushGroovy.js',
    //    'java                       @shBrushJava.js',
    //    'jfx javafx                 @shBrushJavaFX.js',
    //    'js json jscript javascript @shBrushJScript.js',
    //    'perl pl                    @shBrushPerl.js',
    //    'php                        @shBrushPhp.js',
    //    'text plain                 @shBrushPlain.js',
    //    'py python                  @shBrushPython.js',
    //    'powershell ps posh         @shBrushPowerShell.js',
    //    'ruby rails ror rb          @shBrushRuby.js',
    //    'sass scss                  @shBrushSass.js',
    //    'scala                      @shBrushScala.js',
    //    'sql                        @shBrushSql.js',
    //    'vb vbnet                   @shBrushVb.js',
    //    'xml xhtml xslt html        @shBrushXml.js'
    //));

    $(function () {
        $('*[data-original]').lazyload();

        $('pre.highlight>code').each(function (i, block) {
            hljs.highlightBlock(block);
            var elem = $(block),
                pos = elem.position(),
                height = elem.height(),
                line = height / 18,
                ol = $('<ol class="linenumber"></ol>'),
                html = [];
            for (var i = 1; i <= line; i++) {
                html.push('<li>' + i + '</li>');
            }
            ol.html(html.join('')).css({
                position: 'absolute',
                left: pos.left,
                top: pos.top,
                width: 40,
                height: elem.height()
            }).insertBefore(block.parentNode);
        });

        $('*[data-hook]').each(function () {
            var hook = this.getAttribute('data-hook');
            var option = this.getAttribute('data-hook-option');
            if (option) {
                option = eval('(' + option + ')');
            } else {
                option = {};
            }
            switch (hook) {
                case 'ajaxable':
                    doAjaxableHook(this, option);
                    break;
            }
        });


        SyntaxHighlighter.all();
    });


    /**
     * 执行post连接请求
     * @param href
     * @param option
     * @param data
     */
    function doLinkPost(href, option, data) {
        $.post(href, data, function (ret) {
            if (window.currentDialog) {
                window.currentDialog.close();
            }
            if (ret.status == 'firegit.ok') {
                dialog({
                    title: '操作成功',
                    okValue: '确认',
                    content: option.okText ? option.okText : '操作成功',
                    ok: function () {
                        location.reload();
                    },
                    onClose: function () {
                        location.reload();
                    }
                }).width(400).show();
            } else {
                var detail = '';
                if (ret.status in $.firegit.errMap) {
                    detail = $.firegit.errMap[ret.status];
                }
                var content = '<label>错误码：</label><code>' + ret.status + '</code>';
                if (detail) {
                    content += '<br/><label>&nbsp;&nbsp;说明：</label><span>' + detail + '</span>';
                }
                dialog({
                    title: '操作失败',
                    content: content,
                    okValue: '确定',
                    ok: function () {
                    }
                }).width(400).show();
            }
        });
    }

    function doAjaxableHook(node, option) {
        var nodeName = node.nodeName;
        switch (nodeName) {
            case 'A':
                $(node).on('click', function () {
                    var confirm = this.getAttribute('data-confirm'), href = this.href;
                    if (confirm) {
                        dialog({
                            content: confirm,
                            title: '提示',
                            okValue: '确认',
                            ok: function () {
                                doLinkPost(href, option);
                            },
                            cancelValue: '取消',
                            cancel: function () {
                            }
                        }).width(300).show();
                    } else {
                        doLinkPost(this.href, option);
                    }

                    return false;
                });
                break;
            case 'FORM':
                $(node).on('submit', function () {
                    doLinkPost(this.action, option, $(this).serializeArray());
                    return false;
                });
        }
    }
}(jQuery);