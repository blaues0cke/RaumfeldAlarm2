# Raumfeld alarm clock

This is just a small script to make a raspberry pi or any other linux based system wake you up with some music. I am kinda lazy, so this script can only play reggae by default, you have to modify it on your own to play your own music.

### Installation

Install php on your raspberry pi: 

	sudo apt-get install php5

Please check out [RaumfeldAlarm 1](https://github.com/blaues0cke/RaumfeldAlarm) if you want a pure bash solution. I was too lazy to finish the first version. Writing this in php was much easier.

Just unzip all the files to `/usr/raumfeld/`. To install the crontab, just type:

    sudo sh /usr/raumfeld/install.sh

Or run this code (one line) to automatically download and install everything you need:

    sudo mkdir /usr/raumfeld && sudo wget -O /usr/raumfeld/raumfeld.zip https://github.com/blaues0cke/RaumfeldAlarm2/archive/master.zip && sudo unzip /usr/raumfeld/raumfeld.zip -d /usr/raumfeld && sudo cp -rf /usr/raumfeld/RaumfeldAlarm2-master/* /usr/raumfeld && sudo rm -rf /usr/raumfeld/RaumfeldAlarm2-master && sudo rm /usr/raumfeld/raumfeld.zip && sudo sh /usr/raumfeld/install-crontab.sh

### Config

Please modify the `/usr/raumfeld/play.php` and `/usr/raumfeld/stop.php`  by entering the ip address of your host device.

### Alarms

To set alarms, just modify `/usr/raumfeld/crontab.sh`. You can use the following parameters:

	*     *     *     *     *  sh /usr/raumfeld/wake-up.sh
	-     -     -     -     -
	|     |     |     |     |
	|     |     |     |     +----- Day of week (0 = sunday)
	|     |     |     +----------- Month (1 - 12)
	|     |     +----------------- Day (1 - 31)
	|     +----------------------- Hour (0 - 23)
	+----------------------------- Minute (0-59)
	
To make you changes take effect, just re-run the installation script.