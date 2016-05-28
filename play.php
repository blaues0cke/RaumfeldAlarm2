<?php

require_once('raumfeldController.php');

$raumfeldController = new RaumfeldController('192.168.0.10');
$raumfeldController->stopMusic();
$raumfeldController->setVolumeInAllRoomsTo(0);
$raumfeldController->addAllRoomsToFirstZone();
$raumfeldController->stopMusic();
$raumfeldController->setVolumeInAllRoomsTo(0);
$raumfeldController->playTuneInJahfari();
$raumfeldController->sleep(40);
$raumfeldController->fadeVolumeInAllRoomsTo(50);