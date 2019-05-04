/**
 * gulpfile.js for php-library developement
 *
 * 1. install node.js & npm
 * 2. $ npm install
 * 3. $ npm start
 */
const { parallel, watch } = require('gulp');
const { exec } = require('child_process');
const bs = require('browser-sync').create();

exports.default = parallel(test, inspect)

function test(done) {
  var p = exec('composer fasttest');
  p.stdout.pipe(process.stdout);
  p.stderr.pipe(process.stderr);
  p.on('exit', done);
};

exports.test = test;

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
};

exports.inspect = inspect;

function lint(target, done) {
  var p = exec('composer lint -- ' + target);
  p.stdout.pipe(process.stdout);
  p.stderr.pipe(process.stderr);
  p.on('exit', done);
}

exports.start = function start(done) {
  bs.init({
    server: {
      baseDir: "artifacts/"
    },
    ghostMode: false
  });

  const watcher = watch(['src/**/*.php', 'tests/**/*Test.php']);

  watcher.on('change', function(path, stats) {
    console.log(`File ${path} was changed`);
    lint(path, function(){
      test(bs.reload);
    });
  });

  watcher.on('add', function(path, stats) {
    console.log(`File ${path} was added`);
    lint(path, function(){
      test(bs.reload);
    });
  });

  watcher.on('unlink', function(path, stats) {
    console.log(`File ${path} was unlinked`);
    inspect(function(){
      test(bs.reload);
    });
  });
};
