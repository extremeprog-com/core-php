<?php

class _OSDeploy {

    static function makeLinks() {
        CatchEvent(\_OS\Core\System_InitConfigs::class);
        $current_dir = __DIR__;
        `mkdir -p \$PROJECTENV/bin/`;
        foreach(array('deploy') as $cmd) {
            echo `rm \$PROJECTENV/bin/$cmd 2>/dev/null; ln -s $current_dir/$cmd \$PROJECTENV/bin/$cmd`;
        }
    }

    static function run($server, $project, $branch) {
        echo __METHOD__." ".print_r(func_get_args(), true)."\n";

        passthru("rsync -r ~/.projectsrc '$server':.projectsrc", $retval);

        $revnum_file = __DIR__."/revnum-$project.rev";

        if(file_exists($revnum_file)) {
            $revnum = file_get_contents(__DIR__."/revnum-$project.rev");
            $revnum++;
        } else {
            $revnum = 0;
        }

        file_put_contents(__DIR__."/revnum-$project.rev", $revnum);
        $revnum = date('md').sprintf("%03d", $revnum);

        passthru(dbg::$i->sijf = "ssh '$server' 'mkdir -p $project/$revnum $project.config $project.data $project.env/bin $project.env/etc $project.env/var $project.log $project.tmp'; ", $retval);

        $PROJECTPATH = system(dbg::$i->sijf = "ssh '$server' 'source .projectsrc; cdproject $project $revnum; echo \$PROJECTPATH'", $retval);

        if($retval != 0) { //not okay
            Logger()->error($msg = 'Deploy failed - cannot do cdproject');
            echo $msg;
            return;
        }

        if($branch) {
            $merge = explode('+', $branch);
            $branch = $merge[0];
            array_shift($merge);

            $dir = "/tmp/deploy-$server-$project";
            passthru("rm -rf $dir; git clone . $dir; cd $dir; git checkout $branch;");
            foreach($merge as $branch) {
                passthru("cd $dir; git merge origin/$branch", $retval);
                if($retval != 0) {
                    throw new Exception("cannot merge $branch");
                }
            }
        } else {
            $dir = PATH_WORKDIR;
        }

        _OSTask::run_inline(array(
            'is_possible' => function(Context $Ctx) use($server, $project) {
                passthru(dbg::$i->sdij = "ssh '$server' 'source .projectsrc; cdproject $project; if [ \"\$PROJECTREV\" ]; then exit 0; else exit 1; fi; '", $retval);
                return $retval == 0;
            },
            'make_possible' => function(Context $Ctx) use($server, $project) {
                passthru(dbg::$i->sdij = "ssh '$server' 'source .projectsrc; cdproject $project; mkdir 0000001; echo -n 0000001 > project-revision'", $retval);
                $Ctx->destroy_0 = true;
            },
            'been_run' => function(Context $Ctx) use($server, $project) {
                return isset($Ctx->been_run);
            },
            'run' => function(Context $Ctx) use($server, $project, $revnum) {
                passthru(dbg::$i->sdij = "ssh '$server' 'source .projectsrc; cdproject $project; if [ \"\$PROJECTREV\" ]; then cp -r ./ ~/$project/$revnum; else exit 0; fi; ". (isset($Ctx->destroy_0)?'cd ..; rm -r 0000001':'')."'", $retval);

                if($retval != 0) { //not okay
                    Logger()->error($msg = 'Deploy failed - can\'t make copy of previous revision');
                    echo $msg;
                    return;
                }
                $Ctx->been_run = true;
            }
        ));


        system(dbg::$i->sijf = "rsync -r --delete $dir/* '$server':$PROJECTPATH", $retval);

        if($retval != 0) { //not okay
            Logger()->error($msg = 'Cannot do rsync');
            echo $msg;
            return;
        }

        passthru(dbg::$i->sijf = "ssh '$server' 'source .projectsrc; cdproject $project $revnum; ./init_configs'", $retval);

        if($retval != 0) { //not okay
            Logger()->error($msg = 'Deploy failed - cannot do init_configs');
            echo $msg;
            return;
        }

        $selftest_result = system(dbg::$i->sijf = "ssh '$server' 'source .projectsrc; cdproject $project $revnum; echo \$PROJECTPATH'", $retval);

        if($selftest_result!="ok") {
            if($retval != 0) { //not okay
                Logger()->error($msg = 'Deploy failed - selftest is not ok');
                echo $msg;
                return;
            }
        }

        passthru(dbg::$i->sijf = "ssh '$server' 'echo -n $revnum > $project/project-revision'", $retval);

    }

}