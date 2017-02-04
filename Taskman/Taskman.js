require(process.env.PROJECTPATH + '/core/_OS/Core/Core.js');

var net = require('net');
var child_process = require('child_process');
var fs = require('fs');

Taskman = {
      configfile  : process.env.PROJECTENV + '/etc/taskman.conf'
    , socketfile  : process.env.PROJECTENV + '/var/taskman.sock'
    , pidfile     : process.env.PROJECTENV + '/var/taskman.pid'
    , revisionfile: process.env.HOME       + '/' + process.env.PROJECT + '/project-revision'
};

process.title = process.env.PROJECTENV + '/etc/taskman.conf';

var
    /**
     * @function
     * Write to info log-file
     *
     * @param {String} data
     */
    writeInfoLogFile = function(data) {
        var t = new Date().toISOString().split('T')[0].split('-').join('');
        fs.appendFile(process.env.PROJECTLOG + '/' + t + '-info-taskman.log', data + '\n');
    }
    /**
     * @function
     * Write to warn log-file
     *
     * @param {String} data
     */
    , writeWarnLogFile = function(data) {
        var t = new Date().toISOString().split('T')[0].split('-').join('');
        fs.appendFile(process.env.PROJECTLOG + '/' + t + '-warn-taskman.log', data + '\n');
    };

Taskman.Server = new (function(){
    var
          _this      = this
        , projectENV = process.env.PROJECTENV

        /**
         * @function
         * Delete folder deeply
         *
         * @param {String} path
         */
        , deleteFolderRecursive = function(path) {
            var files = [];
            if( fs.existsSync(path) ) {
                files = fs.readdirSync(path);
                files.forEach(function(file, index){
                    var curPath = path + "/" + file;
                    if( fs.lstatSync(curPath).isDirectory() ) { // recurse
                        deleteFolderRecursive(curPath);
                    } else { // delete file
                        fs.unlinkSync(curPath);
                    }
                });
                fs.rmdirSync(path);
            }
        };

    this.Init                       = new Core.EventPoint();
    this.PIDFileCheckedSuccess      = new Core.EventPoint();
    this.Started                    = new Core.EventPoint();
    this.Stopped                    = new Core.EventPoint();
    this.NewConnection              = new Core.EventPoint();
    this.ConfigChanged              = new Core.EventPoint();
    this.NewCommandList             = new Core.EventPoint();
    this.CommandForStartTaskCreated = new Core.EventPoint();
    this.CommandForStopTaskCreated  = new Core.EventPoint();

    this.lines = {}; //tasks list

    var
          _watcher // watcher config file handler
        , _watchConfigFileTimeout   = null
        , _watchRevisionFileTimeout = null;


    /**
     * @function
     * Check PID file. If it does not exists send event to start server
     *
     * @event Taskman.Server.Init
     *
     */
    this.checkPIDFile = function() {
        CatchEvent(Taskman.Server.Init);

        fs.readFile(Taskman.pidfile, function(err, data) {
            if( err ) {
                writeWarnLogFile('Start on pid-file with error: ' + err);

                FireEvent(new Taskman.Server.PIDFileCheckedSuccess());
            } else {
                var pid = parseInt(data.toString());

                if( !pid ) {
                    FireEvent(new Taskman.Server.PIDFileCheckedSuccess());
                } else {
                    var
                          var_dir = projectENV + '/var'
                        , cfile   = projectENV + '/.created_at.var';

                    try {
                        if( !fs.existsSync(cfile) ) {
                            fs.writeFileSync(cfile, new Date().getTime());
                        }
                        if( fs.existsSync(var_dir) && Sys.uptime_since > new Date(parseInt(fs.readFileSync(cfile))) ) {
                            writeInfoLogFile('Delete var dir.');
                            deleteFolderRecursive(var_dir);
                        }
                        if( !fs.existsSync(var_dir) ) {
                            fs.mkdirSync(var_dir);
                            fs.writeFileSync(cfile, new Date().getTime());
                        }

                        if( process.kill(pid, 0) ) {
                            //writeWarnLogFile('No permission to signal PID or invalid PID. Terminating...');
                            return;
                        }
                    } catch(e) {
                        writeWarnLogFile(e + '. I think, process is not running.');
                        FireEvent(new Taskman.Server.PIDFileCheckedSuccess());
                    }
                }
            }
        });
    };

    /**
     * @function
     * Start Taskman Server
     *
     * @event Taskman.Server.PIDFileChecked
     *
     */
    this.startServer = function() {
        CatchEvent(Taskman.Server.PIDFileCheckedSuccess);

        fs.unlink(Taskman.socketfile, function() {});
        fs.writeFileSync(Taskman.pidfile, process.pid.toString());

        this.server = net.createServer(function(conn) {
            FireEvent(new Taskman.Server.NewConnection(conn));
        });
        this.server.listen(Taskman.socketfile);

        writeInfoLogFile("Taskman Server started.")
        FireEvent(new Taskman.Server.Started( { server: _this.server } ));
    };

    /**
     * @function
     * Read and parse config file
     *
     * @event Taskman.Server.Started
     * @event Taskman.Server.ConfigChanged
     *
     */
    this.readAndParseFileAndMakeCommandList = function() {
        CatchEvent(Taskman.Server.Started, Taskman.Server.ConfigChanged);

        fs.readFile(Taskman.configfile, function(err, data) {
            if( err ) {
                writeWarnLogFile("Can't read config file. Terminating...");
                return;
            }

            writeInfoLogFile('Config' + data);
            writeInfoLogFile('Process rev' + process.env.PROJECTREV);
            var
                  i
                , raw_lines = data.toString().split(/\n/)
                , lines     = {}
                , commands  = [];

            //parse config file lines
            for( i = 0; i < raw_lines.length; i++ ) {
                var line = raw_lines[i].replace(/[ \t]+/g, ' ').replace(/^ /, '').replace(/ $/, '');
                if( line && !line.match(/^#/) ) {
                    lines[line] = true;
                }
            }

            for( var j in _this.lines ) {
                if( !_this.lines.hasOwnProperty(j) ) continue;
                if( !lines.hasOwnProperty(j) ) {
                    commands.push(['stop', j]);
                }
            }

            for( var k in lines ) {
                if( !lines.hasOwnProperty(k) ) continue;
                if( !_this.lines.hasOwnProperty(k) ) {
                    commands.push(['exec', k]);
                }
            }

            writeInfoLogFile('Commands created : ' + commands.join(';'));

            FireEvent(new Taskman.Server.NewCommandList({commands: commands}));
        });
    };

    var _firstWatchTs;

    /**
     * @function
     * Start watching Taskman config file
     *
     * @event Taskman.Server.Started
     *
     */
    this.watchConfigFile = function() {
        CatchEvent(Taskman.Server.Started);

        fs.watchFile(Taskman.configfile, _watcher = function () {
            // подождём 2 секунды, если будут ещё изменения - накопим их
            if(!_firstWatchTs) {
                _firstWatchTs = new Date;
            }
            if( _watchConfigFileTimeout && new Date - _firstWatchTs <= 4000) {
                clearTimeout(_watchConfigFileTimeout);
            }
            if( _watchConfigFileTimeout && new Date - _firstWatchTs > 4000) {
                FireEvent(new Taskman.Server.ConfigChanged());
                clearTimeout(_watchConfigFileTimeout);
                _firstWatchTs = new Date;
            }
            _watchConfigFileTimeout = setTimeout(function() {
                _firstWatchTs = null;
                FireEvent(new Taskman.Server.ConfigChanged());
            }, 2000);
        });
    };

    /**
     * @function
     * Stop watch Taskman config file
     *
     */
    this.unwatchConfigFile = function() {
        fs.unwatchFile(Taskman.configfile, _watcher);
    };

    /**
     * @function
     * Start watch revision file
     *
     * @event Taskman.Server.Started
     *
     */
    this.watchRevisionFile = function() {
        CatchEvent(Taskman.Server.Started);

        fs.watchFile(Taskman.revisionfile, function () {
            //wait for 100ms
            if( _watchRevisionFileTimeout ) {
                clearTimeout(_watchRevisionFileTimeout);
            }
            _watchRevisionFileTimeout = setTimeout(function() {
                fs.readFile(Taskman.revisionfile, function(err, data) {
                    if( err ) {
                        writeWarnLogFile("Can't read revision file.");
                        return;
                    }
                    writeInfoLogFile('Revision changed to ' + data.toString());
                    process.env.PROJECTREV = data.toString();

                    writeInfoLogFile('process.env.PROJECTREV = ' + process.env.PROJECTREV);
                    process.env.PROJECTPATH = process.env.HOME + '/' + process.env.PROJECT + '/' + data.toString();
                    writeInfoLogFile('process.env.PROJECTPATH = ' + process.env.PROJECTPATH);

                    writeInfoLogFile('chdir to ' + process.env.PROJECTPATH + '/');
                    process.chdir(process.env.PROJECTPATH + '/');
                });
            }, 100);
        });
    };

    /**
     * @function
     * Execute command after update
     *
     * @event Taskman.Server.NewCommandList
     */
    this.executeCommandsAfterUpdate = function() {
        var
              event    = CatchEvent(Taskman.Server.NewCommandList)
            , commands = event.commands;

        for(var i = 0; i < commands.length; i++) {
            var command = commands[i];
            switch( command[0] ) {
                case 'exec':
                    try {
                        FireEvent(new Taskman.Server.CommandForStartTaskCreated({command: command[1]}));
                    } catch(e) {
                        writeInfoLogFile('Error while exec ' + command[1] + ': ' + e);
                    }
                break;
                case 'stop':
                    try {
                        FireEvent(new Taskman.Server.CommandForStopTaskCreated({command: command[1]}));
                    } catch(e) {
                        writeInfoLogFile('Error while stop ' + command[1] + ': ' + e);
                    }
                break;
            }
        }

    };

    /**
     * @function
     * Create new restartable task
     *
     * @event Taskman.Server.CommandForStartTaskCreated
     *
     */
    this.startTaskForLine = function() {
        var
              event = CatchEvent(Taskman.Server.CommandForStartTaskCreated)
            , line  = event.command;

        var matches = line.match(/^(\*) +([^ ]+) +(.+)$/);
        writeInfoLogFile('Start task with command: ' + line);

        var name    = matches[2];
        var cmdline = matches[3];
        var task    = new RestartableTask(name, cmdline);

        this.lines[line] = {
            task: task
        };
    };

    /**
     * @function
     * Stop task
     *
     * @event Taskman.Server.CommandForStopTaskCreated
     *
     */
    this.stopTaskForLine = function() {
        var
              event = CatchEvent(Taskman.Server.CommandForStopTaskCreated)
            , line  = event.command;

        writeInfoLogFile('Stop task with command: ' + line);

        this.lines[line].task.stopRespawning();
        this.kill(this.lines[line].task, function() {

        });
        delete _this.lines[line];
    };

    /**
     * @function
     * Stop Taskman Server
     *
     * @event Signals.SIGINT
     * @event Signals.SIGTERM
     *
     */
    this.stopTaskmanServer = function() {
        CatchEvent(Signals.SIGINT, Signals.SIGTERM);

        for( var i in this.lines ) {
            if( this.lines.hasOwnProperty(i) ) {
                FireEvent(new Taskman.Server.CommandForStopTaskCreated({command: i}));
            }
        }

        writeInfoLogFile('Stop watching config file and revisions file.')

        Taskman.Server.unwatchConfigFile();
        fs.unwatchFile(Taskman.revisionfile);

        Taskman.Server.server.close(function() {
            writeInfoLogFile('Taskman Server Stopped.')
        });

        FireEvent(new Taskman.Server.Stopped());
    };

    this.kill = function(task, callback){
        if( !task.pid ) {
            setTimeout(function(){
                _this.kill(task, callback);
            }, 250);
            return;
        }

        child_process.exec("ps x -o pid,ppid", function(code, data, errdata) {
            if( errdata ) {
                writeWarnLogFile(errdata);
            }
            var parsed = data.replace(/^(.*?)\n/, '').match(/(\d+) +(\d+)/g);

            for( var i = 0; i < parsed.length; i++ ) {
                parsed[i] = parsed[i].split(/ +/);
            }
            var pids_to_kill = [], pids_to_check = [task.pid];
            while( pids_to_check.length ) {
                var pid = pids_to_check.shift();

                for( var j = 0; j < parsed.length; j++ ) {
                    if( parsed[j][1] == pid ) {
                        pids_to_check.push(parsed[j][0]);
                    }
                }
                pids_to_kill.unshift(pid);
            }
            writeInfoLogFile('Kill tasks with pids ' + pids_to_kill.join(' '));

            var kill_process = child_process.exec('kill ' + pids_to_kill.join(' '));

            kill_process.on("exit", function(code){ callback(); });
        });
    };

})();

function RestartableTask(name, cmdline) {
    var _this = this;
    var restart = true;

    this.stopRespawning = function() {
        restart = false;
    };

    child_process.exec('for i in ' + cmdline + '; do echo $i; done;', function(err, out){
        var
              args      = out.split("\n")
            , cmd       = args.shift()
            , instances = 0;

        args.pop();

        (function start() {
            try {
                var task = child_process.spawn(cmd, args, { cwd: process.env.PROJECTPATH, env: process.env });
                instances++;
                task
                    .on('exit', function(code) {
                        _this.pid = null;
                        writeInfoLogFile('Child process \'' + name + '\' exited with code ' + code + '. Restarting...');
                        if( restart ) {
                            setTimeout(start, 1000);
                        }
                    })
                    .on('error', function() {
                        _this.pid = null;
                        writeWarnLogFile(arguments);
                        if( restart ) {
                            setTimeout(start, 1000);
                        }
                    });
                task.stdout.on('data', function(data) {
                    writeInfoLogFile(name + ': ' + data.toString());
                });
                task.stderr.on('data', function(data) {
                    writeWarnLogFile(name + ': [err] ' + data.toString());
                });
                _this.pid = task.pid;
            } catch(e) {
                writeWarnLogFile(name + ': [taskman] ' + e.toString());
                if( !instances ) {
                    setTimeout(start, 5000);
                }
            }
        })();
    });
}


var Sys = {
      get uptime_since() {
        return new Date(new Date - this.uptime_usec);
    }
    , get uptime_usec() {
        try {
            var proc_uptime = fs.readFileSync('/proc/uptime').toString();
        } catch(e) {
            proc_uptime = "6000000.02 6000000.91";
        }
        return parseFloat(proc_uptime.match(/\d+\.\d+/)[0]) * 1000;
    }
};

Signals = new (function() {
    this.Init    = new Core.EventPoint();
    this.SIGINT  = new Core.EventPoint();
    this.SIGTERM = new Core.EventPoint();

    /**
     * @function
     * Handle system signals and create relative events
     *
     * @event Signals.Init
     *
     */
    this.handleSystemSignals = function() {
        CatchEvent(Signals.Init);

        process.on('SIGINT' , function() { writeInfoLogFile('SYS SIGINT');  FireEvent(new Signals.SIGINT());  });
        process.on('SIGTERM', function() { writeInfoLogFile('SYS SIGTERM'); FireEvent(new Signals.SIGTERM()); });
    };
})();

Core.processObject(Signals);
Core.processObject(Taskman.Server);
