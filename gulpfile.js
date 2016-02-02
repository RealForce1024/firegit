var gulp = require('gulp');
var del = require('del');
var exec = require('child_process').execSync;
var less = require('gulp-less');
var fs = require('fs');
var sourcemap = require('gulp-sourcemaps');
var uglify = require('gulp-uglify');
var concat = require('gulp-concat');

gulp.task('dist', function () {

    del.sync('public/static/');

    gulp.src(['bower_components/bootstrap/dist/**/*'])
        .pipe(gulp.dest('public/static/bootstrap/'));

    gulp.src(['bower_components/jquery/dist/*'])
        .pipe(gulp.dest('public/static/jquery'));

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

    console.log('发布hapj');
    distHapj();
});

/**
 * 发布SyntaxHighlighter
 */
function distShl() {
    "use strict";

    gulp.src([
            'bower_components/SyntaxHighlighter/scripts/shCore.js',
            'bower_components/SyntaxHighlighter/scripts/shLegacy.js',
            'bower_components/SyntaxHighlighter/scripts/XRegExp.js',
            'bower_components/SyntaxHighlighter/scripts/shBrush*.js',
            'bower_components/shBrushGo/shBrushGo.js',
            'resource/patch/SyntaxHighlighter/shAutoloader.js'
        ])
        .pipe(gulp.dest('public/static/SyntaxHighlighter/js/'));


    gulp.src('bower_components/SyntaxHighlighter/styles/*.css')
        .pipe(gulp.dest('public/static/SyntaxHighlighter/css/'));

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

/**
 * 发布hapj
 */
function distHapj() {
    "use strict";
    
    gulp.src([
            'bower_components/hapj/src/js/hapj.js',
            'bower_components/hapj/src/js/lib/md5.js',
            'bower_components/hapj/src/js/lib/serial.js',
            'bower_components/hapj/src/js/core/hook.js',
            'bower_components/hapj/src/js/core/conf.js',
            'bower_components/hapj/src/js/core/browser.js',
            'bower_components/hapj/src/js/core/string.js',
            'bower_components/hapj/src/js/core/array.js',
            'bower_components/hapj/src/js/core/object.js',
            'bower_components/hapj/src/js/core/date.js',
            'bower_components/hapj/src/js/core/json.js',
            'bower_components/hapj/src/js/core/log.js',
            'bower_components/hapj/src/js/core/page.js',
            'bower_components/hapj/src/js/core/cache.js',
            'bower_components/hapj/src/js/core/hook.js',
            'bower_components/hapj/src/js/hapj.hook.js',
        ])
        .pipe(sourcemap.init({
            charset: 'utf8'
        }))
        .pipe(uglify())
        .pipe(concat('hapj.min.js'))
        .pipe(sourcemap.write('./'))
        .pipe(gulp.dest('public/static/hapj/js'));

    gulp.src(['bower_components/hapj/src/js/ui/*.js'])
        .pipe(gulp.dest('public/static/hapj/js/ui'));

    gulp.src('bower_components/hapj/src/css/**/*.less')
        .pipe(less())
        .pipe(gulp.dest('public/static/hapj/css'));
}


gulp.task('watch', function () {
    gulp.watch('resource/**/*', ['dist']);
});