var child_process = require('child_process');
var fs = require('fs');

var hosts = fs.readFile.sync(null, '/etc/hosts').toString().split("\n")
    .filter(function(line) {return /([a-z]+\d+)\.int/.test(line)})
    .map   (function(line) {return /([a-z]+\d+)\.int/.exec(line)[1] });
var i, host;

var futures = {};
for (i in hosts) {
    if (!hosts.hasOwnProperty(i)) {
        continue;
    }
    host = hosts[i];
    futures[host] = (function (host, on_end) {
        console.log("\n===== deploy to " + host + " =====\n" + child_process.exec.sync(null, 'php-r \'Deploy::deployFromBase("' + host + '");\''));
        on_end();
    }).future(null, host);
}
for (host in hosts) {
    if (!hosts.hasOwnProperty(i)) {
        continue;
    }
    host = hosts[i];
    futures[host].result;
}
for (i in hosts) {
    if (!hosts.hasOwnProperty(i)) {
        continue;
    }
    host = hosts[i];
    (function (host, on_end) {
        console.log("\n===== init data on " + host + " =====\n" + child_process.exec.sync(null, 'php-r \'Deploy::initData("' + host + '");\''));
        on_end();
    }).future(null, host);
}
console.log("node-runner stage done");
