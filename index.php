<?php
require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;

function indexStudentData($students)
{
    $client = ClientBuilder::create()
    ->setHosts(['https://localhost:9200'])  // Use HTTPS
    ->setBasicAuthentication('elastic', 'esra1999')  // Add your Elasticsearch credentials here
    ->setSSLVerification(false)  // Only use this for testing. In production, use proper SSL verification
    ->build();

    foreach ($students as $student) {
        $params = [
            'index' => 'student_grades',
            'id' => $student['id'],
            'body' => $student
        ];

        $response = $client->index($params);
    }

    return count($students);
}

// Usage in process_pdf.php (continued from previous step)
$indexedCount = indexStudentData($studentData);
echo json_encode(['indexedCount' => $indexedCount, 'message' => 'Data processed and indexed successfully']);
