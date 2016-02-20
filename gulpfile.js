/**
 * gulpfile.js for php-library developement
 *
 * 1. install node.js & npm
 * 2. $ npm install
 * 3. $ npm start
 */
var gulp = require('gulp');
var exec = require('child_process').exec;
var browserSync = require('browser-sync').create();

gulp.task('default', ['test', 'inspect']);

function phpunit(done) {
    var p = exec('vendor/bin/phpunit --colors=always');
    p.stdout.pipe(process.stdout);
    p.stderr.pipe(process.stderr);
    p.on('end', done);
}

function inspect(done) {
    var r = 2;
    function wait() {
        --r || done();
    }
    exec([
        'vendor/bin/pdepend',
        '--jdepend-chart=artifacts/pdepend.svg',
        '--overview-pyramid=artifacts/pyramid.svg',
        '--summary-xml=artifacts/summary.xml',
        'src/'
    ].join(' '), wait);

    var p = exec('vendor/bin/phpcs', wait);
}

gulp.task('test', phpunit);

gulp.task('inspect', inspect);

gulp.task('start', function(){
    browserSync.init({
        server: {
            baseDir: "artifacts/"
        }
    });

    gulp.watch(['src/**/*.php', 'tests/**/*Test.php'], {}, function(ev){
        var r = 2;
        function wait() {
            --r || browserSync.reload();
        }
        inspect(wait);
        phpunit(wait);
    });
});

