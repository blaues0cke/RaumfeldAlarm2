<?php

require_once('raumfeldController.php');

$raumfeldController = new RaumfeldController('192.168.0.101');
$raumfeldController->stopMusic();
$raumfeldController->setVolumeInAllRoomsTo(0);
$raumfeldController->addAllRoomsToFirstZone();
$raumfeldController->stopMusic();
$raumfeldController->setVolumeInAllRoomsTo(0);
$raumfeldController->playRandomTuneInRadio();
$raumfeldController->sleep(40);
$raumfeldController->fadeVolumeInAllRoomsTo(50);