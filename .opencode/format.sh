#!/bin/bash

file=$1

if [[ $file == *.blade.php ]]; then
    npx prettier --write "$file"
elif [[ $file == *.php ]]; then
    ./vendor/bin/pint "$file"
else
    echo "No formatter configured for this file type."
fi
