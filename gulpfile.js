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
    var p = exec('composer fasttest');
    p.stdout.pipe(process.stdout);
    p.stderr.pipe(process.stderr);
    p.on('exit', done);
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
    lint.on('exit', wait);
}

function lint(target, done) {
    var p = exec('composer lint -- ' + target);
    p.stdout.pipe(process.stdout);
    p.stderr.pipe(process.stderr);
    p.on('exit', done);
}

gulp.task('test', test);
gulp.task('inspect', inspect);

gulp.task('start', function(){
    bs.init({
        server: {
            baseDir: "artifacts/"
        },
        ghostMode: false
    });

    gulp.watch(['src/**/*.php', 'tests/**/*Test.php'], {}, function(ev){
        switch (ev.type) {
            case "added":
            case "changed":
                lint(ev.path, function(){
                    test(bs.reload);
                });
                break;
            default:
                inspect(function(){
                    test(bs.reload);
                });
        }
    });
});

