/**
 * gulpfile.js for php-library developement
 *
 * 1. install node.js & npm
 * 2. $ npm install
 * 3. $ npm start
 */
var gulp = require('gulp');
var exec = require('child_process').exec;
var bs = require('browser-sync').create();

gulp.task('default', ['test', 'inspect']);

function test(done) {
    var p = exec('composer test');
    p.stdout.pipe(process.stdout);
    p.stderr.pipe(process.stderr);
    p.on('end', done);
}

function inspect(done) {
    var r = 3;
    function wait() {
        --r || done();
    }
    exec('composer doc', wait);
    exec('composer metrics', wait);
    var lint = exec('composer lint'); 
    lint.stdout.pipe(process.stdout);
    lint.stderr.pipe(process.stderr);
    lint.on('end', wait);
}

gulp.task('test', test);
gulp.task('inspect', inspect);

gulp.task('start', function(){
    bs.init({
        server: {
            baseDir: "artifacts/"
        }
    });

    gulp.watch(['src/**/*.php', 'tests/**/*Test.php'], {}, function(ev){
        test(bs.reload);
    });
});

