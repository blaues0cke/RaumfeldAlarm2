#!/bin/bash
# 
# This file is part of RaumfeldAlarm2.
# Learn more at: https://github.com/blaues0cke/RaumfeldAlarm
# 
# Author:  Thomas Kekeisen <raumfeldalarm@tk.ca.kekeisen.it>
# License: This work is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License.
#          To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/4.0/.
#

echo "Installing php"

apt-get update -y
apt-get install php5 -y

echo "Done!"