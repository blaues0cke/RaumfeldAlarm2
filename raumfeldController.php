<?php

# 
# This file is part of RaumfeldAlarm2.
# Learn more at: https://github.com/blaues0cke/RaumfeldAlarm
# 
# Author:  Thomas Kekeisen <raumfeldalarm@tk.ca.kekeisen.it>
# License: This work is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License.
#          To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/4.0/.
#

class RaumfeldController
{
	protected $baseIp  = null;
	protected $devices = [];
	protected $zones   = [];

	public function __construct ($baseIp = null)
	{
		$this->log('Initialized');

		if ($baseIp)
		{
			$this->setBaseIp($baseIp);
			$this->loadDevices();
			$this->loadZones();
		}
	}

	public function __destruct ()
	{
		$this->log('Everything done');
	}

	public function addRoomToZone ($room, $zone, $noSleep = false)
	{
		$this->log('Adding room to '.$room.' to zone: '.$zone);

		$baseDevice = $this->findBase();

		$this->httpRequest(
			'http://192.168.0.10:47365/1b9b97b752e6adfd9c6efe7abf43d252c04885f86d01c90e55a401afcc86ed8d/connectRoomToZone?zoneUDN='.urlencode($zone).'&roomUDN='.urlencode($room)
		);

		if ($noSleep)
		{
			$this->log('Done');
		}
		else
		{
			$this->log('Done, waiting 2 seconds since this seems to help');
			$this->sleep(2);
		}
	}

	public function addAllRoomsToZone ($zoneUdn)
	{
		foreach ($this->zones as $zone)
		{
			foreach ($zone['rooms'] as $room)
			{
				$this->log('Current room: '.$room['name']);

				$this->addRoomToZone($room['udn'], $zoneUdn);
			}
		}
	}

	public function addAllRoomsToFirstZone ()
	{
		if (count($this->zones) > 1)
		{
			$zoneFound = false;

			foreach ($this->zones as $zone)
			{
				var_dump($zone);
				if (!empty($zone['udn']))
				{
					$zoneUdn = $zone['udn'];

					$this->log('Adding all rooms to zone: '.$zoneUdn);
					$this->addAllRoomsToZone($zoneUdn);

					$zoneFound = true;

					break;
				}
			}

			if (!$zoneFound)
			{
				$this->log('No zone found to add all rooms to');
			}
		}
		else
		{
			$this->log('Got only one zone, nothing to do');
		}
	}

	public function fadeVolumeInAllRoomsTo ($targetVolume, $step = 1, $sleep = 3)
	{
		for ($volume = 1; $volume < $targetVolume; $volume += $step)
		{
			$this->setVolumeInAllRoomsTo($volume);
			$this->log('Waiting '.$sleep.' seconds');
			$this->sleep($sleep);
		}
	}

	public function findBase ()
	{
		return $this->findRendererWithIpAnd($this->baseIp);
	}

	public function findDeviceWithIp ($ip, $type = null)
	{
		foreach ($this->devices as $device)
		{
			if (
				strpos($device['location'].':', $ip.':') !== false &&
				(
					$type 						   === null ||
					strpos($device['type'], $type) !== false
				)
			)
			{
				return $device;
			}
		}

		return false;
	}

	public function findRendererWithIpAnd ($ip)
	{
		return $this->findDeviceWithIp($ip, 'MediaRenderer');
	}

	protected function httpRequest ($url, $postData = null, $soapAction = null)
	{
		$this->log('Requesting '.$url);
		$this->log('  '.$postData);
		$this->log('  '.$soapAction);

		$ch     = curl_init();
		$header = array(
		    'Content-Type: text/xml; charset="utf-8"',
		    'User-Agent: RaumfeldAlarm2'
	    );

	    if ($soapAction)
	    {
	    	$header[] = 'SOAPAction: "'.$soapAction.'"';
	    }

		curl_setopt($ch, CURLOPT_HTTPHEADER,     $header);
		curl_setopt($ch, CURLOPT_URL, 	         $url);
		curl_setopt($ch, CURLOPT_POST,           !empty($postData));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		if (!empty($postData))
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS,     $postData);
		}

		$result = curl_exec($ch);

		$this->log('Done, response: '.(empty($result) ? '<empty response>' : $result));
		$this->log(' ');

		curl_close($ch);

		return $result;
	}

	public function loadDevices ()
	{
		$newDeviceList  = [];
		$deviceListData = $this->httpRequest('http://'.$this->baseIp.':47365/listDevices');
		preg_match_all('/<device (.*?)>(.*?)<\/device>/is', $deviceListData, $matches);

		foreach ($matches[1] as $index => $match)
		{
			$newDevice = [];

			if (!empty($matches[2]) && !empty($matches[2][$index]))
			{
				$newDevice['name'] = $matches[2][$index]; 
			}

			$explodedString = explode('\' ', $match);

			foreach ($explodedString as $dataPart)
			{
				$explodedDataPart = explode('=\'', $dataPart);
				$newDevice[$explodedDataPart[0]] = substr($explodedDataPart[1], 0, -1);
			}

			$ipFound = preg_match('/([0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}:[0-9]{1,5})/is', $newDevice['location'], $matches);

			if ($ipFound)
			{
				$newDevice['ip'] = $matches[1];
			}

			$newDeviceList[] = $newDevice;
		}

		$this->devices = $newDeviceList;

		$this->log('Loaded '.count($this->devices).' devices');	
	}

	public function loadZones ()
	{
		$newZoneList  = [];
		$zoneListData = $this->httpRequest('http://'.$this->baseIp.':47365/getZones');
		
		preg_match_all('/<(?:zone|unassignedRooms)( .*?|)>(.*?)<\/(?:zone|unassignedRooms)>/is', $zoneListData, $matches);

		foreach ($matches[1] as $index => $match)
		{
			$newZone     = ['roomData' => $matches[2][$index]];
			$newRoomList = []; 

			$explodedString = explode('\' ', $match);

			foreach ($explodedString as $dataPart)
			{
				$explodedDataPart = explode('=\'', $dataPart);

				if (!empty($explodedDataPart[0]))
				{
					$newZone[trim($explodedDataPart[0])] = str_replace('\'', '', $explodedDataPart[1]);
				}
			}

			preg_match_all('/<room (.*?)>(.*?)<\/room>/is', $newZone['roomData'], $matches2);

			foreach ($matches2[1] as $index2 => $match2)
			{
				$newRoom         = ['rendererData' => $matches2[2][$index2]];
				$newRendererList = []; 

				$explodedString = explode('\' ', $match2);

				foreach ($explodedString as $dataPart)
				{
					$explodedDataPart = explode('=\'', $dataPart);
					$newRoom[$explodedDataPart[0]] = $explodedDataPart[1];
				}

				unset($newRoom['rendererData']);

				$newRoom['renderer'] = $newRendererList;
				$newRoomList[]       = $newRoom;
			}

			unset($newZone['roomData']);

			$newZone['rooms'] = $newRoomList;
			$newZoneList[]    = $newZone;
		}

		$this->zones = $newZoneList;

		$this->log('Loaded '.count($this->zones).' zones');	
	}

	protected function log ($text)
	{
		echo '> '.$text."\n";
	}

	public function playRandomTuneInRadio ()
	{
		$radios = array
		(
			'playTuneInHerrMerkt',
			'playTuneInJahfari',
			'playTuneInRawFM'
		);

		$function = $radios[array_rand($radios)];

		$this->$function();
	}

	public function playTuneInHerrMerkt ()
	{
		$this->log('Playing herr merkt');

		$baseDevice = $this->findBase();

		$this->httpRequest(
			'http://'.$baseDevice['ip'].'/TransportService/Control',
			'<?xml version="1.0"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><s:Body><u:SetAVTransportURI xmlns:u="urn:schemas-upnp-org:service:AVTransport:1"><CurrentURIMetaData>&lt;DIDL-Lite xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot; xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:dlna=&quot;urn:schemas-dlna-org:metadata-1-0/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:raumfeld=&quot;urn:schemas-raumfeld-com:meta-data/raumfeld&quot;&gt;&lt;item restricted=&quot;1&quot; id=&quot;0/RadioTime/Search/s-s97342&quot; parentID=&quot;0/RadioTime/Search&quot;&gt;&lt;dc:title&gt;Herr Merkt Radio&lt;/dc:title&gt;&lt;upnp:signalStrength&gt;100&lt;/upnp:signalStrength&gt;&lt;raumfeld:section&gt;RadioTime&lt;/raumfeld:section&gt;&lt;upnp:class&gt;object.item.audioItem.audioBroadcast.radio&lt;/upnp:class&gt;&lt;raumfeld:ebrowse&gt;http://opml.radiotime.com/Tune.ashx?partnerId=7aJ9pvV5&amp;amp;formats=wma%2Cmp3%2Cogg&amp;amp;serial=00%3A0d%3Ab9%3A24%3A50%3A14&amp;amp;id=s97342&amp;amp;c=ebrowse&lt;/raumfeld:ebrowse&gt;&lt;upnp:albumArtURI&gt;http://cdn-radiotime-logos.tunein.com/s97342q.png&lt;/upnp:albumArtURI&gt;&lt;raumfeld:name&gt;Station&lt;/raumfeld:name&gt;&lt;res protocolInfo=&quot;http-get:*:audio/x-mpegurl:*&quot;&gt;http://opml.radiotime.com/Tune.ashx?id=s97342&amp;amp;formats=wma,mp3,ogg&amp;amp;partnerId=7aJ9pvV5&amp;amp;serial=00:0d:b9:24:50:14&lt;/res&gt;&lt;raumfeld:durability&gt;117&lt;/raumfeld:durability&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</CurrentURIMetaData><InstanceID>0</InstanceID><CurrentURI>http://opml.radiotime.com/Tune.ashx?id=s97342&amp;formats=wma,mp3,ogg&amp;partnerId=7aJ9pvV5&amp;serial=00:0d:b9:24:50:14</CurrentURI></u:SetAVTransportURI></s:Body></s:Envelope>',
			'urn:schemas-upnp-org:service:AVTransport:1#SetAVTransportURI'
		);

		$this->log('Done');
	}

	public function playTuneInJahfari ()
	{
		$this->log('Playing jahfari');

		$baseDevice = $this->findBase();

		$this->httpRequest(
			'http://'.$baseDevice['ip'].'/TransportService/Control',
			'<?xml version="1.0"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><s:Body><u:SetAVTransportURI xmlns:u="urn:schemas-upnp-org:service:AVTransport:1"><CurrentURIMetaData>&lt;DIDL-Lite xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot; xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:dlna=&quot;urn:schemas-dlna-org:metadata-1-0/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:raumfeld=&quot;urn:schemas-raumfeld-com:meta-data/raumfeld&quot;&gt;&lt;item restricted=&quot;1&quot; id=&quot;0/RadioTime/Search/s-s100890&quot; parentID=&quot;0/RadioTime/Search&quot;&gt;&lt;dc:title&gt;JAHFARI&lt;/dc:title&gt;&lt;upnp:signalStrength&gt;97&lt;/upnp:signalStrength&gt;&lt;raumfeld:section&gt;RadioTime&lt;/raumfeld:section&gt;&lt;upnp:class&gt;object.item.audioItem.audioBroadcast.radio&lt;/upnp:class&gt;&lt;raumfeld:ebrowse&gt;http://opml.radiotime.com/Tune.ashx?partnerId=7aJ9pvV5&amp;amp;formats=wma%2Cmp3%2Cogg&amp;amp;serial=00%3A0d%3Ab9%3A24%3A50%3A14&amp;amp;id=s100890&amp;amp;c=ebrowse&lt;/raumfeld:ebrowse&gt;&lt;upnp:albumArtURI&gt;http://cdn-radiotime-logos.tunein.com/s100890q.png&lt;/upnp:albumArtURI&gt;&lt;raumfeld:name&gt;Station&lt;/raumfeld:name&gt;&lt;res protocolInfo=&quot;http-get:*:audio/x-mpegurl:*&quot;&gt;http://opml.radiotime.com/Tune.ashx?id=s100890&amp;amp;formats=wma,mp3,ogg&amp;amp;partnerId=7aJ9pvV5&amp;amp;serial=00:0d:b9:24:50:14&lt;/res&gt;&lt;raumfeld:durability&gt;115&lt;/raumfeld:durability&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</CurrentURIMetaData><InstanceID>0</InstanceID><CurrentURI>http://opml.radiotime.com/Tune.ashx?id=s100890&amp;formats=wma,mp3,ogg&amp;partnerId=7aJ9pvV5&amp;serial=00:0d:b9:24:50:14</CurrentURI></u:SetAVTransportURI></s:Body></s:Envelope>',
			'urn:schemas-upnp-org:service:AVTransport:1#SetAVTransportURI'
		);

		$this->log('Done');
	}

	public function playTuneInRawFM ()
	{
		$this->log('Playing raw fm');

		$baseDevice = $this->findBase();

		$this->httpRequest(
			'http://'.$baseDevice['ip'].'/TransportService/Control',
			'<?xml version="1.0"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><s:Body><u:SetAVTransportURI xmlns:u="urn:schemas-upnp-org:service:AVTransport:1"><CurrentURIMetaData>&lt;DIDL-Lite xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot; xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:dlna=&quot;urn:schemas-dlna-org:metadata-1-0/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:raumfeld=&quot;urn:schemas-raumfeld-com:meta-data/raumfeld&quot;&gt;&lt;item restricted=&quot;1&quot; id=&quot;0/RadioTime/Search/s-s82747&quot; parentID=&quot;0/RadioTime/Search&quot;&gt;&lt;dc:title&gt;Raw FM&lt;/dc:title&gt;&lt;upnp:signalStrength&gt;98&lt;/upnp:signalStrength&gt;&lt;raumfeld:section&gt;RadioTime&lt;/raumfeld:section&gt;&lt;upnp:class&gt;object.item.audioItem.audioBroadcast.radio&lt;/upnp:class&gt;&lt;raumfeld:ebrowse&gt;http://opml.radiotime.com/Tune.ashx?partnerId=7aJ9pvV5&amp;amp;formats=wma%2Cmp3%2Cogg&amp;amp;serial=00%3A0d%3Ab9%3A24%3A50%3A14&amp;amp;id=s82747&amp;amp;c=ebrowse&lt;/raumfeld:ebrowse&gt;&lt;upnp:albumArtURI&gt;http://cdn-radiotime-logos.tunein.com/s82747q.png&lt;/upnp:albumArtURI&gt;&lt;raumfeld:name&gt;Station&lt;/raumfeld:name&gt;&lt;res protocolInfo=&quot;http-get:*:audio/x-mpegurl:*&quot;&gt;http://opml.radiotime.com/Tune.ashx?id=s82747&amp;amp;formats=wma,mp3,ogg&amp;amp;partnerId=7aJ9pvV5&amp;amp;serial=00:0d:b9:24:50:14&lt;/res&gt;&lt;raumfeld:durability&gt;115&lt;/raumfeld:durability&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;</CurrentURIMetaData><InstanceID>0</InstanceID><CurrentURI>http://opml.radiotime.com/Tune.ashx?id=s82747&amp;formats=wma,mp3,ogg&amp;partnerId=7aJ9pvV5&amp;serial=00:0d:b9:24:50:14</CurrentURI></u:SetAVTransportURI></s:Body></s:Envelope>',
			'urn:schemas-upnp-org:service:AVTransport:1#SetAVTransportURI'
		);

		$this->log('Done');
	}

	public function setBaseIp ($ip)
	{
		$this->baseIp = $ip;
	}

	public function setVolumeInAllRoomsTo ($volume)
	{
		$this->log('Setting volume for all rooms to '.$volume);

		foreach ($this->zones as $zone)
		{
			foreach ($zone['rooms'] as $room)
			{
				$this->log('Current room: '.$room['name']);

				$this->setVolumeInRoomTo($room['udn'], $volume);
			}
		}
	}

	public function setVolumeInRoomTo ($room, $volume)
	{
		$this->log('Setting volume to '.$volume.' for room: '.$room);

		$baseDevice = $this->findBase();

		$this->httpRequest(
			'http://'.$baseDevice['ip'].'/RenderingService/Control',
			'<?xml version="1.0"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><s:Body><u:SetRoomVolume xmlns:u="urn:schemas-upnp-org:service:RenderingControl:1"><Room>'.$room.'</Room><InstanceID>0</InstanceID><DesiredVolume>'.$volume.'</DesiredVolume></u:SetRoomVolume></s:Body></s:Envelope>',
			'urn:schemas-upnp-org:service:RenderingControl:1#SetRoomVolume'
		);

		$this->log('Done');
	}

	public function sleep ($seconds)
	{
		sleep($seconds);
	}

	public function stopMusic ()
	{
		$this->log('Stopping music');

		$baseDevice = $this->findBase();

		$this->httpRequest(
			'http://'.$baseDevice['ip'].'/TransportService/Control',
			'<?xml version="1.0"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><s:Body><u:Pause xmlns:u="urn:schemas-upnp-org:service:AVTransport:1"><InstanceID>0</InstanceID></u:Pause></s:Body></s:Envelope>',
			'urn:schemas-upnp-org:service:AVTransport:1#Pause'
		);

		$this->log('Done');
	}
}