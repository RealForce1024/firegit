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

    gulp.src([
            'bower_components/SyntaxHighlighter/scripts/shCore.js',
            'bower_components/SyntaxHighlighter/scripts/shLegacy.js',
            'bower_components/SyntaxHighlighter/scripts/XRegExp.js',
            'bower_components/SyntaxHighlighter/scripts/shBrush*.js',
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


gulp.task('watch', function () {
    gulp.watch('resource/**/*', ['dist']);
});