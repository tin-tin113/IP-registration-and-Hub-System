<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('director');

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

$response = [
    'success' => false,
    'type' => $type,
    'title' => '',
    'columns' => [],
    'data' => []
];

switch ($type) {
    case 'total_applications':
        $response['title'] = 'All Applications';
        $response['columns'] = ['Title', 'Applicant', 'IP Type', 'Status', 'Date Submitted'];
        
        $query = "SELECT a.id, a.title, u.full_name as applicant, a.ip_type, a.status, a.created_at 
                  FROM ip_applications a 
                  JOIN users u ON a.user_id = u.id 
                  ORDER BY a.created_at DESC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'title' => htmlspecialchars($row['title']),
                'applicant' => htmlspecialchars($row['applicant']),
                'ip_type' => $row['ip_type'],
                'status' => ucwords(str_replace('_', ' ', $row['status'])),
                'date' => date('M d, Y', strtotime($row['created_at']))
            ];
        }
        $response['success'] = true;
        break;

    case 'approved':
        $response['title'] = 'Approved Applications';
        $response['columns'] = ['Title', 'Applicant', 'IP Type', 'Certificate ID', 'Approved Date'];
        
        $query = "SELECT a.id, a.title, u.full_name as applicant, a.ip_type, a.certificate_id, a.approved_at 
                  FROM ip_applications a 
                  JOIN users u ON a.user_id = u.id 
                  WHERE a.status = 'approved' 
                  ORDER BY a.approved_at DESC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'title' => htmlspecialchars($row['title']),
                'applicant' => htmlspecialchars($row['applicant']),
                'ip_type' => $row['ip_type'],
                'certificate_id' => $row['certificate_id'] ?? 'N/A',
                'date' => $row['approved_at'] ? date('M d, Y', strtotime($row['approved_at'])) : 'N/A'
            ];
        }
        $response['success'] = true;
        break;

    case 'pending':
        $response['title'] = 'Pending Applications';
        $response['columns'] = ['Title', 'Applicant', 'IP Type', 'Status', 'Date Submitted'];
        
        $query = "SELECT a.id, a.title, u.full_name as applicant, a.ip_type, a.status, a.created_at 
                  FROM ip_applications a 
                  JOIN users u ON a.user_id = u.id 
                  WHERE a.status IN ('submitted', 'office_visit', 'payment_pending', 'payment_verified') 
                  ORDER BY a.created_at DESC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'title' => htmlspecialchars($row['title']),
                'applicant' => htmlspecialchars($row['applicant']),
                'ip_type' => $row['ip_type'],
                'status' => ucwords(str_replace('_', ' ', $row['status'])),
                'date' => date('M d, Y', strtotime($row['created_at']))
            ];
        }
        $response['success'] = true;
        break;

    case 'total_users':
        $response['title'] = 'Registered Researchers';
        $response['columns'] = ['Name', 'Email', 'Department', 'Date Registered'];
        
        $query = "SELECT u.id, u.full_name, u.email, u.department, u.created_at 
                  FROM users u 
                  WHERE u.role = 'user' 
                  ORDER BY u.created_at DESC";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['full_name']),
                'email' => htmlspecialchars($row['email']),
                'department' => htmlspecialchars($row['department'] ?? 'Not specified'),
                'date' => date('M d, Y', strtotime($row['created_at']))
            ];
        }
        $response['success'] = true;
        break;

    default:
        $response['error'] = 'Invalid type specified';
}

echo json_encode($response);
