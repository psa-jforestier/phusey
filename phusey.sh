#!/bin/bash

# Search for a valid PHP command line
[[ $(type -P "zts-php") ]] && PHPEXE="zts-php"  || 
    { 
        [[ $(type -P "php") ]] && PHPEXE="php"  || 
            { echo "PHP is NOT in PATH" 1>&2; exit 1; }
    }
$PHPEXE app/console $*