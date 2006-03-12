#!/bin/sh

if [ -z $1 ]; then
    echo "Usage: mkrelease.sh <version>"
    exit;
fi

# Pre-clean
rm rabograp-$1.tar.gz
rm rabograp-$1-win.zip

# Documentation
links -dump README.html > README

# Unix (src) release
mkdir rabograp-$1
cp * rabograp-$1
rm rabograp-$1/*.dll
rm rabograp-$1/*.exe
rm rabograp-$1/*.bat
rm rabograp-$1/mkrelease.sh
tar -vczf rabograp-$1.tar.gz rabograp-$1

# Windows release
mkdir rabograp-$1-win
cp * rabograp-$1-win
rm rabograp-$1-win/mkrelease.sh
zip -r rabograp-$1-win.zip rabograp-$1-win

# Cleanup.

rm rabograp-$1 -r
rm rabograp-$1-win -r

