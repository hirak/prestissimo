/**
 * gulpfile.js for php-library developement
 *
 * 1. install node.js & npm
 * 2. $ npm install
 * 3. $ gulp server
 * 4. open http://localhost:9000/ (livereload enabled)
 * 5. coding on src/*.php and tests/*.php
 *
 * enjoy!
 *
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0-1.0 (No Rights Reserved.)
 * @link https://github.com/spindle/spindle-lib-template
 */
var gulp = require('gulp');
var exec = require('child_process').exec;
var browserSync = require('browser-sync').create();

gulp.task('default', ['test', 'inspect']);

gulp.task('test', function(done){
    exec('vendor/bin/phpunit --colors=always', function(err, stdout, stderr){
        console.log(stdout);
        console.error(stderr);
        done();
    });
});

gulp.task('inspect', function(done){
    exec([
        'vendor/bin/pdepend',
        '--jdepend-chart=artifacts/pdepend.svg',
        '--overview-pyramid=artifacts/pyramid.svg',
        '--summary-xml=artifacts/summary.xml',
        'src/'].join(' '), done);
});

gulp.task('serve', function(){
    browserSync.init({
        server: {
            baseDir: "artifacts/"
        }
    });

    gulp.watch(['src/**/*.php', 'tests/**/*Test.php'], ['test', 'inspect'], function(ev){
        browserSync.reload();
    });
});

