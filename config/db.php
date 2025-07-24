<?php
// config/db.php
require_once __DIR__ . '/../DbConnector.php';
$db = new DBConnector();
$conn = $db->connect();
