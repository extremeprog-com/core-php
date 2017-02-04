
if [[ -z "$PROJECTPATH" ]]; then
    echo "Error: not in project. You need to run 'cdproject' "`realpath $(dirname $0)` 1>&2;
    exit 255;
fi;

cd $PROJECTPATH;

export HOSTNAME=`hostname`
export APPLICATION_ROOT=$PROJECTPATH
export PHP_PATH=$PROJECTENV/bin/php
if  [ ! -e $PHP_PATH ]; then
    export PHP_PATH=/usr/local/bin/php
fi
if  [ ! -e $PHP_PATH ]; then
    export PHP_PATH=/usr/bin/php
fi

cd $PROJECTPATH;

$PHP_PATH -r 'include "core/_OS/Core/generate_maps.php";' &&\

if [ "$1" ]; then
    php-r 'Test::runTests("'$1'");'
else
    php-r 'Test::runTests();'
fi;
