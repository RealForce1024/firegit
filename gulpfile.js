var gulp = require('gulp');
var del = require('del');
var exec = require('child_process').execSync;
var less = require('gulp-less');
var fs = require('fs');

gulp.task('dist', function () {

    del.sync('public/static/');

    gulp.src(['bower_components/bootstrap/dist/**/*'])
        .pipe(gulp.dest('public/static/bootstrap/'));

    gulp.src(['bower_components/jquery/dist/*'])
        .pipe(gulp.dest('public/static/jquery'));

    //gulp.src(['bower_components/highlightjs/highlight.pack.min.js'])
    //    .pipe(gulp.dest('public/static/highlightjs'));

    //gulp.src('bower_components/highlightjs/styles/*')
    //    .pipe(gulp.dest('public/static/highlightjs/styles'));

    gulp.src('bower_components/artDialog/dist/*')
        .pipe(gulp.dest('public/static/artDialog/js/'));

    gulp.src('bower_components/artDialog/css/*')
        .pipe(gulp.dest('public/static/artDialog/css/'));

    gulp.src('bower_components/jquery_lazyload/*.js')
        .pipe(gulp.dest('public/static/lazyload/'));


    console.log('发布SyntaxHighlighter');
    distShl();

    console.log('发布firegit');
    distFiregit();
});

/**
 * 发布SyntaxHighlighter
 */
function distShl() {
    "use strict";

    //TODO http://alexgorbatchev.com/SyntaxHighlighter/download/ 从改页面下载源代码

    //if (!fs.existsSync('bower_components/SyntaxHighlighter/')) {
    //    if (!fs.existsSync('bower_components/SyntaxHighlighter.zip')) {
    //        console.log("开始下载SyntaxHighlighter...");
    //        exec('wget http://alexgorbatchev.com/SyntaxHighlighter/download/download.php?sh_current -O bower_components/SyntaxHighlighter.zip');
    //    }
    //    console.log("开始解压...");
    //    exec('unzip -o bower_components/SyntaxHighlighter.zip -d bower_components/');
    //    exec('mv bower_components/syntaxhighlighter* bower_components/SyntaxHighlighter');
    //}
    //
    gulp.src('bower_components/SyntaxHighlighter/scripts/*.js')
        .pipe(gulp.dest('public/static/SyntaxHighlighter/js/'));

    gulp.src('bower_components/SyntaxHighlighter/styles/*.css')
        .pipe(gulp.dest('public/static/SyntaxHighlighter/css/'));

    /*
    // gulp-sass安装总是有问题，直接使用sass来进行处理
    var fs = require('fs'),
        prefix = 'bower_components/SyntaxHighlighter/src/sass/',
        dest = 'public/static/SyntaxHighlighter/css/',
        files = fs.readdirSync('bower_components/SyntaxHighlighter/src/sass/'),
        path = require('path');

    if (!fs.existsSync(dest)) {
        exec('mkdir -p ' + dest);
    }



    files.forEach(function (file) {
        if (file == 'shCore.scss' || file.indexOf('shTheme') === 0) {
            var basename = path.basename(file, '.scss');
            exec('sass ' + prefix + file + ' ' + dest + basename + '.css', function (error, stdout, stderr) {
                console.log('stdout: ' + stdout);
                console.log('stderr: ' + stderr);
                if (error !== null) {
                    console.log('exec error: ' + error);
                }
            });
        }
    });

    gulp.src('bower_components/xregexp/min/xregexp-all-min.js')
        .pipe(gulp.dest('public/static/xregexp/'));

    // SyntaxHighlighter
    gulp.src('bower_components/SyntaxHighlighter/src/js/*.js')
        .pipe(gulp.dest('public/static/SyntaxHighlighter/js/'));
        */
}

/**
 * 发布firegit
 */
function distFiregit() {
    "use strict";
    
    gulp.src('resource/js/**/*.js')
        .pipe(gulp.dest('public/static/firegit/js'));

    gulp.src('resource/less/**/*.less')
        .pipe(less())
        .pipe(gulp.dest('public/static/firegit/css/'));
}
