#!/bin/bash

mkdir ~/.bashrc.d

echo """
# .bashrc
alias sail='./vendor/bin/sail'
alias art='./vendor/bin/sail artisan'
alias tinker='./vendor/bin/sail tinker'
alias migrate='./vendor/bin/sail art migrate:refresh --seed'
""" > ~/bashrc.d/octiv.rc
