<?php
header('Content-Type: application/json');

require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;
use Smalot\PdfParser\Parser;

function createElasticsearchClient() {
    return ClientBuilder::create()
        ->setHosts(['https://localhost:9200'])
        ->setBasicAuthentication('elastic', 'esra1999')
        ->setSSLVerification(false)  // Only for development
        ->build();
}

function forceCreateIndex($client) {
    $indexName = 'pdf_files';
    $params = [
        'index' => $indexName,
        'body' => [
            'mappings' => [
                'properties' => [
                    'file_name' => ['type' => 'text'],
                    'file_url' => ['type' => 'text'],
                    'upload_date' => [
                        'type' => 'date',
                        'format' => 'strict_date_optional_time||epoch_millis'
                    ],
                    'student_names' => ['type' => 'text']
                ]
            ]
        ]
    ];

    try {
        $client->indices()->create($params);
    } catch (\Exception $e) {
        // If the index already exists, that's fine, we can continue
        if (!strpos($e->getMessage(), 'resource_already_exists_exception')) {
            throw $e;
        }
    }
}
function extractStudentNames($pdfUrl) {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfUrl);
    $text = $pdf->getText();
    
    // Split the text into lines
    $lines = explode("\n", $text);
    
    $students = array();
    
    foreach ($lines as $line) {
        // Match lines that start with a number or 'غ', followed by a 12-digit ID
        if (preg_match('/^(\d+|غ)\t(\d{12})(.+?)(\d|$)/', $line, $matches)) {
            $fullName = trim($matches[3]);
            
            // Remove any trailing numbers or special characters
            $fullName = preg_replace('/[\d\sغ]+$/', '', $fullName);
            
            // Trim any leading or trailing whitespace
            $fullName = trim($fullName);
            
            if (!empty($fullName)) {
                // Split the name into words
                $words = explode(' ', $fullName);
                
                // Reverse the letters in each word
                $correctedWords = array_map(function($word) {
                    return implode('', array_reverse(preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY)));
                }, $words);
                
                // Reverse the order of the corrected words
                $correctedWords = array_reverse($correctedWords);
                
                // Join the corrected words back together
                $correctedName = implode(' ', $correctedWords);
                
                $students[] = $correctedName;
            }
        }
    }
    
    return $students;
}

function mb_strrev($str){
    $reversed = '';
    $length = mb_strlen($str, 'UTF-8');
    while ($length-- > 0) {
        $reversed .= mb_substr($str, $length, 1, 'UTF-8');
    }
    return $reversed;
}


function indexPDF($pdfUrl, $fileName) {
    $client = createElasticsearchClient();
    forceCreateIndex($client);

    // Check if the file is already indexed
    $params = [
        'index' => 'pdf_files',
        'body' => [
            'query' => [
                'match' => [
                    'file_name' => $fileName
                ]
            ]
        ]
    ];

    $response = $client->search($params);

    if ($response['hits']['total']['value'] > 0) {
        return [
            'status' => 'already_indexed',
            'message' => 'File is already indexed.'
        ];
    }

    $students = extractStudentNames($pdfUrl);

    $params = [
        'index' => 'pdf_files',
        'body' => [
            'file_name' => $fileName,
            'file_url' => $pdfUrl,
            'upload_date' => date('Y-m-d\TH:i:s\Z'), // ISO 8601 format
            'student_names' => $students
        ]
    ];

    $response = $client->index($params);

    return [
        'status' => 'success',
        'message' => 'File indexed successfully.',
        'id' => $response['_id']
    ];
}

// Main execution
$pdfUrl = $_POST['pdfUrl'] ?? '';
$fileName = $_POST['fileName'] ?? '';

if (empty($pdfUrl) || empty($fileName)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'PDF URL or file name not provided'
    ]);
} else {
    try {
        $result = indexPDF($pdfUrl, $fileName);
        echo json_encode($result);
    } catch (\Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}