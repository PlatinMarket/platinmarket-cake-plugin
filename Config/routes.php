<?php

//Parse Json extensions
Router::parseExtensions('json');

//Main Page
Router::connect('/:session_id', array('controller' => 'main', 'action' => 'index'), array('session_id' => '[a-z|A-Z|0-9]+'));

// Session Start
Router::connect('/session_start', array('plugin' => 'platinmarket', 'controller' => 'main', 'action' => 'index'));

// OAuth Callback
Router::connect('/oauth/callback', array('plugin' => 'platinmarket', 'controller' => 'oauth', 'action' => 'callback'));

// Plugin SessionId
Router::connect('/:session_id/:plugin/:controller/:action', array(), array('session_id' => '[a-z|A-Z|0-9]+'));

// SessionId
Router::connect('/:session_id/:controller/:action', array(), array('session_id' => '[a-z|A-Z|0-9]+'));
Router::connect('/:session_id/:controller', array('action' => 'index'), array('session_id' => '[a-z|A-Z|0-9]+'));
Router::connect('/:session_id', array('controller' => 'main', 'action' => 'index'), array('session_id' => '[a-z|A-Z|0-9]+'));
