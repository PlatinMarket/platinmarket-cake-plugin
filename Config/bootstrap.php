<?php

/* Default Config Zone */

// Client Id
Configure::write('PlatinMarket.ClientID', null);

// Client Secret
Configure::write('PlatinMarket.ClientSecret', null);

// Application UUID
Configure::write('PlatinMarket.ApplicationUUID', null);

// Platform UUID
Configure::write('PlatinMarket.PlatformUUID', 'aa3f59ac-380b-11e4-8f24-000c29ebda4d');

// Application Permission Scope
Configure::write('PlatinMarket.Scope', '*:*');

// ReformApi Base
Configure::write('PlatinMarket.Api.Base', 'http://developer.platinmarket.com');

// ReformApi Base Path
Configure::write('PlatinMarket.Api.BasePath', '/reform');

// ReformApi Base Path
Configure::write('PlatinMarket.Api.OauthPath', '/oauth');

// Call Config
require_once 'config.php';
