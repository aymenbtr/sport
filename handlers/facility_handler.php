<?php
require_once '../database.php';

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $wilaya_id = isset($_GET['wilaya']) ? intval($_GET['wilaya']) : null;
    $sport_id = isset($_GET['sport']) ? intval($_GET['sport']) : null;
    
    if ($wilaya_id || $sport_id) {
        $facilities = $db->getFacilities($wilaya_id, $sport_id);
    } else {
        $wilayas = $db->getWilayas();
        $allFacilities = [];
        
        foreach ($wilayas as $wilaya) {
            $facilities = $db->getFacilities($wilaya['id']);
            if (!empty($facilities)) {
                $allFacilities[$wilaya['name']] = $facilities;
            }
        }
        $facilities = $allFacilities;
    }
    
    header('Content-Type: application/json');
    echo json_encode($facilities);
}