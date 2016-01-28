var gulp = require('gulp');
var del = require('del');

gulp.task('dist', function () {
    del.sync('public/static');

    gulp.src(['bower_components/bootstrap/dist/**/*'])
        .pipe(gulp.dest('public/static/bootstrap/'));

    gulp.src(['bower_components/jquery/dist/*'])
        .pipe(gulp.dest('public/static/jquery'));

    gulp.src(['bower_components/highlightjs/highlight.pack.min.js'])
        .pipe(gulp.dest('public/static/highlightjs'));

    gulp.src('bower_components/highlightjs/styles/*')
        .pipe(gulp.dest('public/static/highlightjs/styles'));

    gulp.src('bower_components/artDialog/dist/*')
        .pipe(gulp.dest('public/static/artDialog/js/'));

    gulp.src('bower_components/artDialog/css/*')
        .pipe(gulp.dest('public/static/artDialog/css/'));

});