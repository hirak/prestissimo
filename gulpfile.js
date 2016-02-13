/**
 * gulpfile.js for php-library developement
 *
 * 1. install node.js & npm
 * 2. $ npm install
 * 3. $ gulp serve
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

function phpunit(done) {
    exec('vendor/bin/phpunit --colors=always', function(err, stdout, stderr){
        console.log(stdout);
        console.error(stderr);
        done();
    });
}

function pdepend(done) {
    exec([
        'vendor/bin/pdepend',
        '--jdepend-chart=artifacts/pdepend.svg',
        '--overview-pyramid=artifacts/pyramid.svg',
        '--summary-xml=artifacts/summary.xml',
        'src/'
    ].join(' '), done);
}

gulp.task('test', phpunit);

gulp.task('inspect', pdepend);

gulp.task('start', function(){
    browserSync.init({
        server: {
            baseDir: "artifacts/"
        }
    });

    gulp.watch(['src/**/*.php', 'tests/**/*Test.php'], {}, function(ev){
        phpunit(function(){
            browserSync.reload();
        });
    });
});

